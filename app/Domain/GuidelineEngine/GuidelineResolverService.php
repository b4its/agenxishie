<?php

declare(strict_types=1);

namespace App\Domain\GuidelineEngine;

use App\Domain\GuidelineEngine\Data\GuidelineDefinition;
use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Models\XishieProject;

/**
 * Menyelesaikan pedoman penulisan efektif sebuah proyek:
 * - Mode default: memuat preset baku sesuai jenis dokumen.
 * - Mode custom: memakai aturan hasil ekstraksi RuleKeeperAgent dari PDF/DOCX,
 *   dengan fallback ke preset bila ekstraksi belum tersedia.
 */
final class GuidelineResolverService
{
    public function __construct(
        private readonly GuidelinePresetFactory $presets,
    ) {}

    public function resolve(XishieProject $project): GuidelineDefinition
    {
        $preset = $this->presets->forDocumentType($project->document_type, $this->citationStyle($project));

        if ($project->guideline_type === 'custom' && is_array($project->guideline_rules) && $project->guideline_rules !== []) {
            $custom = GuidelineDefinition::fromArray($project->guideline_rules);

            // Pastikan stage map tetap lengkap; lengkapi dengan preset bila kosong.
            if ($custom->sections !== [] && $custom->stages !== []) {
                return $custom;
            }
        }

        return $preset;
    }

    /**
     * Simpan hasil preset ke kolom guideline_rules agar transparan di UI.
     */
    public function persistPreset(XishieProject $project): GuidelineDefinition
    {
        $definition = $this->presets->forDocumentType($project->document_type, $this->citationStyle($project));

        $project->forceFill(['guideline_rules' => $definition->toArray()])->save();

        return $definition;
    }

    private function citationStyle(XishieProject $project): string
    {
        $style = $project->citation_style;

        return $style instanceof CitationStyle ? $style->value : (string) $style;
    }
}
