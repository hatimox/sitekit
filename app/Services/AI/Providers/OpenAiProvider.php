<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiProviderInterface;
use App\Services\AI\AiResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $maxTokens;
    protected float $temperature;

    public function __construct()
    {
        $this->apiKey = config('ai.providers.openai.api_key', '');
        $this->model = config('ai.providers.openai.model', 'gpt-5');
        $this->baseUrl = config('ai.providers.openai.base_url', 'https://api.openai.com/v1');
        $this->maxTokens = config('ai.providers.openai.max_tokens', 4096);
        $this->temperature = config('ai.providers.openai.temperature', 0.7);
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        if (!$this->isAvailable()) {
            return AiResponse::failure('OpenAI API key not configured', $this->getName(), $this->model);
        }

        try {
            $model = $options['model'] ?? $this->model;
            $maxTokens = $options['max_tokens'] ?? $this->maxTokens;

            // GPT-5+ uses max_completion_tokens and doesn't support temperature
            $isGpt5Plus = str_starts_with($model, 'gpt-5') || str_starts_with($model, 'gpt-6');
            $payload = [
                'model' => $model,
                'messages' => $messages,
            ];

            if ($isGpt5Plus) {
                $payload['max_completion_tokens'] = $maxTokens;
                // GPT-5 only supports temperature=1
            } else {
                $payload['max_tokens'] = $maxTokens;
                $payload['temperature'] = $options['temperature'] ?? $this->temperature;
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post("{$this->baseUrl}/chat/completions", $payload);

            if (!$response->successful()) {
                $error = $response->json('error.message', 'Unknown error');
                Log::error('OpenAI API error', ['status' => $response->status(), 'error' => $error]);
                return AiResponse::failure($error, $this->getName(), $this->model);
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;
            $usage = $data['usage'] ?? [];

            return AiResponse::success(
                content: $choice['message']['content'] ?? '',
                provider: $this->getName(),
                model: $data['model'] ?? $this->model,
                promptTokens: $usage['prompt_tokens'] ?? 0,
                completionTokens: $usage['completion_tokens'] ?? 0,
                finishReason: $choice['finish_reason'] ?? null,
                raw: $data,
            );
        } catch (\Exception $e) {
            Log::error('OpenAI API exception', ['error' => $e->getMessage()]);
            return AiResponse::failure($e->getMessage(), $this->getName(), $this->model);
        }
    }

    public function complete(string $prompt, array $options = []): AiResponse
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $options);
    }
}
