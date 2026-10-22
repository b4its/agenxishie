<?php

declare(strict_types=1);

use App\Domain\Assistant\OmnigensiChatClient;
use App\Filament\Pages\AiChatPage;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * AI Chat — integrasi ke backend Omnigensi (/auth/login, /chat/models,
 * /chat/completions). Semua HTTP di-fake agar deterministik.
 */
function chatClient(): OmnigensiChatClient
{
    return new OmnigensiChatClient('http://omnigensi.test', '123', 30, 5, true);
}

it('reports availability from the health endpoint', function (): void {
    Http::fake(['*/health' => Http::response(['status' => 'ok'], 200)]);

    expect(chatClient()->isAvailable())->toBeTrue();
});

it('reports unavailability when health fails', function (): void {
    Http::fake(['*/health' => Http::response('down', 500)]);

    expect(chatClient()->isAvailable())->toBeFalse();
});

it('exchanges the operator password for a token then lists models', function (): void {
    Http::fake([
        '*/api/v1/auth/login' => Http::response(['token' => 'tok-abc'], 200),
        '*/api/v1/chat/models' => Http::response([
            'models' => [
                ['provider_id' => 'p1', 'provider_name' => '9router', 'model_id' => 'deepseek', 'display_name' => 'DeepSeek', 'context_length' => 128000, 'is_default' => true],
                ['provider_id' => 'p1', 'provider_name' => '9router', 'model_id' => 'claude', 'display_name' => 'Claude', 'is_default' => false],
            ],
        ], 200),
    ]);

    $models = chatClient()->models();

    expect($models)->toHaveCount(2)
        ->and($models[0]['model_id'])->toBe('deepseek')
        ->and($models[0]['is_default'])->toBeTrue()
        ->and($models[1]['display_name'])->toBe('Claude');

    // Bearer token from login is sent on the models request.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/chat/models')
        && $request->hasHeader('Authorization', 'Bearer tok-abc'));
});

it('rejects when login fails', function (): void {
    Http::fake(['*/api/v1/auth/login' => Http::response(['detail' => 'bad'], 401)]);

    chatClient()->models();
})->throws(RuntimeException::class);

it('stitches SSE tokens into the final assistant reply', function (): void {
    $sse = <<<'SSE'
event: start
data: {"provider_id":"p1","provider_name":"9router","model_id":"deepseek"}

event: token
data: {"text":"Hello"}

event: token
data: {"text":", world"}

event: done
data: {"content":"Hello, world","latency_ms":42}

SSE;

    Http::fake([
        '*/api/v1/auth/login' => Http::response(['token' => 'tok'], 200),
        '*/api/v1/chat/completions' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
    ]);

    $result = chatClient()->complete('p1', 'deepseek', [['role' => 'user', 'content' => 'hi']]);

    expect($result['content'])->toBe('Hello, world')
        ->and($result['latency_ms'])->toBe(42)
        ->and($result['provider_name'])->toBe('9router')
        ->and($result['model_id'])->toBe('deepseek');
});

it('surfaces an SSE error event as an exception', function (): void {
    $sse = "event: start\ndata: {\"provider_name\":\"x\",\"model_id\":\"y\"}\n\n"
        ."event: error\ndata: {\"message\":\"quota exceeded\"}\n\n";

    Http::fake([
        '*/api/v1/auth/login' => Http::response(['token' => 'tok'], 200),
        '*/api/v1/chat/completions' => Http::response($sse, 200),
    ]);

    chatClient()->complete('p1', 'm1', [['role' => 'user', 'content' => 'hi']]);
})->throws(RuntimeException::class, 'quota exceeded');

it('renders the AI chat page with models and sends a message through the picker', function (): void {
    $this->actingAs(User::factory()->create());

    Http::fake([
        '*/health' => Http::response(['status' => 'ok'], 200),
        '*/api/v1/auth/login' => Http::response(['token' => 'tok'], 200),
        '*/api/v1/chat/models' => Http::response([
            'models' => [
                ['provider_id' => 'p1', 'provider_name' => '9router', 'model_id' => 'deepseek', 'display_name' => 'DeepSeek', 'is_default' => true],
            ],
        ], 200),
        '*/api/v1/chat/completions' => Http::response(
            "event: start\ndata: {\"provider_name\":\"9router\",\"model_id\":\"deepseek\"}\n\n"
            ."event: token\ndata: {\"text\":\"Hi there\"}\n\n"
            ."event: done\ndata: {\"content\":\"Hi there\",\"latency_ms\":10}\n\n",
            200,
        ),
    ]);

    $component = Livewire\Livewire::test(AiChatPage::class)
        ->assertSuccessful()
        ->assertSet('selectedModel', 'p1|deepseek')
        ->set('draft', 'Halo')
        ->call('send')
        ->assertSuccessful();

    // Transkrip memuat pesan user dan jawaban asisten.
    $messages = $component->get('messages');

    expect($messages)->toHaveCount(2)
        ->and($messages[0]['role'])->toBe('user')
        ->and($messages[0]['content'])->toBe('Halo')
        ->and($messages[1]['role'])->toBe('assistant')
        ->and($messages[1]['content'])->toBe('Hi there');
});

it('shows a graceful unavailable state when omnigensi is offline', function (): void {
    $this->actingAs(User::factory()->create());

    Http::fake(['*/health' => Http::response('down', 503)]);

    Livewire\Livewire::test(AiChatPage::class)
        ->assertSuccessful()
        ->assertSet('backendOnline', false);
});

it('filters the model picker by the search query', function (): void {
    $this->actingAs(User::factory()->create());

    Http::fake([
        '*/health' => Http::response(['status' => 'ok'], 200),
        '*/api/v1/auth/login' => Http::response(['token' => 'tok'], 200),
        '*/api/v1/chat/models' => Http::response([
            'models' => [
                ['provider_id' => 'p1', 'provider_name' => '9router', 'model_id' => 'deepseek', 'display_name' => 'DeepSeek', 'is_default' => true],
                ['provider_id' => 'p1', 'provider_name' => '9router', 'model_id' => 'claude', 'display_name' => 'Claude', 'is_default' => false],
                ['provider_id' => 'p1', 'provider_name' => '9router', 'model_id' => 'gpt', 'display_name' => 'GPT-4', 'is_default' => false],
            ],
        ], 200),
    ]);

    $component = Livewire\Livewire::test(AiChatPage::class)->assertSuccessful();

    $all = $component->instance()->modelOptions();
    expect($all)->toHaveCount(3);

    $component->set('modelSearch', 'claude');
    expect($component->instance()->filteredModelOptions())->toHaveCount(1);

    // Tidak peka huruf besar/kecil.
    $component->set('modelSearch', 'DEEP');
    expect($component->instance()->filteredModelOptions())->toHaveCount(1);

    // Banyak kata kunci (AND).
    $component->set('modelSearch', 'claude 9router');
    expect($component->instance()->filteredModelOptions())->toHaveCount(1);

    // Tidak ada yang cocok.
    $component->set('modelSearch', 'zzz');
    expect($component->instance()->filteredModelOptions())->toHaveCount(0)
        ->and($component->instance()->modelCounts())->toMatchArray(['total' => 3, 'shown' => 0]);

    // Bersihkan pencarian.
    $component->call('clearModelSearch');
    expect($component->get('modelSearch'))->toBe('')
        ->and($component->instance()->filteredModelOptions())->toHaveCount(3);
});

it('renders assistant replies as safe markdown html', function (): void {
    $component = Livewire\Livewire::test(AiChatPage::class);

    $html = $component->instance()->markdownToHtml("**Tebal** dan `kode`.\n\n- satu\n- dua");

    expect($html)->toContain('<strong>Tebal</strong>')
        ->and($html)->toContain('<code>kode</code>')
        ->and($html)->toContain('<li>satu</li>');
});

it('strips raw html from model markdown output', function (): void {
    $component = Livewire\Livewire::test(AiChatPage::class);

    // Blok HTML mentah (mis. <script>) dibuang total oleh html_input: strip.
    $html = $component->instance()->markdownToHtml('<script>alert(1)</script>');

    expect($html)->not->toContain('<script>')
        ->and($html)->not->toContain('alert(1)');

    // Markdown biasa di baris terpisah tetap dirender dengan aman.
    $clean = $component->instance()->markdownToHtml("Halo dunia\n\n**aman**");
    expect($clean)->toContain('<strong>aman</strong>');
});

it('renders an error reply as escaped plain text', function (): void {
    $component = Livewire\Livewire::test(AiChatPage::class);

    $html = $component->instance()->markdownToHtml('[Gagal] <b>x</b> & y');

    expect($html)->toContain('&lt;b&gt;')
        ->and($html)->not->toContain('<b>');
});

it('streams integration tokens through a callback and stitches the final reply', function (): void {
    $sse = "event: start\ndata: {\"model\":\"deepseek\",\"client\":\"agen xishie\"}\n\n"
        ."event: token\ndata: {\"text\":\"**Ha\"}\n\n"
        ."event: token\ndata: {\"text\":\"lo**\"}\n\n"
        ."event: done\ndata: {\"content\":\"**Halo**\",\"latency_ms\":15}\n\n";

    Http::fake([
        '*/api/v1/integrations/v1/chat/completions' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
    ]);

    $client = new OmnigensiChatClient('http://omnigensi.test', '123', 30, 5, true, 'omni_live_test');

    $tokens = [];
    $result = $client->streamIntegration(
        'deepseek',
        [['role' => 'user', 'content' => 'hi']],
        function (string $t) use (&$tokens): void {
            $tokens[] = $t;
        },
    );

    expect($tokens)->toBe(['**Ha', 'lo**'])
        ->and($result['content'])->toBe('**Halo**')
        ->and($result['model_id'])->toBe('deepseek')
        ->and($result['latency_ms'])->toBe(15);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/integrations/v1/chat/completions')
        && ($request['stream'] ?? null) === true);
});

it('sends through the integration endpoint and renders the reply as markdown', function (): void {
    $this->actingAs(User::factory()->create());

    config()->set('omnigensi.api_key', 'omni_live_test');
    $this->app->forgetInstance(OmnigensiChatClient::class);

    Http::fake([
        '*/health' => Http::response(['status' => 'ok'], 200),
        '*/api/v1/integrations/v1/models' => Http::response(['object' => 'list', 'data' => [
            ['id' => 'deepseek', 'display_name' => 'DeepSeek', 'provider' => '9router', 'provider_id' => 'p1', 'is_default' => true],
        ]], 200),
        '*/api/v1/integrations/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl-1',
            'object' => 'chat.completion',
            'model' => 'deepseek',
            'client' => 'agen xishie',
            'choices' => [
                ['index' => 0, 'message' => ['role' => 'assistant', 'content' => '# Judul'], 'finish_reason' => 'stop'],
            ],
            'latency_ms' => 9,
        ], 200),
    ]);

    $component = Livewire\Livewire::test(AiChatPage::class)
        ->assertSuccessful()
        ->set('selectedModel', 'p1|deepseek')
        ->set('draft', 'halo')
        ->call('send');

    $messages = $component->get('messages');

    expect($messages)->toHaveCount(2)
        ->and($messages[1]['role'])->toBe('assistant')
        ->and($messages[1]['content'])->toBe('# Judul');

    // Markdown dirender menjadi heading.
    expect($component->instance()->markdownToHtml($messages[1]['content']))->toContain('<h1>Judul</h1>');
});
