<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Services\AI\AiService;
use App\Services\AI\KeyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AiController extends Controller
{
    public function __construct(
        protected AiService $aiService,
        protected KeyResolver $keyResolver,
    ) {}

    /**
     * Send a chat message to AI.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:10000',
            'conversation_id' => 'nullable|uuid',
            'context_type' => 'nullable|string|in:server,webapp,database,service,cronjob,supervisor',
            'context_id' => 'nullable|uuid',
            'provider' => 'nullable|string|in:openai,anthropic,gemini',
        ]);

        $user = $request->user();
        $team = $user->currentTeam;

        // Rate limiting
        $rateLimitKey = "ai:chat:{$team->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, config('ai.rate_limits.requests_per_minute', 20))) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again in a moment.',
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 60);

        // Check if AI is enabled
        if (!$this->keyResolver->isEnabled($team)) {
            return response()->json([
                'success' => false,
                'error' => $this->keyResolver->getDisabledReason($team),
                'settings_url' => route('filament.app.tenant.profile', ['tenant' => $team->id]),
            ], 403);
        }

        // Configure AI service with context
        $this->aiService->forTeam($team)->forUser($user);

        if (!empty($validated['context_type']) && !empty($validated['context_id'])) {
            $this->aiService->withContext($validated['context_type'], $validated['context_id']);
        }

        // Send message
        $response = $this->aiService->chat(
            $validated['message'],
            $validated['conversation_id'] ?? null,
            ['provider' => $validated['provider'] ?? null]
        );

        return response()->json([
            'success' => $response->isSuccess(),
            'message' => $response->content,
            'provider' => $response->provider,
            'model' => $response->model,
            'tokens' => $response->totalTokens,
            'error' => $response->error,
        ]);
    }

    /**
     * Quick explain without conversation history.
     */
    public function explain(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:5000',
            'context' => 'nullable|array',
        ]);

        $user = $request->user();
        $team = $user->currentTeam;

        // Rate limiting
        $rateLimitKey = "ai:explain:{$team->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, config('ai.rate_limits.requests_per_minute', 20))) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded.',
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 60);

        if (!$this->keyResolver->isEnabled($team)) {
            return response()->json([
                'success' => false,
                'error' => $this->keyResolver->getDisabledReason($team),
                'settings_url' => route('filament.app.tenant.profile', ['tenant' => $team->id]),
            ], 403);
        }

        $this->aiService->forTeam($team)->forUser($user);

        $response = $this->aiService->explain(
            $validated['prompt'],
            $validated['context'] ?? []
        );

        return response()->json([
            'success' => $response->isSuccess(),
            'message' => $response->content,
            'error' => $response->error,
        ]);
    }

    /**
     * Get conversation history.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;

        $conversations = AiConversation::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'context_type', 'context_id', 'provider', 'updated_at']);

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    /**
     * Get a single conversation.
     */
    public function conversation(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;

        $conversation = AiConversation::where('id', $id)
            ->where('team_id', $team->id)
            ->first();

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        return response()->json([
            'conversation' => $conversation,
        ]);
    }

    /**
     * Delete a conversation.
     */
    public function deleteConversation(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;

        $deleted = AiConversation::where('id', $id)
            ->where('team_id', $team->id)
            ->delete();

        return response()->json([
            'success' => $deleted > 0,
        ]);
    }

    /**
     * Get AI usage statistics.
     */
    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;

        $period = $request->query('period', 'day');

        $stats = \App\Models\AiUsageLog::getTeamStats($team->id, $period);

        return response()->json([
            'stats' => $stats,
            'period' => $period,
        ]);
    }

    /**
     * Get available AI providers for the team.
     */
    public function providers(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;

        return response()->json([
            'enabled' => $this->keyResolver->isEnabled($team),
            'providers' => $this->keyResolver->getAvailableProviders($team),
            'preferred' => $this->keyResolver->getPreferredProvider($team),
        ]);
    }
}
