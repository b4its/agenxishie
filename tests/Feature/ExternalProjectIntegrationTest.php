<?php

declare(strict_types=1);

use App\Domain\Integration\ExternalClientManager;
use App\Filament\Pages\ExternalProjectsPage;
use App\Models\AcademicVenue;
use App\Models\ExternalClient;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    // Pastikan database siap untuk testing
});

it('creates an external client with unique slug and secure api key', function (): void {
    $manager = app(ExternalClientManager::class);

    $client = $manager->register(
        name: 'Agen Xishie Target',
        targetUrl: 'http://localhost:8208/',
        allowedModels: ['AcademicScribeAgent', 'RuleKeeperAgent'],
        description: 'Proyek target uji koneksi',
    );

    expect($client)->toBeInstanceOf(ExternalClient::class)
        ->and($client->client_slug)->toBe('agen-xishie-target')
        ->and($client->api_key)->toStartWith('xsk_')
        ->and($client->target_url)->toBe('http://localhost:8208/')
        ->and($client->is_active)->toBeTrue()
        ->and($client->isModelAllowed('AcademicScribeAgent'))->toBeTrue()
        ->and($client->isModelAllowed('VenueMatcherAgent'))->toBeFalse();
});

it('regenerates an api key for an existing client', function (): void {
    $client = ExternalClient::factory()->create(['api_key' => 'xsk_old_token_123']);
    $manager = app(ExternalClientManager::class);

    $newKey = $manager->regenerateApiKey($client);

    expect($newKey)->toStartWith('xsk_')
        ->and($newKey)->not->toBe('xsk_old_token_123')
        ->and($client->fresh()->api_key)->toBe($newKey);
});

it('authenticates api keys with or without bearer prefix', function (): void {
    $client = ExternalClient::factory()->create([
        'api_key' => 'xsk_secret_token_abc',
        'is_active' => true,
    ]);

    $inactive = ExternalClient::factory()->inactive()->create([
        'api_key' => 'xsk_secret_token_inactive',
    ]);

    $manager = app(ExternalClientManager::class);

    expect($manager->authenticateApiKey('xsk_secret_token_abc')?->id)->toBe($client->id);
    expect($manager->authenticateApiKey('Bearer xsk_secret_token_abc')?->id)->toBe($client->id);
    expect($manager->authenticateApiKey('xsk_secret_token_inactive'))->toBeNull();
    expect($manager->authenticateApiKey('invalid_token'))->toBeNull();
    expect($manager->authenticateApiKey(null))->toBeNull();
});

it('probes a target client url and records online status and latency', function (): void {
    Http::fake([
        'http://localhost:8208/*' => Http::response('OK', 200, ['Server' => 'nginx/1.31.4']),
    ]);

    $client = ExternalClient::factory()->create([
        'target_url' => 'http://localhost:8208/',
        'status' => 'unknown',
    ]);

    $manager = app(ExternalClientManager::class);
    $result = $manager->ping($client);

    expect($result['status'])->toBe('online')
        ->and($result['status_code'])->toBe(200)
        ->and($result['server'])->toBe('nginx/1.31.4')
        ->and($client->fresh()->status)->toBe('online')
        ->and($client->fresh()->last_status_code)->toBe(200);
});

it('records offline status when probe target connection fails', function (): void {
    Http::fake([
        'http://unreachable-project.test/*' => fn () => throw new Exception('Connection refused'),
    ]);

    $client = ExternalClient::factory()->create([
        'target_url' => 'http://unreachable-project.test/',
        'status' => 'unknown',
    ]);

    $manager = app(ExternalClientManager::class);
    $result = $manager->ping($client);

    expect($result['status'])->toBe('offline')
        ->and($result['status_code'])->toBeNull()
        ->and($result['error'])->toContain('Connection refused')
        ->and($client->fresh()->status)->toBe('offline');
});

it('records timeout status when probe target times out', function (): void {
    Http::fake([
        'http://timeout-project.test/*' => fn () => throw new Exception('cURL error 28: Operation timed out'),
    ]);

    $client = ExternalClient::factory()->create([
        'target_url' => 'http://timeout-project.test/',
        'status' => 'unknown',
    ]);

    $manager = app(ExternalClientManager::class);
    $result = $manager->ping($client);

    expect($result['status'])->toBe('timeout')
        ->and($client->fresh()->status)->toBe('timeout');
});

it('probes all active clients in registry', function (): void {
    Http::fake([
        '*' => Http::response('OK', 200),
    ]);

    ExternalClient::factory()->count(3)->create(['is_active' => true]);
    ExternalClient::factory()->inactive()->create();

    $manager = app(ExternalClientManager::class);
    $results = $manager->pingAll();

    expect($results)->toHaveCount(3);
});

it('executes chat and increments inbound hit counter', function (): void {
    Http::fake([
        '*/health' => Http::response('down', 503),
    ]);

    $client = ExternalClient::factory()->create([
        'allowed_models' => ['AcademicScribeAgent'],
        'inbound_requests_count' => 0,
    ]);

    $manager = app(ExternalClientManager::class);
    $result = $manager->handleChat($client, 'Bantu draf bagian metodologi penelitian kuantitatif.');

    expect($result['success'])->toBeTrue()
        ->and($result['model'])->toBe('AcademicScribeAgent')
        ->and($result['response'])->toBeString()
        ->and($client->fresh()->inbound_requests_count)->toBe(1)
        ->and($client->fresh()->last_inbound_endpoint)->toBe('/api/v1/external/chat');
});

it('forbids chat when model is not permitted for the client', function (): void {
    $client = ExternalClient::factory()->create([
        'allowed_models' => ['RuleKeeperAgent'],
    ]);

    $manager = app(ExternalClientManager::class);
    $manager->handleChat($client, 'Tulis naskah', 'AcademicScribeAgent');
})->throws(AuthorizationException::class);

it('executes manuscript audit and returns compliance checklist', function (): void {
    $client = ExternalClient::factory()->create(['allowed_models' => ['*']]);

    $text = "Introduction: Penelitian ini mengeksplorasi AI.\nMethodology: Menggunakan kuantitatif.\nResults: Ditemukan akurasi 95%.\nDiscussion: Hasil sejalan teori.\nReferences: [1] Doe, 2024.";

    $manager = app(ExternalClientManager::class);
    $audit = $manager->handleAudit($client, $text);

    expect($audit['success'])->toBeTrue()
        ->and($audit['checklist']['introduction'])->toBeTrue()
        ->and($audit['checklist']['methods'])->toBeTrue()
        ->and($audit['checklist']['results'])->toBeTrue()
        ->and($audit['checklist']['discussion'])->toBeTrue()
        ->and($audit['checklist']['references'])->toBeTrue()
        ->and($audit['compliance_score'])->toBe(100)
        ->and($client->fresh()->inbound_requests_count)->toBe(1);
});

it('matches academic venues for client', function (): void {
    AcademicVenue::factory()->create([
        'journal_name' => 'Jurnal Teknologi Informasi',
        'field_of_study' => 'Computer Science',
        'is_active' => true,
    ]);

    $client = ExternalClient::factory()->create(['allowed_models' => ['*']]);

    $manager = app(ExternalClientManager::class);
    $match = $manager->handleVenueMatch($client, 'Abstrak sistem cerdas rekomendasi', 'Computer Science');

    expect($match['success'])->toBeTrue()
        ->and($match['total_matches'])->toBeGreaterThanOrEqual(1)
        ->and($match['recommendations'][0]['journal_name'])->toBe('Jurnal Teknologi Informasi');
});

// ==========================================
// TEST INBOUND API ENDPOINTS (/api/v1/external/...)
// ==========================================

it('rejects unauthenticated requests to external api endpoints', function (): void {
    $this->getJson('/api/v1/external/ping')->assertStatus(401);
    $this->getJson('/api/v1/external/models')->assertStatus(401);
    $this->postJson('/api/v1/external/chat', ['prompt' => 'halo'])->assertStatus(401);
});

it('handles authenticated ping via X-API-Key and records inbound hit', function (): void {
    $client = ExternalClient::factory()->create([
        'api_key' => 'xsk_test_token_999',
        'inbound_requests_count' => 0,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'xsk_test_token_999',
    ])->getJson('/api/v1/external/ping');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'connected',
            'app' => 'xishie-academic-hub',
            'client_name' => $client->name,
        ]);

    expect($client->fresh()->inbound_requests_count)->toBe(1);
});

it('lists allowed models via external api endpoint', function (): void {
    $client = ExternalClient::factory()->create([
        'api_key' => 'xsk_models_test',
        'allowed_models' => ['AcademicScribeAgent', 'RuleKeeperAgent'],
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer xsk_models_test',
    ])->getJson('/api/v1/external/models');

    $response->assertStatus(200)
        ->assertJsonStructure(['client', 'total_models', 'models'])
        ->assertJson([
            'total_models' => 2,
        ]);
});

it('executes chat endpoint with validation and permissions', function (): void {
    Http::fake([
        '*/health' => Http::response('down', 503),
    ]);

    $client = ExternalClient::factory()->create([
        'api_key' => 'xsk_chat_test',
        'allowed_models' => ['AcademicScribeAgent'],
    ]);

    // Validasi input kosong
    $this->withHeaders(['X-API-Key' => 'xsk_chat_test'])
        ->postJson('/api/v1/external/chat', [])
        ->assertStatus(422);

    // Model tidak diizinkan
    $this->withHeaders(['X-API-Key' => 'xsk_chat_test'])
        ->postJson('/api/v1/external/chat', [
            'prompt' => 'Uji coba chat',
            'model' => 'VenueMatcherAgent',
        ])
        ->assertStatus(403);

    // Model diizinkan
    $response = $this->withHeaders(['X-API-Key' => 'xsk_chat_test'])
        ->postJson('/api/v1/external/chat', [
            'prompt' => 'Tuliskan kajian pustaka',
            'model' => 'AcademicScribeAgent',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'model' => 'AcademicScribeAgent',
        ]);
});

it('executes manuscript audit via api endpoint', function (): void {
    $client = ExternalClient::factory()->create([
        'api_key' => 'xsk_audit_test',
        'allowed_models' => ['*'],
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'xsk_audit_test'])
        ->postJson('/api/v1/external/audit', [
            'content' => 'Introduction of the paper. Methods and results are discussed. References included.',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);
});

it('executes venue matching via api endpoint', function (): void {
    AcademicVenue::factory()->create([
        'journal_name' => 'International Journal of AI',
        'is_active' => true,
    ]);

    $client = ExternalClient::factory()->create([
        'api_key' => 'xsk_venue_test',
        'allowed_models' => ['*'],
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'xsk_venue_test'])
        ->postJson('/api/v1/external/match-venue', [
            'abstract' => 'This study proposes novel machine learning architectures.',
        ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonStructure(['recommendations']);
});

// ==========================================
// TEST FILAMENT PAGE (ExternalProjectsPage)
// ==========================================

it('allows authenticated user to view Connected Projects page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/connected-projects')
        ->assertStatus(200);
});

it('seeds agen xishie at localhost:8208 directly from livewire page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Http::fake([
        'http://localhost:8208/*' => Http::response('OK', 200),
    ]);

    Livewire::test(ExternalProjectsPage::class)
        ->call('seedAgenXishie')
        ->assertSet('onlineClients', 1)
        ->assertSet('newApiKey', fn ($k) => is_string($k) && str_starts_with($k, 'xsk_'));

    $client = ExternalClient::where('target_url', 'http://localhost:8208/')->first();
    expect($client)->not->toBeNull()
        ->and($client->name)->toContain('Agen Xishie');
});

it('registers a new project and probes connectivity via Livewire page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Http::fake([
        'http://my-target-portal.test/*' => Http::response('OK', 200, ['Server' => 'nginx']),
    ]);

    Livewire::test(ExternalProjectsPage::class)
        ->call('openRegisterModal')
        ->assertSet('showRegisterModal', true)
        ->set('name', 'Portal Jurnal Univ')
        ->set('targetUrl', 'http://my-target-portal.test/')
        ->set('selectedModels', ['AcademicScribeAgent'])
        ->call('registerProject')
        ->assertSet('showRegisterModal', false)
        ->assertSet('newApiKey', fn ($k) => is_string($k) && str_starts_with($k, 'xsk_'))
        ->assertSet('totalClients', fn ($c) => $c >= 1);

    expect(ExternalClient::where('name', 'Portal Jurnal Univ')->exists())->toBeTrue();
});

it('supports pinging, regenerating key, toggling active, and deleting on Livewire page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Http::fake([
        '*/health' => Http::response('down', 503),
        '*' => Http::response('OK', 200),
    ]);

    $client = ExternalClient::factory()->create(['name' => 'Target Test Project', 'is_active' => true]);

    $component = Livewire::test(ExternalProjectsPage::class);

    // Ping single
    $component->call('pingClient', $client->id);
    expect($client->fresh()->status)->toBe('online');

    // Ping all
    $component->call('pingAll');

    // Regenerate key
    $oldKey = $client->api_key;
    $component->call('regenerateKey', $client->id);
    expect($client->fresh()->api_key)->not->toBe($oldKey);

    // Toggle active
    $component->call('toggleActive', $client->id);
    expect($client->fresh()->is_active)->toBeFalse();

    // Test Inbound
    $component->call('testInboundChat', $client->id);
    expect($component->get('testResult'))->not->toBeNull();

    // Delete client
    $component->call('deleteClient', $client->id);
    expect(ExternalClient::find($client->id))->toBeNull();
});

it('logs ping history and computes uptime and recent pings', function (): void {
    Http::fake([
        'http://uptime.test/*' => Http::response('OK', 200),
    ]);

    $client = ExternalClient::factory()->create(['target_url' => 'http://uptime.test/']);
    $manager = app(ExternalClientManager::class);

    $manager->ping($client);
    $manager->ping($client);

    expect($client->pingLogs()->count())->toBe(2)
        ->and($client->getUptimePercentage())->toBe(100.0)
        ->and($client->getRecentPings(5))->toHaveCount(2);
});

it('executes peer critic endpoint via external api', function (): void {
    $client = ExternalClient::factory()->create([
        'api_key' => 'xsk_critic_test',
        'allowed_models' => ['*'],
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'xsk_critic_test'])
        ->postJson('/api/v1/external/critic', [
            'text' => 'Pendahuluan paper ini menguji arsitektur baru. Metode penelitian menggunakan kuantitatif eksperimental dengan analisis regresi.',
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'score', 'verdict', 'strengths', 'recommendations'])
        ->assertJson(['success' => true]);
});

it('formats citations via external api endpoint', function (): void {
    $client = ExternalClient::factory()->create([
        'api_key' => 'xsk_citation_test',
        'allowed_models' => ['*'],
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'xsk_citation_test'])
        ->postJson('/api/v1/external/citation', [
            'style' => 'ieee',
            'references' => [
                [
                    'author' => 'Smith, J.',
                    'year' => '2025',
                    'title' => 'Deep Learning in Academia',
                    'journal' => 'IEEE Trans. AI',
                ],
            ],
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'style', 'bibliography'])
        ->assertJson([
            'success' => true,
            'style' => 'IEEE',
            'total_references' => 1,
        ]);
});

it('executes artisan ping-clients command successfully', function (): void {
    Http::fake([
        '*' => Http::response('OK', 200, ['Server' => 'nginx']),
    ]);

    ExternalClient::factory()->create(['target_url' => 'http://localhost:8208/', 'is_active' => true]);

    $this->artisan('xishie:ping-clients')
        ->assertExitCode(0)
        ->expectsOutputToContain('Probe selesai');
});

it('opens diagnostics and snippet modal on Livewire page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Http::fake([
        '*' => Http::response('OK', 200),
    ]);

    $client = ExternalClient::factory()->create(['name' => 'Snippet Test Client']);

    $component = Livewire::test(ExternalProjectsPage::class);

    // Diagnostics modal
    $component->call('showDiagnostics', $client->id)
        ->assertSet('clientDiagnostics', fn ($d) => is_array($d) && $d['id'] === $client->id)
        ->call('closeDiagnostics')
        ->assertSet('clientDiagnostics', null);

    // Snippet modal
    $component->call('showSnippet', $client->id)
        ->assertSet('clientSnippet', fn ($s) => is_array($s) && $s['client_name'] === 'Snippet Test Client')
        ->call('closeSnippet')
        ->assertSet('clientSnippet', null);
});

// ==========================================
// TEST STANDALONE PUBLIC WEB ROUTE (/connected-projects)
// ==========================================

it('renders public connected projects web page without authentication', function (): void {
    Http::fake([
        '*' => Http::response('OK', 200),
    ]);

    $response = $this->get('/connected-projects');

    $response->assertStatus(200)
        ->assertSee('HUB PROYEK TERHUBUNG')
        ->assertSee('http://localhost:8208/');
});

it('supports web actions: store, ping, ping-all, regen, toggle, test, destroy', function (): void {
    Http::fake([
        '*/health' => Http::response('down', 503),
        '*' => Http::response('OK', 200),
    ]);

    // Store
    $this->post('/connected-projects', [
        'name' => 'Web Registered Target',
        'target_url' => 'http://target-web.test/',
        'selected_models' => ['AcademicScribeAgent'],
        'description' => 'Uji form web',
    ])->assertRedirect(route('connected-projects.index'))
        ->assertSessionHas('new_api_key');

    $client = ExternalClient::where('name', 'Web Registered Target')->first();
    expect($client)->not->toBeNull();

    // Ping single
    $this->post("/connected-projects/{$client->id}/ping")
        ->assertRedirect(route('connected-projects.index'));

    // Ping all
    $this->post('/connected-projects/ping-all')
        ->assertRedirect(route('connected-projects.index'));

    // Regen key
    $oldKey = $client->api_key;
    $this->post("/connected-projects/{$client->id}/regenerate-key")
        ->assertRedirect(route('connected-projects.index'))
        ->assertSessionHas('new_api_key');
    expect($client->fresh()->api_key)->not->toBe($oldKey);

    // Toggle active
    $this->post("/connected-projects/{$client->id}/toggle")
        ->assertRedirect(route('connected-projects.index'));
    expect($client->fresh()->is_active)->toBeFalse();

    // Test Inbound
    $this->post("/connected-projects/{$client->id}/test-inbound")
        ->assertRedirect(route('connected-projects.index'))
        ->assertSessionHas('test_result');

    // Destroy
    $this->delete("/connected-projects/{$client->id}")
        ->assertRedirect(route('connected-projects.index'));
    expect(ExternalClient::find($client->id))->toBeNull();
});

it('redirects aliases /status, /integrations, /external-projects to /connected-projects', function (): void {
    $this->get('/status')->assertRedirect('/connected-projects');
    $this->get('/integrations')->assertRedirect('/connected-projects');
    $this->get('/external-projects')->assertRedirect('/connected-projects');
});

it('shows connected projects link on welcome page', function (): void {
    $this->get('/')
        ->assertStatus(200)
        ->assertSee('/connected-projects');
});
