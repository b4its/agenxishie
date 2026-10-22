<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Enums;

enum ProjectStage: string
{
    case DRAFT = 'draft';
    case PROPOSAL = 'proposal';
    case FINAL = 'final';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PROPOSAL => 'Proposal',
            self::FINAL => 'Final',
        };
    }

    public function sequence(): int
    {
        return match ($this) {
            self::DRAFT => 1,
            self::PROPOSAL => 2,
            self::FINAL => 3,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::DRAFT => self::PROPOSAL,
            self::PROPOSAL => self::FINAL,
            self::FINAL => null,
        };
    }

    public function previous(): ?self
    {
        return match ($this) {
            self::DRAFT => null,
            self::PROPOSAL => self::DRAFT,
            self::FINAL => self::PROPOSAL,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
