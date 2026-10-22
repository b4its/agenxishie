<?php

use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Personalization\Enums\FieldOfStudy;
use App\Domain\Personalization\Enums\PublicationTarget;
use App\Domain\Personalization\ProjectOnboardingService;
use App\Domain\Workspace\Enums\ControlMode;
use App\Models\User;
use App\Models\XishieProject;
use App\Omnigensi\Agents\ScholarScoutAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Pengujian dijalankan di atas MySQL testing (InnoDB + JSON + FULLTEXT).
| RefreshDatabase memastikan setiap test mendapat skema yang bersih.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Buat proyek via ProjectOnboardingService (jalur yang sama dengan UI).
 *
 * @param  array<string, mixed>  $overrides
 */
function makeProject(array $overrides = []): XishieProject
{
    $user = User::factory()->create();

    return app(ProjectOnboardingService::class)->create($user->id, array_merge([
        'title' => 'Sistem Deteksi Anomali Jaringan',
        'field_of_study' => FieldOfStudy::STEM,
        'document_type' => DocumentType::JOURNAL,
        'target_scope' => PublicationTarget::INTERNATIONAL_SCOPUS,
        'target_tier' => 'Q2',
        'control_mode' => ControlMode::AUTOMATION,
    ], $overrides));
}

/**
 * Seed beberapa referensi terverifikasi untuk proyek.
 *
 * @param  array<int, array<string, mixed>>  $candidates
 */
function seedReferences(XishieProject $project, array $candidates = []): void
{
    $candidates = $candidates !== [] ? $candidates : [
        [
            'title' => 'Anomaly Detection in Network Traffic Using Deep Autoencoders',
            'abstract' => 'Deep autoencoder for network anomaly detection with high precision.',
            'authors' => ['Prasetyo', 'Hidayat'],
            'year' => 2023,
            'method' => 'autoencoder',
            'result' => 'F1 0.94',
            'gap' => 'limited realtime',
        ],
        [
            'title' => 'Machine Learning for Intrusion Detection Systems',
            'abstract' => 'Survey of machine learning intrusion detection datasets.',
            'authors' => ['Kumar', 'Singh'],
            'year' => 2022,
            'method' => 'survey',
            'result' => 'taxonomy',
            'gap' => 'no deployment',
        ],
    ];

    app(ScholarScoutAgent::class)->ingestCandidates($project, $candidates);
}
