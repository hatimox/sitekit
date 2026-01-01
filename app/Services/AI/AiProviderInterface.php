<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    /**
     * Get the provider name identifier.
     */
    public function getName(): string;

    /**
     * Check if the provider is configured and available.
     */
    public function isAvailable(): bool;

    /**
     * Send a chat completion request.
     *
     * @param array $messages Array of messages [{role: 'user'|'assistant'|'system', content: string}]
     * @param array $options Additional options (temperature, max_tokens, etc.)
     * @return AiResponse
     */
    public function chat(array $messages, array $options = []): AiResponse;

    /**
     * Send a simple completion request (single prompt, no history).
     *
     * @param string $prompt The prompt to send
     * @param array $options Additional options
     * @return AiResponse
     */
    public function complete(string $prompt, array $options = []): AiResponse;

    /**
     * Get the model being used.
     */
    public function getModel(): string;

    /**
     * Set a custom API key (for team-specific keys).
     */
    public function setApiKey(string $apiKey): self;
}
