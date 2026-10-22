<?php

declare(strict_types=1);

use App\Domain\Workspace\Enums\ProjectStage;
use App\Omnigensi\Graph\AgentRegistry;
use App\Omnigensi\Graph\DefaultGraphSchema;
use App\Omnigensi\Graph\GraphRunner;
use App\Omnigensi\Graph\GraphValidator;
use App\Omnigensi\Graph\WorkflowGraphSchema;

it('validates the default multi-agent graph as structurally sound', function (): void {
    $validator = app(GraphValidator::class);
    $result = $validator->validate(
        DefaultGraphSchema::definition(),
        app(AgentRegistry::class)->names(),
    );

    expect($result->valid)->toBeTrue()
        ->and($result->nodeCount)->toBe(4)
        ->and($result->edgeCount)->toBe(4)
        ->and($result->entryPoint)->toBe('scout');
});

it('rejects a graph that references an unregistered agent', function (): void {
    $schema = [
        'entry_point' => 'ghost',
        'nodes' => [
            ['node_id' => 'ghost', 'agent' => 'nonexistent_agent', 'node_type' => 'worker'],
        ],
        'edges' => [],
    ];

    $result = app(GraphValidator::class)->validate($schema, app(AgentRegistry::class)->names());

    expect($result->valid)->toBeFalse()
        ->and(collect($result->errors())->pluck('code'))->toContain('unknown_agent');
});

it('flags conditional edges that target unknown nodes', function (): void {
    $schema = [
        'entry_point' => 'critic',
        'nodes' => [
            ['node_id' => 'critic', 'agent' => 'rule_keeper', 'node_type' => 'reviewer'],
        ],
        'edges' => [
            ['from' => 'critic', 'type' => 'conditional', 'on_approve' => 'nowhere', 'on_reject' => 'END'],
        ],
    ];

    $result = app(GraphValidator::class)->validate($schema, app(AgentRegistry::class)->names());

    expect($result->valid)->toBeFalse()
        ->and(collect($result->errors())->pluck('code'))->toContain('unknown_edge_target');
});

it('flags duplicate node ids and invalid node types', function (): void {
    $schema = [
        'nodes' => [
            ['node_id' => 'dup', 'agent' => 'scholar_scout', 'node_type' => 'worker'],
            ['node_id' => 'dup', 'agent' => 'academic_scribe', 'node_type' => 'alien'],
        ],
        'edges' => [],
    ];

    $codes = collect(
        app(GraphValidator::class)->validate($schema, app(AgentRegistry::class)->names())->errors(),
    )->pluck('code');

    expect($codes)->toContain('duplicate_node_id')
        ->and($codes)->toContain('invalid_node_type');
});

it('resolves the first node as entry point when entry_point is absent', function (): void {
    $schema = [
        'nodes' => [
            ['node_id' => 'first', 'agent' => 'scholar_scout', 'node_type' => 'worker'],
            ['node_id' => 'second', 'agent' => 'academic_scribe', 'node_type' => 'worker'],
        ],
        'edges' => [['from' => 'first', 'to' => 'second', 'type' => 'normal']],
    ];

    $validation = app(GraphValidator::class)->validate($schema, app(AgentRegistry::class)->names());

    expect($validation->valid)->toBeTrue()
        ->and(WorkflowGraphSchema::fromArray($schema)->resolveEntryPoint())->toBe('first');
});

it('runs the default graph end-to-end and produces a compiled draft', function (): void {
    $project = makeProject();
    seedReferences($project);

    $outcome = app(GraphRunner::class)->run(
        $project,
        ProjectStage::DRAFT,
        DefaultGraphSchema::definition(),
        ['approval_threshold' => 0.0],
        maxRevisions: 3,
    );

    expect($outcome['graph']['nodes_executed'])->toContain('scout')
        ->and($outcome['graph']['nodes_executed'])->toContain('scribe')
        ->and($outcome['graph']['nodes_executed'])->toContain('critic')
        ->and($outcome['graph']['is_approved'])->toBeTrue()
        ->and($outcome['current_draft'])->not->toBe('');
});

it('loops back to the writer when the reviewer rejects, then stops at max revisions', function (): void {
    $project = makeProject();
    seedReferences($project);

    // Ambang tak terjangkau memaksa reviewer menolak setiap kali.
    $outcome = app(GraphRunner::class)->run(
        $project,
        ProjectStage::DRAFT,
        DefaultGraphSchema::definition(),
        ['approval_threshold' => 1000.0],
        maxRevisions: 2,
    );

    $executed = $outcome['graph']['nodes_executed'];
    $scribeVisits = count(array_filter($executed, static fn (string $n): bool => $n === 'scribe'));
    $criticVisits = count(array_filter($executed, static fn (string $n): bool => $n === 'critic'));

    // entry scribe + 1 putaran revisi = tetap berhenti setelah maxRevisions.
    expect($scribeVisits)->toBeGreaterThan(0)
        ->and($criticVisits)->toBeGreaterThan(0)
        ->and($outcome['graph']['revision_count'])->toBe(2)
        ->and($outcome['graph']['is_approved'])->toBeFalse();
});

it('records a telemetry log entry for every executed node', function (): void {
    $project = makeProject();
    seedReferences($project);

    app(GraphRunner::class)->run(
        $project,
        ProjectStage::DRAFT,
        DefaultGraphSchema::definition(),
        ['approval_threshold' => 0.0],
    );

    expect($project->agentLogs()->count())->toBeGreaterThanOrEqual(4);
});

it('throws when running an invalid graph', function (): void {
    $project = makeProject();

    app(GraphRunner::class)->run($project, ProjectStage::DRAFT, [
        'nodes' => [],
        'edges' => [],
    ]);
})->throws(RuntimeException::class);
