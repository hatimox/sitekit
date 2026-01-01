<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiProviderInterface;
use App\Services\AI\AiResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $maxTokens;
    protected float $temperature;

    public function __construct()
    {
        $this->apiKey = config('ai.providers.gemini.api_key', '');
        $this->model = config('ai.providers.gemini.model', 'gemini-3-flash-preview');
        $this->baseUrl = config('ai.providers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->maxTokens = config('ai.providers.gemini.max_tokens', 4096);
        $this->temperature = config('ai.providers.gemini.temperature', 0.7);
    }

    public function getName(): string
    {
        return 'gemini';
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
            return AiResponse::failure('Gemini API key not configured', $this->getName(), $this->model);
        }

        try {
            $model = $options['model'] ?? $this->model;

            // Convert messages to Gemini format
            $contents = [];
            $systemInstruction = null;

            foreach ($messages as $message) {
                if ($message['role'] === 'system') {
                    $systemInstruction = $message['content'];
                } else {
                    $contents[] = [
                        'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => $message['content']]],
                    ];
                }
            }

            $payload = [
                'contents' => $contents,
                'generationConfig' => [
                    'maxOutputTokens' => $options['max_tokens'] ?? $this->maxTokens,
                    'temperature' => $options['temperature'] ?? $this->temperature,
                ],
            ];

            if ($systemInstruction) {
                $payload['systemInstruction'] = [
                    'parts' => [['text' => $systemInstruction]],
                ];
            }

            $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($url, $payload);

            if (!$response->successful()) {
                $error = $response->json('error.message', 'Unknown error');
                Log::error('Gemini API error', ['status' => $response->status(), 'error' => $error]);
                return AiResponse::failure($error, $this->getName(), $model);
            }

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;
            $content = '';

            if ($candidate && isset($candidate['content']['parts'])) {
                foreach ($candidate['content']['parts'] as $part) {
                    $content .= $part['text'] ?? '';
                }
            }

            $usage = $data['usageMetadata'] ?? [];

            return AiResponse::success(
                content: $content,
                provider: $this->getName(),
                model: $model,
                promptTokens: $usage['promptTokenCount'] ?? 0,
                completionTokens: $usage['candidatesTokenCount'] ?? 0,
                finishReason: $candidate['finishReason'] ?? null,
                raw: $data,
            );
        } catch (\Exception $e) {
            Log::error('Gemini API exception', ['error' => $e->getMessage()]);
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
