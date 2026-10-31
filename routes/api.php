<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ExternalIntegrationApiController;
use App\Http\Controllers\Api\NaskahStudioApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External Modular API Routes
|--------------------------------------------------------------------------
|
| Rute API modular yang memungkinkan proyek eksternal, website lain, atau
| instance agen xishie (misal di http://localhost:8208/) untuk mengonsumsi
| model, inferensi chat, audit kepatuhan, serta rekomendasi jurnal Xishie.
|
*/

Route::prefix('v1/external')->group(function (): void {
    Route::post('/register', [ExternalIntegrationApiController::class, 'selfRegister'])->name('api.v1.external.register');
    Route::get('/ping', [ExternalIntegrationApiController::class, 'ping'])->name('api.v1.external.ping');
    Route::get('/status/{slug}', [ExternalIntegrationApiController::class, 'clientStatus'])->name('api.v1.external.client-status');
    Route::get('/models', [ExternalIntegrationApiController::class, 'models'])->name('api.v1.external.models');
    Route::post('/chat', [ExternalIntegrationApiController::class, 'chat'])->name('api.v1.external.chat');
    Route::post('/audit', [ExternalIntegrationApiController::class, 'audit'])->name('api.v1.external.audit');
    Route::post('/critic', [ExternalIntegrationApiController::class, 'critic'])->name('api.v1.external.critic');
    Route::post('/citation', [ExternalIntegrationApiController::class, 'citation'])->name('api.v1.external.citation');
    Route::post('/export', [ExternalIntegrationApiController::class, 'export'])->name('api.v1.external.export');
    Route::post('/references/parse-bibtex', [ExternalIntegrationApiController::class, 'parseBibtex'])->name('api.v1.external.references.parse-bibtex');
    Route::post('/format-paragraph', [ExternalIntegrationApiController::class, 'formatParagraph'])->name('api.v1.external.format-paragraph');
    Route::post('/readability', [ExternalIntegrationApiController::class, 'readability'])->name('api.v1.external.readability');
    Route::post('/webhook/test', [ExternalIntegrationApiController::class, 'webhookTest'])->name('api.v1.external.webhook.test');
    Route::post('/match-venue', [ExternalIntegrationApiController::class, 'matchVenue'])->name('api.v1.external.match-venue');
});

Route::prefix('v1/naskah')->group(function (): void {
    Route::get('/documents', [NaskahStudioApiController::class, 'index'])->name('api.v1.naskah.index');
    Route::get('/models', [NaskahStudioApiController::class, 'models'])->name('api.v1.naskah.models');
    Route::get('/documents/{id}', [NaskahStudioApiController::class, 'show'])->name('api.v1.naskah.show');
    Route::post('/documents/{id}/autocorrect', [NaskahStudioApiController::class, 'autoCorrect'])->name('api.v1.naskah.autocorrect');
    Route::get('/documents/{id}/export', [NaskahStudioApiController::class, 'export'])->name('api.v1.naskah.export');
    Route::get('/documents/{id}/export-page', [NaskahStudioApiController::class, 'exportPage'])->name('api.v1.naskah.export-page');
    Route::post('/documents/{id}/audit-page', [NaskahStudioApiController::class, 'auditPage'])->name('api.v1.naskah.audit-page');
    Route::post('/documents/{id}/clean-page', [NaskahStudioApiController::class, 'cleanPage'])->name('api.v1.naskah.clean-page');
    Route::post('/documents/{id}/format-paragraph', [NaskahStudioApiController::class, 'formatParagraph'])->name('api.v1.naskah.format-paragraph');
    Route::get('/documents/{id}/readability-page', [NaskahStudioApiController::class, 'readabilityPage'])->name('api.v1.naskah.readability-page');
    Route::get('/documents/{id}/statistics', [NaskahStudioApiController::class, 'statistics'])->name('api.v1.naskah.statistics');
    Route::get('/documents/{id}/outline', [NaskahStudioApiController::class, 'outline'])->name('api.v1.naskah.outline');
    Route::get('/documents/{id}/comments', [NaskahStudioApiController::class, 'comments'])->name('api.v1.naskah.comments');
    Route::post('/documents/{id}/comments', [NaskahStudioApiController::class, 'addComment'])->name('api.v1.naskah.add-comment');
    Route::post('/documents/{id}/comments/{commentId}/resolve', [NaskahStudioApiController::class, 'resolveComment'])->name('api.v1.naskah.resolve-comment');
    Route::get('/documents/{id}/revisions', [NaskahStudioApiController::class, 'revisions'])->name('api.v1.naskah.revisions');
    Route::post('/documents/{id}/revisions/{revisionId}', [NaskahStudioApiController::class, 'handleRevision'])->name('api.v1.naskah.handle-revision');
    Route::get('/documents/{id}/snapshots', [NaskahStudioApiController::class, 'snapshots'])->name('api.v1.naskah.snapshots');
    Route::post('/documents/{id}/snapshots', [NaskahStudioApiController::class, 'createSnapshot'])->name('api.v1.naskah.create-snapshot');
    Route::post('/documents/{id}/snapshots/{snapshotId}/restore', [NaskahStudioApiController::class, 'restoreSnapshot'])->name('api.v1.naskah.restore-snapshot');
    Route::post('/documents/{id}/find-replace', [NaskahStudioApiController::class, 'findReplace'])->name('api.v1.naskah.find-replace');
    Route::post('/documents/{id}/align-page', [NaskahStudioApiController::class, 'alignPage'])->name('api.v1.naskah.align-page');
    Route::match(['get', 'post'], '/documents/{id}/audit-citations', [NaskahStudioApiController::class, 'auditCitations'])->name('api.v1.naskah.audit-citations');
    Route::post('/documents/{id}/execute-model', [NaskahStudioApiController::class, 'executeModel'])->name('api.v1.naskah.execute-model');
});
