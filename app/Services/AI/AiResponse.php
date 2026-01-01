<?php

namespace App\Services\AI;

class AiResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly int $totalTokens = 0,
        public readonly bool $success = true,
        public readonly ?string $error = null,
        public readonly ?string $finishReason = null,
        public readonly array $raw = [],
    ) {}

    /**
     * Create a successful response.
     */
    public static function success(
        string $content,
        string $provider,
        string $model,
        int $promptTokens = 0,
        int $completionTokens = 0,
        ?string $finishReason = null,
        array $raw = [],
    ): self {
        return new self(
            content: $content,
            provider: $provider,
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $promptTokens + $completionTokens,
            success: true,
            finishReason: $finishReason,
            raw: $raw,
        );
    }

    /**
     * Create a failed response.
     */
    public static function failure(string $error, string $provider, string $model): self
    {
        return new self(
            content: '',
            provider: $provider,
            model: $model,
            success: false,
            error: $error,
        );
    }

    /**
     * Check if the response was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get estimated cost in USD (rough estimates).
     */
    public function getEstimatedCost(): float
    {
        // Pricing per 1M tokens (approximate as of Dec 2025)
        $pricing = [
            'gpt-5' => ['input' => 5.00, 'output' => 15.00],
            'gpt-5-mini' => ['input' => 1.00, 'output' => 4.00],
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'claude-sonnet-4-5-20250929' => ['input' => 3.00, 'output' => 15.00],
            'claude-opus-4-5-20251101' => ['input' => 5.00, 'output' => 25.00],
            'claude-3-5-haiku-20241022' => ['input' => 0.25, 'output' => 1.25],
            'gemini-3-flash-preview' => ['input' => 0.075, 'output' => 0.30],
            'gemini-3-pro-preview' => ['input' => 1.25, 'output' => 5.00],
        ];

        $modelPricing = $pricing[$this->model] ?? ['input' => 1.00, 'output' => 2.00];

        $inputCost = ($this->promptTokens / 1_000_000) * $modelPricing['input'];
        $outputCost = ($this->completionTokens / 1_000_000) * $modelPricing['output'];

        return $inputCost + $outputCost;
    }

    /**
     * Convert to array for JSON responses.
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'provider' => $this->provider,
            'model' => $this->model,
            'tokens' => [
                'prompt' => $this->promptTokens,
                'completion' => $this->completionTokens,
                'total' => $this->totalTokens,
            ],
            'success' => $this->success,
            'error' => $this->error,
            'finish_reason' => $this->finishReason,
        ];
    }
}
