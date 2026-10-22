<?php

declare(strict_types=1);

namespace App\Omnigensi\Graph\Enums;

/**
 * Tipe node pada graf workflow multi-agen (port dari omnigensi
 * `VALID_NODE_TYPES`).
 *
 * - WORKER      : agen pelaksana (riset, penulisan, pencocokan venue).
 * - REVIEWER    : agen penilai yang mengeluarkan keputusan `is_approved`.
 * - SUPERVISOR  : agen koordinator yang menyintesis arahan tingkat atas.
 */
enum NodeType: string
{
    case WORKER = 'worker';
    case REVIEWER = 'reviewer';
    case SUPERVISOR = 'supervisor';

    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    public function isReviewer(): bool
    {
        return $this === self::REVIEWER;
    }
}
