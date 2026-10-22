<?php

declare(strict_types=1);

namespace App\Omnigensi\Graph\Enums;

/**
 * Tipe edge pada graf workflow multi-agen (port dari omnigensi
 * `VALID_EDGE_TYPES`).
 *
 * - NORMAL      : transisi langsung dari satu node ke node berikutnya.
 * - CONDITIONAL : percabangan berdasarkan keputusan reviewer
 *                 (`on_approve` / `on_reject`), dengan batas `max_revisions`.
 */
enum EdgeType: string
{
    case NORMAL = 'normal';
    case CONDITIONAL = 'conditional';

    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    public function isConditional(): bool
    {
        return $this === self::CONDITIONAL;
    }
}
