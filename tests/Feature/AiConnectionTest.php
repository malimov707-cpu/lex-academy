<?php

use App\Services\Ai\AiDriverManager;
use App\Services\Ai\AiUnavailableException;
use Illuminate\Support\Facades\Http;

it('checks the configured provider without sending student information', function () {
    config()->set('ai_tutor.api_key', 'test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    config()->set('ai_tutor.model', 'test-model');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/models/test-model' => Http::response(['id' => 'test-model']), 'https://ai.example.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);

    $this->artisan('academy:check-ai')->assertSuccessful();

    Http::assertSent(fn ($request) => ($request->data()['model'] ?? null) === 'test-model' && $request['messages'][1]['content'] === 'Connection check.');
});

it('fails clearly without making a request when the provider is not configured', function () {
    config()->set('ai_tutor.api_key', null);
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/*' => Http::response([])]);

    $this->artisan('academy:check-ai')->assertFailed();

    Http::assertNothingSent();
});

it('distinguishes quota failures without exposing provider error text or credentials', function () {
    config()->set('ai_tutor.api_key', 'private-test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => Http::response(['error' => ['code' => 'insufficient_quota', 'message' => 'private-test-key sensitive provider message']], 429)]);

    try {
        app(AiDriverManager::class)->driver()->complete([], [['role' => 'user', 'content' => 'Test']]);
        $this->fail('Expected provider error');
    } catch (AiUnavailableException $exception) {
        expect($exception->getMessage())->toContain('HTTP 429')->toContain('billing')->not->toContain('private-test-key')->not->toContain('sensitive provider message');
    }
    Http::assertSentCount(1);
});

it('handles connection timeouts without preserving sensitive transport exception details', function () {
    config()->set('ai_tutor.api_key', 'private-test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => Http::failedConnection()]);

    try {
        app(AiDriverManager::class)->driver()->complete([], []);
        $this->fail('Expected connection error');
    } catch (AiUnavailableException $exception) {
        expect($exception->getMessage())->toContain('ulanish')->not->toContain('private-test-key');
        expect($exception->getPrevious())->toBeNull();
    }
    Http::assertSentCount(1);
});

it('provides actionable authentication model and server errors', function (int $status, string $expected) {
    config()->set('ai_tutor.api_key', 'test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => Http::response([], $status)]);

    expect(fn () => app(AiDriverManager::class)->driver()->complete([], []))
        ->toThrow(AiUnavailableException::class, $expected);
    Http::assertSentCount(1);
})->with([[401, 'autentifikatsiyasi'], [403, 'taqiqlangan'], [404, 'topilmadi'], [500, 'vaqtinchalik']]);

it('identifies exhausted OpenAI credits independently from request rate limits', function () {
    config()->set('ai_tutor.api_key', 'test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => Http::response(['error' => ['code' => 'credit_balance_exhausted', 'type' => 'insufficient_quota']], 429)]);

    expect(fn () => app(AiDriverManager::class)->driver()->complete([], []))
        ->toThrow(AiUnavailableException::class, 'credit_balance_exhausted');
    Http::assertSentCount(1);
});
