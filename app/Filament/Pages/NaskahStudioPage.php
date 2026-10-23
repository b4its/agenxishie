<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Assistant\OmnigensiChatClient;
use App\Domain\Assistant\SlashCommand;
use App\Domain\Assistant\XishieAssistantService;
use App\Domain\Publication\NaskahBlockParser;
use App\Domain\Shared\DocumentTextExtractor;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\NaskahDocument;
use App\Models\OmnigensiAgentLog;
use App\Models\XishieProject;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NaskahStudioPage: editor naskah bergaya word processor (mengikuti WordCraft)
 * dengan ribbon, panel navigasi judul, kanvas halaman, galeri gaya, dan status
 * bar. Naskah dapat bersumber dari unggahan dokumen lokal (.docx/.pdf/.txt/.md)
 * atau dari sub-bab sebuah proyek Xishie.
 *
 * Xishie Assistant hadir sebagai tombol mengapung di kanan bawah; menekannya
 * membuka jendela tools berisi opsi perintah yang dapat dipilih untuk diaktifkan.
 */
class NaskahStudioPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = 'Naskah Studio';

    protected static ?string $slug = 'naskah-studio';

    protected static ?string $title = 'Naskah Studio';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.naskah-studio';

    public ?int $documentId = null;

    public string $docTitle = 'Dokumen Tanpa Judul';

    /**
     * Blok naskah: ['type' => heading|paragraph|quote|bullet|numbered, 'level' => int, 'text' => string].
     *
     * @var array<int, array{type: string, level: int, text: string}>
     */
    public array $blocks = [];

    public string $sourceType = NaskahDocument::SOURCE_LOCAL;

    public ?string $sourceFilename = null;

    public ?string $sourceStage = null;

    public bool $dirty = false;

    public ?string $lastSavedAt = null;

    // --- Sumber dokumen ---
    public mixed $upload = null;

    public ?int $sourceProjectId = null;

    public string $sourceProjectStage = ProjectStage::DRAFT->value;

    // --- Preferensi tampilan (ribbon & kanvas) ---
    public string $activeTab = 'home';

    public string $zoom = '100';

    public bool $showNavigation = true;

    public string $fontFamily = 'serif';

    public string $fontSize = '16';

    // --- Xishie Assistant (mengapung) ---
    public bool $assistantOpen = false;

    /**
     * Tools asisten yang dipilih (diaktifkan) dari jendela tools.
     *
     * @var array<int, string>
     */
    public array $activeTools = [];

    public string $assistantInput = '';

    /**
     * @var array<int, array{role: string, content: string, command?: string}>
     */
    public array $assistantLog = [];

    public bool $assistantBusy = false;

    public function mount(): void
    {
        $documentId = request()->query('document');

        if (is_numeric($documentId)) {
            $document = NaskahDocument::query()->find((int) $documentId);

            if ($document !== null) {
                $this->loadDocument($document);

                return;
            }
        }

        $this->blocks = [
            ['type' => 'heading', 'level' => 1, 'text' => 'Dokumen Tanpa Judul'],
            ['type' => 'paragraph', 'text' => 'Mulai menulis di sini, atau muat naskah dari dokumen lokal maupun dari proyek Anda melalui panel sumber di atas.'],
        ];
    }

    // =====================================================================
    // Sumber dokumen
    // =====================================================================

    /**
     * Muat naskah dari berkas lokal yang diunggah (DOCX/PDF/TXT/MD).
     */
    public function updatedUpload(): void
    {
        if ($this->upload === null) {
            return;
        }

        try {
            $binary = $this->upload->get();
            $filename = (string) $this->upload->getClientOriginalName();
            $text = app(DocumentTextExtractor::class)->extract($binary);

            if (trim($text) === '') {
                Notification::make()
                    ->title('Teks tidak terbaca')
                    ->body('Berkas tidak memuat teks yang dapat diekstrak. Coba DOCX, PDF, atau TXT.')
                    ->warning()
                    ->send();

                return;
            }

            $blocks = app(NaskahBlockParser::class)->parse($text);

            $this->blocks = $blocks !== [] ? $blocks : $this->blocks;
            $this->docTitle = pathinfo($filename, PATHINFO_FILENAME) ?: 'Dokumen Impor';
            $this->sourceType = NaskahDocument::SOURCE_LOCAL;
            $this->sourceFilename = $filename;
            $this->sourceStage = null;
            $this->sourceProjectId = null;
            $this->documentId = null;
            $this->dirty = true;

            Notification::make()
                ->title('Naskah dimuat dari berkas')
                ->body($filename.' — '.count($blocks).' blok diekstrak.')
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Gagal memuat berkas')
                ->body(Str::limit($exception->getMessage(), 160))
                ->danger()
                ->send();
        }
    }

    /**
     * Muat naskah dari sub-bab tahap sebuah proyek Xishie.
     */
    public function loadFromProject(): void
    {
        $project = $this->sourceProjectId !== null ? XishieProject::find($this->sourceProjectId) : null;

        if ($project === null) {
            Notification::make()->title('Pilih proyek terlebih dahulu')->warning()->send();

            return;
        }

        $stageEnum = ProjectStage::tryFrom($this->sourceProjectStage) ?? ProjectStage::DRAFT;
        $stage = $project->stages()->where('stage_name', $stageEnum->value)->first();

        if ($stage === null) {
            Notification::make()
                ->title('Tahap belum tersedia')
                ->body('Proyek ini belum memiliki rekaman tahap '.$stageEnum->label().'.')
                ->warning()
                ->send();

            return;
        }

        $blocks = app(NaskahBlockParser::class)->fromProjectStage($project, $stage);

        if ($blocks === []) {
            Notification::make()
                ->title('Naskah proyek masih kosong')
                ->body('Tahap '.$stageEnum->label().' belum memiliki sub-bab untuk dimuat.')
                ->warning()
                ->send();

            return;
        }

        $this->blocks = $blocks;
        $this->docTitle = $project->title.' — '.$stageEnum->label();
        $this->sourceType = NaskahDocument::SOURCE_PROJECT;
        $this->sourceStage = $stageEnum->value;
        $this->sourceFilename = null;
        $this->documentId = null;
        $this->dirty = true;

        Notification::make()
            ->title('Naskah proyek dimuat')
            ->body($project->title.' ('.$stageEnum->label().') — '.count($blocks).' blok.')
            ->success()
            ->send();
    }

    /**
     * Muat dokumen tersimpan ke kanvas.
     */
    public function loadDocument(NaskahDocument $document): void
    {
        $this->documentId = $document->id;
        $this->docTitle = $document->title;
        $this->blocks = app(NaskahBlockParser::class)->normalize((array) $document->blocks);
        $this->sourceType = $document->source_type;
        $this->sourceFilename = $document->source_filename;
        $this->sourceStage = $document->source_stage;
        $this->sourceProjectId = $document->project_id;
        $this->lastSavedAt = $document->last_saved_at?->diffForHumans();
        $this->dirty = false;
    }

    /**
     * Buka dokumen tersimpan berdasarkan id (dari daftar dokumen).
     */
    public function openDocument(int $documentId): void
    {
        $document = NaskahDocument::query()->find($documentId);

        if ($document === null) {
            Notification::make()->title('Dokumen tidak ditemukan')->danger()->send();

            return;
        }

        $this->loadDocument($document);

        Notification::make()->title('Dokumen dibuka')->success()->send();
    }

    /**
     * Dokumen kosong baru.
     */
    public function newDocument(): void
    {
        $this->documentId = null;
        $this->docTitle = 'Dokumen Tanpa Judul';
        $this->sourceType = NaskahDocument::SOURCE_LOCAL;
        $this->sourceFilename = null;
        $this->sourceStage = null;
        $this->sourceProjectId = null;
        $this->lastSavedAt = null;
        $this->blocks = [
            ['type' => 'heading', 'level' => 1, 'text' => 'Dokumen Tanpa Judul'],
            ['type' => 'paragraph', 'text' => 'Mulai menulis di sini…'],
        ];
        $this->dirty = false;
    }

    // =====================================================================
    // Penyuntingan blok
    // =====================================================================

    public function markDirty(): void
    {
        $this->dirty = true;
    }

    public function addBlock(string $type = 'paragraph'): void
    {
        $level = $type === 'heading' ? 1 : 0;

        $this->blocks[] = ['type' => $type, 'level' => $level, 'text' => ''];
        $this->dirty = true;
    }

    public function removeBlock(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        array_splice($this->blocks, $index, 1);
        $this->blocks = array_values($this->blocks);
        $this->dirty = true;
    }

    public function moveBlock(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->blocks[$index], $this->blocks[$target])) {
            return;
        }

        [$this->blocks[$index], $this->blocks[$target]] = [$this->blocks[$target], $this->blocks[$index]];
        $this->dirty = true;
    }

    /**
     * Terapkan gaya blok (Paragraph/Heading/Quote/List) ke satu blok.
     */
    public function applyStyle(int $index, string $style): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        [$type, $level] = match ($style) {
            'title' => ['heading', 1],
            'heading1' => ['heading', 1],
            'heading2' => ['heading', 2],
            'heading3' => ['heading', 3],
            'quote' => ['quote', 0],
            'bullet' => ['bullet', 0],
            'numbered' => ['numbered', 0],
            default => ['paragraph', 0],
        };

        $this->blocks[$index]['type'] = $type;
        $this->blocks[$index]['level'] = $level;
        $this->dirty = true;
    }

    // =====================================================================
    // Simpan & ekspor
    // =====================================================================

    public function save(): void
    {
        $blocks = app(NaskahBlockParser::class)->normalize($this->blocks);
        $wordCount = app(NaskahBlockParser::class)->countWords($blocks);

        $data = [
            'title' => trim($this->docTitle) !== '' ? trim($this->docTitle) : 'Dokumen Tanpa Judul',
            'source_type' => in_array($this->sourceType, [NaskahDocument::SOURCE_LOCAL, NaskahDocument::SOURCE_PROJECT], true)
                ? $this->sourceType
                : NaskahDocument::SOURCE_LOCAL,
            'source_stage' => $this->sourceType === NaskahDocument::SOURCE_PROJECT ? $this->sourceStage : null,
            'source_filename' => $this->sourceFilename,
            'project_id' => $this->sourceType === NaskahDocument::SOURCE_PROJECT ? $this->sourceProjectId : null,
            'blocks' => $blocks,
            'word_count' => $wordCount,
            'last_saved_at' => now(),
            'user_id' => Auth::id(),
        ];

        if ($this->documentId !== null && ($document = NaskahDocument::query()->find($this->documentId)) !== null) {
            $document->update($data);
        } else {
            $document = NaskahDocument::create($data);
            $this->documentId = $document->id;
        }

        $this->blocks = $blocks;
        $this->dirty = false;
        $this->lastSavedAt = 'baru saja';

        Notification::make()
            ->title('Naskah tersimpan')
            ->body($wordCount.' kata · '.count($blocks).' blok.')
            ->success()
            ->send();
    }

    /**
     * Unduh naskah dalam format markdown/txt/html.
     */
    public function export(string $format = 'md'): ?StreamedResponse
    {
        if ($this->blocks === []) {
            Notification::make()->title('Naskah masih kosong')->warning()->send();

            return null;
        }

        $document = new NaskahDocument(['title' => $this->docTitle, 'blocks' => $this->blocks]);
        $base = Str::slug($this->docTitle) ?: 'naskah';

        [$content, $extension, $mime] = match ($format) {
            'txt' => [$document->toPlainText(), 'txt', 'text/plain; charset=UTF-8'],
            'html' => [$this->toHtml($document), 'html', 'text/html; charset=UTF-8'],
            default => ['# '.$this->docTitle."\n\n".$document->toPlainText(), 'md', 'text/markdown; charset=UTF-8'],
        };

        $filename = $base.'.'.$extension;

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, ['Content-Type' => $mime]);
    }

    private function toHtml(NaskahDocument $document): string
    {
        $escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = $escape((string) $this->docTitle);

        $body = '';
        foreach ($document->blocks as $block) {
            $text = $escape((string) ($block['text'] ?? ''));
            $type = (string) ($block['type'] ?? 'paragraph');

            $body .= match ($type) {
                'heading' => '<h'.(int) ($block['level'] ?? 1).'>'.$text.'</h'.(int) ($block['level'] ?? 1).'>',
                'quote' => '<blockquote>'.$text.'</blockquote>',
                'bullet' => '<li>'.$text.'</li>',
                'numbered' => '<li>'.$text.'</li>',
                default => '<p>'.$text.'</p>',
            };
        }

        return '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>'.$title.'</title>'
            .'<style>body{font-family:Georgia,serif;line-height:1.7;max-width:800px;margin:2rem auto;padding:0 1.5rem;color:#111}'
            .'h1,h2,h3{font-family:system-ui,sans-serif}li{margin-bottom:.35rem}blockquote{border-left:3px solid #1e40af;padding-left:1rem;color:#334155;font-style:italic}</style>'
            .'</head><body>'.$body.'</body></html>';
    }

    // =====================================================================
    // Xishie Assistant (mengapung)
    // =====================================================================

    /**
     * @return array<int, array{name: string, label: string, description: string}>
     */
    public function assistantTools(): array
    {
        $tools = [];
        foreach (SlashCommand::cases() as $command) {
            $tools[] = [
                'name' => $command->value,
                'label' => str_replace('-', ' ', ltrim($command->value, '/')),
                'description' => $command->description(),
            ];
        }

        $tools[] = [
            'name' => 'chat',
            'label' => 'tanya bebas',
            'description' => 'Ajukan pertanyaan bebas; jawaban memakai konteks naskah dan proyek.',
        ];

        return $tools;
    }

    /**
     * Aktifkan/nonaktifkan sebuah tool asisten.
     */
    public function toggleTool(string $tool): void
    {
        if (in_array($tool, $this->activeTools, true)) {
            $this->activeTools = array_values(array_diff($this->activeTools, [$tool]));

            return;
        }

        $this->activeTools[] = $tool;
    }

    public function openAssistant(): void
    {
        $this->assistantOpen = true;
    }

    public function closeAssistant(): void
    {
        $this->assistantOpen = false;
    }

    /**
     * Jalankan tool terpilih terhadap konteks naskah; hasil masuk ke log.
     */
    public function runTool(string $tool): void
    {
        if (! in_array($tool, $this->activeTools, true)) {
            $this->activeTools[] = $tool;
        }

        $documentText = $this->documentText();

        if (str_starts_with($tool, '/')) {
            $input = $tool;

            if (str_contains($tool, 'parafrase')) {
                $excerpt = Str::limit(trim($documentText), 400, '');
                $input = $tool.' '.$excerpt;
            }

            $this->dispatchAssistant($input, $tool);

            return;
        }

        // Tool "chat": butuh pertanyaan pengguna.
        $question = trim($this->assistantInput);

        if ($question === '') {
            Notification::make()
                ->title('Tulis pertanyaan dulu')
                ->body('Ketik pertanyaan pada kolom chat, lalu tekan Kirim.')
                ->info()
                ->send();

            return;
        }

        $this->dispatchAssistant($question.' '.Str::limit($documentText, 600, ''), 'chat');
    }

    /**
     * Kirim pesan bebas dari kolom chat asisten.
     */
    public function sendAssistant(): void
    {
        $question = trim($this->assistantInput);

        if ($question === '') {
            return;
        }

        $this->dispatchAssistant($question, 'freeform');
    }

    private function dispatchAssistant(string $input, string $command): void
    {
        $this->assistantLog[] = ['role' => 'user', 'content' => $input, 'command' => $command];
        $this->assistantInput = '';
        $this->assistantBusy = true;

        try {
            $project = $this->resolveAssistantProject();
            $reply = $this->answer($project, $input);

            $this->assistantLog[] = ['role' => 'assistant', 'content' => $reply, 'command' => $command];

            if ($project !== null) {
                OmnigensiAgentLog::create([
                    'project_id' => $project->id,
                    'agent_name' => 'Xishie-Assistant',
                    'action' => $command,
                    'control_mode' => $project->control_mode->value,
                    'ttft_ms' => 0,
                    'execution_time_ms' => 0,
                    'token_usage' => str_word_count($reply),
                    'hallucination_rejected_count' => 0,
                    'status' => 'success',
                    'payload' => ['query' => Str::limit($input, 500), 'command' => $command, 'surface' => 'naskah-studio'],
                ]);
            }
        } catch (\Throwable $exception) {
            $this->assistantLog[] = ['role' => 'assistant', 'content' => '[Gagal] '.$exception->getMessage(), 'command' => $command];
        } finally {
            $this->assistantBusy = false;
        }
    }

    /**
     * Proyek konteks asisten: dari sumber terpilih, atau proyek aktif pengguna.
     */
    private function resolveAssistantProject(): ?XishieProject
    {
        if ($this->sourceType === NaskahDocument::SOURCE_PROJECT && $this->sourceProjectId !== null) {
            return XishieProject::query()->find($this->sourceProjectId);
        }

        if ($this->documentId !== null) {
            return NaskahDocument::query()->find($this->documentId)?->project;
        }

        return XishieProject::query()->latest()->first();
    }

    private function answer(?XishieProject $project, string $input): string
    {
        $documentText = Str::limit($this->documentText(), 1500, ' …');

        if ($project !== null) {
            $result = app(XishieAssistantService::class)->handle($project, $input);

            return (string) $result['reply'];
        }

        // Tanpa proyek: jawab lewat LLM dengan konteks naskah, fallback offline.
        $wordCount = app(NaskahBlockParser::class)->countWords($this->blocks);

        $llm = app(OmnigensiChatClient::class);
        $generated = $llm->generate(
            "Konteks naskah:\n".$documentText."\n\nPermintaan pengguna:\n".$input,
            'Anda Xishie Assistant untuk penulisan akademik. Jawab ringkas dan actionable dalam Bahasa Indonesia. Jangan mengarang referensi.',
            0.6,
            700,
        );

        if ($generated !== null && trim((string) ($generated['content'] ?? '')) !== '') {
            return trim((string) $generated['content']);
        }

        return "Dokumen '{$this->docTitle}' berisi ".count($this->blocks)." blok dan {$wordCount} kata. "
            .'Untuk perintah berbasis proyek (/sitasi, /cek-gap, /cek-pedoman), muat dulu naskah dari sebuah proyek.';
    }

    public function documentText(): string
    {
        return (new NaskahDocument(['blocks' => $this->blocks]))->toPlainText();
    }

    // =====================================================================
    // Data tampilan
    // =====================================================================

    /**
     * Daftar judul untuk panel navigasi (heading blok).
     *
     * @return array<int, array{index: int, level: int, text: string}>
     */
    public function outline(): array
    {
        $items = [];

        foreach ($this->blocks as $index => $block) {
            if (($block['type'] ?? '') !== 'heading') {
                continue;
            }

            $text = trim((string) ($block['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $items[] = [
                'index' => $index,
                'level' => (int) ($block['level'] ?? 1),
                'text' => $text,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $wordCount = app(NaskahBlockParser::class)->countWords($this->blocks);

        return [
            'outline' => $this->outline(),
            'wordCount' => $wordCount,
            'charCount' => mb_strlen($this->documentText()),
            'blockCount' => count($this->blocks),
            'projects' => XishieProject::query()->latest()->get(['id', 'title']),
            'documents' => NaskahDocument::query()
                ->latest('last_saved_at')
                ->limit(8)
                ->get(['id', 'title', 'source_type', 'word_count', 'last_saved_at']),
            'assistantTools' => $this->assistantTools(),
            'stageOptions' => ProjectStage::options(),
            'ribbonTabs' => [
                'home' => 'Home',
                'insert' => 'Sisipkan',
                'layout' => 'Tata Letak',
                'references' => 'Referensi',
                'review' => 'Review',
                'view' => 'Tampilan',
            ],
            'stylesGallery' => [
                'normal' => 'Normal',
                'title' => 'Title',
                'heading1' => 'Heading 1',
                'heading2' => 'Heading 2',
                'heading3' => 'Heading 3',
                'quote' => 'Kutipan',
                'bullet' => 'Daftar Poin',
                'numbered' => 'Daftar Nomor',
            ],
        ];
    }
}
