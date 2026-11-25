<?php

namespace App\Core;

/**
 * Describes the AI providers supported by the assistant. Provider calls are
 * always made server-side, so HA ingress and plain Docker behave identically.
 */
final class AiProviders
{
    /** @return array<string, array<string, string>> */
    public static function all(): array
    {
        return [
            'openai' => [
                'label' => 'OpenAI',
                'key_setting' => 'openai_api_key',
                'base' => 'https://api.openai.com/v1',
                'default_model' => 'gpt-4o-mini',
                'format' => 'openai',
            ],
            'opencode' => [
                'label' => 'OpenCode',
                'key_setting' => 'opencode_api_key',
                'base' => 'https://opencode.ai/zen/v1',
                'default_model' => 'gpt-4o-mini',
                'format' => 'openai',
            ],
            'opencode_server' => [
                'label' => 'OpenCode Server',
                'key_setting' => 'opencode_server_password',
                'base' => 'http://localhost:4096',
                'default_model' => 'gpt-4o-mini',
                'format' => 'opencode_server',
            ],
            'claude' => [
                'label' => 'Claude',
                'key_setting' => 'anthropic_api_key',
                'base' => 'https://api.anthropic.com/v1',
                'default_model' => 'claude-3-5-haiku-latest',
                'format' => 'anthropic',
            ],
            'gemini' => [
                'label' => 'Gemini',
                'key_setting' => 'gemini_api_key',
                'base' => 'https://generativelanguage.googleapis.com/v1beta',
                'default_model' => 'gemini-1.5-flash',
                'format' => 'gemini',
            ],
        ];
    }

    /** @return array<string, string>|null */
    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function isValid(string $id): bool
    {
        return self::get($id) !== null;
    }

    public static function defaultModel(string $id): string
    {
        $provider = self::get($id);
        return $provider['default_model'] ?? 'gpt-4o-mini';
    }

    public static function baseUrl(string $id, string $configured): string
    {
        $provider = self::get($id);
        $base = $configured !== '' ? $configured : (string)($provider['base'] ?? '');
        return rtrim($base, '/');
    }

    /** Endpoint used to list available models. */
    public static function modelsUrl(string $id, string $configuredBase): string
    {
        $base = self::baseUrl($id, $configuredBase);
        return (string)(self::get($id)['format'] ?? 'openai') === 'opencode_server'
            ? $base . '/api/model'
            : $base . '/models';
    }

    /**
     * Build the HTTP request for a chat completion.
     *
     * @param array<int, array<string, string>> $messages
     * @return array{url:string, headers:array<int,string>, body:string}
     */
    public static function chatRequest(string $id, string $apiKey, string $model, string $base, array $messages): array
    {
        $format = (string)(self::get($id)['format'] ?? 'openai');
        $base = self::baseUrl($id, $base);

        if ($format === 'opencode_server') {
            // The self-hosted HttpApi is stateless: flatten the conversation
            // into one prompt and let the server use its configured provider.
            $parts = [];
            foreach ($messages as $message) {
                $role = (string)($message['role'] ?? 'user');
                $label = $role === 'system' ? 'System' : ($role === 'assistant' ? 'Assistant' : 'User');
                $parts[] = $label . ': ' . (string)($message['content'] ?? '');
            }
            return [
                'url' => $base . '/api/experimental/generate/text',
                'headers' => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . base64_encode(':' . $apiKey),
                ],
                'body' => (string)json_encode([
                    'prompt' => implode("\n\n", $parts),
                    'model' => $model,
                ]),
            ];
        }

        if ($format === 'anthropic') {
            $system = '';
            $conversation = [];
            foreach ($messages as $message) {
                if (($message['role'] ?? '') === 'system') {
                    $system .= (string)($message['content'] ?? '');
                    continue;
                }
                $conversation[] = ['role' => (string)($message['role'] ?? 'user'), 'content' => (string)($message['content'] ?? '')];
            }
            return [
                'url' => $base . '/messages',
                'headers' => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                ],
                'body' => (string)json_encode([
                    'model' => $model,
                    'max_tokens' => 1500,
                    'system' => $system,
                    'messages' => $conversation,
                ]),
            ];
        }

        if ($format === 'gemini') {
            $system = '';
            $contents = [];
            foreach ($messages as $message) {
                $role = (string)($message['role'] ?? 'user');
                if ($role === 'system') {
                    $system .= (string)($message['content'] ?? '');
                    continue;
                }
                $contents[] = [
                    'role' => $role === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => (string)($message['content'] ?? '')]],
                ];
            }
            $url = $base . '/models/' . rawurlencode($model) . ':generateContent?' . http_build_query(['key' => $apiKey]);
            return [
                'url' => $url,
                'headers' => ['Content-Type: application/json'],
                'body' => (string)json_encode([
                    'contents' => $contents,
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'generationConfig' => ['maxOutputTokens' => 1500, 'temperature' => 0.7],
                ]),
            ];
        }

        // OpenAI-compatible (OpenAI / OpenCode Zen or self-hosted).
        return [
            'url' => $base . '/chat/completions',
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            'body' => (string)json_encode([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 1500,
                'temperature' => 0.7,
            ]),
        ];
    }

    /** Headers required for the model-list request. */
    public static function modelHeaders(string $id, string $apiKey): array
    {
        $format = (string)(self::get($id)['format'] ?? 'openai');
        if ($format === 'anthropic') {
            return ['x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'];
        }
        if ($format === 'opencode_server') {
            return ['Authorization: Basic ' . base64_encode(':' . $apiKey)];
        }
        return ['Authorization: Bearer ' . $apiKey];
    }

    /** Gemini takes the key as a query parameter instead of a header. */
    public static function modelsGetUrl(string $id, string $base, string $apiKey): string
    {
        $url = self::modelsUrl($id, $base);
        if ((string)(self::get($id)['format'] ?? 'openai') === 'gemini') {
            return $url . '?' . http_build_query(['key' => $apiKey]);
        }
        return $url;
    }

    /** @return array<int, string> */
    public static function parseModels(string $id, string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $format = (string)(self::get($id)['format'] ?? 'openai');

        $items = [];
        if ($format === 'gemini') {
            $items = is_array($data['models'] ?? null) ? $data['models'] : [];
        } else {
            $items = is_array($data['data'] ?? null) ? $data['data'] : [];
        }

        $models = [];
        foreach ($items as $item) {
            $name = '';
            if (is_array($item)) {
                $name = (string)($item['id'] ?? $item['modelID'] ?? $item['name'] ?? '');
            } elseif (is_string($item)) {
                $name = $item;
            }
            if ($format === 'gemini' && str_starts_with($name, 'models/')) {
                $name = substr($name, strlen('models/'));
            }
            if ($name !== '' && !in_array($name, $models, true)) {
                $models[] = $name;
            }
        }
        return $models;
    }

    public static function parseChatResponse(string $id, string $json): string
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return '';
        }
        $format = (string)(self::get($id)['format'] ?? 'openai');

        if ($format === 'opencode_server') {
            return (string)($data['data']['text'] ?? '');
        }

        if ($format === 'anthropic') {
            $text = '';
            foreach (is_array($data['content'] ?? null) ? $data['content'] : [] as $part) {
                if (is_array($part) && ($part['type'] ?? 'text') === 'text') {
                    $text .= (string)($part['text'] ?? '');
                }
            }
            return $text;
        }

        if ($format === 'gemini') {
            $text = '';
            $candidates = is_array($data['candidates'] ?? null) ? $data['candidates'] : [];
            $first = $candidates[0] ?? null;
            $parts = is_array($first) ? ($first['content']['parts'] ?? []) : [];
            foreach (is_array($parts) ? $parts : [] as $part) {
                if (is_array($part)) {
                    $text .= (string)($part['text'] ?? '');
                }
            }
            return $text;
        }

        return (string)($data['choices'][0]['message']['content'] ?? '');
    }

    /** Offline fallbacks used when a provider's model list cannot be fetched. */
    public static function fallbackModels(string $id): array
    {
        return [self::defaultModel($id)];
    }
}
