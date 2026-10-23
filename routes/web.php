<?php

declare(strict_types=1);

use App\Http\Controllers\ExternalProjectsWebController;
use App\Http\Controllers\NaskahStudioWebController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/welcome', fn () => view('welcome'))->name('welcome');

// Naskah Studio Standalone (Akses Publik / Langsung di Luar Panel Admin)
Route::get('/studio/{document?}', [NaskahStudioWebController::class, 'index'])->name('studio.index');
Route::get('/naskah-studio/{document?}', fn (?int $document = null) => redirect()->route('studio.index', ['document' => $document]))->name('naskah.studio.alias');
Route::get('/studio/export/{document}/{format}', [NaskahStudioWebController::class, 'exportDirect'])->name('studio.export.direct');

// Halaman Manajemen & Monitoring Proyek Terhubung (Akses Publik / Langsung)
Route::get('/connected-projects', [ExternalProjectsWebController::class, 'index'])->name('connected-projects.index');
Route::post('/connected-projects', [ExternalProjectsWebController::class, 'store'])->name('connected-projects.store');
Route::post('/connected-projects/ping-all', [ExternalProjectsWebController::class, 'pingAll'])->name('connected-projects.ping-all');
Route::post('/connected-projects/{client}/ping', [ExternalProjectsWebController::class, 'ping'])->name('connected-projects.ping');
Route::post('/connected-projects/{client}/handshake', [ExternalProjectsWebController::class, 'handshake'])->name('connected-projects.handshake');
Route::post('/connected-projects/{client}/regenerate-key', [ExternalProjectsWebController::class, 'regenerateKey'])->name('connected-projects.regenerate-key');
Route::post('/connected-projects/{client}/toggle', [ExternalProjectsWebController::class, 'toggle'])->name('connected-projects.toggle');
Route::post('/connected-projects/{client}/test-inbound', [ExternalProjectsWebController::class, 'testInbound'])->name('connected-projects.test-inbound');
Route::post('/connected-projects/handshake-all', [ExternalProjectsWebController::class, 'handshakeAll'])->name('connected-projects.handshake-all');
Route::post('/connected-projects/{client}/test-webhook', [ExternalProjectsWebController::class, 'testWebhook'])->name('connected-projects.test-webhook');
Route::delete('/connected-projects/{client}', [ExternalProjectsWebController::class, 'destroy'])->name('connected-projects.destroy');

// Shortcut alias
Route::get('/status', fn () => redirect()->route('connected-projects.index'));
Route::get('/integrations', fn () => redirect()->route('connected-projects.index'));
Route::get('/external-projects', fn () => redirect()->route('connected-projects.index'));
