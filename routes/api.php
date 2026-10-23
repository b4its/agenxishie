<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ExternalIntegrationApiController;
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
    Route::get('/ping', [ExternalIntegrationApiController::class, 'ping'])->name('api.v1.external.ping');
    Route::get('/models', [ExternalIntegrationApiController::class, 'models'])->name('api.v1.external.models');
    Route::post('/chat', [ExternalIntegrationApiController::class, 'chat'])->name('api.v1.external.chat');
    Route::post('/audit', [ExternalIntegrationApiController::class, 'audit'])->name('api.v1.external.audit');
    Route::post('/match-venue', [ExternalIntegrationApiController::class, 'matchVenue'])->name('api.v1.external.match-venue');
});
