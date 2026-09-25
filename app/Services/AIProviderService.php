<?php

namespace App\Services;

use App\Models\Setting;

class AIProviderService
{
    private array $settings;

    public function __construct()
    {
        $this->settings = (new Setting())->getAllAsKeyValue();
    }

    public function ask(array $messages, ?string $provider = null, ?string $model = null): array
    {
        $provider = strtolower(trim((string) ($provider ?: ($this->settings['ai_provider'] ?? 'openai'))));

        if ($provider === 'gemini') {
            $result = $this->askGemini($messages, $model ?: ($this->settings['ai_gemini_model'] ?? 'gemini-3.7-flash'));
            $result['provider'] = 'gemini';
            return $result;
        }

        $result = $this->askOpenAI($messages, $model ?: ($this->settings['ai_openai_model'] ?? 'openai/gpt-4o-mini'));
        $result['provider'] = 'openai';
        return $result;
    }

    private function askOpenAI(array $messages, string $model): array
    {
        $apiKey = $this->resolveConfigValue('ai_openai_api_key', 'OPENROUTER_API_KEY', ['ai_openrouter_api_key', 'openrouter_api_key', 'OPENAI_API_KEY', 'openai_api_key']);
        if ($apiKey === '') {
            return ['ok' => false, 'content' => '', 'error' => 'Thiếu API key OpenRouter.'];
        }

        $maxTokens = (int) ($this->settings['ai_max_output_tokens'] ?? 800);
        $maxTokens = max(500, min(2500, $maxTokens));

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.45,
            'max_tokens' => $maxTokens,
        ];

        $response = $this->requestJson(
            'https://openrouter.ai/api/v1/chat/completions',
            $payload,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: http://localhost',
                'X-Title: VC VPN Chatbot'
            ]
        );

        if (!$response['ok']) {
            return ['ok' => false, 'content' => '', 'error' => $response['error']];
        }

        $content = (string) ($response['data']['choices'][0]['message']['content'] ?? '');
        if (trim($content) === '') {
            return ['ok' => false, 'content' => '', 'error' => 'OpenRouter trả về nội dung rỗng.'];
        }

        return ['ok' => true, 'content' => $content, 'error' => null, 'model' => $model];
    }

    private function askGemini(array $messages, string $model): array
    {
        $apiKey = $this->resolveConfigValue('ai_gemini_api_key', 'GEMINI_API_KEY', ['gemini_api_key']);
        if ($apiKey === '') {
            return ['ok' => false, 'content' => '', 'error' => 'Thiếu API key Gemini.'];
        }

        $systemParts = [];
        $contents = [];

        foreach ($messages as $msg) {
            $role = (string) ($msg['role'] ?? 'user');
            $text = trim((string) ($msg['content'] ?? ''));
            if ($text === '') {
                continue;
            }

            if ($role === 'system') {
                $systemParts[] = ['text' => $text];
            } else {
                $geminiRole = ($role === 'assistant' || $role === 'model') ? 'model' : 'user';
                $contents[] = [
                    'role' => $geminiRole,
                    'parts' => [
                        ['text' => $text]
                    ]
                ];
            }
        }

        if (empty($contents)) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => 'Xin chào']]
            ];
        }

        $maxTokens = (int) ($this->settings['ai_max_output_tokens'] ?? 500);
        // Với Gemini thế hệ mới (2.5, 3.x Flash), maxOutputTokens bao gồm cả Thinking Tokens + Answer Tokens.
        // Cần cấp đủ headroom (tối thiểu 2500 - 4000 tokens) để không bị ngắt giữa chừng.
        $maxOutputTokens = max(2500, min(4096, $maxTokens + 2000));

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => $maxOutputTokens,
            ]
        ];

        if (!empty($systemParts)) {
            $payload['system_instruction'] = [
                'parts' => $systemParts
            ];
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . ':generateContent?key='
            . rawurlencode($apiKey);

        $response = $this->requestJson(
            $url,
            $payload,
            ['Content-Type: application/json']
        );

        if (!$response['ok']) {
            return ['ok' => false, 'content' => '', 'error' => $response['error']];
        }

        $parts = $response['data']['candidates'][0]['content']['parts'] ?? [];
        $texts = [];
        foreach ($parts as $part) {
            if (!empty($part['text'])) {
                $texts[] = $part['text'];
            }
        }
        $content = trim(implode('', $texts));

        if ($content === '') {
            return ['ok' => false, 'content' => '', 'error' => 'Gemini trả về nội dung rỗng.'];
        }

        return ['ok' => true, 'content' => $content, 'error' => null, 'model' => $model];
    }

    private function buildGeminiPrompt(array $messages): string
    {
        $lines = [];
        foreach ($messages as $msg) {
            $role = strtoupper((string) ($msg['role'] ?? 'USER'));
            $content = trim((string) ($msg['content'] ?? ''));
            if ($content !== '') {
                $lines[] = $role . ': ' . $content;
            }
        }
        return implode("\n\n", $lines);
    }

    private function requestJson(string $url, array $payload, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $this->applyProxy($ch);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'error' => 'Lỗi kết nối AI: ' . $error, 'status' => 0, 'data' => null];
        }

        $data = json_decode((string) $raw, true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : (string) $raw;
            return ['ok' => false, 'error' => 'AI HTTP ' . $status . ': ' . $message, 'status' => $status, 'data' => $data];
        }

        return ['ok' => true, 'error' => null, 'status' => $status, 'data' => is_array($data) ? $data : []];
    }

    private function applyProxy($ch): void
    {
        $proxy = trim((string) ($this->settings['ai_proxy'] ?? ''));
        if ($proxy === '') {
            $proxy = trim((string) (
                getenv('AI_PROXY') ?:
                getenv('HTTPS_PROXY') ?:
                getenv('HTTP_PROXY') ?:
                getenv('ALL_PROXY') ?: ''
            ));
        }

        if ($proxy === '') {
            $proxy = $this->detectLocalDevProxy();
        }

        if ($proxy !== '') {
            if (preg_match('/^socks5:\/\//i', $proxy)) {
                $proxy = preg_replace('/^socks5:\/\//i', 'socks5h://', $proxy);
            } elseif (!preg_match('/^[a-z0-9]+:\/\//i', $proxy) && (str_contains($proxy, '10808') || str_contains($proxy, '1080'))) {
                $proxy = 'socks5h://' . $proxy;
            }
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
    }

    private function detectLocalDevProxy(): string
    {
        $ports = [
            ['port' => 10808, 'type' => 'socks5h://'],
            ['port' => 7890,  'type' => 'http://'],
            ['port' => 10809, 'type' => 'http://'],
        ];

        foreach ($ports as $p) {
            $fp = @fsockopen('127.0.0.1', $p['port'], $errno, $errstr, 0.05);
            if ($fp) {
                fclose($fp);
                return $p['type'] . '127.0.0.1:' . $p['port'];
            }
        }

        return '';
    }

    private function resolveConfigValue(string $settingKey, string $envKey, array $settingAliases = []): string
    {
        $settingValue = trim((string) ($this->settings[$settingKey] ?? ''));
        if ($settingValue !== '') {
            return $settingValue;
        }

        foreach ($settingAliases as $alias) {
            $aliasValue = trim((string) ($this->settings[$alias] ?? ''));
            if ($aliasValue !== '') {
                return $aliasValue;
            }
        }

        $envValue = trim((string) (getenv($envKey) ?: ''));
        return $envValue;
    }
}
