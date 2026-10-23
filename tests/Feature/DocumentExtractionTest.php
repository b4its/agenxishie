<?php

declare(strict_types=1);

use App\Domain\GuidelineEngine\CustomGuidelineParser;
use App\Domain\GuidelineEngine\GuidelinePresetFactory;
use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Personalization\Enums\FieldOfStudy;
use App\Domain\Personalization\Enums\PublicationTarget;
use App\Domain\Revision\Enums\RevisionStatus;
use App\Domain\Shared\DocumentTextExtractor;
use App\Domain\Workspace\Enums\ControlMode;
use App\Filament\Pages\ProjectOnboardingWizard;
use App\Filament\Pages\SmartRevisionCenterPage;
use App\Models\ProjectRevision;
use App\Models\User;
use App\Models\XishieProject;
use Illuminate\Support\Facades\Storage;

/**
 * Perbaikan & maksimalisasi fitur: ekstraksi dokumen bersama, parsing
 * pedoman custom otomatis, dan aksi revisi massal.
 */
function makeDocx(array $paragraphs): string
{
    $body = '';

    foreach ($paragraphs as $para) {
        $body .= '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($para, ENT_QUOTES | ENT_XML1).'</w:t></w:r></w:p>';
    }

    $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body>'.$body.'</w:body></w:document>';

    $tmp = tempnam(sys_get_temp_dir(), 'xishie-tdocx-');
    $zip = new ZipArchive;
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->close();

    $binary = (string) file_get_contents($tmp);
    @unlink($tmp);

    return $binary;
}

it('extracts text from a DOCX archive', function (): void {
    $docx = makeDocx(['BAB I PENDAHULUAN', 'BAB II TINJAUAN PUSTAKA']);

    $text = app(DocumentTextExtractor::class)->extract($docx);

    expect($text)->toContain('BAB I PENDAHULUAN')
        ->and($text)->toContain('BAB II TINJAUAN PUSTAKA');
});

it('extracts plain text and strips control characters', function (): void {
    $text = app(DocumentTextExtractor::class)->extract("Hello\x00\x01 World");

    expect($text)->toContain('Hello')->toContain('World');
});

it('parses a custom guideline document into structured rules', function (): void {
    $fallback = app(GuidelinePresetFactory::class)->forDocumentType(DocumentType::SKRIPSI, 'apa7');

    $text = "BAB I PENDAHULUAN\nBAB II TINJAUAN PUSTAKA\nBAB III METODOLOGI\nBAB IV HASIL\nBAB V KESIMPULAN\n"
        .'Total 8000 - 15000 kata. Format sitasi APA.';

    $definition = app(CustomGuidelineParser::class)->parse($text, $fallback);
    $array = $definition->toArray();

    expect($array['source'])->toBe('custom_upload')
        ->and($array['citation_style'])->toBe('apa7')
        ->and($array['total_word_range']['min'])->toBe(8000)
        ->and($array['total_word_range']['max'])->toBe(15000)
        ->and(count($array['sections']))->toBeGreaterThanOrEqual(5);
});

it('persists a custom guideline uploaded through the onboarding wizard', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $docx = makeDocx([
        'BAB I PENDAHULUAN',
        'BAB II TINJAUAN PUSTAKA',
        'BAB III METODOLOGI',
    ]);

    Storage::fake('local');
    Storage::disk('local')->put('guidelines/pedoman.docx', $docx);

    Livewire\Livewire::test(ProjectOnboardingWizard::class)
        ->fillForm([
            'title' => 'Skripsi Uji Pedoman Custom',
            'field_of_study' => FieldOfStudy::STEM->value,
            'document_type' => DocumentType::SKRIPSI->value,
            'target_scope' => PublicationTarget::CAMPUS_REPOSITORY->value,
            'target_tier' => 'Repository Kampus',
            'guideline_type' => 'custom',
            'citation_style' => 'apa7',
            'control_mode' => ControlMode::MANUAL->value,
            'acknowledge_control_mode' => true,
            'custom_guideline_path' => 'guidelines/pedoman.docx',
        ])
        ->set('step', 4)
        ->call('createProject')
        ->assertHasNoFormErrors();

    $project = XishieProject::query()->where('title', 'Skripsi Uji Pedoman Custom')->first();

    expect($project)->not->toBeNull()
        ->and($project->guideline_type)->toBe('custom')
        ->and($project->guideline_rules)->toBeArray()
        ->and($project->guideline_rules['source'])->toBe('custom_upload');
});

it('marks every revision item resolved in bulk', function (): void {
    $project = makeProject();

    $revision = ProjectRevision::create([
        'project_id' => $project->id,
        'reviewer_role' => 'pembimbing_1',
        'raw_feedback' => 'Perbaiki semuanya.',
        'action_items' => [
            ['id' => 1, 'task' => 'A', 'section_key' => 'chapter1', 'status' => 'pending'],
            ['id' => 2, 'task' => 'B', 'section_key' => 'chapter2', 'status' => 'pending'],
        ],
        'response_matrix_doc' => 'x',
        'status' => RevisionStatus::PENDING,
    ]);

    Livewire\Livewire::test(SmartRevisionCenterPage::class)
        ->call('markAllResolved', $revision->id)
        ->assertSuccessful();

    $fresh = $revision->fresh();

    expect($fresh->resolutionPercentage())->toBe(100.0)
        ->and($fresh->status)->toBe(RevisionStatus::RESOLVED);
});

it('accepts a FileUpload array value for custom_guideline_path without crashing', function (): void {
    // Regresi: Filament FileUpload mengembalikan array; kolom DB bertipe string
    // sehingga array mentah memicu "Array to string conversion".
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test(ProjectOnboardingWizard::class)
        ->fillForm([
            'title' => 'Proyek Path Array',
            'field_of_study' => FieldOfStudy::STEM->value,
            'document_type' => DocumentType::JOURNAL->value,
            'target_scope' => PublicationTarget::NATIONAL_SINTA->value,
            'control_mode' => ControlMode::MANUAL->value,
            'acknowledge_control_mode' => true,
        ])
        ->set('step', 4)
        ->set('data.custom_guideline_path', ['guidelines/a.docx'])
        ->call('createProject')
        ->assertHasNoFormErrors();

    $project = XishieProject::query()->where('title', 'Proyek Path Array')->first();

    expect($project)->not->toBeNull()
        ->and($project->custom_guideline_path)->toBe('guidelines/a.docx');
});
