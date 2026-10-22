<?php

declare(strict_types=1);

namespace App\Omnigensi\Graph;

use App\Omnigensi\Graph\Enums\EdgeType;
use App\Omnigensi\Graph\Enums\NodeType;

/**
 * GraphValidator: validasi struktural graf workflow *sebelum* disimpan atau
 * dijalankan. Port dari `validate_graph` (omnigensi `app/engine/graph_validation.py`).
 *
 * Compiler hanya gagal saat eksekusi dengan exception mentah; validator ini
 * memberi pesan yang dapat ditindaklanjuti lebih dulu.
 */
final class GraphValidator
{
    /**
     * @param  array<int, string>|null  $knownAgents  nama agen terdaftar (opsional)
     */
    public function validate(mixed $schema, ?array $knownAgents = null): GraphValidation
    {
        $issues = [];

        if (! is_array($schema)) {
            return new GraphValidation(
                valid: false,
                issues: [new GraphIssue(
                    code: 'invalid_schema',
                    message: 'graph_schema harus berupa objek dengan "nodes" dan "edges".',
                    field: 'graph_schema',
                )],
            );
        }

        $nodes = $schema['nodes'] ?? null;
        $edges = $schema['edges'] ?? [];

        if (! is_array($nodes)) {
            $issues[] = new GraphIssue('missing_nodes', 'graph_schema.nodes harus berupa list.', field: 'nodes');
            $nodes = [];
        } elseif ($nodes === []) {
            $issues[] = new GraphIssue('empty_graph', 'Workflow membutuhkan minimal satu node.', field: 'nodes');
        }

        if (! is_array($edges)) {
            $issues[] = new GraphIssue('invalid_edges', 'graph_schema.edges harus berupa list.', field: 'edges');
            $edges = [];
        }

        $known = $knownAgents !== null ? array_map('strval', $knownAgents) : null;

        $nodeIds = [];
        $seen = [];

        foreach ($nodes as $index => $node) {
            if (! is_array($node)) {
                $issues[] = new GraphIssue('invalid_node', "Node #{$index} harus berupa objek.");

                continue;
            }

            $nodeId = $node['node_id'] ?? null;

            if (! is_string($nodeId) || $nodeId === '') {
                $issues[] = new GraphIssue('missing_node_id', "Node #{$index} tidak memiliki 'node_id'.", field: 'node_id');
            } elseif (in_array($nodeId, $seen, true)) {
                $issues[] = new GraphIssue('duplicate_node_id', "node_id duplikat '{$nodeId}'.", nodeId: $nodeId, field: 'node_id');
            } else {
                $seen[] = $nodeId;
                $nodeIds[] = $nodeId;
            }

            $agent = $node['agent'] ?? $node['agent_id'] ?? null;

            if ($agent === null || $agent === '') {
                $issues[] = new GraphIssue(
                    'missing_agent_id',
                    "Node '".($nodeId ?: $index)."' tidak memiliki 'agent'.",
                    nodeId: is_string($nodeId) ? $nodeId : null,
                    field: 'agent',
                );
            } elseif ($known !== null && ! in_array((string) $agent, $known, true)) {
                $issues[] = new GraphIssue(
                    'unknown_agent',
                    "Node '{$nodeId}' merujuk agen '{$agent}' yang tidak terdaftar.",
                    nodeId: is_string($nodeId) ? $nodeId : null,
                    field: 'agent',
                );
            }

            $nodeType = $node['node_type'] ?? null;

            if ($nodeType !== null && ! NodeType::isValid((string) $nodeType)) {
                $issues[] = new GraphIssue(
                    'invalid_node_type',
                    "Node '{$nodeId}' memiliki node_type tidak valid '{$nodeType}'.",
                    nodeId: is_string($nodeId) ? $nodeId : null,
                    field: 'node_type',
                );
            }
        }

        $validIds = $nodeIds;
        $end = WorkflowGraphSchema::END;

        foreach ($edges as $index => $edge) {
            if (! is_array($edge)) {
                $issues[] = new GraphIssue('invalid_edge', "Edge #{$index} harus berupa objek.", edgeIndex: $index);

                continue;
            }

            $source = $edge['from'] ?? null;
            $target = $edge['to'] ?? null;
            $type = (string) ($edge['type'] ?? EdgeType::NORMAL->value);

            if ($source === null || $source === '') {
                $issues[] = new GraphIssue('missing_edge_source', "Edge #{$index} tidak memiliki 'from'.", edgeIndex: $index, field: 'from');
            } elseif (! in_array((string) $source, $validIds, true)) {
                $issues[] = new GraphIssue('unknown_edge_source', "Edge #{$index} dimulai dari node tak dikenal '{$source}'.", edgeIndex: $index, field: 'from');
            }

            if (! EdgeType::isValid($type)) {
                $issues[] = new GraphIssue('invalid_edge_type', "Edge #{$index} memiliki type tidak valid '{$type}'.", edgeIndex: $index, field: 'type');
            }

            if ($type === EdgeType::CONDITIONAL->value) {
                $targets = [
                    'on_approve' => $edge['on_approve'] ?? $end,
                    'on_reject' => $edge['on_reject'] ?? $end,
                ];

                foreach ($targets as $label => $value) {
                    if (is_string($value) && $value !== '' && $value !== $end && ! in_array($value, $validIds, true)) {
                        $issues[] = new GraphIssue(
                            'unknown_edge_target',
                            "Edge #{$index} mengarahkan {$label} ke node tak dikenal '{$value}'.",
                            edgeIndex: $index,
                            field: $label,
                        );
                    }
                }
            } elseif (is_string($target) && $target !== '' && $target !== $end && ! in_array($target, $validIds, true)) {
                $issues[] = new GraphIssue(
                    'unknown_edge_target',
                    "Edge #{$index} menunjuk node tak dikenal '{$target}'.",
                    edgeIndex: $index,
                    field: 'to',
                );
            }
        }

        $entryPoint = $schema['entry_point'] ?? null;
        $resolvedEntry = is_string($entryPoint) ? $entryPoint : null;

        if (is_string($entryPoint) && $entryPoint !== '' && $nodeIds !== [] && ! in_array($entryPoint, $validIds, true)) {
            $issues[] = new GraphIssue('unknown_entry_point', "entry_point '{$entryPoint}' bukan salah satu node.", field: 'entry_point');
            $resolvedEntry = $nodeIds[0] ?? null;
        } elseif ((! is_string($entryPoint) || $entryPoint === '') && $nodeIds !== []) {
            $resolvedEntry = $nodeIds[0];
        }

        return new GraphValidation(
            valid: $issues === [],
            issues: $issues,
            nodeCount: count($nodeIds),
            edgeCount: count($edges),
            entryPoint: $resolvedEntry,
        );
    }
}
