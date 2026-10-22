<?php

declare(strict_types=1);

namespace App\Omnigensi\Graph;

/**
 * DefaultGraphSchema: skema graf multi-agen baku untuk workspace Xishie.
 *
 * Alur:  scout -> scribe -> critic(reviewer)
 *                          critic --approve--> venue -> END
 *                          critic --reject---> scribe (siklus revisi)
 *
 * Graf ini mereplikasi pola omnigensi "dynamic team" (worker + reviewer +
 * conditional loop dengan batas `max_revisions`).
 */
final class DefaultGraphSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return [
            'entry_point' => 'scout',
            'nodes' => [
                ['node_id' => 'scout', 'agent' => 'scholar_scout', 'node_type' => 'worker'],
                ['node_id' => 'scribe', 'agent' => 'academic_scribe', 'node_type' => 'worker'],
                ['node_id' => 'critic', 'agent' => 'rule_keeper', 'node_type' => 'reviewer'],
                ['node_id' => 'venue', 'agent' => 'venue_matcher', 'node_type' => 'worker'],
            ],
            'edges' => [
                ['from' => 'scout', 'to' => 'scribe', 'type' => 'normal'],
                ['from' => 'scribe', 'to' => 'critic', 'type' => 'normal'],
                [
                    'from' => 'critic',
                    'type' => 'conditional',
                    'on_approve' => 'venue',
                    'on_reject' => 'scribe',
                ],
                ['from' => 'venue', 'to' => WorkflowGraphSchema::END, 'type' => 'normal'],
            ],
        ];
    }
}
