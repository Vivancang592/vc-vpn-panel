<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contracts\AICapability;
use App\AI\Contracts\AIException;
use App\AI\Contracts\AIProviderInterface;
use App\AI\Contracts\AIResult;
use App\AI\Providers\KiraProvider;

/**
 * AICore — điểm vào duy nhất của hệ AI.
 *
 * Luồng xử lý (một chiều, không phụ thuộc vòng):
 *   Input → ModuleRegistry → PromptRegistry → ModelResolver → RequestBuilder
 *   → RetryPolicy → Provider → ResponseParser → ErrorHandler → OutputHandler
 *
 * Quy tắc:
 *  - Business module CHỈ gọi AICore, KHÔNG gọi provider trực tiếp.
 *  - Provider KHÔNG biết Core/Module/Prompt/Asset.
 *  - Core không chứa prompt nghiệp vụ, không hard-code tên model.
 *  - Mọi media đi qua AssetManager (OutputHandler gọi hộ).
 */
final class AICore
{
    /** @var array<string, mixed> */
    private array $config;

    private ModuleRegistry $modules;
    private ModelResolver $models;
    private PromptRegistry $prompts;
    private RequestBuilder $requests;
    private ResponseParser $responses;
    private ErrorHandler $errors;
    private RetryPolicy $retryPolicy;
    private AILogger $logger;
    private InputHandler $input;
    private ?OutputHandler $output;

    /** @var array<string, AIProviderInterface> */
    private array $providers = [];

    /**
     * @param array<string, mixed> $config Config AI (config/ai.php)
     */
    public function __construct(
        array $config = [],
        ?AIProviderInterface $provider = null,
        ?ModuleRegistry $modules = null,
        ?AILogger $logger = null
    ) {
        $this->config = $config;

        $this->modules = $modules ?? new ModuleRegistry();
        $this->models = new ModelResolver($this->defaultProviderKey());
        $this->prompts = new PromptRegistry();
        $this->requests = new RequestBuilder();
        $this->responses = new ResponseParser();
        $this->errors = new ErrorHandler();
        $this->retryPolicy = new RetryPolicy($config);
        $this->logger = $logger ?? new AILogger($config);
        $this->input = new InputHandler($this->modules);
        $this->output = null;

        if ($provider !== null) {
            $this->providers[$provider->key()] = $provider;
        }
    }

    /**
     * Cho phép inject provider giả khi test.
     */
    public function setProvider(AIProviderInterface $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function setOutputHandler(OutputHandler $output): void
    {
        $this->output = $output;
    }

    public function setModelResolver(ModelResolver $resolver): void
    {
        $this->models = $resolver;
    }

    public function modules(): ModuleRegistry
    {
        return $this->modules;
    }

    public function logger(): AILogger
    {
        return $this->logger;
    }

    /**
     * Thực thi một yêu cầu AI cho module.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    public function run(string $module, array $payload, array $options = []): AIResult
    {
        $context = [
            'capability' => $this->modules->capabilityOf($module),
            'module'     => $module,
            'provider'   => $this->defaultProviderKey(),
        ];

        return $this->errors->guard(function () use ($module, $payload, $options) {
            return $this->execute($module, $payload, $options);
        }, $context);
    }

    /**
     * Chỉ gọi phần LLM/text (tiện cho chat hội thoại nhiều lượt).
     *
     * @param array<int, array{role: string, content: mixed}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): AIResult
    {
        $module = (string) ($options['module'] ?? 'support_chat');
        $capability = AICapability::CHAT;

        $context = [
            'capability' => $capability,
            'module'     => $module,
            'provider'   => $this->defaultProviderKey(),
        ];

        return $this->errors->guard(function () use ($messages, $options, $module, $capability) {
            $provider = $this->resolveProvider();

            if (!$provider->supports($capability)) {
                return AIResult::failure(
                    AIException::VALIDATION,
                    'Provider không hỗ trợ chat.',
                    [],
                    ['module' => $module]
                );
            }

            try {
                $model = $this->models->resolve($module, $capability, $options);
            } catch (AIException $e) {
                return AIResult::failure($e->getErrorType(), $e->getMessage(), $e->getContext(), ['module' => $module]);
            }

            $options['capability'] = $capability;
            $options['module'] = $module;
            $options['model'] = $model['model_key'];
            $options['retry'] = $this->retryPolicy->default();

            $result = $provider->chat($messages, $options);

            return $this->responses->parse($result, [
                'capability' => $capability,
                'module'     => $module,
                'provider'   => $provider->key(),
                'model'      => $model['model_key'],
            ]);
        }, $context);
    }

    public function listModels(): AIResult
    {
        return $this->errors->guard(fn(): AIResult => $this->resolveProvider()->models([
            'retry' => $this->retryPolicy->default(),
        ]), ['capability' => null, 'module' => null, 'provider' => $this->defaultProviderKey()]);
    }

    /**
     * Kiểm tra trạng thái tác vụ video bất đồng bộ.
     *
     * @param array<string, mixed> $options
     */
    public function videoStatus(string $operationId, array $options = []): AIResult
    {
        return $this->errors->guard(fn(): AIResult => $this->resolveProvider()->videoStatus($operationId, array_merge($options, [
            'capability' => AICapability::VIDEO,
            'retry'      => $this->retryPolicy->default(),
        ])), ['capability' => AICapability::VIDEO, 'module' => $options['module'] ?? 'video_generation', 'provider' => $this->defaultProviderKey()]);
    }

    // -----------------------------------------------------------------
    // Pipeline
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function execute(string $module, array $payload, array $options): AIResult
    {
        $meta = $this->modules->get($module);

        if ($meta === null) {
            return AIResult::failure(
                AIException::VALIDATION,
                'Module không tồn tại: ' . $module,
                [],
                ['module' => $module]
            );
        }

        if (($meta['enabled'] ?? false) !== true) {
            return AIResult::failure(
                AIException::VALIDATION,
                'Module đang bị tắt: ' . $module,
                [],
                ['module' => $module]
            );
        }

        $capability = AICapability::normalize((string) $meta['capability']);

        // 1. Chuẩn hoá input (có thể ném AIException::VALIDATION → ErrorHandler bắt).
        $normalizedPayload = $this->input->normalize($module, $payload);

        // 2. Chọn provider tương ứng capability.
        $provider = $this->resolveProvider();

        if (!$provider->supports($capability)) {
            return AIResult::failure(
                AIException::VALIDATION,
                'Provider không hỗ trợ capability: ' . $capability,
                [],
                ['module' => $module, 'capability' => $capability]
            );
        }

        // 3. Chọn model (không hard-code).
        $model = $this->models->resolve($module, $capability, $options);

        // 4. Lấy prompt + render biến.
        $promptKey = (string) $meta['prompt_key'];
        $prompt = $this->prompts->get($module, $promptKey, $normalizedPayload);

        // 5. Lắp payload theo capability.
        $built = $this->requests->build($capability, $prompt, $model, $options);
        $requestPayload = $built['payload'];
        $requestOptions = $built['options'];

        // 6. Retry policy tập trung + idempotency key.
        $idempotencyKey = $this->input->idempotencyKey($module, $normalizedPayload);
        $requestOptions['retry'] = $this->retryPolicy->default();
        $requestOptions['module'] = $module;
        $requestOptions['provider'] = $provider->key();

        // 7. Gọi provider theo capability.
        $rawResult = $this->dispatch($provider, $capability, $requestPayload, $requestOptions);

        // 8. Chuẩn hoá response.
        $result = $this->responses->parse($rawResult, [
            'capability' => $capability,
            'module'     => $module,
            'provider'   => $provider->key(),
            'model'      => $model['model_key'],
        ]);

        // 9. Log (metadata only).
        $this->logger->logResult('ai.run.' . ($result->isOk() ? 'ok' : 'fail'), $result, [
            'module'          => $module,
            'capability'      => $capability,
            'prompt_source'   => $prompt['source'],
            'idempotency_key' => substr($idempotencyKey, 0, 12),
        ]);

        return $result;
    }

    /**
     * Gọi provider đúng method theo capability.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function dispatch(AIProviderInterface $provider, string $capability, array $payload, array $options): AIResult
    {
        return match ($capability) {
            AICapability::TEXT,
            AICapability::COMMENT,
            AICapability::CHAT,
            AICapability::PUBLISH => $provider->chat($payload['messages'] ?? [], $options),

            AICapability::IMAGE => $provider->image($payload, $options),

            AICapability::VIDEO => $provider->videoCreate($payload, $options),

            AICapability::AUDIO => $provider->speech($payload, $options),

            default => AIResult::failure(
                AIException::VALIDATION,
                'Capability chưa được hỗ trợ dispatch: ' . $capability
            ),
        };
    }

    private function resolveProvider(): AIProviderInterface
    {
        $key = $this->defaultProviderKey();

        if (isset($this->providers[$key])) {
            return $this->providers[$key];
        }

        $allowed = (array) ($this->config['allowed_providers'] ?? ['kira']);
        if (!in_array($key, $allowed, true)) {
            throw new AIException('Provider không được phép: ' . $key, AIException::CONFIG);
        }

        // Phase 5: chỉ Kira. Không có provider nào khác.
        $providerConfig = $this->config['providers'][$key] ?? [];
        $providerConfig['retry'] = $this->retryPolicy->default();

        $provider = new KiraProvider(is_array($providerConfig) ? $providerConfig : []);

        return $this->providers[$key] = $provider;
    }

    private function defaultProviderKey(): string
    {
        $key = (string) ($this->config['default_provider'] ?? 'kira');

        return $key !== '' ? $key : 'kira';
    }
}
