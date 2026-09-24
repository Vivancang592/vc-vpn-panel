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
            $result = $this->askGemini($messages, $model ?: ($this->settings['ai_gemini_model'] ?? 'gemini-1.5-flash'));
            $result['provider'] = 'gemini';
            return $result;
        }

        $result = $this->askOpenAI($messages, $model ?: ($this->settings['ai_openai_model'] ?? 'gpt-4o-mini'));
        $result['provider'] = 'openai';
        return $result;
    }

    private function askOpenAI(array $messages, string $model): array
    {
        $apiKey = $this->resolveConfigValue('ai_openai_api_key', 'OPENAI_API_KEY');
        if ($apiKey === '') {
            return ['ok' => false, 'content' => '', 'error' => 'Thiếu API key OpenAI.'];
        }

        $maxTokens = (int) ($this->settings['ai_max_output_tokens'] ?? 500);
        $maxTokens = max(120, min(900, $maxTokens));

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.45,
            'max_tokens' => $maxTokens,
        ];

        $response = $this->requestJson(
            'https://api.openai.com/v1/chat/completions',
            $payload,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        );

        if (!$response['ok']) {
            return ['ok' => false, 'content' => '', 'error' => $response['error']];
        }

        $content = (string) ($response['data']['choices'][0]['message']['content'] ?? '');
        if (trim($content) === '') {
            return ['ok' => false, 'content' => '', 'error' => 'OpenAI trả về nội dung rỗng.'];
        }

        return ['ok' => true, 'content' => $content, 'error' => null, 'model' => $model];
    }

    private function askGemini(array $messages, string $model): array
    {
        $apiKey = $this->resolveConfigValue('ai_gemini_api_key', 'GEMINI_API_KEY');
        if ($apiKey === '') {
            return ['ok' => false, 'content' => '', 'error' => 'Thiếu API key Gemini.'];
        }

        $maxTokens = (int) ($this->settings['ai_max_output_tokens'] ?? 500);
        $maxTokens = max(120, min(900, $maxTokens));

        $prompt = $this->buildGeminiPrompt($messages);
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.45,
                'maxOutputTokens' => $maxTokens,
            ]
        ];

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

        $content = (string) ($response['data']['candidates'][0]['content']['parts'][0]['text'] ?? '');
        if (trim($content) === '') {
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

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

    private function resolveConfigValue(string $settingKey, string $envKey): string
    {
        $settingValue = trim((string) ($this->settings[$settingKey] ?? ''));
        if ($settingValue !== '') {
            return $settingValue;
        }

        $envValue = trim((string) (getenv($envKey) ?: ''));
        return $envValue;
    }
}
