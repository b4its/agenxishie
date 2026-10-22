<?php

declare(strict_types=1);

namespace App\Domain\Personalization\Enums;

enum FieldOfStudy: string
{
    case STEM = 'stem';
    case HEALTH = 'health';
    case SOCIAL_HUMANITIES = 'social_humanities';
    case ECONOMICS_BUSINESS = 'economics_business';
    case LAW_POLICY = 'law_policy';
    case EDUCATION = 'education';
    case MULTIDISCIPLINARY = 'multidisciplinary';

    public function label(): string
    {
        return match ($this) {
            self::STEM => 'STEM & Computer Science',
            self::HEALTH => 'Kesehatan & Medis',
            self::SOCIAL_HUMANITIES => 'Sosial & Humaniora',
            self::ECONOMICS_BUSINESS => 'Ekonomi & Bisnis',
            self::LAW_POLICY => 'Hukum & Kebijakan',
            self::EDUCATION => 'Pendidikan',
            self::MULTIDISCIPLINARY => 'Multi-disiplin',
        };
    }

    /**
     * Nada akademik bawaan untuk bidang keilmuan.
     */
    public function academicTone(): string
    {
        return match ($this) {
            self::STEM => 'objektif, kuantitatif, berbasis eksperimen dan metrik terukur',
            self::HEALTH => 'empiris, klinis, berbasis bukti (evidence-based) dan etika medis',
            self::SOCIAL_HUMANITIES => 'interpretatif, kritis, dan reflektif terhadap fenomena sosial',
            self::ECONOMICS_BUSINESS => 'analitis, berorientasi data pasar dan implikasi kebijakan bisnis',
            self::LAW_POLICY => 'normatif, doktrinal, dan argumentatif berbasis regulasi',
            self::EDUCATION => 'pedagogis, evaluatif, dan berorientasi praktik pembelajaran',
            self::MULTIDISCIPLINARY => 'interdisipliner dengan sintesis lintas bidang keilmuan',
        };
    }

    /**
     * Metodologi riset bawaan yang disarankan.
     */
    public function defaultMethodology(): string
    {
        return match ($this) {
            self::STEM => 'eksperimen terkontrol / rekayasa perangkat lunak / benchmark kuantitatif',
            self::HEALTH => 'studi observasional, uji klinis, atau systematic review (PRISMA)',
            self::SOCIAL_HUMANITIES => 'kualitatif (wawancara mendalam, studi kasus, analisis wacana)',
            self::ECONOMICS_BUSINESS => 'kuantitatif (regresi, ekonometrika, analisis pasar)',
            self::LAW_POLICY => 'yuridis-normatif dan analisis kebijakan komparatif',
            self::EDUCATION => 'penelitian tindakan kelas atau kuasi-eksperimen pendidikan',
            self::MULTIDISCIPLINARY => 'mixed-methods dengan triangulasi data',
        };
    }

    /**
     * Sumber database literatur utama.
     *
     * @return array<int, string>
     */
    public function literatureSources(): array
    {
        return match ($this) {
            self::STEM => ['IEEE Xplore', 'ACM Digital Library', 'Scopus', 'OpenAlex'],
            self::HEALTH => ['PubMed', 'Scopus', 'Crossref', 'OpenAlex'],
            self::SOCIAL_HUMANITIES => ['Scopus', 'Garuda', 'DOAJ', 'OpenAlex'],
            self::ECONOMICS_BUSINESS => ['Scopus', 'Garuda', 'DOAJ', 'Crossref'],
            self::LAW_POLICY => ['Garuda', 'Scopus', 'DOAJ', 'Crossref'],
            self::EDUCATION => ['Garuda', 'DOAJ', 'Scopus', 'Crossref'],
            self::MULTIDISCIPLINARY => ['OpenAlex', 'Scopus', 'Crossref', 'Garuda'],
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
