<?php

declare(strict_types=1);

use App\Domain\Assistant\OmnigensiChatClient;
use App\Filament\Pages\OmnigensiMonitoringPage;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Integrasi platform Omnigensi (Connected Projects & Integration Hub):
 * API key handshake/models/completions + monitoring operator.
 */
function integrationClient(string $apiKey = 'omni_live_test'): OmnigensiChatClient
{
    return new OmnigensiChatClient('http://omnigensi.test', '123', 30, 5, true, $apiKey);
}

it('reports whether an integration API key is configured', function (): void {
    expect(integrationClient('omni_live_x')->hasIntegrationKey())->toBeTrue()
        ->and(integrationClient('')->hasIntegrationKey())->toBeFalse();
});

it('performs an API-key handshake and returns available models', function (): void {
    Http::fake([
        '*/api/v1/integrations/v1/handshake' => Http::response([
            'status' => 'connected',
            'client_id' => 'c1',
            'client_name' => 'agen xishie',
            'target_url' => 'http://localhost:8208/',
            'omnigensi_version' => '1.0.0',
            'allowed_models' => ['*'],
            'available_models' => [
                ['provider' => '9router', 'model_id' => 'deepseek', 'display_name' => 'DeepSeek', 'is_default' => true],
                ['provider' => '9router', 'model_id' => 'claude', 'display_name' => 'Claude', 'is_default' => false],
            ],
            'timestamp' => now()->toIso8601String(),
        ], 200),
    ]);

    $handshake = integrationClient()->handshake();

    expect($handshake['status'])->toBe('connected')
        ->and($handshake['client_name'])->toBe('agen xishie')
        ->and($handshake['available_models'])->toHaveCount(2);

    // API key sent as X-API-Key header.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/integrations/v1/handshake')
        && $request->hasHeader('X-API-Key', 'omni_live_test'));
});

it('lists integration models in the OpenAI-compatible shape', function (): void {
    Http::fake([
        '*/api/v1/integrations/v1/models' => Http::response([
            'object' => 'list',
            'data' => [
                ['id' => 'deepseek', 'display_name' => 'DeepSeek', 'provider' => '9router', 'provider_id' => 'p1', 'context_length' => 128000, 'is_default' => true],
            ],
        ], 200),
    ]);

    $models = integrationClient()->integrationModels();

    expect($models)->toHaveCount(1)
        ->and($models[0]['model_id'])->toBe('deepseek')
        ->and($models[0]['provider_name'])->toBe('9router')
        ->and($models[0]['context_length'])->toBe(128000);
});

it('completes a chat via the integration endpoint (OpenAI-compatible)', function (): void {
    Http::fake([
        '*/api/v1/integrations/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl-1',
            'object' => 'chat.completion',
            'model' => 'deepseek',
            'client' => 'agen xishie',
            'choices' => [
                ['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'PONG'], 'finish_reason' => 'stop'],
            ],
            'latency_ms' => 42,
        ], 200),
    ]);

    $result = integrationClient()->integrationComplete('deepseek', [['role' => 'user', 'content' => 'ping']]);

    expect($result['content'])->toBe('PONG')
        ->and($result['model_id'])->toBe('deepseek')
        ->and($result['latency_ms'])->toBe(42);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/integrations/v1/chat/completions')
        && $request->hasHeader('X-API-Key', 'omni_live_test')
        && ($request['stream'] ?? null) === false);
});

it('throws when the integration completion is rejected', function (): void {
    Http::fake([
        '*/api/v1/integrations/v1/chat/completions' => Http::response(['detail' => 'Client not authorized'], 403),
    ]);

    integrationClient()->integrationComplete('forbidden', [['role' => 'user', 'content' => 'hi']]);
})->throws(RuntimeException::class, 'not authorized');

it('lists connected projects for monitoring via the operator token', function (): void {
    Http::fake([
        '*/api/v1/auth/login' => Http::response(['token' => 'tok'], 200),
        '*/api/v1/integrations/clients' => Http::response([
            ['id' => 'c1', 'name' => 'agen xishie', 'target_url' => 'http://localhost:8208/', 'status' => 'online', 'last_status_code' => 200, 'last_latency_ms' => 77, 'inbound_requests_count' => 5, 'target_server' => 'nginx/1.31.4'],
        ], 200),
    ]);

    $clients = integrationClient()->integrationClients();

    expect($clients)->toHaveCount(1)
        ->and($clients[0]['status'])->toBe('online')
        ->and($clients[0]['inbound_requests_count'])->toBe(5);
});

it('pings a connected project and pings all', function (): void {
    Http::fake([
        '*/api/v1/auth/login' => Http::response(['token' => 'tok'], 200),
        '*/api/v1/integrations/clients/c1/ping' => Http::response(['client_id' => 'c1', 'name' => 'agen xishie', 'status' => 'online', 'status_code' => 200, 'latency_ms' => 77], 200),
        '*/api/v1/integrations/ping-all' => Http::response([
            ['client_id' => 'c1', 'status' => 'online', 'status_code' => 200, 'latency_ms' => 70],
        ], 200),
    ]);

    $client = integrationClient();

    expect($client->pingClient('c1')['status'])->toBe('online');
    expect($client->pingAll())->toHaveCount(1);
});

it('renders the monitoring page with connected projects', function (): void {
    $this->actingAs(User::factory()->create());

    Http::fake([
        '*/health' => Http::response(['status' => 'ok'], 200),
        '*/api/v1/auth/login' => Http::response(['token' => 'tok'], 200),
        '*/api/v1/integrations/clients' => Http::response([
            ['id' => 'c1', 'name' => 'agen xishie', 'target_url' => 'http://localhost:8208/', 'status' => 'online', 'last_status_code' => 200, 'last_latency_ms' => 77, 'inbound_requests_count' => 5, 'target_server' => 'nginx'],
        ], 200),
        '*/api/v1/integrations/v1/handshake' => Http::response([
            'status' => 'connected', 'client_name' => 'agen xishie', 'target_url' => 'http://localhost:8208/',
            'omnigensi_version' => '1.0.0', 'allowed_models' => ['*'],
            'available_models' => [['provider' => '9router', 'model_id' => 'deepseek']],
        ], 200),
    ]);

    Livewire\Livewire::test(OmnigensiMonitoringPage::class)
        ->assertSuccessful()
        ->assertSet('backendOnline', true)
        ->assertSet('clients', fn ($c) => is_array($c) && count($c) === 1);
});

it('shows the monitoring page offline state when backend is down', function (): void {
    $this->actingAs(User::factory()->create());

    Http::fake(['*/health' => Http::response('down', 503)]);

    Livewire\Livewire::test(OmnigensiMonitoringPage::class)
        ->assertSuccessful()
        ->assertSet('backendOnline', false);
});
