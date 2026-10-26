<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Assistant\SlashCommand;
use App\Domain\Assistant\XishieAssistantService;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Models\OmnigensiAgentLog;
use App\Models\XishieProject;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * XishieAssistantPage: konsol Xishie Assistant mandiri — chat kontekstual ke
 * proyek terpilih dengan palet slash command (/sitasi, /parafrase, /cek-gap,
 * /cek-pedoman, /matriks-literatur, /cari-referensi) dan jawaban Markdown.
 */
class XishieAssistantPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Xishie Assistant';

    protected static ?string $title = 'Xishie Assistant';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.xishie-assistant';

    public ?int $projectId = null;

    public string $message = '';

    /**
     * Transkrip: [['role' => 'user'|'assistant', 'content' => ...]].
     *
     * @var array<int, array{role: string, content: string}>
     */
    public array $log = [];

    public function mount(): void
    {
        $param = request()->query('project');

        $this->projectId = $param !== null
            ? (int) $param
            : XishieProject::query()->latest()->value('id');
    }

    public function getProject(): ?XishieProject
    {
        return $this->projectId !== null ? XishieProject::find($this->projectId) : null;
    }

    /**
     * Pilih proyek aktif.
     */
    public function switchProject(int $projectId): void
    {
        if (XishieProject::find($projectId) === null) {
            Notification::make()->title('Proyek tidak ditemukan')->danger()->send();

            return;
        }

        $this->projectId = $projectId;

        Notification::make()->title('Konteks proyek diubah')->success()->send();

        $this->dispatch('$refresh');
    }

    /**
     * Sisipkan sebuah slash command ke kolom pesan.
     */
    public function insertCommand(string $command): void
    {
        $name = SlashCommand::tryFrom($command)?->value ?? $command;

        $this->message = trim($this->message.' '.$name).' ';
    }

    /**
     * Kirim pesan ke Xishie Assistant dan tambahkan jawabannya ke transkrip.
     */
    public function send(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            Notification::make()->title('Tidak ada proyek aktif')->warning()->send();

            return;
        }

        $text = trim($this->message);

        if ($text === '') {
            return;
        }

        $this->log[] = ['role' => 'user', 'content' => $text];
        $this->message = '';

        try {
            $result = app(XishieAssistantService::class)->handle($project, $text);

            // Catat untuk KPI "Assistant Query Effectiveness".
            OmnigensiAgentLog::create([
                'project_id' => $project->id,
                'agent_name' => 'Xishie-Assistant',
                'action' => $result['command'],
                'control_mode' => $project->control_mode->value,
                'ttft_ms' => 0,
                'execution_time_ms' => 0,
                'token_usage' => str_word_count((string) $result['reply']),
                'hallucination_rejected_count' => 0,
                'status' => 'success',
                'payload' => [
                    'query' => $text,
                    'command' => $result['command'],
                    'metadata' => [
                        'applied' => in_array($result['command'], SlashCommand::names(), true),
                    ],
                ],
            ]);

            $this->log[] = ['role' => 'assistant', 'content' => (string) $result['reply']];
        } catch (\Throwable $exception) {
            $this->log[] = ['role' => 'assistant', 'content' => '[Gagal] '.$exception->getMessage()];
        }

        $this->dispatch('assistant-updated');
    }

    /**
     * Bersihkan transkrip.
     */
    public function clearLog(): void
    {
        $this->log = [];
    }

    /**
     * Unduh transkrip sesi percakapan sebagai file Markdown, JSON, atau TXT.
     */
    public function exportTranscript(string $format = 'markdown'): ?StreamedResponse
    {
        $project = $this->getProject();
        if (empty($this->log)) {
            Notification::make()->title('Transkrip masih kosong')->warning()->send();

            return null;
        }

        $title = $project?->title ?? 'Xishie Assistant';
        $timestamp = now()->format('Y-m-d_H-i-s');
        $format = mb_strtolower($format);

        if ($format === 'json') {
            $filename = 'xishie_assistant_transcript_'.$timestamp.'.json';
            $payload = [
                'project' => [
                    'id' => $project?->id,
                    'title' => $title,
                ],
                'exported_at' => now()->toIso8601String(),
                'total_messages' => count($this->log),
                'messages' => $this->log,
            ];
            $content = (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $mime = 'application/json; charset=UTF-8';
        } elseif ($format === 'txt') {
            $filename = 'xishie_assistant_transcript_'.$timestamp.'.txt';
            $lines = [
                'TRANSKRIP PERCAKAPAN XISHIE ASSISTANT',
                'Proyek: '.$title,
                'Waktu Ekspor: '.now()->toIso8601String(),
                'Total Pesan: '.count($this->log),
                str_repeat('=', 50),
                '',
            ];

            foreach ($this->log as $entry) {
                $sender = $entry['role'] === 'user' ? 'Peneliti' : 'Xishie Assistant';
                $lines[] = "[{$sender}]";
                $lines[] = (string) $entry['content'];
                $lines[] = str_repeat('-', 30);
                $lines[] = '';
            }

            $content = implode("\n", $lines);
            $mime = 'text/plain; charset=UTF-8';
        } else {
            $filename = 'xishie_assistant_transcript_'.$timestamp.'.md';
            $lines = [
                '# Transkrip Sesi Xishie Assistant',
                '- **Proyek**: '.$title,
                '- **Waktu Ekspor**: '.now()->toIso8601String(),
                '- **Total Pesan**: '.count($this->log),
                '',
                '---',
                '',
            ];

            foreach ($this->log as $entry) {
                $sender = $entry['role'] === 'user' ? 'Peneliti' : 'Xishie Assistant';
                $lines[] = "### [{$sender}]";
                $lines[] = $entry['content'];
                $lines[] = '';
            }

            $content = implode("\n", $lines);
            $mime = 'text/markdown; charset=UTF-8';
        }

        Notification::make()
            ->title('Transkrip Percakapan Diekspor')
            ->body('Transkrip berhasil diunduh dalam format '.strtoupper($format).'.')
            ->success()
            ->send();

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => $mime,
        ]);
    }

    /**
     * Render jawaban (Markdown) menjadi HTML aman.
     */
    public function markdownToHtml(string $markdown): string
    {
        if (str_starts_with($markdown, '[Gagal]')) {
            return htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        try {
            $converter = new GithubFlavoredMarkdownConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);

            return (string) $converter->convert($markdown)->getContent();
        } catch (\Throwable) {
            return '<p>'.nl2br(htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p>';
        }
    }

    /**
     * @return array<int, array{name: string, description: string}>
     */
    public function commands(): array
    {
        return array_map(static fn (SlashCommand $c): array => [
            'name' => $c->value,
            'description' => $c->description(),
        ], SlashCommand::cases());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'project' => $this->getProject(),
            'projects' => XishieProject::query()->latest()->get(['id', 'title']),
            'commands' => $this->commands(),
            'kpi' => UxKpiMetric::ASSISTANT_QUERY_EFFECTIVENESS->label(),
        ];
    }
}
