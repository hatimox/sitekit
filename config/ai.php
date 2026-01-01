<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Enable or disable AI features globally. When disabled, all AI triggers
    | and chat functionality will be hidden from the UI.
    |
    */
    'enabled' => env('AI_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default AI provider to use when no team-specific provider is set.
    | Supported: "openai", "anthropic", "gemini"
    |
    */
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Provider Fallback Order
    |--------------------------------------------------------------------------
    |
    | When the primary provider fails, try these providers in order.
    |
    */
    'fallback_order' => ['openai', 'anthropic', 'gemini'],

    /*
    |--------------------------------------------------------------------------
    | AI Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Configure each AI provider with their API keys and model settings.
    | Team-specific keys will override these global keys when present.
    |
    */
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-5'),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 4096),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929'),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),
            'temperature' => env('ANTHROPIC_TEMPERATURE', 0.7),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3-flash-preview'),
            'max_tokens' => env('GEMINI_MAX_TOKENS', 4096),
            'temperature' => env('GEMINI_TEMPERATURE', 0.7),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for AI requests per team.
    |
    */
    'rate_limits' => [
        'requests_per_minute' => env('AI_RATE_LIMIT_PER_MINUTE', 20),
        'requests_per_hour' => env('AI_RATE_LIMIT_PER_HOUR', 200),
        'requests_per_day' => env('AI_RATE_LIMIT_PER_DAY', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Settings
    |--------------------------------------------------------------------------
    |
    | Configure how much context to include in AI requests.
    |
    */
    'context' => [
        'max_log_lines' => 100,
        'max_history_messages' => 20,
        'include_server_stats' => true,
        'include_recent_errors' => true,
        'include_service_status' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    |
    | The base system prompt used for all AI interactions.
    |
    */
    'system_prompt' => <<<'PROMPT'
You are SiteKit AI, an expert DevOps assistant integrated into the SiteKit server management platform.

Your role is to help users:
- Diagnose server issues (CPU, memory, disk, network)
- Troubleshoot deployment failures
- Optimize configurations (Nginx, PHP-FPM, MySQL, PostgreSQL)
- Explain error messages and logs
- Suggest security improvements
- Guide through common tasks

Guidelines:
- Be concise and actionable
- Provide specific commands when helpful
- Explain technical concepts simply
- Always consider security implications
- Warn before destructive operations
- Use markdown formatting for readability

When suggesting commands:
- Use ```bash code blocks
- Explain what each command does
- Provide the option to execute directly when safe

Current context will be provided about the user's servers, applications, and recent activity.
PROMPT,

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Cache similar queries to reduce API costs and improve response time.
    |
    */
    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'ttl' => env('AI_CACHE_TTL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Log AI interactions for debugging and usage tracking.
    |
    */
    'logging' => [
        'enabled' => env('AI_LOGGING_ENABLED', true),
        'log_prompts' => env('AI_LOG_PROMPTS', false), // Privacy consideration
        'log_responses' => env('AI_LOG_RESPONSES', false),
    ],
];
