<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicVenue;
use App\Omnigensi\Memory\HybridVectorMatcherService;
use Illuminate\Database\Seeder;

class AcademicVenueSeeder extends Seeder
{
    public function run(): void
    {
        /** @var HybridVectorMatcherService $matcher */
        $matcher = app(HybridVectorMatcherService::class);

        foreach ($this->venues() as $venue) {
            $embedding = $matcher->embed($venue['journal_name'].' '.$venue['aims_and_scope']);

            AcademicVenue::updateOrCreate(
                ['journal_name' => $venue['journal_name']],
                [
                    'publisher' => $venue['publisher'],
                    'scope_type' => $venue['scope_type'],
                    'index_tier' => $venue['index_tier'],
                    'field_of_study' => $venue['field_of_study'],
                    'aims_and_scope' => $venue['aims_and_scope'],
                    'apc_estimate' => $venue['apc_estimate'],
                    'submission_url' => $venue['submission_url'],
                    'review_time_days' => $venue['review_time_days'],
                    'template_rules' => $venue['template_rules'],
                    'aims_scope_embedding' => $embedding,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function venues(): array
    {
        return [
            [
                'journal_name' => 'IEEE Access',
                'publisher' => 'IEEE',
                'scope_type' => 'scopus',
                'index_tier' => 'Q1',
                'field_of_study' => 'stem',
                'aims_and_scope' => 'Multidisciplinary open access journal covering computer science, engineering, artificial intelligence, machine learning, software engineering, and applied technology research.',
                'apc_estimate' => 1950.00,
                'submission_url' => 'https://ieeeaccess.ieee.org/',
                'review_time_days' => 60,
                'template_rules' => ['citation_style' => 'ieee', 'max_words' => 8000],
            ],
            [
                'journal_name' => 'Jurnal RESTI',
                'publisher' => 'IAES Indonesia',
                'scope_type' => 'sinta',
                'index_tier' => 'SINTA 2',
                'field_of_study' => 'stem',
                'aims_and_scope' => 'Jurnal rekayasa sistem dan teknologi informasi mencakup rekayasa perangkat lunak, sistem informasi, kecerdasan buatan, jaringan komputer, dan keamanan siber.',
                'apc_estimate' => 500000.00,
                'submission_url' => 'https://jurnal.iaii.or.id/index.php/RESTI',
                'review_time_days' => 45,
                'template_rules' => ['citation_style' => 'apa7', 'max_words' => 6000],
            ],
            [
                'journal_name' => 'Jurnal Ilmu Komputer dan Informasi',
                'publisher' => 'Universitas Indonesia',
                'scope_type' => 'sinta',
                'index_tier' => 'SINTA 2',
                'field_of_study' => 'stem',
                'aims_and_scope' => 'Publikasi penelitian bidang ilmu komputer, kecerdasan artifisial, pengolahan citra, sistem cerdas, dan teknologi informasi.',
                'apc_estimate' => 0.00,
                'submission_url' => 'https://jiki.cs.ui.ac.id/index.php/jiki',
                'review_time_days' => 30,
                'template_rules' => ['citation_style' => 'apa7', 'max_words' => 7000],
            ],
            [
                'journal_name' => 'Indonesian Journal of Electrical Engineering and Computer Science',
                'publisher' => 'IAES Indonesia',
                'scope_type' => 'scopus',
                'index_tier' => 'Q3',
                'field_of_study' => 'stem',
                'aims_and_scope' => 'Electrical engineering, computer science, power systems, control, telecommunications, and computing technology research.',
                'apc_estimate' => 400.00,
                'submission_url' => 'https://ijeecs.iaescore.com/',
                'review_time_days' => 50,
                'template_rules' => ['citation_style' => 'ieee', 'max_words' => 7000],
            ],
            [
                'journal_name' => 'Jurnal Kesehatan Masyarakat',
                'publisher' => 'Universitas Diponegoro',
                'scope_type' => 'sinta',
                'index_tier' => 'SINTA 2',
                'field_of_study' => 'health',
                'aims_and_scope' => 'Kesehatan masyarakat, epidemiologi, gizi, kesehatan lingkungan, promosi kesehatan, dan kebijakan kesehatan publik.',
                'apc_estimate' => 300000.00,
                'submission_url' => 'https://ejournal.undip.ac.id/index.php/jkm',
                'review_time_days' => 40,
                'template_rules' => ['citation_style' => 'vancouver', 'max_words' => 6000],
            ],
            [
                'journal_name' => 'Jurnal Ners',
                'publisher' => 'Universitas Airlangga',
                'scope_type' => 'scopus',
                'index_tier' => 'Q3',
                'field_of_study' => 'health',
                'aims_and_scope' => 'Nursing science, clinical nursing, community health nursing, and evidence-based healthcare practice.',
                'apc_estimate' => 250.00,
                'submission_url' => 'https://e-journal.unair.ac.id/JNERS',
                'review_time_days' => 55,
                'template_rules' => ['citation_style' => 'vancouver', 'max_words' => 5000],
            ],
            [
                'journal_name' => 'Jurnal Sosial Humaniora',
                'publisher' => 'Institut Teknologi Sepuluh Nopember',
                'scope_type' => 'sinta',
                'index_tier' => 'SINTA 3',
                'field_of_study' => 'social_humanities',
                'aims_and_scope' => 'Kajian sosial, budaya, humaniora, sosiologi, antropologi, dan studi interdisipliner masyarakat.',
                'apc_estimate' => 200000.00,
                'submission_url' => 'https://iptek.its.ac.id/index.php/jsh',
                'review_time_days' => 35,
                'template_rules' => ['citation_style' => 'apa7', 'max_words' => 6000],
            ],
            [
                'journal_name' => 'Jurnal Ekonomi dan Bisnis Indonesia',
                'publisher' => 'Universitas Gadjah Mada',
                'scope_type' => 'sinta',
                'index_tier' => 'SINTA 2',
                'field_of_study' => 'economics_business',
                'aims_and_scope' => 'Ekonomi, manajemen, akuntansi, keuangan, pemasaran, dan bisnis dengan analisis kuantitatif maupun kualitatif.',
                'apc_estimate' => 350000.00,
                'submission_url' => 'https://jurnal.ugm.ac.id/jebi',
                'review_time_days' => 45,
                'template_rules' => ['citation_style' => 'apa7', 'max_words' => 7000],
            ],
            [
                'journal_name' => 'Jurnal Hukum dan Peradilan',
                'publisher' => 'Mahkamah Agung RI',
                'scope_type' => 'sinta',
                'index_tier' => 'SINTA 2',
                'field_of_study' => 'law_policy',
                'aims_and_scope' => 'Ilmu hukum, peradilan, kebijakan publik, hukum tata negara, hukum pidana, dan kajian yuridis normatif.',
                'apc_estimate' => 0.00,
                'submission_url' => 'https://jurnal.mahkamahagung.go.id/index.php/jhp',
                'review_time_days' => 40,
                'template_rules' => ['citation_style' => 'chicago', 'max_words' => 9000],
            ],
            [
                'journal_name' => 'Jurnal Pendidikan Indonesia',
                'publisher' => 'Universitas Pendidikan Indonesia',
                'scope_type' => 'sinta',
                'index_tier' => 'SINTA 3',
                'field_of_study' => 'education',
                'aims_and_scope' => 'Pendidikan, kurikulum, pembelajaran, evaluasi pendidikan, teknologi pembelajaran, dan penelitian tindakan kelas.',
                'apc_estimate' => 250000.00,
                'submission_url' => 'https://jpi.upi.edu/index.php/jpi',
                'review_time_days' => 30,
                'template_rules' => ['citation_style' => 'apa7', 'max_words' => 6000],
            ],
            [
                'journal_name' => 'International Journal of Instruction',
                'publisher' => 'Gate Association for Teaching and Education',
                'scope_type' => 'scopus',
                'index_tier' => 'Q1',
                'field_of_study' => 'education',
                'aims_and_scope' => 'Instructional design, learning theory, curriculum development, educational technology, and teaching effectiveness research.',
                'apc_estimate' => 500.00,
                'submission_url' => 'https://www.e-iji.net/',
                'review_time_days' => 70,
                'template_rules' => ['citation_style' => 'apa7', 'max_words' => 8000],
            ],
            [
                'journal_name' => 'International Journal of Multidisciplinary Research',
                'publisher' => 'Global Academic Press',
                'scope_type' => 'wos',
                'index_tier' => 'WoS',
                'field_of_study' => 'multidisciplinary',
                'aims_and_scope' => 'Multidisciplinary research across science, social science, humanities, and applied interdisciplinary studies.',
                'apc_estimate' => 600.00,
                'submission_url' => 'https://example.org/ijmr',
                'review_time_days' => 65,
                'template_rules' => ['citation_style' => 'apa7', 'max_words' => 7500],
            ],
        ];
    }
}
