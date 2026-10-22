<?php

declare(strict_types=1);

namespace App\Omnigensi\Graph;

/**
 * GraphIssue: satu alasan sebuah graf workflow tidak dapat dijalankan.
 * Port dari `GraphIssue` (omnigensi `app/engine/graph_validation.py`).
 */
final readonly class GraphIssue
{
    public function __construct(
        public string $code,
        public string $message,
        public ?string $nodeId = null,
        public ?int $edgeIndex = null,
        public ?string $field = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['code' => $this->code, 'message' => $this->message];

        if ($this->nodeId !== null) {
            $data['node_id'] = $this->nodeId;
        }

        if ($this->edgeIndex !== null) {
            $data['edge_index'] = $this->edgeIndex;
        }

        if ($this->field !== null) {
            $data['field'] = $this->field;
        }

        return $data;
    }
}
