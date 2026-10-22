<?php

declare(strict_types=1);

namespace App\Domain\GuidelineEngine;

use App\Domain\GuidelineEngine\Data\GuidelineDefinition;
use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Workspace\Enums\ProjectStage;

/**
 * Menyediakan preset pedoman penulisan baku (Default Mode) berdasarkan
 * jenis dokumen. Dipisah dari resolver agar mudah diuji dan dipakai ulang
 * oleh RuleKeeperAgent ketika mengekstrak aturan dari PDF/DOCX.
 */
final class GuidelinePresetFactory
{
    public function forDocumentType(DocumentType $type, string $citationStyle): GuidelineDefinition
    {
        return $type === DocumentType::JOURNAL
            ? $this->journalImrad($citationStyle)
            : $this->thesisFamily($type, $citationStyle);
    }

    public function journalImrad(string $citationStyle): GuidelineDefinition
    {
        return new GuidelineDefinition(
            source: 'default_imrad',
            citationStyle: $citationStyle,
            minTotalWords: 4000,
            maxTotalWords: 7000,
            abstractRequired: true,
            minAbstractWords: 150,
            maxAbstractWords: 250,
            sections: [
                ['key' => 'abstract', 'heading' => 'Abstract', 'min_words' => 150, 'max_words' => 250, 'required' => true],
                ['key' => 'introduction', 'heading' => '1. Introduction', 'min_words' => 600, 'max_words' => 1000, 'required' => true],
                ['key' => 'related_work', 'heading' => '2. Related Work & Literature Matrix', 'min_words' => 700, 'max_words' => 1200, 'required' => true],
                ['key' => 'methodology', 'heading' => '3. Methodology', 'min_words' => 700, 'max_words' => 1200, 'required' => true],
                ['key' => 'results', 'heading' => '4. Results & Discussion', 'min_words' => 1000, 'max_words' => 2000, 'required' => true],
                ['key' => 'conclusion', 'heading' => '5. Conclusion', 'min_words' => 300, 'max_words' => 600, 'required' => true],
            ],
            stages: [
                ProjectStage::DRAFT->value => ['abstract', 'introduction', 'related_work'],
                ProjectStage::PROPOSAL->value => ['introduction', 'related_work', 'methodology'],
                ProjectStage::FINAL->value => ['abstract', 'introduction', 'related_work', 'methodology', 'results', 'conclusion'],
            ],
            notes: [
                'Struktur IMRaD wajib dipatuhi.',
                'Setiap klaim wajib memiliki sitasi terverifikasi.',
            ],
        );
    }

    public function thesisFamily(DocumentType $type, string $citationStyle): GuidelineDefinition
    {
        $factor = match ($type) {
            DocumentType::SKRIPSI => 1,
            DocumentType::THESIS => 2,
            DocumentType::DISSERTATION => 3,
            default => 1,
        };

        return new GuidelineDefinition(
            source: 'default_dikti_'.$type->value,
            citationStyle: $citationStyle,
            minTotalWords: 8000 * $factor,
            maxTotalWords: 20000 * $factor,
            abstractRequired: true,
            minAbstractWords: 200,
            maxAbstractWords: 350,
            sections: [
                ['key' => 'problem_statement', 'heading' => 'Rumusan Masalah', 'min_words' => 150, 'max_words' => 400, 'required' => true],
                ['key' => 'sota', 'heading' => 'Keaslian Penelitian (State of the Art)', 'min_words' => 300, 'max_words' => 700, 'required' => true],
                ['key' => 'literature_matrix', 'heading' => 'Matriks Literatur', 'min_words' => 250, 'max_words' => 600, 'required' => true],
                ['key' => 'framework', 'heading' => 'Kerangka Berpikir', 'min_words' => 200, 'max_words' => 500, 'required' => true],
                ['key' => 'chapter1', 'heading' => 'Bab 1 Pendahuluan', 'min_words' => 1200, 'max_words' => 2500, 'required' => true],
                ['key' => 'chapter2', 'heading' => 'Bab 2 Tinjauan Pustaka', 'min_words' => 2000, 'max_words' => 5000, 'required' => true],
                ['key' => 'chapter3', 'heading' => 'Bab 3 Metodologi Penelitian', 'min_words' => 1200, 'max_words' => 3000, 'required' => true],
                ['key' => 'chapter4', 'heading' => 'Bab 4 Hasil dan Pembahasan', 'min_words' => 2500, 'max_words' => 7000, 'required' => true],
                ['key' => 'chapter5', 'heading' => 'Bab 5 Kesimpulan dan Saran', 'min_words' => 600, 'max_words' => 1500, 'required' => true],
                ['key' => 'abstract', 'heading' => 'Abstrak (ID/EN)', 'min_words' => 200, 'max_words' => 350, 'required' => true],
            ],
            stages: [
                ProjectStage::DRAFT->value => ['problem_statement', 'sota', 'literature_matrix', 'framework'],
                ProjectStage::PROPOSAL->value => ['chapter1', 'chapter2', 'chapter3'],
                ProjectStage::FINAL->value => ['chapter1', 'chapter2', 'chapter3', 'chapter4', 'chapter5', 'abstract'],
            ],
            notes: [
                'Mengikuti struktur standar DIKTI Bab 1-5.',
                'Tahap Final wajib menyertakan abstrak ID dan EN.',
            ],
        );
    }
}
