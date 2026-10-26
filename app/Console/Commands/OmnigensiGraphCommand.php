<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\XishieProject;
use App\Omnigensi\Graph\AgentRegistry;
use App\Omnigensi\Graph\DefaultGraphSchema;
use App\Omnigensi\Graph\GraphRunner;
use App\Omnigensi\Graph\GraphValidator;
use App\Omnigensi\Graph\WorkflowGraphSchema;
use Illuminate\Console\Command;

/**
 * Validasi dan jalankan graf workflow multi-agen Omnigensi dari CLI.
 *
 * Contoh:
 *   php artisan omnigensi:graph --validate
 *   php artisan omnigensi:graph --project=1 --stage=draft
 */
final class OmnigensiGraphCommand extends Command
{
    protected $signature = 'omnigensi:graph
        {--project= : ID proyek Xishie yang akan dijalankan}
        {--stage=draft : Tahap (draft|proposal|final)}
        {--max-revisions=3 : Batas siklus revisi reviewer}
        {--schema= : Path berkas JSON skema graf (default: skema baku)}
        {--mermaid : Tampilkan representasi visual graf sebagai diagram Mermaid}
        {--validate : Hanya validasi skema graf, tanpa eksekusi}';

    protected $description = 'Validasi/jalankan graf multi-agen Omnigensi (dynamic workflow graph)';

    public function handle(GraphValidator $validator, AgentRegistry $registry, GraphRunner $runner): int
    {
        $schema = $this->resolveSchema();
        $known = $registry->names();

        $validation = $validator->validate($schema, $known);

        $this->line('Ringkasan: '.$validation->summary());

        if (! $validation->valid) {
            foreach ($validation->errors() as $issue) {
                $this->error(sprintf(' - [%s] %s', $issue['code'], $issue['message']));
            }

            return self::FAILURE;
        }

        $this->info(sprintf('Graf valid: %d node, %d edge. Entry: %s', $validation->nodeCount, $validation->edgeCount, $validation->entryPoint ?? '-'));

        if ($this->option('mermaid')) {
            $graph = WorkflowGraphSchema::fromArray($schema);
            $this->line($graph->toMermaid());

            return self::SUCCESS;
        }

        if ($this->option('validate')) {
            return self::SUCCESS;
        }

        $projectId = $this->option('project');

        if ($projectId === null) {
            $this->warn('Tidak ada --project; validasi saja. Gunakan --project=<id> untuk eksekusi.');

            return self::SUCCESS;
        }

        $project = XishieProject::find((int) $projectId);

        if ($project === null) {
            $this->error("Proyek dengan ID {$projectId} tidak ditemukan.");

            return self::FAILURE;
        }

        $stage = ProjectStage::tryFrom((string) $this->option('stage')) ?? ProjectStage::DRAFT;

        $outcome = $runner->run(
            $project,
            $stage,
            $schema,
            [],
            max(1, (int) $this->option('max-revisions')),
        );

        $this->info('Node dieksekusi: '.implode(' -> ', $outcome['graph']['nodes_executed']));
        $this->line('Revisi: '.$outcome['graph']['revision_count'].' · Disetujui: '.($outcome['graph']['is_approved'] ? 'ya' : 'belum'));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSchema(): array
    {
        $path = $this->option('schema');

        if (! is_string($path) || $path === '') {
            return DefaultGraphSchema::definition();
        }

        if (! is_file($path)) {
            $this->warn("Skema '{$path}' tidak ditemukan; memakai skema baku.");

            return DefaultGraphSchema::definition();
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : DefaultGraphSchema::definition();
    }
}
