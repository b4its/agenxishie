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
