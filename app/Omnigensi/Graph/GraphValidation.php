<?php

declare(strict_types=1);

namespace App\Omnigensi\Graph;

/**
 * GraphValidation: hasil validasi struktural sebuah graf workflow.
 * Port dari `GraphValidation` (omnigensi `app/engine/graph_validation.py`).
 */
final readonly class GraphValidation
{
    /**
     * @param  array<int, GraphIssue>  $issues
     */
    public function __construct(
        public bool $valid,
        public array $issues = [],
        public int $nodeCount = 0,
        public int $edgeCount = 0,
        public ?string $entryPoint = null,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function errors(): array
    {
        return array_map(static fn (GraphIssue $issue): array => $issue->toArray(), $this->issues);
    }

    public function summary(): string
    {
        if ($this->valid) {
            return sprintf('Graph valid (%d node, %d edge).', $this->nodeCount, $this->edgeCount);
        }

        $first = $this->issues[0]->message ?? 'Graf tidak valid.';
        $extra = count($this->issues) - 1;

        return $first.($extra > 0 ? " (+{$extra} masalah lain)" : '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'summary' => $this->summary(),
            'node_count' => $this->nodeCount,
            'edge_count' => $this->edgeCount,
            'entry_point' => $this->entryPoint,
            'issues' => $this->errors(),
        ];
    }
}
