<?php

declare(strict_types=1);

namespace App\Omnigensi\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dipancarkan setiap kali agen Omnigensi memperbarui statusnya agar
 * terminal aktivitas di workspace dapat menampilkan progres secara live.
 */
class AgentTelemetryUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $projectId,
        public readonly string $agentName,
        public readonly string $message,
        public readonly string $status = 'running',
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('xishie.project.'.$this->projectId)];
    }

    public function broadcastAs(): string
    {
        return 'agent.telemetry.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'project_id' => $this->projectId,
            'agent_name' => $this->agentName,
            'message' => $this->message,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'at' => now()->toIso8601String(),
        ];
    }
}
