<?php

declare(strict_types=1);

namespace App\Omnigensi\Graph;

use App\Omnigensi\Agents\AcademicScribeAgent;
use App\Omnigensi\Agents\PeerCriticAgent;
use App\Omnigensi\Agents\RuleKeeperAgent;
use App\Omnigensi\Agents\ScholarScoutAgent;
use App\Omnigensi\Agents\VenueMatcherAgent;
use App\Omnigensi\Contracts\XishieAgentContract;
use InvalidArgumentException;

/**
 * AgentRegistry: peta nama agen -> instance agen spesialis Omnigensi.
 *
 * Graf workflow merujuk agen lewat kunci stabil (mis. "scholar_scout"),
 * sehingga skema graf dapat dideklarasikan sebagai JSON tanpa bergantung pada
 * detail kelas PHP.
 */
final class AgentRegistry
{
    /** @var array<string, class-string<XishieAgentContract>> */
    private const AGENTS = [
        'scholar_scout' => ScholarScoutAgent::class,
        'academic_scribe' => AcademicScribeAgent::class,
        'rule_keeper' => RuleKeeperAgent::class,
        'peer_critic' => PeerCriticAgent::class,
        'venue_matcher' => VenueMatcherAgent::class,
    ];

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys(self::AGENTS);
    }

    public function has(string $name): bool
    {
        return isset(self::AGENTS[$name]);
    }

    public function make(string $name): XishieAgentContract
    {
        $class = self::AGENTS[$name] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Agen tidak terdaftar pada registry: '{$name}'.");
        }

        return app($class);
    }

    /**
     * Tipe node default untuk sebuah agen, dipakai saat skema tidak menentukan.
     */
    public function defaultNodeType(string $name): string
    {
        return $name === 'peer_critic' ? 'reviewer' : 'worker';
    }
}
