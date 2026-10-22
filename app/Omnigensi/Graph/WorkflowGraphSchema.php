<?php

declare(strict_types=1);

namespace App\Omnigensi\Graph;

use App\Omnigensi\Graph\Enums\EdgeType;
use App\Omnigensi\Graph\Enums\NodeType;

/**
 * WorkflowGraphSchema: representasi tervalidasi dari skema graf workflow
 * multi-agen yang dideklarasikan sebagai JSON:
 *
 * {
 *   "entry_point": "scout",
 *   "nodes": [
 *     {"node_id": "scout",  "agent": "scholar_scout",  "node_type": "worker"},
 *     {"node_id": "scribe", "agent": "academic_scribe", "node_type": "worker"},
 *     {"node_id": "critic", "agent": "peer_critic",     "node_type": "reviewer"}
 *   ],
 *   "edges": [
 *     {"from": "scout",  "to": "scribe"},
 *     {"from": "scribe", "to": "critic"},
 *     {"from": "critic", "type": "conditional",
 *      "on_approve": "END", "on_reject": "scribe"}
 *   ]
 * }
 *
 * Struktur identik dengan omnigensi `WorkflowGraphSchema` (`schemas/workflow.py`),
 * hanya `agent_id` diganti nama agen terdaftar agar sesuai arsitektur modular
 * monolith Laravel (tanpa tabel agent-template terpisah).
 */
final readonly class WorkflowGraphSchema
{
    public const END = 'END';

    /**
     * @param  array<int, array{node_id: string, agent: string, node_type: string}>  $nodes
     * @param  array<int, array{from: string, to: string|null, type: string, on_approve: string|null, on_reject: string|null}>  $edges
     */
    public function __construct(
        public array $nodes,
        public array $edges,
        public ?string $entryPoint = null,
    ) {}

    /**
     * Bangun DTO dari array mentah (mis. hasil decode JSON). Normalisasi nilai
     * `type` edge dan `END` mengikuti semantik omnigensi tanpa melakukan
     * validasi struktural (itu tugas GraphValidator).
     *
     * @param  array<string, mixed>  $schema
     */
    public static function fromArray(array $schema): self
    {
        $nodes = [];
        $rawNodes = is_array($schema['nodes'] ?? null) ? $schema['nodes'] : [];

        foreach ($rawNodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $nodeType = (string) ($node['node_type'] ?? NodeType::WORKER->value);

            $nodes[] = [
                'node_id' => (string) ($node['node_id'] ?? ''),
                'agent' => (string) ($node['agent'] ?? $node['agent_id'] ?? ''),
                'node_type' => $nodeType,
            ];
        }

        $edges = [];
        $rawEdges = is_array($schema['edges'] ?? null) ? $schema['edges'] : [];

        foreach ($rawEdges as $edge) {
            if (! is_array($edge)) {
                continue;
            }

            $type = (string) ($edge['type'] ?? EdgeType::NORMAL->value);

            $edges[] = [
                'from' => (string) ($edge['from'] ?? ''),
                'to' => self::normalizeTarget($edge['to'] ?? null),
                'type' => $type,
                'on_approve' => self::normalizeTarget($edge['on_approve'] ?? null),
                'on_reject' => self::normalizeTarget($edge['on_reject'] ?? null),
            ];
        }

        $entry = $schema['entry_point'] ?? null;

        return new self(
            nodes: $nodes,
            edges: $edges,
            entryPoint: is_string($entry) && $entry !== '' ? $entry : null,
        );
    }

    /**
     * Normalisasi target edge: string kosong/null menjadi `END`.
     */
    private static function normalizeTarget(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @return array<int, string>
     */
    public function nodeIds(): array
    {
        return array_column($this->nodes, 'node_id');
    }

    public function findNode(string $nodeId): ?array
    {
        foreach ($this->nodes as $node) {
            if ($node['node_id'] === $nodeId) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Edge keluar dari sebuah node.
     *
     * @return array<int, array{from: string, to: string|null, type: string, on_approve: string|null, on_reject: string|null}>
     */
    public function outgoingEdges(string $nodeId): array
    {
        return array_values(array_filter(
            $this->edges,
            static fn (array $edge): bool => $edge['from'] === $nodeId,
        ));
    }

    /**
     * Entry point efektif: `entry_point` eksplisit bila valid, jika tidak node pertama.
     */
    public function resolveEntryPoint(): ?string
    {
        if ($this->entryPoint !== null && in_array($this->entryPoint, $this->nodeIds(), true)) {
            return $this->entryPoint;
        }

        return $this->nodes[0]['node_id'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entry_point' => $this->entryPoint,
            'nodes' => $this->nodes,
            'edges' => $this->edges,
        ];
    }
}
