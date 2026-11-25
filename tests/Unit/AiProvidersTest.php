<?php

namespace Tests\Unit;

use App\Core\AiProviders;
use Tests\TestCase;

final class AiProvidersTest extends TestCase
{
    public function testAllProvidersAreRegistered(): void
    {
        $providers = AiProviders::all();
        self::assertSame(['openai', 'openrouter', 'opencode', 'opencode_server', 'claude', 'gemini'], array_keys($providers));
        foreach ($providers as $id => $provider) {
            self::assertSame($id, AiProviders::isValid($id) ? $id : 'invalid');
            self::assertNotSame('', AiProviders::defaultModel($id));
        }
        self::assertFalse(AiProviders::isValid('unknown'));
    }

    public function testOpenAiCompatibleRequest(): void
    {
        $request = AiProviders::chatRequest('openai', 'sk-test', 'gpt-4o-mini', '', [
            ['role' => 'system', 'content' => 'System'],
            ['role' => 'user', 'content' => 'Hello'],
        ]);
        self::assertSame('https://api.openai.com/v1/chat/completions', $request['url']);
        self::assertContains('Authorization: Bearer sk-test', $request['headers']);
        self::assertStringContainsString('gpt-4o-mini', $request['body']);
        self::assertStringContainsString('Hello', $request['body']);
    }

    public function testOpenRouterRequestCarriesAttributionHeaders(): void
    {
        $request = AiProviders::chatRequest('openrouter', 'sk-or-test', 'openai/gpt-4o-mini', '', [
            ['role' => 'user', 'content' => 'Hello'],
        ]);
        self::assertSame('https://openrouter.ai/api/v1/chat/completions', $request['url']);
        self::assertContains('Authorization: Bearer sk-or-test', $request['headers']);
        self::assertSame('https://openrouter.ai/api/v1/models', AiProviders::modelsUrl('openrouter', ''));

        $attribution = array_values(array_filter(
            $request['headers'],
            static fn(string $header): bool => str_starts_with($header, 'HTTP-Referer: ') || str_starts_with($header, 'X-Title: ')
        ));
        self::assertCount(2, $attribution);
        self::assertContains('X-Title: Turtle', $attribution);
        self::assertSame(
            ['openai/gpt-4o-mini'],
            AiProviders::parseModels('openrouter', '{"data":[{"id":"openai/gpt-4o-mini"}]}')
        );
    }

    public function testOpenCodeSupportsZenAndSelfHostedBase(): void
    {
        $zen = AiProviders::chatRequest('opencode', 'key', 'model', '', []);
        self::assertSame('https://opencode.ai/zen/v1/chat/completions', $zen['url']);
        $selfHosted = AiProviders::chatRequest('opencode', 'key', 'model', 'http://localhost:4000/v1/', []);
        self::assertSame('http://localhost:4000/v1/chat/completions', $selfHosted['url']);
    }

    public function testOpenCodeServerUsesHttpApiAndBasicAuth(): void
    {
        $request = AiProviders::chatRequest('opencode_server', 'secret', 'model-a', 'http://192.168.2.4:4096', [
            ['role' => 'system', 'content' => 'System text'],
            ['role' => 'user', 'content' => 'Question'],
        ]);
        self::assertSame('http://192.168.2.4:4096/api/experimental/generate/text', $request['url']);
        self::assertContains('Authorization: Basic ' . base64_encode('opencode:secret'), $request['headers']);
        self::assertContains(
            AiProviders::basicHeader('secret', ''),
            AiProviders::fallbackAuthHeaders($request['headers'], 'secret')
        );
        self::assertStringContainsString('System: System text', $request['body']);
        self::assertStringContainsString('User: Question', $request['body']);
        self::assertSame('http://192.168.2.4:4096/api/model', AiProviders::modelsUrl('opencode_server', 'http://192.168.2.4:4096'));
        self::assertSame(
            ['model-a'],
            AiProviders::parseModels('opencode_server', '{"data":[{"modelID":"model-a"}]}')
        );
        self::assertSame('answer', AiProviders::parseChatResponse('opencode_server', '{"data":{"text":"answer"}}'));
    }

    public function testClaudeRequestSeparatesSystemPrompt(): void
    {
        $request = AiProviders::chatRequest('claude', 'sk-ant', 'claude-test', '', [
            ['role' => 'system', 'content' => 'Be brief'],
            ['role' => 'user', 'content' => 'Hello'],
        ]);
        self::assertSame('https://api.anthropic.com/v1/messages', $request['url']);
        self::assertContains('x-api-key: sk-ant', $request['headers']);
        self::assertContains('anthropic-version: 2023-06-01', $request['headers']);
        self::assertStringContainsString('"system":"Be brief"', $request['body']);
        self::assertStringNotContainsString('"role":"system"', $request['body']);
    }

    public function testGeminiRequestUsesKeyInQueryAndModelRole(): void
    {
        $request = AiProviders::chatRequest('gemini', 'secret key', 'gemini-test', '', [
            ['role' => 'system', 'content' => 'Be brief'],
            ['role' => 'assistant', 'content' => 'Prior reply'],
        ]);
        self::assertStringContainsString('models/gemini-test:generateContent', $request['url']);
        self::assertStringContainsString('key=secret+key', $request['url']);
        self::assertStringContainsString('"role":"model"', $request['body']);
        self::assertStringContainsString('"systemInstruction"', $request['body']);
    }

    public function testModelListsAreParsedForEachProvider(): void
    {
        self::assertSame(
            ['gpt-4o', 'gpt-4o-mini'],
            AiProviders::parseModels('openai', '{"data":[{"id":"gpt-4o"},{"id":"gpt-4o-mini"}]}')
        );
        self::assertSame(
            ['claude-3-5-haiku-latest'],
            AiProviders::parseModels('claude', '{"data":[{"id":"claude-3-5-haiku-latest"}]}')
        );
        self::assertSame(
            ['gemini-1.5-flash'],
            AiProviders::parseModels('gemini', '{"models":[{"name":"models/gemini-1.5-flash"}]}')
        );
    }

    public function testResponsesAreParsedForEachProvider(): void
    {
        self::assertSame(
            'Hello',
            AiProviders::parseChatResponse('openai', '{"choices":[{"message":{"content":"Hello"}}]}')
        );
        self::assertSame(
            'Hi',
            AiProviders::parseChatResponse('claude', '{"content":[{"type":"text","text":"Hi"}]}')
        );
        self::assertSame(
            'Hey',
            AiProviders::parseChatResponse('gemini', '{"candidates":[{"content":{"parts":[{"text":"Hey"}]}}]}')
        );
    }

    public function testFallbackModelsAreNeverEmpty(): void
    {
        foreach (array_keys(AiProviders::all()) as $id) {
            self::assertNotSame([], AiProviders::fallbackModels($id));
        }
    }
}
