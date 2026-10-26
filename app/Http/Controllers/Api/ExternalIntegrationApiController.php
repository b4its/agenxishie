<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Integration\ExternalClientManager;
use App\Domain\Publication\ManuscriptExporter;
use App\Domain\Research\ReferenceImportService;
use App\Http\Controllers\Controller;
use App\Models\ExternalClient;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Controller API Modular untuk integrasi proyek/website eksternal
 * yang ingin mengonsumsi endpoint, model, dan pipeline akademik Xishie.
 */
class ExternalIntegrationApiController extends Controller
{
    public function __construct(
        private readonly ExternalClientManager $clientManager,
    ) {}

    /**
     * Handshake & probe koneksi dari klien eksternal ke Xishie.
     */
    public function ping(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $client->recordInboundHit('/api/v1/external/ping');

        return response()->json([
            'status' => 'connected',
            'app' => 'xishie-academic-hub',
            'version' => '1.0.0',
            'client_slug' => $client->client_slug,
            'client_name' => $client->name,
            'allowed_models' => $client->allowed_models ?? ['*'],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Dapatkan status dan metadata proyek berdasarkan slug atau ID.
     */
    public function clientStatus(Request $request, string $slug): JsonResponse
    {
        $client = ExternalClient::query()
            ->where('client_slug', $slug)
            ->orWhere('id', is_numeric($slug) ? (int) $slug : 0)
            ->first();

        if ($client === null) {
            return response()->json(['error' => 'Klien tidak ditemukan.'], 404);
        }

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'client_slug' => $client->client_slug,
            'target_url' => $client->target_url,
            'status' => $client->status,
            'uptime_percentage' => $client->getUptimePercentage(),
            'last_status_code' => $client->last_status_code,
            'last_latency_ms' => $client->last_latency_ms,
            'last_ping_at' => $client->last_ping_at?->toIso8601String(),
            'inbound_requests_count' => $client->inbound_requests_count,
            'allowed_models' => $client->allowed_models ?? ['*'],
            'is_active' => $client->is_active,
        ]);
    }

    /**
     * Pendaftaran mandiri (self-registration) proyek/website eksternal.
     *
     * Memungkinkan proyek lain (mis. agen xishie di http://localhost:8208/)
     * mendaftarkan dirinya dan langsung menerima API key untuk mengonsumsi
     * endpoint/model Xishie. Wajib menyertakan X-Registration-Secret.
     */
    public function selfRegister(Request $request): JsonResponse
    {
        $expectedSecret = (string) config('integration.registration_secret');

        if ($expectedSecret === '') {
            return response()->json([
                'error' => 'Self-registration dinonaktifkan pada hub ini.',
                'hint' => 'Minta admin hub mendaftarkan proyek Anda via halaman Connected Projects.',
            ], 403);
        }

        $providedSecret = (string) $request->header('X-Registration-Secret', '');

        if (! hash_equals($expectedSecret, $providedSecret)) {
            return response()->json(['error' => 'Unauthorized: X-Registration-Secret tidak valid.'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:128'],
            'target_url' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'webhook_url' => ['nullable', 'string', 'max:255'],
            'allowed_models' => ['nullable', 'array'],
            'allowed_models.*' => ['string', 'max:64'],
        ]);

        $existing = ExternalClient::query()
            ->where('target_url', $validated['target_url'])
            ->first();

        if ($existing !== null) {
            return response()->json([
                'success' => true,
                'already_registered' => true,
                'client' => [
                    'id' => $existing->id,
                    'name' => $existing->name,
                    'client_slug' => $existing->client_slug,
                    'connection_status' => $existing->connection_state->value,
                ],
                'message' => 'Proyek sudah terdaftar. Gunakan API key yang tersimpan di proyek Anda.',
            ], 200);
        }

        $client = $this->clientManager->register(
            name: $validated['name'],
            targetUrl: $validated['target_url'],
            allowedModels: $validated['allowed_models'] ?? ['*'],
            description: $validated['description'] ?? null,
            webhookUrl: $validated['webhook_url'] ?? null,
        );

        return response()->json([
            'success' => true,
            'already_registered' => false,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'client_slug' => $client->client_slug,
                'api_key' => $client->api_key,
                'allowed_models' => $client->allowed_models,
                'connection_status' => $client->connection_state->value,
            ],
            'next_step' => 'Simpan api_key pada config proyek Anda (mis. services.xishie.api_key), lalu buka halaman Connected Projects untuk memantau status.',
        ], 201);
    }

    /**
     * Daftar model dan agen yang dapat dikonsumsi oleh klien eksternal.
     */
    public function models(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $allModels = $this->clientManager->getAvailableModels();
        $allowed = [];

        foreach ($allModels as $key => $meta) {
            if ($client->isModelAllowed($key)) {
                $allowed[$key] = $meta;
            }
        }

        $client->recordInboundHit('/api/v1/external/models');

        return response()->json([
            'client' => $client->name,
            'total_models' => count($allowed),
            'models' => $allowed,
        ]);
    }

    /**
     * Inferensi chat/asisten akademik modular.
     */
    public function chat(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:10000'],
            'model' => ['nullable', 'string', 'max:64'],
            'context' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->clientManager->handleChat(
                client: $client,
                prompt: $validated['prompt'],
                model: $validated['model'] ?? null,
                context: $validated['context'] ?? [],
            );

            return response()->json($result);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Gagal memproses permintaan chat: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Audit kepatuhan naskah/manuskrip terhadap aturan dan format.
     */
    public function audit(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'style' => ['nullable', 'string', 'max:64'],
            'options' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->clientManager->handleAudit(
                client: $client,
                content: $validated['content'],
                style: $validated['style'] ?? null,
                options: $validated['options'] ?? [],
            );

            return response()->json($result);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Gagal memproses audit naskah: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rekomendasi jurnal/venue berdasarkan abstrak artikel.
     */
    public function matchVenue(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $validated = $request->validate([
            'abstract' => ['required', 'string', 'max:5000'],
            'field' => ['nullable', 'string', 'max:64'],
            'options' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->clientManager->handleVenueMatch(
                client: $client,
                abstract: $validated['abstract'],
                field: $validated['field'] ?? null,
                options: $validated['options'] ?? [],
            );

            return response()->json($result);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Gagal mencocokkan venue: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Review kritis / simulasi peer-review terhadap naskah.
     */
    public function critic(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:20000'],
            'options' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->clientManager->handleCritic(
                client: $client,
                text: $validated['text'],
                options: $validated['options'] ?? [],
            );

            return response()->json($result);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Gagal memproses kritik sejawat: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format sitasi / bibliografi ke berbagai gaya selingkung.
     */
    public function citation(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $validated = $request->validate([
            'references' => ['required', 'array', 'min:1'],
            'style' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $result = $this->clientManager->handleCitation(
                client: $client,
                references: $validated['references'],
                style: $validated['style'] ?? 'ieee',
            );

            return response()->json($result);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Gagal memformat sitasi: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Konversi dan ekspor naskah ke berbagai format ilmiah.
     */
    public function export(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $validated = $request->validate([
            'format' => ['required', 'string', 'in:docx,odt,latex,markdown,html,json,bibtex,text'],
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['nullable', 'string'],
            'keywords' => ['nullable', 'array'],
            'sections' => ['nullable', 'array'],
            'bibliography' => ['nullable', 'array'],
            'citation_style' => ['nullable', 'string'],
        ]);

        $client->recordInboundHit('/api/v1/external/export');

        $exporter = app(ManuscriptExporter::class);
        $format = $validated['format'];

        $manuscript = [
            'title' => $validated['title'],
            'abstract' => $validated['abstract'] ?? '',
            'keywords' => $validated['keywords'] ?? [],
            'sections' => $validated['sections'] ?? [],
            'bibliography' => $validated['bibliography'] ?? [],
            'citation_style' => $validated['citation_style'] ?? 'IEEE',
        ];

        $reflection = new \ReflectionClass($exporter);
        $methodName = match ($format) {
            'markdown' => 'toMarkdown',
            'latex' => 'toLatex',
            'html' => 'toHtml',
            'json' => 'toJson',
            'text' => 'toText',
            'bibtex' => 'toBibtex',
            default => 'toMarkdown',
        };

        if ($format === 'docx') {
            $method = $reflection->getMethod('toDocx');
            $method->setAccessible(true);
            $binary = $method->invoke($exporter, $manuscript);

            return response()->json([
                'success' => true,
                'format' => 'docx',
                'filename' => Str::slug($validated['title']).'.docx',
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'base64' => base64_encode((string) $binary),
            ]);
        }

        if ($format === 'odt') {
            $method = $reflection->getMethod('toOdt');
            $method->setAccessible(true);
            $binary = $method->invoke($exporter, $manuscript);

            return response()->json([
                'success' => true,
                'format' => 'odt',
                'filename' => Str::slug($validated['title']).'.odt',
                'mime' => 'application/vnd.oasis.opendocument.text',
                'base64' => base64_encode((string) $binary),
            ]);
        }

        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        $content = $method->invoke($exporter, $manuscript);

        return response()->json([
            'success' => true,
            'format' => $format,
            'filename' => Str::slug($validated['title']).'.'.$exporter->formats()[$format]['extension'],
            'mime' => $exporter->formats()[$format]['mime'],
            'content' => $content,
        ]);
    }

    /**
     * Parsing teks BibTeX mentah menjadi array referensi terstruktur.
     */
    public function parseBibtex(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $validated = $request->validate([
            'bibtex' => ['required', 'string'],
        ]);

        $client->recordInboundHit('/api/v1/external/references/parse-bibtex');

        $importer = app(ReferenceImportService::class);
        $parsed = $importer->parseBibtex($validated['bibtex']);

        return response()->json([
            'success' => true,
            'total_parsed' => count($parsed),
            'references' => $parsed,
        ]);
    }

    /**
     * Uji pengiriman webhook callback ke endpoint klien.
     */
    public function webhookTest(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $validated = $request->validate([
            'event' => ['nullable', 'string', 'max:64'],
            'payload' => ['nullable', 'array'],
        ]);

        $event = $validated['event'] ?? 'ping.test';
        $payload = $validated['payload'] ?? ['message' => 'Uji coba pengiriman webhook dari Xishie Hub.'];

        $result = $this->clientManager->sendWebhook($client, $event, $payload);

        return response()->json($result);
    }

    /**
     * Ekstrak dan validasi API key dari header request.
     */
    private function resolveClient(Request $request): ExternalClient|JsonResponse
    {
        $apiKey = $request->header('X-API-Key') ?: $request->bearerToken();

        $client = $this->clientManager->authenticateApiKey($apiKey);

        if ($client === null) {
            return response()->json([
                'error' => 'Unauthorized: API Key tidak valid atau izin proyek telah dinonaktifkan.',
                'hint' => 'Sertakan header X-API-Key atau Authorization: Bearer <API_KEY>. Kunci dapat digenerasikan di panel Connected Projects.',
            ], 401);
        }

        return $client;
    }
}
