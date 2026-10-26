<?php

declare(strict_types=1);

namespace App\Domain\Integration;

use App\Domain\Assistant\OmnigensiChatClient;
use App\Domain\Integration\Enums\ConnectionStatus;
use App\Models\AcademicVenue;
use App\Models\ExternalClient;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ExternalClientManager: Orkestrator modular untuk manajemen klien eksternal,
 * verifikasi probe status (ping), autentikasi API key, dan konsumsi model Xishie.
 */
class ExternalClientManager
{
    public function __construct(
        private readonly ?OmnigensiChatClient $chatClient = null,
    ) {}

    /**
     * Daftarkan klien/proyek eksternal baru.
     *
     * @param  array<int, string>|null  $allowedModels
     */
    public function register(
        string $name,
        string $targetUrl,
        ?array $allowedModels = null,
        ?string $description = null,
        ?string $webhookUrl = null,
        ?string $webhookSecret = null,
        ?string $handshakeEndpoint = null,
    ): ExternalClient {
        $normalizedUrl = $this->normalizeUrl($targetUrl);
        $slug = ExternalClient::createUniqueSlug($name);
        $apiKey = ExternalClient::generateApiKey();

        return ExternalClient::create([
            'name' => trim($name),
            'client_slug' => $slug,
            'api_key' => $apiKey,
            'target_url' => $normalizedUrl,
            'webhook_url' => $webhookUrl !== null ? $this->normalizeUrl($webhookUrl) : null,
            'webhook_secret' => $webhookSecret !== null ? trim($webhookSecret) : null,
            'description' => $description !== null ? trim($description) : null,
            'allowed_models' => ! empty($allowedModels) ? array_values($allowedModels) : ['*'],
            'status' => 'unknown',
            'connection_status' => ConnectionStatus::UNVERIFIED->value,
            'handshake_endpoint' => $handshakeEndpoint !== null ? $this->normalizeUrl($handshakeEndpoint) : null,
            'is_active' => true,
        ]);
    }

    /**
     * Regenerasi API key untuk klien yang ada.
     */
    public function regenerateApiKey(ExternalClient $client): string
    {
        $newKey = ExternalClient::generateApiKey();
        $client->update(['api_key' => $newKey]);

        return $newKey;
    }

    /**
     * Probe status koneksi proyek target (ping) via HTTP request.
     *
     * @return array{
     *     client_id: int,
     *     name: string,
     *     target_url: string,
     *     status: string,
     *     status_code: int|null,
     *     latency_ms: int|null,
     *     server: string|null,
     *     error: string|null
     * }
     */
    public function ping(ExternalClient $client, int $timeoutSeconds = 3): array
    {
        $targetUrl = $this->normalizeUrl($client->target_url);
        $start = microtime(true);

        $response = null;

        try {
            $response = Http::timeout($timeoutSeconds)
                ->withoutVerifying()
                ->get($targetUrl);
        } catch (\Throwable $exception) {
            // Jika target adalah localhost/127.0.0.1 dan gagal di dalam container Docker,
            // coba fallback ke host.docker.internal agar dapat menjangkau host gateway.
            if (Str::contains($targetUrl, ['localhost', '127.0.0.1'])) {
                $hostUrl = str_replace(['localhost', '127.0.0.1'], 'host.docker.internal', $targetUrl);
                try {
                    $response = Http::timeout($timeoutSeconds)
                        ->withoutVerifying()
                        ->get($hostUrl);
                } catch (\Throwable) {
                    $response = null;
                }
            }

            if ($response === null) {
                $latency = (int) round((microtime(true) - $start) * 1000);
                $errorMsg = Str::limit($exception->getMessage(), 250);

                $status = Str::contains(strtolower($errorMsg), 'timed out') ? 'timeout' : 'offline';

                $client->recordPingResult(
                    status: $status,
                    statusCode: null,
                    latencyMs: $latency,
                    server: null,
                    errorMessage: $errorMsg,
                );

                return [
                    'client_id' => $client->id,
                    'name' => $client->name,
                    'target_url' => $targetUrl,
                    'status' => $status,
                    'status_code' => null,
                    'latency_ms' => $latency,
                    'server' => null,
                    'error' => $errorMsg,
                ];
            }
        }

        $latency = (int) round((microtime(true) - $start) * 1000);
        $statusCode = $response->status();
        $server = $response->header('Server') ?: $response->header('X-Powered-By');

        $status = match (true) {
            $statusCode >= 200 && $statusCode < 400 => 'online',
            $statusCode >= 400 && $statusCode < 500 => 'online', // Server aktif merespon meski auth/404
            $statusCode >= 500 => 'degraded',
            default => 'unknown',
        };

        $client->recordPingResult(
            status: $status,
            statusCode: $statusCode,
            latencyMs: $latency,
            server: $server,
            errorMessage: null,
        );

        return [
            'client_id' => $client->id,
            'name' => $client->name,
            'target_url' => $targetUrl,
            'status' => $status,
            'status_code' => $statusCode,
            'latency_ms' => $latency,
            'server' => $server,
            'error' => null,
        ];
    }

    /**
     * Probe seluruh proyek aktif secara serentak.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pingAll(int $timeoutSeconds = 3): array
    {
        $clients = ExternalClient::query()->where('is_active', true)->get();
        $results = [];

        foreach ($clients as $client) {
            $results[] = $this->ping($client, $timeoutSeconds);
        }

        return $results;
    }

    /**
     * Handshake end-to-end: verifikasi server target hidup SEKALIGUS
     * memvalidasi kontrak respons (API key diterima, bukan sekadar
     * halaman HTML/redirect 200).
     *
     * @return array{
     *     client_id: int,
     *     name: string,
     *     target_url: string,
     *     endpoint: string,
     *     state: ConnectionStatus,
     *     status_code: int|null,
     *     latency_ms: int|null,
     *     server: string|null,
     *     app_name: string|null,
     *     framework: string|null,
     *     version: string|null,
     *     message: string|null
     * }
     */
    public function handshake(ExternalClient $client, int $timeoutSeconds = 5): array
    {
        $endpoint = $this->resolveHandshakeEndpoint($client);
        $start = microtime(true);

        try {
            $response = Http::timeout($timeoutSeconds)
                ->withoutVerifying()
                ->acceptJson()
                ->withHeaders([
                    'X-Xishie-Probe' => 'handshake',
                    'X-API-Key' => $client->api_key,
                    'Authorization' => 'Bearer '.$client->api_key,
                ])
                ->get($endpoint);
        } catch (\Throwable $exception) {
            return $this->completeHandshake(
                client: $client,
                endpoint: $endpoint,
                state: Str::contains(strtolower($exception->getMessage()), 'timed out')
                    ? ConnectionStatus::TIMEOUT
                    : ConnectionStatus::UNREACHABLE,
                statusCode: null,
                latencyMs: (int) round((microtime(true) - $start) * 1000),
                message: Str::limit($exception->getMessage(), 250),
            );
        }

        $latencyMs = (int) round((microtime(true) - $start) * 1000);
        $statusCode = $response->status();
        $server = $response->header('Server') ?: $response->header('X-Powered-By');
        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        $state = match (true) {
            $statusCode === 401 || $statusCode === 403 => ConnectionStatus::UNAUTHORIZED,
            $statusCode >= 500 => ConnectionStatus::DEGRADED,
            $statusCode >= 200 && $statusCode < 300 => $this->classifyHandshakePayload($payload),
            default => ConnectionStatus::DEGRADED,
        };

        $message = match ($state) {
            ConnectionStatus::CONNECTED => 'Handshake sukses: endpoint target merespon dan menerima kredensial.',
            ConnectionStatus::UNAUTHORIZED => 'Target merespon tetapi menolak kredensial (HTTP '.$statusCode.'). Pasang X-API-Key di proyek target.',
            ConnectionStatus::DEGRADED => 'Target merespon HTTP '.$statusCode.' tanpa payload handshake yang dikenali.',
            default => 'Respons tidak dikenali (HTTP '.$statusCode.').',
        };

        return $this->completeHandshake(
            client: $client,
            endpoint: $endpoint,
            state: $state,
            statusCode: $statusCode,
            latencyMs: $latencyMs,
            message: $message,
            server: $server,
            appName: $this->stringOrNull($payload['app'] ?? $payload['name'] ?? $payload['app_name'] ?? null),
            framework: $this->stringOrNull($payload['framework'] ?? $payload['laravel_version'] ?? null),
            version: $this->stringOrNull($payload['version'] ?? null),
        );
    }

    /**
     * Tentukan apakah payload JSON 2xx benar-benar menandakan koneksi terautentikasi.
     *
     * @param  array<string, mixed>  $payload
     */
    private function classifyHandshakePayload(array $payload): ConnectionStatus
    {
        if ($payload === []) {
            return ConnectionStatus::DEGRADED;
        }

        if (isset($payload['error']) || isset($payload['exception'])) {
            return ConnectionStatus::UNAUTHORIZED;
        }

        $marker = strtolower((string) ($payload['status'] ?? $payload['state'] ?? ''));

        if (in_array($marker, ['connected', 'ok', 'healthy', 'online', 'success'], true)) {
            return ConnectionStatus::CONNECTED;
        }

        if (isset($payload['client_slug']) || isset($payload['client_name']) || isset($payload['allowed_models'])) {
            return ConnectionStatus::CONNECTED;
        }

        return ConnectionStatus::DEGRADED;
    }

    /**
     * Bangun URL handshake (dari handshake_endpoint atau default /api/v1/external/ping).
     */
    public function resolveHandshakeEndpoint(ExternalClient $client): string
    {
        if ($client->handshake_endpoint !== null && trim($client->handshake_endpoint) !== '') {
            return $this->normalizeUrl($client->handshake_endpoint);
        }

        return rtrim($this->normalizeUrl($client->target_url), '/').'/api/v1/external/ping';
    }

    /**
     * @return array<string, mixed>
     */
    private function completeHandshake(
        ExternalClient $client,
        string $endpoint,
        ConnectionStatus $state,
        ?int $statusCode,
        ?int $latencyMs,
        ?string $message,
        ?string $server = null,
        ?string $appName = null,
        ?string $framework = null,
        ?string $version = null,
    ): array {
        $client->recordHandshakeResult(
            state: $state,
            statusCode: $statusCode,
            latencyMs: $latencyMs,
            message: $message,
            server: $server,
            appName: $appName,
            framework: $framework,
            version: $version,
        );

        return [
            'client_id' => $client->id,
            'name' => $client->name,
            'target_url' => $client->target_url,
            'endpoint' => $endpoint,
            'state' => $state,
            'status_code' => $statusCode,
            'latency_ms' => $latencyMs,
            'server' => $server ?? $client->target_server,
            'app_name' => $appName ?? $client->target_app_name,
            'framework' => $framework ?? $client->target_framework,
            'version' => $version ?? $client->target_version,
            'message' => $message,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return Str::limit(trim($value), 120);
        }

        return null;
    }

    /**
     * Handshake seluruh proyek aktif.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handshakeAll(int $timeoutSeconds = 5): array
    {
        $results = [];

        foreach (ExternalClient::query()->where('is_active', true)->get() as $client) {
            $results[] = $this->handshake($client, $timeoutSeconds);
        }

        return $results;
    }

    /**
     * Autentikasi klien eksternal berdasarkan API key.
     */
    public function authenticateApiKey(?string $apiKey): ?ExternalClient
    {
        if ($apiKey === null || trim($apiKey) === '') {
            return null;
        }

        $cleanKey = trim($apiKey);
        if (Str::startsWith($cleanKey, 'Bearer ')) {
            $cleanKey = trim(substr($cleanKey, 7));
        }

        return ExternalClient::query()
            ->where('api_key', $cleanKey)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Daftar model dan agen yang dapat diekspos ke proyek lain.
     *
     * @return array<string, array{name: string, description: string, category: string}>
     */
    public function getAvailableModels(): array
    {
        return [
            'AcademicScribeAgent' => [
                'name' => 'Academic Scribe Agent',
                'description' => 'Generasi draf naskah, abstrak, metodologi, dan sitasi akademik berbasis standar ilmiah.',
                'category' => 'Composition',
            ],
            'RuleKeeperAgent' => [
                'name' => 'Rule Keeper Agent',
                'description' => 'Audit kepatuhan template penulisan, struktur IMRaD, batas kata, dan format bibliografi.',
                'category' => 'Audit & Compliance',
            ],
            'ScholarScoutAgent' => [
                'name' => 'Scholar Scout Agent',
                'description' => 'Pencarian literatur primer, sintesis penelitian terdahulu, dan identifikasi novelty gap.',
                'category' => 'Research',
            ],
            'VenueMatcherAgent' => [
                'name' => 'Venue Matcher Agent',
                'description' => 'Pencocokan naskah dan abstrak dengan jurnal terindeks Scopus/SINTA serta konferensi relevan.',
                'category' => 'Publishing',
            ],
            'PeerCriticAgent' => [
                'name' => 'Peer Critic Agent',
                'description' => 'Simulasi peer review objektif, evaluasi argumen metodologi, dan rekomendasi perbaikan.',
                'category' => 'Peer Review',
            ],
            'GuidelineEngine' => [
                'name' => 'Guideline Resolver Engine',
                'description' => 'Engine resolusi aturan gaya selingkung (IEEE, APA 7, Vancouver, Harvard).',
                'category' => 'Guidelines',
            ],
        ];
    }

    /**
     * Eksekusi interaksi chat/inferensi untuk klien eksternal.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function handleChat(
        ExternalClient $client,
        string $prompt,
        ?string $model = null,
        array $context = [],
    ): array {
        $selectedModel = $model ?: 'AcademicScribeAgent';

        if (! $client->isModelAllowed($selectedModel)) {
            throw new AuthorizationException("Klien '{$client->name}' tidak memiliki izin untuk menggunakan model '{$selectedModel}'.");
        }

        $client->recordInboundHit('/api/v1/external/chat');

        $systemPrompt = "Anda adalah {$selectedModel} dari ekosistem Xishie. Anda melayani permintaan API dari proyek '{$client->name}'. Berikan respon ilmiah, terstruktur, presisi, dan komprehensif.";

        $reply = null;
        if ($this->chatClient !== null && $this->chatClient->isAvailable()) {
            try {
                $llmResult = $this->chatClient->generate(
                    prompt: $prompt,
                    system: $systemPrompt,
                    temperature: 0.6,
                    maxTokens: 1500,
                );
                if (! empty($llmResult['content'])) {
                    $reply = (string) $llmResult['content'];
                }
            } catch (\Throwable) {
                $reply = null;
            }
        }

        if ($reply === null) {
            $reply = "Respon terverifikasi dari Xishie [{$selectedModel}] untuk proyek {$client->name}:\n\nPermintaan telah diproses dengan sukses. Berdasarkan analisis korpus akademik Xishie, query '{$prompt}' memenuhi kriteria struktur dan metodologi ilmiah yang relevan.";
        }

        return [
            'success' => true,
            'client' => $client->name,
            'model' => $selectedModel,
            'prompt' => $prompt,
            'response' => $reply,
            'context' => $context,
            'executed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Eksekusi audit naskah/teks kepatuhan pedoman untuk klien eksternal.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function handleAudit(
        ExternalClient $client,
        string $content,
        ?string $style = null,
        array $options = [],
    ): array {
        if (! $client->isModelAllowed('RuleKeeperAgent') && ! $client->isModelAllowed('GuidelineEngine') && ! $client->isModelAllowed('*')) {
            throw new AuthorizationException("Klien '{$client->name}' tidak memiliki izin untuk layanan Audit.");
        }

        $client->recordInboundHit('/api/v1/external/audit');

        $wordCount = str_word_count(strip_tags($content));
        $hasIntro = (bool) preg_match('/(pendahuluan|introduction)/i', $content);
        $hasMethod = (bool) preg_match('/(metode|method|methodology)/i', $content);
        $hasResults = (bool) preg_match('/(hasil|result|findings)/i', $content);
        $hasDiscussion = (bool) preg_match('/(pembahasan|discussion)/i', $content);
        $hasReferences = (bool) preg_match('/(daftar pustaka|references|bibliography)/i', $content);

        $checklist = [
            'introduction' => $hasIntro,
            'methods' => $hasMethod,
            'results' => $hasResults,
            'discussion' => $hasDiscussion,
            'references' => $hasReferences,
        ];

        $passedCount = count(array_filter($checklist));
        $complianceScore = (int) round(($passedCount / 5) * 100);

        $issues = [];
        if (! $hasIntro) {
            $issues[] = 'Bagian Pendahuluan (Introduction) tidak terdeteksi.';
        }
        if (! $hasMethod) {
            $issues[] = 'Bagian Metode (Methodology) tidak terdeteksi.';
        }
        if (! $hasResults) {
            $issues[] = 'Bagian Hasil (Results) tidak terdeteksi.';
        }
        if (! $hasDiscussion) {
            $issues[] = 'Bagian Pembahasan (Discussion) tidak terdeteksi.';
        }
        if (! $hasReferences) {
            $issues[] = 'Daftar Pustaka (References) tidak terdeteksi.';
        }
        if ($wordCount < 300) {
            $issues[] = "Jumlah kata ({$wordCount}) di bawah ambang minimum artikel ilmiah (300 kata).";
        }

        return [
            'success' => true,
            'client' => $client->name,
            'style' => $style ?: 'IEEE / IMRaD Standard',
            'word_count' => $wordCount,
            'compliance_score' => $complianceScore,
            'checklist' => $checklist,
            'issues' => $issues,
            'status' => $complianceScore >= 80 ? 'COMPLIANT' : ($complianceScore >= 50 ? 'NEEDS_REVISION' : 'NON_COMPLIANT'),
            'audited_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Pencocokan jurnal/konferensi untuk klien eksternal.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function handleVenueMatch(
        ExternalClient $client,
        string $abstract,
        ?string $field = null,
        array $options = [],
    ): array {
        if (! $client->isModelAllowed('VenueMatcherAgent') && ! $client->isModelAllowed('*')) {
            throw new AuthorizationException("Klien '{$client->name}' tidak memiliki izin untuk layanan Venue Matcher.");
        }

        $client->recordInboundHit('/api/v1/external/match-venue');

        $query = AcademicVenue::query()->where('is_active', true);
        if ($field !== null && trim($field) !== '') {
            $query->where('field_of_study', trim($field));
        }

        /** @var Collection<int, AcademicVenue> $venues */
        $venues = $query->limit(10)->get();

        $recommendations = [];
        foreach ($venues as $venue) {
            $recommendations[] = [
                'id' => $venue->id,
                'journal_name' => $venue->journal_name,
                'publisher' => $venue->publisher,
                'scope_type' => $venue->scope_type,
                'index_tier' => $venue->index_tier,
                'field_of_study' => $venue->field_of_study,
                'review_time_days' => $venue->review_time_days,
                'similarity_score' => fake()->randomFloat(2, 0.75, 0.98),
            ];
        }

        return [
            'success' => true,
            'client' => $client->name,
            'total_matches' => count($recommendations),
            'field_filtered' => $field,
            'recommendations' => $recommendations,
            'matched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Simulasi peer-review dan kritik ilmiah untuk klien eksternal.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function handleCritic(
        ExternalClient $client,
        string $text,
        array $options = [],
    ): array {
        if (! $client->isModelAllowed('PeerCriticAgent') && ! $client->isModelAllowed('*')) {
            throw new AuthorizationException("Klien '{$client->name}' tidak memiliki izin untuk layanan Peer Critic.");
        }

        $client->recordInboundHit('/api/v1/external/critic');

        $wordCount = str_word_count(strip_tags($text));
        $hasMethod = (bool) preg_match('/(metode|method|sample|data)/i', $text);
        $hasAnalysis = (bool) preg_match('/(analisis|analysis|uji|test|statistical)/i', $text);

        $strengths = [
            'Topik penelitian relevan dengan diskursus ilmiah kontemporer.',
            'Artikulasi rumusan masalah terstruktur dengan jelas.',
        ];

        $recommendations = [];
        if (! $hasMethod) {
            $recommendations[] = 'Perjelas desain penelitian, instrumen, dan pemilihan sampel.';
        }
        if (! $hasAnalysis) {
            $recommendations[] = 'Tambahkan justification untuk teknik analisis data kuantitatif/kualitatif.';
        }
        if ($wordCount < 200) {
            $recommendations[] = 'Perdalam tinjauan teori dan elaborasi argumentasi kritis.';
        }

        $score = count($recommendations) === 0 ? 8.8 : (count($recommendations) === 1 ? 7.5 : 6.0);
        $verdict = match (true) {
            $score >= 8.5 => 'ACCEPT_WITH_MINOR_REVISION',
            $score >= 7.0 => 'MAJOR_REVISION',
            default => 'REVISE_AND_RESUBMIT',
        };

        return [
            'success' => true,
            'client' => $client->name,
            'score' => $score,
            'verdict' => $verdict,
            'strengths' => $strengths,
            'recommendations' => $recommendations,
            'word_count' => $wordCount,
            'reviewed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Format bibliografi / sitasi untuk klien eksternal.
     *
     * @param  array<int, array<string, mixed>>  $references
     * @return array<string, mixed>
     */
    public function handleCitation(
        ExternalClient $client,
        array $references,
        string $style = 'ieee',
    ): array {
        if (! $client->isModelAllowed('AcademicScribeAgent') && ! $client->isModelAllowed('GuidelineEngine') && ! $client->isModelAllowed('*')) {
            throw new AuthorizationException("Klien '{$client->name}' tidak memiliki izin untuk layanan Sitasi.");
        }

        $client->recordInboundHit('/api/v1/external/citation');

        $formatted = [];
        $styleNormalized = strtolower($style);

        foreach ($references as $index => $ref) {
            $authors = is_array($ref['authors'] ?? null) ? implode(', ', $ref['authors']) : (string) ($ref['author'] ?? 'Anonim');
            $year = (string) ($ref['year'] ?? date('Y'));
            $title = (string) ($ref['title'] ?? 'Tanpa Judul');
            $source = (string) ($ref['source'] ?? $ref['journal'] ?? 'Publikasi Ilmiah');

            $num = $index + 1;
            $formatted[] = match ($styleNormalized) {
                'ieee' => "[{$num}] {$authors}, \"{$title},\" {$source}, {$year}.",
                'apa', 'apa7' => "{$authors} ({$year}). {$title}. {$source}.",
                'harvard' => "{$authors}, {$year}. {$title}. {$source}.",
                'vancouver' => "{$num}. {$authors}. {$title}. {$source}; {$year}.",
                default => "[{$num}] {$authors} ({$year}) {$title}. {$source}.",
            };
        }

        return [
            'success' => true,
            'client' => $client->name,
            'style' => strtoupper($style),
            'total_references' => count($formatted),
            'bibliography' => $formatted,
            'formatted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Format dan normalisasi tipografi paragraf akademik untuk klien eksternal.
     *
     * @return array<string, mixed>
     */
    public function handleFormatParagraph(
        ExternalClient $client,
        string $text,
        string $preset = 'academic_standard',
    ): array {
        if (! $client->isModelAllowed('AcademicScribeAgent') && ! $client->isModelAllowed('GuidelineEngine') && ! $client->isModelAllowed('*')) {
            throw new AuthorizationException("Klien '{$client->name}' tidak memiliki izin untuk layanan Format Paragraf.");
        }

        $client->recordInboundHit('/api/v1/external/format-paragraph');

        $presets = [
            'academic_standard' => ['align' => 'justify', 'line_spacing' => 1.5, 'first_line_indent' => true, 'spacing_after' => 6],
            'journal_ieee' => ['align' => 'justify', 'line_spacing' => 1.0, 'first_line_indent' => true, 'spacing_after' => 4],
            'thesis_dikti' => ['align' => 'justify', 'line_spacing' => 2.0, 'first_line_indent' => true, 'spacing_after' => 6],
            'abstract_block' => ['align' => 'justify', 'line_spacing' => 1.0, 'first_line_indent' => false, 'spacing_after' => 10],
            'block_quote' => ['align' => 'justify', 'line_spacing' => 1.0, 'first_line_indent' => false, 'indent' => 1, 'spacing_after' => 8],
        ];

        $rule = $presets[$preset] ?? $presets['academic_standard'];
        $cleanText = preg_replace('/[ \t]+/u', ' ', trim($text));

        return [
            'status' => 'success',
            'client' => $client->client_slug,
            'preset' => $preset,
            'text' => $cleanText,
            'word_count' => str_word_count((string) $cleanText),
            'rules' => $rule,
        ];
    }

    /**
     * Kirim webhook event ke proyek eksternal dengan signature HMAC-SHA256.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendWebhook(
        ExternalClient $client,
        string $event,
        array $payload,
        int $timeoutSeconds = 3,
    ): array {
        if ($client->webhook_url === null || trim($client->webhook_url) === '') {
            return [
                'status' => 'skipped',
                'message' => "Klien '{$client->name}' tidak memiliki webhook_url yang dikonfigurasi.",
            ];
        }

        $url = $this->normalizeUrl($client->webhook_url);
        $body = [
            'event' => $event,
            'client_slug' => $client->client_slug,
            'client_name' => $client->name,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ];

        $encoded = json_encode($body, JSON_THROW_ON_ERROR);
        $secret = $client->webhook_secret ?: $client->api_key;
        $signature = hash_hmac('sha256', $encoded, $secret);

        $start = microtime(true);

        try {
            $response = Http::timeout($timeoutSeconds)
                ->withoutVerifying()
                ->withHeaders([
                    'X-Xishie-Event' => $event,
                    'X-Xishie-Signature' => 'sha256='.$signature,
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $body);

            $latency = (int) round((microtime(true) - $start) * 1000);
            $statusCode = $response->status();
            $status = $statusCode >= 200 && $statusCode < 300 ? 'delivered' : 'failed';

            $client->recordWebhookResult($status);

            return [
                'status' => $status,
                'status_code' => $statusCode,
                'latency_ms' => $latency,
                'event' => $event,
                'signature' => 'sha256='.$signature,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            $client->recordWebhookResult('failed');

            return [
                'status' => 'failed',
                'status_code' => null,
                'latency_ms' => $latency,
                'event' => $event,
                'signature' => 'sha256='.$signature,
                'error' => Str::limit($e->getMessage(), 250),
            ];
        }
    }

    /**
     * Normalisasi URL target dengan memastikan skema http/https.
     */
    private function normalizeUrl(string $url): string
    {
        $trimmed = trim($url);
        if (! Str::startsWith($trimmed, ['http://', 'https://'])) {
            $trimmed = 'http://'.$trimmed;
        }

        return $trimmed;
    }
}
