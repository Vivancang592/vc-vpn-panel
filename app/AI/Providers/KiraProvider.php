<?php

declare(strict_types=1);

namespace App\AI\Providers;

use App\AI\Contracts\AICapability;
use App\AI\Contracts\AIException;
use App\AI\Contracts\AIProviderInterface;
use App\AI\Contracts\AIResult;
use App\Models\Setting;

/**
 * KiraProvider — provider duy nhất của AI Core ở Phase 5.
 *
 * Trách nhiệm DUY NHẤT:
 *  - authentication (Bearer)
 *  - HTTP request qua transport có thể mock
 *  - endpoint mapping
 *  - timeout
 *  - parse response
 *  - map error → error type chuẩn
 *  - retry theo policy được truyền vào (plain array, KHÔNG phụ thuộc Core)
 *
 * KHÔNG biết: ModuleRegistry, PromptRegistry, business prompt, AssetManager,
 * FFmpeg, STT, business workflow.
 *
 * Contract chưa xác minh: video-edit / video-input tham chiếu
 * (KIRA_VIDEO_EDIT_API_CONTRACT_UNVERIFIED) → KHÔNG được tự tạo parameter.
 * STT: Kira chưa xác minh có endpoint STT → KHÔNG tạo.
 */
final class KiraProvider implements AIProviderInterface
{
    private string $providerKey = 'kira';

    /** @var array<string, mixed> */
    private array $config;

    /** @var callable|null */
    private $transport;

    private ?string $apiKeyOverride = null;

    /**
     * @param array<string, mixed> $config    Cấu hình provider (lấy từ config/ai.php providers.kira
     *                                        cộng thêm key 'retry' do AI Core truyền vào).
     * @param callable|null        $transport HTTP transport giả lập được (dùng cho test).
     *                                        Chữ ký: fn(string $method, string $url, array $headers, ?string $body): array
     *                                        Trả về: ['status' => int, 'body' => string, 'errno' => int, 'error' => string, 'duration_ms' => float]
     */
    public function __construct(array $config = [], ?callable $transport = null, ?string $apiKey = null)
    {
        $this->config = $config;
        $this->transport = $transport;
        $this->apiKeyOverride = $apiKey;
    }

    public function key(): string
    {
        return $this->providerKey;
    }

    public function supports(string $capability): bool
    {
        return AICapability::isValid($capability);
    }

    public function chat(array $messages, array $options = []): AIResult
    {
        return $this->call('chat', $this->buildChatPayload($messages, $options), $options);
    }

    public function image(array $payload, array $options = []): AIResult
    {
        return $this->call('image', $payload, $options);
    }

    public function videoCreate(array $payload, array $options = []): AIResult
    {
        // Chỉ dùng các tham số đã được xác minh ở Phase 2.
        // KHÔNG thêm parameter video-reference/edit chưa xác minh.
        return $this->call('video_create', $payload, $options);
    }

    public function videoStatus(string $operationId, array $options = []): AIResult
    {
        return $this->call('video_status', null, $options, ['id' => rawurlencode($operationId)]);
    }

    public function speech(array $payload, array $options = []): AIResult
    {
        return $this->call('audio_speech', $payload, $options);
    }

    public function models(array $options = []): AIResult
    {
        return $this->call('models', null, $options);
    }

    // -----------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @param array<string, string> $pathParams
     */
    private function call(string $endpointKey, ?array $payload, array $options, array $pathParams = []): AIResult
    {
        $meta = [
            'capability' => $options['capability'] ?? $this->defaultCapabilityFor($endpointKey),
            'module'     => $options['module'] ?? null,
            'provider'   => $this->providerKey,
            'model'      => $options['model'] ?? null,
        ];

        try {
            [$method, $url] = $this->buildEndpoint($endpointKey, $pathParams);
        } catch (AIException $e) {
            return AIResult::failure($e->getErrorType(), $e->getMessage(), $e->getContext(), $meta);
        }

        try {
            $apiKey = $this->resolveApiKey();
        } catch (\Throwable $e) {
            return AIResult::failure(AIException::CONFIG, 'Không đọc được cấu hình API key.', [], $meta);
        }

        if ($apiKey === null || $apiKey === '') {
            return AIResult::failure(
                AIException::AUTH,
                'Kira API key chưa được cấu hình.',
                ['provider' => $this->providerKey],
                $meta
            );
        }

        $headers = $this->buildHeaders($apiKey, $options);
        $body = $payload === null ? null : $this->encodePayload($payload);

        if ($body === false) {
            return AIResult::failure(AIException::VALIDATION, 'Không thể mã hoá payload thành JSON.', [], $meta);
        }

        $retry = is_array($options['retry'] ?? null) ? $options['retry'] : $this->retryPolicyFromConfig();
        $maxAttempts = max(1, (int) ($retry['max_attempts'] ?? 1));

        $attempt = 0;
        $lastResult = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $lastResult = $this->send($method, $url, $headers, $body, $meta);

            if ($lastResult->isOk()) {
                return $lastResult;
            }

            if (!$this->shouldRetry($lastResult->errorType(), $retry) || $attempt >= $maxAttempts) {
                return $lastResult;
            }

            $this->sleepMs($this->delayForAttempt($retry, $attempt));
        }

        return $lastResult ?? AIResult::failure(AIException::UNKNOWN, 'Không có phản hồi từ provider.', [], $meta);
    }

    /**
     * Thực hiện một lần gọi HTTP (không retry).
     *
     * @param array<int, string> $headers
     * @param array<string, mixed> $meta
     */
    private function send(string $method, string $url, array $headers, ?string $body, array $meta): AIResult
    {
        $transport = $this->transport ?? [$this, 'curlTransport'];

        try {
            $response = $transport($method, $url, $headers, $body);
        } catch (\Throwable $e) {
            return AIResult::failure(AIException::UNKNOWN, 'Transport error: ' . $e->getMessage(), [], $meta);
        }

        $status = (int) ($response['status'] ?? 0);
        $raw = (string) ($response['body'] ?? '');
        $errno = (int) ($response['errno'] ?? 0);
        $errMsg = (string) ($response['error'] ?? '');
        $durationMs = (float) ($response['duration_ms'] ?? 0);

        if ($errno !== 0 || $status === 0) {
            $type = $this->isTimeoutError($errno) ? AIException::TIMEOUT : AIException::TIMEOUT;
            return AIResult::failure($type, 'Lỗi kết nối tới Kira: ' . ($errMsg !== '' ? $errMsg : 'unknown'), [
                'endpoint'    => $url,
                'duration_ms' => $durationMs,
            ], $meta);
        }

        // Audio speech trả về BODY NHỊ PHÂN (MP3), không phải JSON. CHỈ phân xử
        // khi HTTP 2xx: nếu không lỗi 4xx/5xx (body JSON chứa error) sẽ bị gói
        // thành "audio" giả thành công. Lỗi đi đường parse JSON chuẩn bên dưới.
        if ($status >= 200 && $status < 300 && $this->isAudioResponse($meta)) {
            if (trim($raw) === '') {
                return AIResult::failure(AIException::MALFORMED, 'Phản hồi audio rỗng.', [
                    'http_status' => $status,
                    'duration_ms' => $durationMs,
                ], $meta);
            }

            return $this->mapSuccess([
                'audio' => [
                    'b64_json' => base64_encode($raw),
                ],
            ], $meta, $durationMs, $status);
        }

        $decoded = $this->decode($raw);

        if ($status < 200 || $status >= 300) {
            $type = AIException::typeFromHttpStatus($status);
            $message = $this->extractErrorMessage($decoded, $raw);
            return AIResult::failure($type, $message, [
                'http_status' => $status,
                'endpoint'    => $url,
                'duration_ms' => $durationMs,
            ], array_merge($meta, ['raw' => is_array($decoded) ? $decoded : []]));
        }

        if ($decoded === null && trim($raw) !== '') {
            return AIResult::failure(AIException::MALFORMED, 'Phản hồi không phải JSON hợp lệ.', [
                'http_status' => $status,
                'duration_ms' => $durationMs,
            ], $meta);
        }

        return $this->mapSuccess(is_array($decoded) ? $decoded : [], $meta, $durationMs, $status);
    }

    /**
     * Phản hồi AUDIO là body nhị phân (MP3) — KHÔNG phải JSON. Endpoint
     * audio_speech (capability AUDIO) là endpoint duy nhất trả nhị phân;
     * STT/video-edit KHÔNG tồn tại (chưa xác minh) nên không đụng tới.
     *
     * @param array<string, mixed> $meta
     */
    private function isAudioResponse(array $meta): bool
    {
        return (string) ($meta['capability'] ?? '') === AICapability::AUDIO;
    }

    /**
     * Map response thành công về AIResult theo capability.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    private function mapSuccess(array $data, array $meta, float $durationMs, int $status): AIResult
    {
        $capability = (string) ($meta['capability'] ?? '');

        $content = null;
        $files = [];

        // OpenAI-compatible chat completion.
        if (isset($data['choices'][0]['message']['content'])) {
            $content = (string) $data['choices'][0]['message']['content'];
        } elseif (isset($data['output_text'])) {
            $content = (string) $data['output_text'];
        } elseif (isset($data['text'])) {
            $content = (string) $data['text'];
        } elseif (isset($data['data'][0]['url'])) {
            // Image generation.
            $files[] = [
                'url'  => (string) $data['data'][0]['url'],
                'kind' => 'image',
            ];
        } elseif (isset($data['data'][0]['b64_json'])) {
            $files[] = [
                'b64_json' => (string) $data['data'][0]['b64_json'],
                'kind'     => 'image',
            ];
        } elseif (isset($data['audio']['b64_json'])) {
            // TTS nhị phân đã được base64 hoá ở send(): file audio thật.
            $files[] = [
                'b64_json' => (string) $data['audio']['b64_json'],
                'kind'     => 'audio',
            ];
        }

        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        return AIResult::success([
            'capability'  => $capability,
            'module'      => $meta['module'] ?? null,
            'provider'    => $this->providerKey,
            'model'       => $data['model'] ?? ($meta['model'] ?? null),
            'content'     => $content,
            'files'       => $files,
            'status'      => 'completed',
            'usage'       => $usage + [
                'duration_ms' => $durationMs,
                'http_status' => $status,
            ],
            'raw'         => $data,
        ]);
    }

    // -----------------------------------------------------------------
    // Endpoint / headers / payload
    // -----------------------------------------------------------------

    /**
     * @param array<string, string> $pathParams
     * @return array{0: string, 1: string}
     */
    private function buildEndpoint(string $endpointKey, array $pathParams = []): array
    {
        $endpoints = $this->config['endpoints'] ?? [];
        $spec = $endpoints[$endpointKey] ?? null;

        if (!is_string($spec) || trim($spec) === '') {
            throw new AIException(
                'Endpoint chưa được cấu hình: ' . $endpointKey,
                AIException::CONFIG
            );
        }

        $parts = preg_split('/\s+/', trim($spec), 2);
        $method = strtoupper($parts[0] ?? 'POST');
        $path = $parts[1] ?? '';

        foreach ($pathParams as $name => $value) {
            $path = str_replace('{' . $name . '}', (string) $value, $path);
        }

        $baseUrl = (string) ($this->config['base_url'] ?? '');
        if ($baseUrl === '') {
            throw new AIException('Base URL của Kira chưa được cấu hình.', AIException::CONFIG);
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        return [$method, $url];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    private function buildHeaders(string $apiKey, array $options): array
    {
        $scheme = (string) ($this->config['auth_scheme'] ?? 'Bearer');
        $headerName = (string) ($this->config['auth_header'] ?? 'Authorization');

        $headers = [
            $headerName . ': ' . trim($scheme . ' ' . $apiKey),
            'Accept: application/json',
        ];

        // Chỉ thêm Content-Type khi có body JSON.
        $headers[] = 'Content-Type: application/json';

        if (is_array($options['headers'] ?? null)) {
            foreach ($options['headers'] as $name => $value) {
                $headers[] = $name . ': ' . $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<int, array{role: string, content: mixed}> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildChatPayload(array $messages, array $options): array
    {
        $payload = ['messages' => $messages];

        // Model KHÔNG hard-code: chỉ lấy từ options (do ModelResolver cung cấp).
        if (!empty($options['model'])) {
            $payload['model'] = (string) $options['model'];
        }

        foreach (['temperature', 'top_p', 'max_tokens', 'stream', 'stop', 'frequency_penalty', 'presence_penalty'] as $key) {
            if (array_key_exists($key, $options)) {
                $payload[$key] = $options[$key];
            }
        }

        // Không cho phép stream ở Phase 5.
        $payload['stream'] = false;

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodePayload(array $payload): string|false
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? false : $json;
    }

    // -----------------------------------------------------------------
    // API key resolution (KHÔNG hard-code, KHÔNG log)
    // -----------------------------------------------------------------

    private function resolveApiKey(): ?string
    {
        if (is_string($this->apiKeyOverride) && trim($this->apiKeyOverride) !== '') {
            return trim($this->apiKeyOverride);
        }

        $envKey = (string) ($this->config['env_key'] ?? 'KIRA_API_KEY');
        $fromEnv = getenv($envKey);
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        // Fallback: đọc từ settings (chỉ đọc, không ghi).
        $settingKey = (string) ($this->config['setting_key'] ?? 'kira_api_key');
        try {
            $value = (new Setting())->get($settingKey);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        // Chuỗi mask (***) hoặc rỗng coi như chưa cấu hình.
        if ($value === '' || str_contains($value, '***')) {
            return null;
        }

        return $value;
    }

    // -----------------------------------------------------------------
    // Retry policy (consume plain array — centralized definition ở RetryPolicy)
    // -----------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function retryPolicyFromConfig(): array
    {
        $retry = $this->config['retry'] ?? [];

        return is_array($retry) ? $retry : [];
    }

    /**
     * @param array<string, mixed> $retry
     */
    private function shouldRetry(?string $errorType, array $retry): bool
    {
        if (($retry['enabled'] ?? false) !== true) {
            return false;
        }

        if ($errorType === null) {
            return false;
        }

        $nonRetryable = (array) ($retry['non_retryable_errors'] ?? []);
        if (in_array($errorType, $nonRetryable, true)) {
            return false;
        }

        $retryable = (array) ($retry['retryable_errors'] ?? []);
        if ($retryable !== [] && !in_array($errorType, $retryable, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $retry
     */
    private function delayForAttempt(array $retry, int $attempt): int
    {
        $base = (int) ($retry['base_delay_ms'] ?? 500);
        $max = (int) ($retry['max_delay_ms'] ?? 8000);
        $multiplier = (float) ($retry['multiplier'] ?? 2.0);

        $delay = (int) ($base * ($multiplier ** max(0, $attempt - 1)));

        return (int) max(0, min($max, $delay));
    }

    private function sleepMs(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    private function isTimeoutError(int $errno): bool
    {
        // CURLE_OPERATION_TIMEDOUT = 28
        return $errno === 28;
    }

    // -----------------------------------------------------------------
    // Decode / error message
    // -----------------------------------------------------------------

    private function decode(string $raw): ?array
    {
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractErrorMessage(?array $decoded, string $raw): string
    {
        if (is_array($decoded)) {
            $candidate = $decoded['error']['message']
                ?? $decoded['error']
                ?? $decoded['message']
                ?? $decoded['detail']
                ?? null;

            if (is_string($candidate) && trim($candidate) !== '') {
                return substr(trim($candidate), 0, 500);
            }

            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);

            return substr((string) $encoded, 0, 500);
        }

        return substr(trim($raw), 0, 500);
    }

    private function defaultCapabilityFor(string $endpointKey): ?string
    {
        return match ($endpointKey) {
            'chat' => AICapability::TEXT,
            'image' => AICapability::IMAGE,
            'video_create', 'video_status' => AICapability::VIDEO,
            'audio_speech' => AICapability::AUDIO,
            default => null,
        };
    }

    // -----------------------------------------------------------------
    // Default transport (cURL) — chỉ dùng khi không truyền transport giả
    // -----------------------------------------------------------------

    /**
     * @param array<int, string> $headers
     * @return array{status: int, body: string, errno: int, error: string, duration_ms: float}
     */
    private function curlTransport(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        $start = microtime(true);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) ($this->config['connect_timeout'] ?? 10));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) ($this->config['timeout'] ?? 60));

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status'      => $status,
            'body'        => is_string($raw) ? $raw : '',
            'errno'       => $errno,
            'error'       => $error,
            'duration_ms' => (microtime(true) - $start) * 1000,
        ];
    }
}
