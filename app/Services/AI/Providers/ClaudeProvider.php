<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiProviderInterface;
use App\Services\AI\AiResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $maxTokens;
    protected float $temperature;

    public function __construct()
    {
        $this->apiKey = config('ai.providers.anthropic.api_key', '');
        $this->model = config('ai.providers.anthropic.model', 'claude-sonnet-4-5-20250929');
        $this->baseUrl = config('ai.providers.anthropic.base_url', 'https://api.anthropic.com');
        $this->maxTokens = config('ai.providers.anthropic.max_tokens', 4096);
        $this->temperature = config('ai.providers.anthropic.temperature', 0.7);
    }

    public function getName(): string
    {
        return 'anthropic';
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
            return AiResponse::failure('Anthropic API key not configured', $this->getName(), $this->model);
        }

        try {
            // Extract system message if present
            $systemMessage = null;
            $chatMessages = [];

            foreach ($messages as $message) {
                if ($message['role'] === 'system') {
                    $systemMessage = $message['content'];
                } else {
                    $chatMessages[] = [
                        'role' => $message['role'],
                        'content' => $message['content'],
                    ];
                }
            }

            $payload = [
                'model' => $options['model'] ?? $this->model,
                'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
                'messages' => $chatMessages,
            ];

            if ($systemMessage) {
                $payload['system'] = $systemMessage;
            }

            if (isset($options['temperature'])) {
                $payload['temperature'] = $options['temperature'];
            }

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ])->timeout(60)->post("{$this->baseUrl}/v1/messages", $payload);

            if (!$response->successful()) {
                $error = $response->json('error.message', 'Unknown error');
                Log::error('Claude API error', ['status' => $response->status(), 'error' => $error, 'body' => $response->body()]);
                return AiResponse::failure($error, $this->getName(), $this->model);
            }

            $data = $response->json();
            $content = '';

            // Claude returns content as an array of content blocks
            foreach ($data['content'] ?? [] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }

            $usage = $data['usage'] ?? [];

            return AiResponse::success(
                content: $content,
                provider: $this->getName(),
                model: $data['model'] ?? $this->model,
                promptTokens: $usage['input_tokens'] ?? 0,
                completionTokens: $usage['output_tokens'] ?? 0,
                finishReason: $data['stop_reason'] ?? null,
                raw: $data,
            );
        } catch (\Exception $e) {
            Log::error('Claude API exception', ['error' => $e->getMessage()]);
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
