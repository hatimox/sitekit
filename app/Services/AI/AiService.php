<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiUsageLog;
use App\Models\Team;
use App\Models\User;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiService
{
    protected KeyResolver $keyResolver;
    protected ?Team $team = null;
    protected ?User $user = null;
    protected ?string $contextType = null;
    protected ?string $contextId = null;

    public function __construct(KeyResolver $keyResolver)
    {
        $this->keyResolver = $keyResolver;
    }

    /**
     * Set the team context for API key resolution.
     */
    public function forTeam(Team $team): self
    {
        $this->team = $team;
        return $this;
    }

    /**
     * Set the user context for logging.
     */
    public function forUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Set the resource context (for contextual AI help).
     */
    public function withContext(string $type, string $id): self
    {
        $this->contextType = $type;
        $this->contextId = $id;
        return $this;
    }

    /**
     * Send a chat message and get a response.
     */
    public function chat(string $message, ?string $conversationId = null, array $options = []): AiResponse
    {
        if (!$this->keyResolver->isEnabled($this->team)) {
            return AiResponse::failure('AI features are not enabled', 'none', 'none');
        }

        $startTime = microtime(true);

        // Get or create conversation
        $conversation = $this->getOrCreateConversation($conversationId);

        // Build messages array with history
        $messages = $this->buildMessages($conversation, $message, $options);

        // Try providers in order
        $response = $this->tryProviders($messages, $options);

        // Log usage
        $this->logUsage($response, $conversation, $startTime);

        // Update conversation if successful
        if ($response->isSuccess()) {
            $this->updateConversation($conversation, $message, $response);
        }

        return $response;
    }

    /**
     * Quick explain without conversation history.
     */
    public function explain(string $prompt, array $context = []): AiResponse
    {
        if (!$this->keyResolver->isEnabled($this->team)) {
            return AiResponse::failure('AI features are not enabled', 'none', 'none');
        }

        $startTime = microtime(true);

        // Check cache
        $cacheKey = $this->getCacheKey($prompt, $context);
        if (config('ai.cache.enabled') && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Build system prompt with context
        $systemPrompt = config('ai.system_prompt');
        if (!empty($context)) {
            $systemPrompt .= "\n\nContext:\n" . json_encode($context, JSON_PRETTY_PRINT);
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ];

        $response = $this->tryProviders($messages);

        // Cache successful responses
        if ($response->isSuccess() && config('ai.cache.enabled')) {
            Cache::put($cacheKey, $response, config('ai.cache.ttl', 3600));
        }

        // Log usage
        $this->logUsage($response, null, $startTime);

        return $response;
    }

    /**
     * Try providers in order until one succeeds.
     */
    protected function tryProviders(array $messages, array $options = []): AiResponse
    {
        $preferredProvider = $options['provider'] ?? $this->keyResolver->getPreferredProvider($this->team);
        $fallbackOrder = config('ai.fallback_order', ['openai', 'anthropic', 'gemini']);

        // Put preferred provider first
        $providersToTry = array_unique(array_merge([$preferredProvider], $fallbackOrder));

        foreach ($providersToTry as $providerName) {
            $apiKey = $this->keyResolver->resolve($providerName, $this->team);
            if (!$apiKey) {
                continue;
            }

            $provider = $this->createProvider($providerName);
            $provider->setApiKey($apiKey);

            $response = $provider->chat($messages, $options);

            if ($response->isSuccess()) {
                return $response;
            }

            Log::warning("AI provider {$providerName} failed, trying next", [
                'error' => $response->error,
            ]);
        }

        return AiResponse::failure(
            'All AI providers failed. Please check your API keys.',
            'none',
            'none'
        );
    }

    /**
     * Create a provider instance.
     */
    protected function createProvider(string $name): AiProviderInterface
    {
        return match ($name) {
            'openai' => new OpenAiProvider(),
            'anthropic' => new ClaudeProvider(),
            'gemini' => new GeminiProvider(),
            default => throw new \InvalidArgumentException("Unknown provider: {$name}"),
        };
    }

    /**
     * Build messages array with system prompt and history.
     */
    protected function buildMessages(AiConversation $conversation, string $newMessage, array $options = []): array
    {
        $messages = [];

        // Add system prompt
        $systemPrompt = $options['system_prompt'] ?? config('ai.system_prompt');
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        // Add conversation history (limited)
        $maxHistory = config('ai.context.max_history_messages', 20);
        $history = $conversation->messages ?? [];
        $history = array_slice($history, -$maxHistory);

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        // Add new user message
        $messages[] = ['role' => 'user', 'content' => $newMessage];

        return $messages;
    }

    /**
     * Get or create a conversation.
     */
    protected function getOrCreateConversation(?string $conversationId): AiConversation
    {
        if ($conversationId) {
            $conversation = AiConversation::find($conversationId);
            if ($conversation) {
                return $conversation;
            }
        }

        return AiConversation::create([
            'id' => Str::uuid(),
            'team_id' => $this->team?->id,
            'user_id' => $this->user?->id,
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'messages' => [],
        ]);
    }

    /**
     * Update conversation with new messages.
     */
    protected function updateConversation(AiConversation $conversation, string $userMessage, AiResponse $response): void
    {
        $messages = $conversation->messages ?? [];

        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
            'timestamp' => now()->toIso8601String(),
        ];

        $messages[] = [
            'role' => 'assistant',
            'content' => $response->content,
            'timestamp' => now()->toIso8601String(),
        ];

        $conversation->update([
            'messages' => $messages,
            'provider' => $response->provider,
            'model' => $response->model,
            'total_tokens' => $conversation->total_tokens + $response->totalTokens,
            'title' => $conversation->title ?? Str::limit($userMessage, 50),
        ]);
    }

    /**
     * Log AI usage for tracking and billing.
     */
    protected function logUsage(AiResponse $response, ?AiConversation $conversation, float $startTime): void
    {
        if (!config('ai.logging.enabled', true)) {
            return;
        }

        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        AiUsageLog::create([
            'id' => Str::uuid(),
            'team_id' => $this->team?->id,
            'user_id' => $this->user?->id,
            'conversation_id' => $conversation?->id,
            'provider' => $response->provider,
            'model' => $response->model,
            'endpoint' => 'chat',
            'prompt_tokens' => $response->promptTokens,
            'completion_tokens' => $response->completionTokens,
            'total_tokens' => $response->totalTokens,
            'cost_usd' => $response->getEstimatedCost(),
            'response_time_ms' => $responseTimeMs,
            'cached' => false,
            'success' => $response->isSuccess(),
            'error_message' => $response->error,
        ]);
    }

    /**
     * Generate a cache key for a prompt.
     */
    protected function getCacheKey(string $prompt, array $context = []): string
    {
        $data = [
            'prompt' => $prompt,
            'context' => $context,
            'team_id' => $this->team?->id,
        ];

        return 'ai:explain:' . md5(json_encode($data));
    }

    /**
     * Get conversation history for a user.
     */
    public function getConversations(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return AiConversation::where('team_id', $this->team?->id)
            ->where('user_id', $this->user?->id)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Delete a conversation.
     */
    public function deleteConversation(string $conversationId): bool
    {
        return AiConversation::where('id', $conversationId)
            ->where('team_id', $this->team?->id)
            ->delete() > 0;
    }
}
