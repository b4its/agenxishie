<?php

declare(strict_types=1);

namespace App\Domain\Publication\Concerns;

use App\Domain\Assistant\OmnigensiChatClient;
use App\Domain\Assistant\SlashCommand;
use App\Domain\Assistant\XishieAssistantService;
use App\Domain\Publication\NaskahBlockParser;
use App\Domain\Publication\NaskahDocxExporter;
use App\Domain\Publication\NaskahOdtExporter;
use App\Domain\Publication\NaskahRichModel;
use App\Domain\Shared\DocumentTextExtractor;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\NaskahDocument;
use App\Models\OmnigensiAgentLog;
use App\Models\XishieProject;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NaskahStudioPage: word processor web bergaya WordCraft.
 *
 * Ribbon lengkap (File/Home/Insert/Design/Layout/References/Review/View/
 * Mailings/Table/Outlining), format inline & paragraf, tabel, gambar,
 * komentar, track changes, daftar isi, catatan kaki, watermark, pengaturan
 * halaman, mail merge CSV, pencarian, mode tampilan, dan ekspor DOCX/HTML/MD/TXT.
 *
 * Xishie Assistant tampil mengapung di kanan bawah.
 */
trait HandlesNaskahStudio
{
    public ?int $documentId = null;

    public string $docTitle = 'Dokumen Tanpa Judul';

    /**
     * Blok naskah kaya (lihat NaskahRichModel untuk skema).
     *
     * @var array<int, array<string, mixed>>
     */
    public array $blocks = [];

    public string $sourceType = NaskahDocument::SOURCE_LOCAL;

    public ?string $sourceFilename = null;

    public ?string $sourceStage = null;

    public bool $dirty = false;

    public ?string $lastSavedAt = null;

    // ===== Sumber dokumen =====
    public mixed $upload = null;

    public mixed $imageUpload = null;

    public ?int $sourceProjectId = null;

    public string $sourceProjectStage = ProjectStage::DRAFT->value;

    // ===== Tampilan =====
    public string $activeTab = 'home';

    public string $zoom = '100';

    public bool $showNavigation = true;

    public bool $showRuler = false;

    public bool $showGridlines = false;

    public string $viewMode = 'print';

    public string $fontFamily = 'serif';

    public string $fontSize = '16';

    public bool $darkMode = false;

    public int $activeBlock = 0;

    /**
     * Stack undo/redo.
     *
     * @var array<int, array<int, array<string, mixed>>>
     */
    public array $undoStack = [];

    /**
     * @var array<int, array<int, array<string, mixed>>>
     */
    public array $redoStack = [];

    /**
     * Format painter buffer.
     *
     * @var array<string, mixed>|null
     */
    public ?array $copiedFormat = null;

    /**
     * Pilihan teks pada blok aktif: ['start'=>int,'end'=>int] atau null.
     *
     * @var array{start: int, end: int}|null
     */
    public ?array $selection = null;

    /**
     * Pengaturan halaman.
     *
     * @var array<string, mixed>
     */
    public array $pageSetup = [
        'size' => 'A4',
        'orientation' => 'portrait',
        'marginTop' => 25,
        'marginRight' => 25,
        'marginBottom' => 25,
        'marginLeft' => 25,
        'columns' => 1,
        'lineNumbers' => false,
        'pageColor' => '#ffffff',
        'pageBorder' => false,
        'watermark' => '',
    ];

    /**
     * Metadata gaya global.
     *
     * @var array<string, mixed>
     */
    public array $styleMeta = [
        'theme' => 'academic',
        'fontFamily' => 'serif',
        'fontSize' => 16,
        'lineSpacing' => 1.7,
    ];

    /**
     * Komentar dokumen.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $comments = [];

    /**
     * Revisi/track changes.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $revisions = [];

    public bool $trackChanges = false;

    public bool $showComments = false;

    public bool $showRevisions = false;

    public string $commentDraft = '';

    // ===== Dialog =====
    public string $dialog = '';

    public string $findQuery = '';

    public string $replaceQuery = '';

    public string $findResult = '';

    public string $thesaurusWord = '';

    public string $thesaurusResult = '';

    public string $spellResult = '';

    public int $tableRows = 3;

    public int $tableCols = 3;

    public string $linkUrl = '';

    public string $linkText = '';

    public string $imageAlt = '';

    public string $imageWidth = '320';

    public string $footnoteText = '';

    public string $mergeCsv = '';

    public string $mergeTemplate = 'Halo {{nama}}, selamat datang di {{lembaga}}.';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $mergePreviews = [];

    public string $commandSearch = '';

    public string $calloutType = 'info';

    /**
     * Versi tersimpan / snapshot naskah (LibreOffice File Versions).
     *
     * @var array<int, array{id: string, label: string, at: string, count: int, blocks: array<int, array<string, mixed>>}>
     */
    public array $snapshots = [];

    public string $snapshotLabel = '';

    /**
     * Metadata dokumen (Author, Subject, Keywords).
     *
     * @var array{author: string, subject: string, keywords: string}
     */
    public array $metadata = [
        'author' => '',
        'subject' => '',
        'keywords' => '',
    ];

    // ===== Xishie Assistant =====
    public bool $assistantOpen = false;

    /**
     * @var array<int, string>
     */
    public array $activeTools = [];

    public string $assistantInput = '';

    /**
     * @var array<int, array{role: string, content: string, command?: string}>
     */
    public array $assistantLog = [];

    public bool $assistantBusy = false;

    private NaskahRichModel $model;

    public function bootHandlesNaskahStudio(): void
    {
        $this->model = app(NaskahRichModel::class);
    }

    public function mountHandlesNaskahStudio(?int $documentId = null): void
    {
        $this->model = app(NaskahRichModel::class);

        $documentId = $documentId ?? (request()->query('document') !== null ? (int) request()->query('document') : null);

        if (is_numeric($documentId)) {
            $document = NaskahDocument::query()->find((int) $documentId);

            if ($document !== null) {
                $this->loadDocument($document);

                return;
            }
        }

        if (empty($this->blocks)) {
            $this->blocks = [
                $this->model->newBlock('title', 'The Open Studio Handbook'),
                $this->model->newBlock('subtitle', 'How a small community of makers shares space, tools and time'),
                $this->model->newBlock('paragraph', 'Every Thursday evening the old print shop on Alder Street fills with people who make things. Painters set up by the north windows, letterpress regulars claim the back room, and somebody always brings bread. This handbook collects what we have learned about running a shared studio: the routines that keep it pleasant, the tools we pool, and the small agreements that let thirty strangers become a crew.'),
                $this->model->newBlock('heading', 'Why share a studio?', 1),
                $this->model->newBlock('paragraph', "Rent is the obvious reason, but it is not the best one. A shared room puts your work next to work you would never have seen otherwise. A ceramicist explains glaze chemistry to a comic artist; a photographer borrows a weaver's loom for a still life. The cross-talk is the point."),
                $this->model->newBlock('quote', 'A studio is a place where unfinished things are welcome.'),
                $this->model->newBlock('heading', 'What we agree on', 2),
                $this->model->newBlock('bullet', 'Clean as you go. Leave a table the way you would like to find it.'),
                $this->model->newBlock('bullet', 'Label what is yours. Unlabelled supplies on the commons shelf are fair game.'),
                $this->model->newBlock('bullet', 'Ask before you borrow. Even from the shelf, a quick note helps everyone keep track.'),
                $this->model->newBlock('heading', 'Shared equipment', 2),
                $this->model->newBlock('heading', 'Opening the studio', 2),
                $this->model->newBlock('heading', 'Membership', 1),
            ];
        }
    }

    // =====================================================================
    // Sumber dokumen
    // =====================================================================

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
                $this->notify('Teks tidak terbaca', 'Berkas tidak memuat teks. Coba DOCX, PDF, atau TXT.', 'warning');

                return;
            }

            $blocks = $this->model->normalize(app(NaskahBlockParser::class)->parse($text));

            $this->blocks = $blocks !== [] ? $blocks : $this->blocks;
            $this->docTitle = pathinfo($filename, PATHINFO_FILENAME) ?: 'Dokumen Impor';
            $this->sourceType = NaskahDocument::SOURCE_LOCAL;
            $this->sourceFilename = $filename;
            $this->sourceStage = null;
            $this->sourceProjectId = null;
            $this->documentId = null;
            $this->dirty = true;

            $this->notify('Naskah dimuat dari berkas', $filename.' — '.count($blocks).' blok.', 'success');
        } catch (\Throwable $exception) {
            $this->notify('Gagal memuat berkas', Str::limit($exception->getMessage(), 160), 'danger');
        }
    }

    public function loadFromProject(): void
    {
        $project = $this->sourceProjectId !== null ? XishieProject::find($this->sourceProjectId) : null;

        if ($project === null) {
            $this->notify('Pilih proyek terlebih dahulu', '', 'warning');

            return;
        }

        $stageEnum = ProjectStage::tryFrom($this->sourceProjectStage) ?? ProjectStage::DRAFT;
        $stage = $project->stages()->where('stage_name', $stageEnum->value)->first();

        if ($stage === null) {
            $this->notify('Tahap belum tersedia', 'Proyek belum memiliki rekaman tahap '.$stageEnum->label().'.', 'warning');

            return;
        }

        $blocks = $this->model->fromSections((array) ($stage->sections_content ?? []));

        if ($blocks === []) {
            $compiled = trim((string) ($stage->compiled_content ?? ''));
            if ($compiled !== '') {
                $blocks = $this->model->normalize(app(NaskahBlockParser::class)->parse($compiled));
            }
        }

        if ($blocks === []) {
            $this->notify('Naskah proyek masih kosong', 'Tahap '.$stageEnum->label().' belum memiliki sub-bab.', 'warning');

            return;
        }

        $this->blocks = $blocks;
        $this->docTitle = $project->title.' — '.$stageEnum->label();
        $this->sourceType = NaskahDocument::SOURCE_PROJECT;
        $this->sourceStage = $stageEnum->value;
        $this->sourceFilename = null;
        $this->documentId = null;
        $this->dirty = true;

        $this->notify('Naskah proyek dimuat', $project->title.' ('.$stageEnum->label().') — '.count($blocks).' blok.', 'success');
    }

    public function loadDocument(NaskahDocument $document): void
    {
        $this->model ??= app(NaskahRichModel::class);

        $this->documentId = $document->id;
        $this->docTitle = $document->title;
        $this->blocks = $this->model->normalize((array) $document->blocks);
        $this->sourceType = $document->source_type;
        $this->sourceFilename = $document->source_filename;
        $this->sourceStage = $document->source_stage;
        $this->sourceProjectId = $document->project_id;
        $this->comments = $document->commentsList();
        $this->revisions = $document->revisionsList();
        $this->pageSetup = array_merge($this->pageSetup, (array) ($document->page_setup ?? []));
        $this->styleMeta = array_merge($this->styleMeta, (array) ($document->style_meta ?? []));
        $this->snapshots = (array) ($document->style_meta['snapshots'] ?? []);
        $this->metadata = array_merge($this->metadata, (array) ($document->style_meta['metadata'] ?? []));
        $this->lastSavedAt = $document->last_saved_at?->diffForHumans();
        $this->dirty = false;
    }

    public function openDocument(int $documentId): void
    {
        $document = NaskahDocument::query()->find($documentId);

        if ($document === null) {
            $this->notify('Dokumen tidak ditemukan', '', 'danger');

            return;
        }

        $this->loadDocument($document);
        $this->notify('Dokumen dibuka', $document->title, 'success');
    }

    public function newDocument(): void
    {
        $this->documentId = null;
        $this->docTitle = 'Dokumen Tanpa Judul';
        $this->sourceType = NaskahDocument::SOURCE_LOCAL;
        $this->sourceFilename = null;
        $this->sourceStage = null;
        $this->sourceProjectId = null;
        $this->lastSavedAt = null;
        $this->comments = [];
        $this->revisions = [];
        $this->blocks = [
            $this->model->newBlock('heading', 'Dokumen Tanpa Judul', 1),
            $this->model->newBlock('paragraph', 'Mulai menulis di sini…'),
        ];
        $this->dirty = false;
        $this->activeBlock = 0;
    }

    // =====================================================================
    // Blok
    // =====================================================================

    public function markDirty(): void
    {
        $this->dirty = true;
    }

    public function pushUndo(): void
    {
        $this->undoStack[] = $this->blocks;
        if (count($this->undoStack) > 30) {
            array_shift($this->undoStack);
        }
        $this->redoStack = [];
    }

    public function undo(): void
    {
        if ($this->undoStack === []) {
            return;
        }

        $this->redoStack[] = $this->blocks;
        $this->blocks = array_pop($this->undoStack) ?? [];
        $this->dirty = true;
    }

    public function redo(): void
    {
        if ($this->redoStack === []) {
            return;
        }

        $this->undoStack[] = $this->blocks;
        $this->blocks = array_pop($this->redoStack) ?? [];
        $this->dirty = true;
    }

    public function copyFormat(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $b = $this->blocks[$index];
        $this->copiedFormat = [
            'align' => $b['align'] ?? 'left',
            'indent' => $b['indent'] ?? 0,
            'lineSpacing' => $b['lineSpacing'] ?? 1.0,
            'spacingBefore' => $b['spacingBefore'] ?? 0,
            'spacingAfter' => $b['spacingAfter'] ?? 0,
            'type' => $b['type'] ?? 'paragraph',
            'level' => $b['level'] ?? 1,
        ];

        $this->notify('Format disalin', 'Klik blok tujuan lalu terapkan format.', 'info');
    }

    public function pasteFormat(int $index): void
    {
        if ($this->copiedFormat === null || ! isset($this->blocks[$index])) {
            return;
        }

        $this->pushUndo();
        $this->blocks[$index]['align'] = $this->copiedFormat['align'];
        $this->blocks[$index]['indent'] = $this->copiedFormat['indent'];
        $this->blocks[$index]['lineSpacing'] = $this->copiedFormat['lineSpacing'];
        $this->blocks[$index]['spacingBefore'] = $this->copiedFormat['spacingBefore'];
        $this->blocks[$index]['spacingAfter'] = $this->copiedFormat['spacingAfter'];

        if (in_array($this->copiedFormat['type'], ['heading', 'paragraph', 'quote', 'bullet', 'numbered'], true)) {
            $this->blocks[$index]['type'] = $this->copiedFormat['type'];
            $this->blocks[$index]['level'] = $this->copiedFormat['level'];
        }

        $this->copiedFormat = null;
        $this->dirty = true;
        $this->notify('Format diterapkan', '', 'success');
    }

    public function toggleDarkMode(): void
    {
        $this->darkMode = ! $this->darkMode;
    }

    public function updatePageSetup(string $key, mixed $value): void
    {
        $this->pageSetup[$key] = $value;
        $this->dirty = true;
    }

    // ===== Bantuan AI Blok (Wordcraft Co-Writing) =====

    public function aiElaborateBlock(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $this->pushUndo();
        $text = (string) ($this->blocks[$index]['text'] ?? '');
        if (trim($text) === '') {
            return;
        }

        $prompt = "Tolong perjelas dan kembangkan gagasan pada paragraf berikut dengan argumen atau elaborasi yang relevan, tetap mempertahankan nada ilmiah/akademik:\n\n\"{$text}\"";
        $reply = $this->answer(null, $prompt);

        if (trim($reply) !== '') {
            $this->recordRevision($index, $text, $reply);
            $this->blocks[$index]['text'] = $reply;
            $this->blocks[$index]['runs'] = [];
            $this->dirty = true;
            $this->notify('Teks dielaborasi', 'Gagasan diperluas oleh Xishie AI.', 'success');
        }
    }

    public function aiShortenBlock(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $this->pushUndo();
        $text = (string) ($this->blocks[$index]['text'] ?? '');
        if (trim($text) === '') {
            return;
        }

        $prompt = "Tolong persingkat dan padatkan paragraf berikut agar lebih padat, efektif, dan langsung ke inti gagasan:\n\n\"{$text}\"";
        $reply = $this->answer(null, $prompt);

        if (trim($reply) !== '') {
            $this->recordRevision($index, $text, $reply);
            $this->blocks[$index]['text'] = $reply;
            $this->blocks[$index]['runs'] = [];
            $this->dirty = true;
            $this->notify('Teks diringkas', 'Paragraf diperpendek.', 'success');
        }
    }

    public function aiImproveBlock(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $this->pushUndo();
        $text = (string) ($this->blocks[$index]['text'] ?? '');
        if (trim($text) === '') {
            return;
        }

        $prompt = "Tolong perbaiki tata bahasa, diksi, dan alur kalimat pada teks berikut agar bernada akademik dan mengalir natural:\n\n\"{$text}\"";
        $reply = $this->answer(null, $prompt);

        if (trim($reply) !== '') {
            $this->recordRevision($index, $text, $reply);
            $this->blocks[$index]['text'] = $reply;
            $this->blocks[$index]['runs'] = [];
            $this->dirty = true;
            $this->notify('Gaya diperbaiki', 'Diksi dan tata bahasa ditingkatkan.', 'success');
        }
    }

    public function aiContinueStory(int $index): void
    {
        $this->pushUndo();
        $context = $this->model->toPlainText(array_slice($this->blocks, max(0, $index - 2), 3));
        $prompt = "Berdasarkan konteks naskah berikut:\n\n\"{$context}\"\n\nLanjutkan dengan 1 atau 2 kalimat/paragraf berikutnya secara logis dan menyatu dengan bahasan naskah.";
        $reply = $this->answer(null, $prompt);

        if (trim($reply) !== '') {
            array_splice($this->blocks, $index + 1, 0, [$this->model->newBlock('paragraph', $reply)]);
            $this->activeBlock = $index + 1;
            $this->dirty = true;
            $this->notify('Kelanjutan naskah dibuat', 'Blok baru disisipkan.', 'success');
        }
    }

    public function aiFixTone(int $index, string $tone = 'akademik'): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $this->pushUndo();
        $text = (string) ($this->blocks[$index]['text'] ?? '');
        $prompt = "Ubah gaya dan nada bahasa teks berikut menjadi bernada '{$tone}' (misalnya formal akademik, ringkas lugas, atau persuasif):\n\n\"{$text}\"";
        $reply = $this->answer(null, $prompt);

        if (trim($reply) !== '') {
            $this->recordRevision($index, $text, $reply);
            $this->blocks[$index]['text'] = $reply;
            $this->blocks[$index]['runs'] = [];
            $this->dirty = true;
            $this->notify('Nada diubah', 'Nada bahasa disesuaikan ke '.$tone.'.', 'success');
        }
    }

    public function addBlock(string $type = 'paragraph'): void
    {
        $block = match ($type) {
            'table' => ['type' => 'table', 'table' => ['rows' => $this->emptyRows($this->tableRows, $this->tableCols), 'header' => true]],
            'pagebreak' => ['type' => 'pagebreak', 'pageBreak' => true],
            'heading' => $this->model->newBlock('heading', '', 1),
            default => $this->model->newBlock($type),
        };

        $this->blocks[] = $this->model->normalizeBlock($block);
        $this->activeBlock = count($this->blocks) - 1;
        $this->dirty = true;
    }

    public function removeBlock(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        array_splice($this->blocks, $index, 1);
        $this->blocks = array_values($this->blocks);
        $this->activeBlock = max(0, min($this->activeBlock, count($this->blocks) - 1));
        $this->dirty = true;
    }

    public function moveBlock(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->blocks[$index], $this->blocks[$target])) {
            return;
        }

        [$this->blocks[$index], $this->blocks[$target]] = [$this->blocks[$target], $this->blocks[$index]];
        $this->activeBlock = $target;
        $this->dirty = true;
    }

    public function duplicateBlock(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        array_splice($this->blocks, $index + 1, 0, [$this->blocks[$index]]);
        $this->dirty = true;
    }

    public function updateBlockText(int $index, string $text): void
    {
        if (isset($this->blocks[$index])) {
            $this->blocks[$index]['text'] = $text;
            if (isset($this->blocks[$index]['runs'][0])) {
                $this->blocks[$index]['runs'][0]['text'] = $text;
            }
            $this->markDirty();
        }
    }

    public function updateTableCell(int $blockIndex, int $rowIndex, int $colIndex, string $text): void
    {
        if (isset($this->blocks[$blockIndex]['table']['rows'][$rowIndex][$colIndex])) {
            $this->blocks[$blockIndex]['table']['rows'][$rowIndex][$colIndex]['text'] = $text;
            $this->markDirty();
        }
    }

    public function selectBlock(int $index): void
    {
        if (isset($this->blocks[$index])) {
            $this->activeBlock = $index;
        }
    }

    /**
     * Simpan seleksi teks dari klien (dipanggil via JS).
     */
    public function setSelection(int $index, int $start, int $end): void
    {
        $this->activeBlock = $index;
        $this->selection = $end > $start ? ['start' => $start, 'end' => $end] : null;
    }

    public function applyStyle(int $index, string $style): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        [$type, $level] = match ($style) {
            'title', 'heading1' => ['heading', 1],
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
    // Format inline
    // =====================================================================

    /**
     * Terapkan format inline ke seleksi (atau seluruh blok bila tak ada seleksi).
     *
     * @param  array<string, mixed>  $attribute
     */
    public function formatInline(string $attribute, mixed $value = null): void
    {
        $index = $this->activeBlock;

        if (! isset($this->blocks[$index])) {
            return;
        }

        $attr = [$attribute => $value];

        $block = $this->blocks[$index];
        $text = (string) ($block['text'] ?? '');
        $length = mb_strlen($text);

        if ($this->selection !== null && $this->selection['end'] > $this->selection['start']) {
            $block = $this->model->applyInlineFormat($block, (int) $this->selection['start'], (int) $this->selection['end'], $attr);
        } elseif ($length > 0) {
            $block = $this->model->applyInlineFormatAll($block, $attr);
        }

        $this->blocks[$index] = $block;
        $this->dirty = true;
    }

    public function clearFormatting(): void
    {
        $index = $this->activeBlock;
        if (! isset($this->blocks[$index])) {
            return;
        }

        $text = (string) ($this->blocks[$index]['text'] ?? '');
        $this->blocks[$index]['runs'] = $text === '' ? [] : [['text' => $text]];
        $this->blocks[$index]['align'] = 'left';
        $this->blocks[$index]['indent'] = 0;
        $this->dirty = true;
    }

    public function changeCase(string $mode): void
    {
        $index = $this->activeBlock;
        if (! isset($this->blocks[$index])) {
            return;
        }

        $text = (string) ($this->blocks[$index]['text'] ?? '');
        $converted = match ($mode) {
            'upper' => mb_strtoupper($text),
            'lower' => mb_strtolower($text),
            'title' => mb_convert_case($text, MB_CASE_TITLE, 'UTF-8'),
            'sentence' => mb_strtoupper(mb_substr($text, 0, 1)).mb_strtolower(mb_substr($text, 1)),
            default => $text,
        };

        $this->blocks[$index]['text'] = $converted;
        $this->blocks[$index]['runs'] = $converted === '' ? [] : [['text' => $converted]];
        $this->dirty = true;
    }

    public function adjustFontSize(int $delta): void
    {
        $this->styleMeta['fontSize'] = max(8, min(48, (int) $this->styleMeta['fontSize'] + $delta));
    }

    /**
     * Format paragraf pada blok aktif.
     */
    public function formatParagraph(string $attribute, mixed $value = null): void
    {
        $index = $this->activeBlock;
        if (! isset($this->blocks[$index])) {
            return;
        }

        match ($attribute) {
            'align' => $this->blocks[$index]['align'] = in_array($value, ['left', 'center', 'right', 'justify'], true) ? $value : 'left',
            'indent+' => $this->blocks[$index]['indent'] = min(8, (int) ($this->blocks[$index]['indent'] ?? 0) + 1),
            'indent-' => $this->blocks[$index]['indent'] = max(0, (int) ($this->blocks[$index]['indent'] ?? 0) - 1),
            'spacingBefore+' => $this->blocks[$index]['spacingBefore'] = min(72, (float) ($this->blocks[$index]['spacingBefore'] ?? 0) + 6),
            'spacingAfter+' => $this->blocks[$index]['spacingAfter'] = min(72, (float) ($this->blocks[$index]['spacingAfter'] ?? 0) + 6),
            'lineSpacing' => $this->blocks[$index]['lineSpacing'] = max(0.8, min(3.0, (float) $value)),
            'listBullet' => $this->toggleList('bullet', $index),
            'listNumbered' => $this->toggleList('numbered', $index),
            default => null,
        };

        $this->dirty = true;
    }

    private function toggleList(string $type, int $index): void
    {
        $current = (string) ($this->blocks[$index]['type'] ?? '');

        if ($current === $type) {
            $this->blocks[$index]['type'] = 'paragraph';
            $this->blocks[$index]['listType'] = 'none';
        } else {
            $this->blocks[$index]['type'] = $type;
            $this->blocks[$index]['listType'] = $type;
        }
    }

    /**
     * Terapkan format ke semua blok (Select All).
     */
    public function formatAllBlocks(string $attribute, mixed $value = null): void
    {
        foreach (array_keys($this->blocks) as $index) {
            $this->activeBlock = $index;
            $this->formatParagraph($attribute, $value);
        }

        $this->activeBlock = 0;
        $this->dirty = true;
    }

    // =====================================================================
    // Tabel
    // =====================================================================

    /**
     * @return array<int, array<int, array{text: string, runs: array<int, mixed>}>>
     */
    private function emptyRows(int $rows, int $cols): array
    {
        $rows = max(1, min(20, $rows));
        $cols = max(1, min(10, $cols));

        $out = [];
        for ($r = 0; $r < $rows; $r++) {
            $row = [];
            for ($c = 0; $c < $cols; $c++) {
                $row[] = ['text' => '', 'runs' => []];
            }
            $out[] = $row;
        }

        return $out;
    }

    public function insertTable(): void
    {
        $this->blocks[] = [
            'type' => 'table',
            'table' => ['rows' => $this->emptyRows($this->tableRows, $this->tableCols), 'header' => true],
            'align' => 'left',
            'text' => '',
            'runs' => [],
        ];
        $this->activeBlock = count($this->blocks) - 1;
        $this->dirty = true;
        $this->dialog = '';
    }

    public function tableInsertRow(int $index, int $after): void
    {
        $block = $this->blocks[$index] ?? null;
        if ($block === null || $block['type'] !== 'table') {
            return;
        }

        $rows = (array) $block['table']['rows'];
        $cols = count((array) ($rows[0] ?? []));
        $newRow = array_fill(0, max(1, $cols), ['text' => '', 'runs' => []]);
        array_splice($rows, $after + 1, 0, [$newRow]);

        $this->blocks[$index]['table']['rows'] = $rows;
        $this->dirty = true;
    }

    public function tableDeleteRow(int $index, int $rowIndex): void
    {
        $rows = (array) ($this->blocks[$index]['table']['rows'] ?? []);
        if (count($rows) <= 1) {
            return;
        }

        array_splice($rows, $rowIndex, 1);
        $this->blocks[$index]['table']['rows'] = array_values($rows);
        $this->dirty = true;
    }

    public function tableInsertColumn(int $index, int $after): void
    {
        $rows = (array) ($this->blocks[$index]['table']['rows'] ?? []);

        foreach ($rows as $r => $row) {
            array_splice($rows[$r], $after + 1, 0, [['text' => '', 'runs' => []]]);
        }

        $this->blocks[$index]['table']['rows'] = $rows;
        $this->dirty = true;
    }

    public function tableDeleteColumn(int $index, int $colIndex): void
    {
        $rows = (array) ($this->blocks[$index]['table']['rows'] ?? []);
        $cols = count((array) ($rows[0] ?? []));

        if ($cols <= 1) {
            return;
        }

        foreach ($rows as $r => $row) {
            array_splice($rows[$r], $colIndex, 1);
        }

        $this->blocks[$index]['table']['rows'] = $rows;
        $this->dirty = true;
    }

    public function tableToggleHeader(int $index): void
    {
        $current = (bool) ($this->blocks[$index]['table']['header'] ?? true);
        $this->blocks[$index]['table']['header'] = ! $current;
        $this->dirty = true;
    }

    // =====================================================================
    // Sisipkan: gambar, tautan, catatan kaki, pemisah halaman
    // =====================================================================

    public function updatedImageUpload(): void
    {
        if ($this->imageUpload === null) {
            return;
        }

        try {
            $binary = $this->imageUpload->get();
            $mime = (string) ($this->imageUpload->getMimeType() ?: 'image/png');

            if (! str_starts_with($mime, 'image/')) {
                $this->notify('Berkas bukan gambar', '', 'warning');

                return;
            }

            $dataUrl = 'data:'.$mime.';base64,'.base64_encode($binary);
            $width = max(80, min(900, (int) ($this->imageWidth ?: 320)));

            $this->blocks[] = $this->model->normalizeBlock([
                'type' => 'image',
                'image' => ['src' => $dataUrl, 'alt' => $this->imageAlt ?: pathinfo((string) $this->imageUpload->getClientOriginalName(), PATHINFO_FILENAME), 'width' => $width],
            ]);

            $this->activeBlock = count($this->blocks) - 1;
            $this->dirty = true;
            $this->dialog = '';
            $this->imageAlt = '';
            $this->notify('Gambar disisipkan', '', 'success');
        } catch (\Throwable $exception) {
            $this->notify('Gagal menyisipkan gambar', Str::limit($exception->getMessage(), 140), 'danger');
        }
    }

    public function insertLink(): void
    {
        $url = trim($this->linkUrl);

        if ($url === '') {
            return;
        }

        $text = trim($this->linkText) !== '' ? trim($this->linkText) : $url;

        $index = $this->activeBlock;
        if (! isset($this->blocks[$index])) {
            $this->blocks[] = $this->model->newBlock('paragraph', '');
            $index = count($this->blocks) - 1;
        }

        $block = $this->blocks[$index];
        $existing = (string) ($block['text'] ?? '');
        $runs = $this->model->runsWithText($block);
        $runs[] = ['text' => ($existing !== '' ? ' ' : '').$text, 'link' => $url] + $this->plainRun();

        $this->blocks[$index]['runs'] = $runs;
        $this->blocks[$index]['text'] = $existing.($existing !== '' ? ' ' : '').$text;
        $this->linkUrl = '';
        $this->linkText = '';
        $this->dialog = '';
        $this->dirty = true;
    }

    public function insertFootnote(): void
    {
        $text = trim($this->footnoteText);
        if ($text === '') {
            return;
        }

        $this->blocks[] = $this->model->newBlock('footnote-ref', $text);
        $this->activeBlock = count($this->blocks) - 1;
        $this->footnoteText = '';
        $this->dialog = '';
        $this->dirty = true;
    }

    public function insertPageBreak(): void
    {
        $this->blocks[] = $this->model->normalizeBlock(['type' => 'pagebreak']);
        $this->dirty = true;
    }

    public function insertHorizontalRule(): void
    {
        $this->blocks[] = $this->model->newBlock('paragraph', '――――――――――――――――――――――――――――――');
        $this->dirty = true;
    }

    public function insertDate(): void
    {
        $this->blocks[] = $this->model->newBlock('paragraph', now()->translatedFormat('d F Y'));
        $this->dirty = true;
    }

    public function insertPageNumberField(): void
    {
        $this->blocks[] = $this->model->newBlock('paragraph', 'Halaman 1');
        $this->dirty = true;
    }

    public function insertSymbol(string $symbol): void
    {
        $this->blocks[] = $this->model->newBlock('paragraph', $symbol);
        $this->dirty = true;
        $this->dialog = '';
    }

    public function applyPageSetup(): void
    {
        $this->markDirty();
        $this->dialog = '';
    }

    public function postCommentAndClose(): void
    {
        $this->addComment();
        $this->dialog = '';
    }

    public function saveProperties(): void
    {
        $this->save();
        $this->dialog = '';
    }

    /**
     * @return array<string, mixed>
     */
    private function plainRun(): array
    {
        return ['bold' => false, 'italic' => false, 'underline' => false, 'strike' => false, 'sub' => false, 'sup' => false, 'smallCaps' => false, 'allCaps' => false, 'code' => false, 'color' => null, 'highlight' => null, 'link' => null];
    }

    // =====================================================================
    // Referensi: daftar isi, bibliografi
    // =====================================================================

    public function insertTableOfContents(): void
    {
        $outline = $this->model->outline($this->blocks);

        if ($outline === []) {
            $this->notify('Belum ada judul', 'Tambahkan blok Heading untuk membuat daftar isi.', 'warning');

            return;
        }

        // Hapus TOC lama lalu sisipkan di awal.
        $this->blocks = array_values(array_filter($this->blocks, static fn (array $b): bool => ($b['type'] ?? '') !== 'toc'));
        array_unshift($this->blocks, $this->model->normalizeBlock(['type' => 'heading', 'level' => 1, 'text' => 'Daftar Isi']));

        foreach (array_reverse($outline) as $node) {
            $indent = str_repeat('— ', max(0, $node['level'] - 1));
            array_splice($this->blocks, 1, 0, [$this->model->newBlock('paragraph', $indent.$node['text'])]);
        }

        $this->buildTocBlock();
        $this->dirty = true;
        $this->notify('Daftar isi dibuat', count($outline).' judul.', 'success');
    }

    private function buildTocBlock(): void
    {
        $outline = $this->model->outline($this->blocks);
        $rows = [];
        foreach ($outline as $node) {
            $rows[] = [['text' => str_repeat('  ', max(0, $node['level'] - 1)).$node['text'], 'runs' => []], ['text' => '…', 'runs' => []]];
        }

        if ($rows === []) {
            return;
        }

        array_unshift($rows, [['text' => 'Judul', 'runs' => []], ['text' => 'Halaman', 'runs' => []]]);

        $toc = $this->model->normalizeBlock(['type' => 'table', 'table' => ['rows' => $rows, 'header' => true]]);
        $toc['type'] = 'toc';

        // Sisipkan tabel TOC setelah judul "Daftar Isi".
        $insertAt = 1;
        array_splice($this->blocks, $insertAt, 0, [$toc]);
    }

    public function insertBibliography(?int $projectId = null): void
    {
        $project = $projectId !== null ? XishieProject::find($projectId) : ($this->sourceProjectId !== null ? XishieProject::find($this->sourceProjectId) : null);

        if ($project === null) {
            $this->notify('Bibliografi butuh proyek', 'Muat naskah dari proyek agar referensinya tersedia.', 'warning');

            return;
        }

        $refs = $project->references()->limit(50)->get();

        if ($refs->isEmpty()) {
            $this->notify('Proyek belum punya referensi', '', 'warning');

            return;
        }

        $this->blocks[] = $this->model->newBlock('heading', 'Daftar Pustaka', 1);

        foreach ($refs as $ref) {
            $authors = implode(', ', (array) ($ref->authors ?? []));
            $entry = trim($authors.'. '.(string) $ref->title.'. '.(string) ($ref->journal_name ?? '').'. '.((string) ($ref->year ?? '')).'.');
            $this->blocks[] = $this->model->newBlock('paragraph', $entry);
        }

        $this->dirty = true;
        $this->notify('Daftar pustaka disisipkan', $refs->count().' referensi.', 'success');
    }

    // =====================================================================
    // Review: komentar, track changes, kata, tesaurus, ejaan
    // =====================================================================

    public function addComment(): void
    {
        $text = trim($this->commentDraft);

        if ($text === '') {
            return;
        }

        $this->comments[] = [
            'id' => (int) (microtime(true) * 1000),
            'blockIndex' => $this->activeBlock,
            'body' => $text,
            'author' => (string) (Auth::user()?->name ?? 'Penulis'),
            'resolved' => false,
            'replies' => [],
            'at' => now()->toIso8601String(),
        ];

        $this->commentDraft = '';
        $this->showComments = true;
        $this->dirty = true;
        $this->notify('Komentar ditambahkan', '', 'success');
    }

    public function resolveComment(int $commentIndex): void
    {
        if (! isset($this->comments[$commentIndex])) {
            return;
        }

        $this->comments[$commentIndex]['resolved'] = ! ($this->comments[$commentIndex]['resolved'] ?? false);
        $this->dirty = true;
    }

    public function deleteComment(int $commentIndex): void
    {
        if (! isset($this->comments[$commentIndex])) {
            return;
        }

        array_splice($this->comments, $commentIndex, 1);
        $this->comments = array_values($this->comments);
        $this->dirty = true;
    }

    public function replyComment(int $commentIndex, string $reply = ''): void
    {
        if (! isset($this->comments[$commentIndex])) {
            return;
        }

        $text = trim($reply);
        if ($text === '') {
            return;
        }

        $this->comments[$commentIndex]['replies'][] = [
            'author' => (string) (Auth::user()?->name ?? 'Penulis'),
            'body' => $text,
            'at' => now()->toIso8601String(),
        ];
        $this->dirty = true;
    }

    /**
     * Catat revisi (track changes) untuk blok aktif bila selisih teks terdeteksi.
     */
    public function recordRevision(int $index, string $before, string $after): void
    {
        if (trim($before) === trim($after)) {
            return;
        }

        $this->revisions[] = [
            'id' => (int) (microtime(true) * 1000),
            'blockIndex' => $index,
            'before' => $before,
            'after' => $after,
            'status' => 'pending',
            'author' => (string) (Auth::user()?->name ?? 'Penulis'),
            'at' => now()->toIso8601String(),
        ];
        $this->dirty = true;
    }

    public function acceptRevision(int $revisionIndex): void
    {
        if (! isset($this->revisions[$revisionIndex])) {
            return;
        }

        $rev = $this->revisions[$revisionIndex];
        $index = (int) ($rev['blockIndex'] ?? -1);

        if (isset($this->blocks[$index])) {
            $this->blocks[$index]['text'] = (string) $rev['after'];
            $this->blocks[$index]['runs'] = [];
        }

        $this->revisions[$revisionIndex]['status'] = 'accepted';
        $this->dirty = true;
    }

    public function rejectRevision(int $revisionIndex): void
    {
        if (! isset($this->revisions[$revisionIndex])) {
            return;
        }

        $rev = $this->revisions[$revisionIndex];
        $index = (int) ($rev['blockIndex'] ?? -1);

        if (isset($this->blocks[$index])) {
            $this->blocks[$index]['text'] = (string) $rev['before'];
            $this->blocks[$index]['runs'] = [];
        }

        $this->revisions[$revisionIndex]['status'] = 'rejected';
        $this->dirty = true;
    }

    public function acceptAllRevisions(): void
    {
        foreach (array_keys($this->revisions) as $i) {
            if (($this->revisions[$i]['status'] ?? '') === 'pending') {
                $this->acceptRevision($i);
            }
        }
    }

    public function rejectAllRevisions(): void
    {
        foreach (array_keys($this->revisions) as $i) {
            if (($this->revisions[$i]['status'] ?? '') === 'pending') {
                $this->rejectRevision($i);
            }
        }
    }

    public function runSpellCheck(): void
    {
        $text = $this->model->toPlainText($this->blocks);
        $words = preg_split('/[^\p{L}\-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $dictionary = $this->smallDictionary();

        $unknown = [];
        foreach (array_unique($words) as $word) {
            $lower = mb_strtolower($word);
            if (mb_strlen($lower) < 4 || in_array($lower, $dictionary, true)) {
                continue;
            }
            $unknown[] = $word;
        }

        $unknown = array_slice($unknown, 0, 25);

        $this->spellResult = $unknown === []
            ? 'Tidak ada kata yang ditandai (kamus ringkas).'
            : 'Kata tak dikenali kamus ringkas: '.implode(', ', $unknown).'.';

        $this->dialog = 'spelling';
    }

    /**
     * @return array<int, string>
     */
    private function smallDictionary(): array
    {
        return explode(' ', 'yang dan atau dengan untuk pada dari dalam adalah ini itu tidak akan dapat juga karena sebagai oleh terhadap serta para telah sudah akan masih lebih sangat hanya jika maka bahwa secara melalui antara hingga sampai bagi bahwa tanpa harus perlu suatu setiap semua beberapa banyak sedikit besar kecil tinggi rendah baru lama baik buruk dapat bisa harus data penelitian metode hasil analisis pembahasan kesimpulan tujuan masalah solusi sistem model proses teori konsep kajian studi artikel jurnal penulis referensi abstrak pendahuluan latar belakang tinjauan pustaka metodologi diskusi saran kontribusi implikasi praktis akademik ilmiah kualitas efektivitas pengaruh hubungan perbandingan pengembangan implementasi evaluasi pengukuran sampel populasi variabel hipotesis signifikan valid reliabel responden kuesioner wawancara observasi dokumentasi kuantitatif kualitatif campuran eksperimen survei kasus literature review framework design research development testing results discussion conclusion this that with from have been were their which these those using based approach analysis method data system model process theory concept study article journal author reference abstract introduction background review methodology contribution implication practical academic scientific quality effectiveness influence relationship comparison implementation evaluation measurement sample population variable hypothesis significant valid reliable respondent questionnaire interview observation documentation quantitative qualitative mixed experiment survey case');
    }

    public function runThesaurus(): void
    {
        $word = mb_strtolower(trim($this->thesaurusWord));

        if ($word === '') {
            $this->thesaurusResult = 'Masukkan kata terlebih dahulu.';
            $this->dialog = 'thesaurus';

            return;
        }

        $map = [
            'penelitian' => ['kajian', 'studi', 'riset', 'investigasi'],
            'metode' => ['metodologi', 'pendekatan', 'prosedur', 'teknik'],
            'hasil' => ['temuan', 'capaian', 'keluaran', 'output'],
            'masalah' => ['permasalahan', 'kendala', 'isu', 'tantangan'],
            'tujuan' => ['sasaran', 'maksud', 'target', 'arah'],
            'analisis' => ['telaah', 'kajian', 'penguraian', 'pembahasan'],
            'penting' => ['signifikan', 'krusial', 'esensial', 'vital'],
            'meningkatkan' => ['menaikkan', 'memperbaiki', 'mengembangkan', 'memperkuat'],
            'data' => ['informasi', 'keterangan', 'fakta', 'bahan'],
        ];

        $this->thesaurusResult = isset($map[$word])
            ? 'Sinonim "'.$word.'": '.implode(', ', $map[$word]).'.'
            : 'Tidak ada sinonim untuk "'.$word.'" di tesaurus ringkas.';

        $this->dialog = 'thesaurus';
    }

    // =====================================================================
    // Cari & ganti
    // =====================================================================

    public function findText(): void
    {
        $query = trim($this->findQuery);

        if ($query === '') {
            $this->findResult = 'Masukkan kata yang dicari.';

            return;
        }

        $hits = 0;
        foreach ($this->blocks as $i => $block) {
            if (($block['type'] ?? '') === 'table') {
                continue;
            }

            $count = mb_substr_count(mb_strtolower((string) ($block['text'] ?? '')), mb_strtolower($query));
            if ($count > 0) {
                $hits += $count;
                if ($hits > 0 && (int) $this->activeBlock === 0) {
                    $this->activeBlock = $i;
                }
            }
        }

        $this->findResult = $hits > 0 ? "Ditemukan {$hits} kemunculan '{$query}'." : "Tidak ditemukan '{$query}'.";
    }

    public function replaceAll(): void
    {
        $find = trim($this->findQuery);
        $replace = $this->replaceQuery;

        if ($find === '') {
            return;
        }

        $replaced = 0;
        foreach ($this->blocks as $i => $block) {
            if (($block['type'] ?? '') === 'table') {
                continue;
            }

            $text = (string) ($block['text'] ?? '');
            $count = mb_substr_count(mb_strtolower($text), mb_strtolower($find));

            if ($count > 0) {
                $this->blocks[$i]['text'] = (string) preg_replace('/'.preg_quote($find, '/').'/iu', $replace, $text);
                $this->blocks[$i]['runs'] = [];
                $replaced += $count;
            }
        }

        $this->findResult = "Mengganti {$replaced} kemunculan.";
        $this->dirty = true;
    }

    // =====================================================================
    // Mail merge
    // =====================================================================

    public function previewMerge(): void
    {
        $rows = $this->parseCsv($this->mergeCsv);

        if ($rows === []) {
            $this->notify('CSV kosong', 'Tempel data CSV (baris pertama = header kolom).', 'warning');

            return;
        }

        $previews = [];
        foreach (array_slice($rows, 0, 10) as $row) {
            $previews[] = ['values' => $row, 'text' => $this->renderMergeTemplate($row)];
        }

        $this->mergePreviews = $previews;
        $this->notify('Pratinjau dibuat', count($previews).' baris.', 'success');
    }

    public function insertMerge(): void
    {
        $rows = $this->parseCsv($this->mergeCsv);

        if ($rows === []) {
            $this->notify('CSV kosong', '', 'warning');

            return;
        }

        foreach ($rows as $row) {
            $text = $this->renderMergeTemplate($row);
            if (trim($text) !== '') {
                $this->blocks[] = $this->model->newBlock('paragraph', $text);
            }
        }

        $this->dirty = true;
        $this->dialog = '';
        $this->notify('Mail merge disisipkan', count($rows).' dokumen.', 'success');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCsv(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $l): bool => trim($l) !== ''));

        if (count($lines) < 2) {
            return [];
        }

        $header = array_map('trim', str_getcsv(array_shift($lines)));
        $rows = [];

        foreach ($lines as $line) {
            $cells = str_getcsv($line);
            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = (string) ($cells[$i] ?? '');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function renderMergeTemplate(array $row): string
    {
        $text = $this->mergeTemplate;
        foreach ($row as $key => $value) {
            $text = str_replace(['{{'.$key.'}}', '{{ '.$key.' }}'], $value, $text);
        }

        return $text;
    }

    // =====================================================================
    // Simpan & ekspor
    // =====================================================================

    public function save(): void
    {
        $blocks = $this->model->normalize($this->blocks);
        $wordCount = $this->model->countWords($blocks);

        $styleMeta = array_merge($this->styleMeta, [
            'snapshots' => $this->snapshots,
            'metadata' => $this->metadata,
        ]);

        $data = [
            'title' => trim($this->docTitle) !== '' ? trim($this->docTitle) : 'Dokumen Tanpa Judul',
            'source_type' => in_array($this->sourceType, [NaskahDocument::SOURCE_LOCAL, NaskahDocument::SOURCE_PROJECT], true) ? $this->sourceType : NaskahDocument::SOURCE_LOCAL,
            'source_stage' => $this->sourceType === NaskahDocument::SOURCE_PROJECT ? $this->sourceStage : null,
            'source_filename' => $this->sourceFilename,
            'project_id' => $this->sourceType === NaskahDocument::SOURCE_PROJECT ? $this->sourceProjectId : null,
            'blocks' => $blocks,
            'comments' => $this->comments,
            'revisions' => $this->revisions,
            'page_setup' => $this->pageSetup,
            'style_meta' => $styleMeta,
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

        $this->notify('Naskah tersimpan', $wordCount.' kata · '.count($blocks).' blok.', 'success');
    }

    public function export(string $format = 'docx'): ?StreamedResponse
    {
        if ($this->blocks === []) {
            $this->notify('Naskah masih kosong', '', 'warning');

            return null;
        }

        $base = Str::slug($this->docTitle) ?: 'naskah';
        $blocks = $this->model->normalize($this->blocks);

        if ($format === 'docx') {
            $binary = app(NaskahDocxExporter::class)->build($blocks, $this->docTitle, ['watermark' => (string) ($this->pageSetup['watermark'] ?? '')]);

            return response()->streamDownload(static function () use ($binary): void {
                echo $binary;
            }, $base.'.docx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
        }

        if ($format === 'odt') {
            $binary = app(NaskahOdtExporter::class)->build($blocks, $this->docTitle, ['watermark' => (string) ($this->pageSetup['watermark'] ?? '')]);

            return response()->streamDownload(static function () use ($binary): void {
                echo $binary;
            }, $base.'.odt', ['Content-Type' => 'application/vnd.oasis.opendocument.text']);
        }

        [$content, $extension, $mime] = match ($format) {
            'html' => [$this->model->toHtml($blocks, $this->docTitle, $this->htmlOptions()), 'html', 'text/html; charset=UTF-8'],
            'txt' => [$this->model->toPlainText($blocks), 'txt', 'text/plain; charset=UTF-8'],
            'rtf' => [$this->model->toRtf($blocks, $this->docTitle), 'rtf', 'application/rtf; charset=UTF-8'],
            'latex', 'tex' => [$this->model->toLatex($blocks, $this->docTitle), 'tex', 'application/x-tex; charset=UTF-8'],
            default => [$this->model->toMarkdown($blocks, $this->docTitle), 'md', 'text/markdown; charset=UTF-8'],
        };

        $filename = $base.'.'.$extension;

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, ['Content-Type' => $mime]);
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlOptions(): array
    {
        return [
            'watermark' => (string) ($this->pageSetup['watermark'] ?? ''),
            'pageColor' => (string) ($this->pageSetup['pageColor'] ?? ''),
            'pageBorder' => (bool) ($this->pageSetup['pageBorder'] ?? false),
            'lineNumbers' => (bool) ($this->pageSetup['lineNumbers'] ?? false),
        ];
    }

    // =====================================================================
    // Dialog
    // =====================================================================

    public function openDialog(string $dialog): void
    {
        $this->dialog = $dialog;
    }

    public function closeDialog(): void
    {
        $this->dialog = '';
    }

    public function applyTheme(string $theme): void
    {
        $presets = [
            'academic' => ['fontFamily' => 'serif', 'fontSize' => 16, 'lineSpacing' => 1.7],
            'modern' => ['fontFamily' => 'sans', 'fontSize' => 15, 'lineSpacing' => 1.6],
            'compact' => ['fontFamily' => 'sans', 'fontSize' => 14, 'lineSpacing' => 1.4],
            'code' => ['fontFamily' => 'mono', 'fontSize' => 14, 'lineSpacing' => 1.5],
        ];

        $this->styleMeta = array_merge($this->styleMeta, ['theme' => $theme] + ($presets[$theme] ?? $presets['academic']));
        $this->dirty = true;
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['print', 'web', 'draft', 'outline', 'read'], true) ? $mode : 'print';
    }

    public function setZoom(string $zoom): void
    {
        $this->zoom = in_array($zoom, ['50', '75', '100', '125', '150', '200'], true) ? $zoom : '100';
    }

    public function setZoomLevel(string $zoom): void
    {
        $this->setZoom($zoom);
    }

    public function zoomIn(): void
    {
        $steps = ['50', '75', '100', '125', '150', '200'];
        $i = array_search($this->zoom, $steps, true);
        $this->zoom = $steps[min(count($steps) - 1, ($i === false ? 2 : $i) + 1)];
    }

    public function zoomOut(): void
    {
        $steps = ['50', '75', '100', '125', '150', '200'];
        $i = array_search($this->zoom, $steps, true);
        $this->zoom = $steps[max(0, ($i === false ? 2 : $i) - 1)];
    }

    // =====================================================================
    // Xishie Assistant
    // =====================================================================

    /**
     * @return array<int, array{name: string, label: string, description: string}>
     */
    public function assistantTools(): array
    {
        $tools = [];
        foreach (SlashCommand::cases() as $command) {
            $tools[] = ['name' => $command->value, 'label' => str_replace('-', ' ', ltrim($command->value, '/')), 'description' => $command->description()];
        }

        $tools[] = ['name' => 'chat', 'label' => 'tanya bebas', 'description' => 'Ajukan pertanyaan bebas; jawaban memakai konteks naskah dan proyek.'];

        return $tools;
    }

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

    public function runTool(string $tool): void
    {
        if (! in_array($tool, $this->activeTools, true)) {
            $this->activeTools[] = $tool;
        }

        $documentText = $this->documentText();

        if (str_starts_with($tool, '/')) {
            $input = $tool;
            if (str_contains($tool, 'parafrase')) {
                $input = $tool.' '.Str::limit(trim($documentText), 400, '');
            }

            $this->dispatchAssistant($input, $tool);

            return;
        }

        $question = trim($this->assistantInput);
        if ($question === '') {
            $this->notify('Tulis pertanyaan dulu', 'Ketik pertanyaan pada kolom chat, lalu tekan Kirim.', 'info');

            return;
        }

        $this->dispatchAssistant($question.' '.Str::limit($documentText, 600, ''), 'chat');
    }

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

        $wordCount = $this->model->countWords($this->model->normalize($this->blocks));

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

    // =====================================================================
    // Perintah & Wordcraft Command Registry
    // =====================================================================

    /**
     * @return array<int, array{id: string, label: string, category: string, icon: string, shortcut?: string}>
     */
    public function commands(): array
    {
        return [
            ['id' => 'file.new', 'label' => 'Dokumen Baru', 'category' => 'Berkas', 'icon' => '📄', 'shortcut' => 'Ctrl+N'],
            ['id' => 'file.save', 'label' => 'Simpan Naskah', 'category' => 'Berkas', 'icon' => '💾', 'shortcut' => 'Ctrl+S'],
            ['id' => 'file.export.docx', 'label' => 'Ekspor ke Microsoft Word (.docx)', 'category' => 'Ekspor', 'icon' => '📝'],
            ['id' => 'file.export.odt', 'label' => 'Ekspor ke LibreOffice Writer (.odt)', 'category' => 'Ekspor', 'icon' => '📖'],
            ['id' => 'file.export.html', 'label' => 'Ekspor ke HTML Cetak', 'category' => 'Ekspor', 'icon' => '🌐'],
            ['id' => 'file.export.latex', 'label' => 'Ekspor ke LaTeX (.tex)', 'category' => 'Ekspor', 'icon' => '🔣'],
            ['id' => 'file.export.rtf', 'label' => 'Ekspor ke RTF', 'category' => 'Ekspor', 'icon' => '📄'],
            ['id' => 'file.export.md', 'label' => 'Ekspor ke Markdown', 'category' => 'Ekspor', 'icon' => '📑'],
            ['id' => 'file.export.txt', 'label' => 'Ekspor ke Teks Polos', 'category' => 'Ekspor', 'icon' => '📃'],
            ['id' => 'file.stats', 'label' => 'Statistik & Skor Keterbacaan', 'category' => 'Berkas', 'icon' => '📊'],
            ['id' => 'file.pagesetup', 'label' => 'Pengaturan Margin & Kertas', 'category' => 'Tata Letak', 'icon' => '⚙️'],
            ['id' => 'file.properties', 'label' => 'Properti Dokumen & Metadata', 'category' => 'Berkas', 'icon' => 'ℹ️'],
            ['id' => 'edit.undo', 'label' => 'Urungkan (Undo)', 'category' => 'Sunting', 'icon' => '↩️', 'shortcut' => 'Ctrl+Z'],
            ['id' => 'edit.redo', 'label' => 'Ulangi (Redo)', 'category' => 'Sunting', 'icon' => '↪️', 'shortcut' => 'Ctrl+Y'],
            ['id' => 'edit.copy_format', 'label' => 'Sapu Format (Salin Gaya)', 'category' => 'Papan Klip', 'icon' => '🖌️'],
            ['id' => 'edit.paste_format', 'label' => 'Terapkan Format Tersalin', 'category' => 'Papan Klip', 'icon' => '📋'],
            ['id' => 'edit.find', 'label' => 'Cari dan Ganti', 'category' => 'Sunting', 'icon' => '🔍', 'shortcut' => 'Ctrl+F'],
            ['id' => 'edit.autocorrect', 'label' => 'Jalankan Auto-Correct Tipografi', 'category' => 'Sunting', 'icon' => '✨'],
            ['id' => 'format.bold', 'label' => 'Huruf Tebal (Bold)', 'category' => 'Format', 'icon' => 'B', 'shortcut' => 'Ctrl+B'],
            ['id' => 'format.italic', 'label' => 'Huruf Miring (Italic)', 'category' => 'Format', 'icon' => 'I', 'shortcut' => 'Ctrl+I'],
            ['id' => 'format.underline', 'label' => 'Garis Bawah (Underline)', 'category' => 'Format', 'icon' => 'U', 'shortcut' => 'Ctrl+U'],
            ['id' => 'format.strike', 'label' => 'Coret (Strikethrough)', 'category' => 'Format', 'icon' => 'S'],
            ['id' => 'format.clear', 'label' => 'Bersihkan Semua Format', 'category' => 'Format', 'icon' => '🧹'],
            ['id' => 'format.align.left', 'label' => 'Rata Kiri', 'category' => 'Paragraf', 'icon' => '⬅️', 'shortcut' => 'Ctrl+L'],
            ['id' => 'format.align.center', 'label' => 'Rata Tengah', 'category' => 'Paragraf', 'icon' => '↔️', 'shortcut' => 'Ctrl+E'],
            ['id' => 'format.align.right', 'label' => 'Rata Kanan', 'category' => 'Paragraf', 'icon' => '➡️', 'shortcut' => 'Ctrl+R'],
            ['id' => 'format.align.justify', 'label' => 'Rata Kanan-Kiri (Justify)', 'category' => 'Paragraf', 'icon' => '↕️', 'shortcut' => 'Ctrl+J'],
            ['id' => 'insert.heading', 'label' => 'Sisipkan Judul Bagian', 'category' => 'Sisipkan', 'icon' => 'H'],
            ['id' => 'insert.paragraph', 'label' => 'Sisipkan Paragraf', 'category' => 'Sisipkan', 'icon' => 'P'],
            ['id' => 'insert.quote', 'label' => 'Sisipkan Kutipan', 'category' => 'Sisipkan', 'icon' => '“'],
            ['id' => 'insert.table', 'label' => 'Sisipkan Tabel', 'category' => 'Sisipkan', 'icon' => '⊞'],
            ['id' => 'insert.image', 'label' => 'Sisipkan Gambar', 'category' => 'Sisipkan', 'icon' => '🖼️'],
            ['id' => 'insert.link', 'label' => 'Sisipkan Tautan (Link)', 'category' => 'Sisipkan', 'icon' => '🔗', 'shortcut' => 'Ctrl+K'],
            ['id' => 'insert.footnote', 'label' => 'Sisipkan Catatan Kaki', 'category' => 'Sisipkan', 'icon' => '⚓'],
            ['id' => 'insert.pagebreak', 'label' => 'Sisipkan Pemisah Halaman', 'category' => 'Sisipkan', 'icon' => '✂️', 'shortcut' => 'Ctrl+Enter'],
            ['id' => 'insert.callout', 'label' => 'Sisipkan Kotak Sorotan (Callout)', 'category' => 'Sisipkan', 'icon' => '💡'],
            ['id' => 'insert.toc', 'label' => 'Buat / Perbarui Daftar Isi', 'category' => 'Referensi', 'icon' => '☰'],
            ['id' => 'insert.bibliography', 'label' => 'Sisipkan Daftar Pustaka Proyek', 'category' => 'Referensi', 'icon' => '📚'],
            ['id' => 'review.spellcheck', 'label' => 'Pemeriksaan Ejaan Kata', 'category' => 'Peninjauan', 'icon' => '✓'],
            ['id' => 'review.thesaurus', 'label' => 'Tesaurus Sinonim Akademik', 'category' => 'Peninjauan', 'icon' => '📖'],
            ['id' => 'review.track_changes', 'label' => 'Toggle Lacak Perubahan (Track Changes)', 'category' => 'Peninjauan', 'icon' => '👁️'],
            ['id' => 'review.accept_all', 'label' => 'Terima Semua Revisi', 'category' => 'Peninjauan', 'icon' => '✔'],
            ['id' => 'review.reject_all', 'label' => 'Tolak Semua Revisi', 'category' => 'Peninjauan', 'icon' => '✖'],
            ['id' => 'ai.elaborate', 'label' => 'Kembangkan Gagasan Teks (AI)', 'category' => 'Wordcraft AI', 'icon' => '⚡'],
            ['id' => 'ai.shorten', 'label' => 'Padatkan Teks (AI)', 'category' => 'Wordcraft AI', 'icon' => '✂️'],
            ['id' => 'ai.improve', 'label' => 'Perbaiki Gaya Akademik (AI)', 'category' => 'Wordcraft AI', 'icon' => '✨'],
            ['id' => 'ai.continue', 'label' => 'Lanjutkan Naskah Otomatis (AI)', 'category' => 'Wordcraft AI', 'icon' => '➕'],
            ['id' => 'view.dark_mode', 'label' => 'Ganti Mode Gelap / Terang', 'category' => 'Tampilan', 'icon' => '◐'],
            ['id' => 'view.focus', 'label' => 'Mode Fokus / Bebas Gangguan', 'category' => 'Tampilan', 'icon' => '🎯'],
            ['id' => 'view.ruler', 'label' => 'Tampilkan / Sembunyikan Penggaris', 'category' => 'Tampilan', 'icon' => '📏'],
            ['id' => 'view.navigator', 'label' => 'Tampilkan / Sembunyikan Navigator', 'category' => 'Tampilan', 'icon' => '🧭'],
            ['id' => 'view.shortcuts', 'label' => 'Daftar Tombol Pintas (Shortcuts)', 'category' => 'Bantuan', 'icon' => '⌨️', 'shortcut' => '?'],
        ];
    }

    public function executeCommand(string $commandId, array $args = []): void
    {
        match ($commandId) {
            'file.new' => $this->newDocument(),
            'file.save' => $this->save(),
            'file.export.docx' => $this->export('docx'),
            'file.export.odt' => $this->export('odt'),
            'file.export.html' => $this->export('html'),
            'file.export.latex' => $this->export('latex'),
            'file.export.rtf' => $this->export('rtf'),
            'file.export.md' => $this->export('md'),
            'file.export.txt' => $this->export('txt'),
            'file.stats' => $this->openDialog('stats'),
            'file.pagesetup' => $this->openDialog('pagesetup'),
            'file.properties' => $this->openDialog('properties'),
            'edit.undo' => $this->undo(),
            'edit.redo' => $this->redo(),
            'edit.copy_format' => $this->copyFormat($this->activeBlock),
            'edit.paste_format' => $this->pasteFormat($this->activeBlock),
            'edit.find' => $this->openDialog('find'),
            'edit.autocorrect' => $this->runAutoCorrect(),
            'format.bold' => $this->formatInline('bold'),
            'format.italic' => $this->formatInline('italic'),
            'format.underline' => $this->formatInline('underline'),
            'format.strike' => $this->formatInline('strike'),
            'format.clear' => $this->clearFormatting(),
            'format.align.left' => $this->formatParagraph('align', 'left'),
            'format.align.center' => $this->formatParagraph('align', 'center'),
            'format.align.right' => $this->formatParagraph('align', 'right'),
            'format.align.justify' => $this->formatParagraph('align', 'justify'),
            'insert.heading' => $this->addBlock('heading'),
            'insert.paragraph' => $this->addBlock('paragraph'),
            'insert.quote' => $this->addBlock('quote'),
            'insert.table' => $this->openDialog('table'),
            'insert.image' => $this->openDialog('image'),
            'insert.link' => $this->openDialog('link'),
            'insert.footnote' => $this->openDialog('footnote'),
            'insert.pagebreak' => $this->insertPageBreak(),
            'insert.callout' => $this->insertCallout((string) ($args['type'] ?? 'info')),
            'insert.toc' => $this->insertTableOfContents(),
            'insert.bibliography' => $this->insertBibliography(),
            'review.spellcheck' => $this->runSpellCheck(),
            'review.thesaurus' => $this->openDialog('thesaurus'),
            'review.track_changes' => $this->trackChanges = ! $this->trackChanges,
            'review.accept_all' => $this->acceptAllRevisions(),
            'review.reject_all' => $this->rejectAllRevisions(),
            'ai.elaborate' => $this->aiElaborateBlock($this->activeBlock),
            'ai.shorten' => $this->aiShortenBlock($this->activeBlock),
            'ai.improve' => $this->aiImproveBlock($this->activeBlock),
            'ai.continue' => $this->aiContinueStory($this->activeBlock),
            'view.dark_mode' => $this->toggleDarkMode(),
            'view.focus' => $this->setViewMode($this->viewMode === 'read' ? 'print' : 'read'),
            'view.ruler' => $this->showRuler = ! $this->showRuler,
            'view.navigator' => $this->showNavigation = ! $this->showNavigation,
            'view.shortcuts' => $this->openDialog('shortcuts'),
            default => null,
        };

        if ($this->dialog === 'palette') {
            $this->dialog = '';
        }
    }

    public function runAutoCorrect(): void
    {
        $this->pushUndo();
        $total = 0;

        foreach ($this->blocks as $i => $block) {
            $text = (string) ($block['text'] ?? '');
            if (trim($text) === '') {
                continue;
            }
            $res = $this->model->autoCorrect($text);
            if ($res['count'] > 0) {
                $total += $res['count'];
                $this->blocks[$i]['text'] = $res['text'];
                $this->blocks[$i]['runs'] = [];
            }
        }

        $this->dirty = true;
        $this->notify('Auto-Correct diterapkan', $total > 0 ? "{$total} tanda tipografi diperbaiki otomatis." : 'Tidak ada tipografi yang perlu diganti.', 'success');
    }

    public function tableSumColumn(int $index, int $colIndex): void
    {
        if (! isset($this->blocks[$index]) || ($this->blocks[$index]['type'] ?? '') !== 'table') {
            return;
        }

        $this->pushUndo();
        $this->blocks[$index] = $this->model->calculateTableColumn($this->blocks[$index], $colIndex, 'sum');
        $this->dirty = true;
        $this->notify('Kalkulasi Tabel', "Kolom #{$colIndex} dijumlahkan.", 'success');
    }

    public function tableAverageColumn(int $index, int $colIndex): void
    {
        if (! isset($this->blocks[$index]) || ($this->blocks[$index]['type'] ?? '') !== 'table') {
            return;
        }

        $this->pushUndo();
        $this->blocks[$index] = $this->model->calculateTableColumn($this->blocks[$index], $colIndex, 'average');
        $this->dirty = true;
        $this->notify('Kalkulasi Tabel', "Rata-rata kolom #{$colIndex} dihitung.", 'success');
    }

    public function createSnapshot(?string $label = null): void
    {
        $label = trim((string) ($label ?: $this->snapshotLabel ?: ('Versi '.now()->translatedFormat('d M Y H:i'))));
        $this->snapshots[] = [
            'id' => (string) Str::uuid(),
            'label' => $label,
            'at' => now()->toIso8601String(),
            'count' => count($this->blocks),
            'blocks' => $this->blocks,
        ];
        $this->snapshotLabel = '';
        $this->dirty = true;
        $this->notify('Snapshot dibuat', "Versi '{$label}' disimpan dalam riwayat naskah.", 'success');
    }

    public function restoreSnapshot(int $snapshotIndex): void
    {
        if (! isset($this->snapshots[$snapshotIndex])) {
            return;
        }

        $this->pushUndo();
        $snapshot = $this->snapshots[$snapshotIndex];
        $this->blocks = (array) $snapshot['blocks'];
        $this->dirty = true;
        $this->notify('Versi dipulihkan', "Naskah dikembalikan ke: {$snapshot['label']}", 'info');
    }

    public function deleteSnapshot(int $snapshotIndex): void
    {
        if (! isset($this->snapshots[$snapshotIndex])) {
            return;
        }

        array_splice($this->snapshots, $snapshotIndex, 1);
        $this->snapshots = array_values($this->snapshots);
        $this->dirty = true;
        $this->notify('Snapshot dihapus', '', 'info');
    }

    public function insertCallout(string $type = 'info'): void
    {
        $this->pushUndo();
        $block = $this->model->newBlock('callout', 'Catatan penting: tulis isi sorotan di sini...');
        $block['calloutType'] = in_array($type, ['info', 'warning', 'tip', 'note', 'success'], true) ? $type : 'info';
        $this->blocks[] = $block;
        $this->activeBlock = count($this->blocks) - 1;
        $this->dirty = true;
    }

    public function toggleDropCap(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $this->blocks[$index]['dropCap'] = ! ($this->blocks[$index]['dropCap'] ?? false);
        $this->dirty = true;
    }

    public function documentText(): string
    {
        return $this->model->toPlainText($this->model->normalize($this->blocks));
    }

    // =====================================================================
    // Data tampilan
    // =====================================================================

    /**
     * @return array<int, array{index: int, level: int, text: string}>
     */
    public function outline(): array
    {
        return $this->model->outline($this->model->normalize($this->blocks));
    }

    protected function notify(string $title, string $body = '', string $type = 'success'): void
    {
        if (method_exists($this, 'dispatch')) {
            $this->dispatch('studio-toast', title: $title, body: $body, type: $type);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getStudioViewData(): array
    {
        $blocks = $this->model->normalize($this->blocks);

        return [
            'renderBlocks' => $blocks,
            'outline' => $this->model->outline($blocks),
            'wordCount' => $this->model->countWords($blocks),
            'charCount' => mb_strlen($this->model->toPlainText($blocks)),
            'blockCount' => count($blocks),
            'projects' => XishieProject::query()->latest()->get(['id', 'title']),
            'documents' => NaskahDocument::query()->latest('last_saved_at')->limit(8)->get(['id', 'title', 'source_type', 'word_count', 'last_saved_at']),
            'assistantTools' => $this->assistantTools(),
            'stageOptions' => ProjectStage::options(),
            'ribbonTabs' => [
                'file' => 'Berkas',
                'home' => 'Home',
                'insert' => 'Sisipkan',
                'design' => 'Desain',
                'layout' => 'Tata Letak',
                'references' => 'Referensi',
                'review' => 'Peninjauan',
                'view' => 'Tampilan',
                'mailings' => 'Surat Menyurat',
                'table' => 'Alat Tabel',
            ],
            'stylesGallery' => [
                'normal' => 'Normal', 'title' => 'Title', 'heading1' => 'Heading 1',
                'heading2' => 'Heading 2', 'heading3' => 'Heading 3', 'quote' => 'Kutipan',
                'bullet' => 'Daftar Poin', 'numbered' => 'Daftar Nomor',
            ],
            'fontSizes' => [10, 11, 12, 14, 16, 18, 20, 24, 28, 32],
            'fontFamilies' => ['serif' => 'Georgia (Serif)', 'sans' => 'Inter (Sans)', 'mono' => 'JetBrains Mono'],
            'colors' => ['#0f172a', '#1e40af', '#b91c1c', '#047857', '#b45309', '#7c3aed', '#0891b2', '#475569'],
            'highlights' => ['#fde68a', '#bbf7d0', '#bfdbfe', '#fecaca', '#e9d5ff', '#fed7aa'],
            'pendingRevisionCount' => collect($this->revisions)->where('status', 'pending')->count(),
            'resolvedCommentCount' => collect($this->comments)->where('resolved', true)->count(),
            'pageSetup' => $this->pageSetup,
            'styleMeta' => $this->styleMeta,
            'snapshots' => $this->snapshots,
            'metadata' => $this->metadata,
            'commands' => $this->commands(),
            'statistics' => $this->model->computeStatistics($blocks),
            'elements' => [
                'headings' => count($this->model->outline($blocks)),
                'tables' => count(array_filter($blocks, static fn (array $b): bool => ($b['type'] ?? '') === 'table')),
                'images' => count(array_filter($blocks, static fn (array $b): bool => ($b['type'] ?? '') === 'image')),
                'footnotes' => count(array_filter($blocks, static fn (array $b): bool => ($b['type'] ?? '') === 'footnote-ref')),
                'comments' => count($this->comments),
                'revisions' => count($this->revisions),
            ],
        ];
    }
}
