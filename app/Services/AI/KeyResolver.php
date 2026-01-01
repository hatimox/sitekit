<?php

namespace App\Services\AI;

use App\Models\Team;
use Illuminate\Support\Facades\Crypt;

class KeyResolver
{
    /**
     * Resolve the API key for a provider, checking team keys first then falling back to global.
     */
    public function resolve(string $provider, ?Team $team = null): ?string
    {
        // First, check team-specific key
        if ($team && $team->ai_enabled) {
            $teamKey = $this->getTeamKey($team, $provider);
            if ($teamKey) {
                return $teamKey;
            }
        }

        // Fall back to global key
        return $this->getGlobalKey($provider);
    }

    /**
     * Get the team's API key for a provider.
     */
    protected function getTeamKey(Team $team, string $provider): ?string
    {
        $keyField = match ($provider) {
            'openai' => 'ai_openai_key',
            'anthropic' => 'ai_anthropic_key',
            'gemini' => 'ai_gemini_key',
            default => null,
        };

        if (!$keyField) {
            return null;
        }

        $encryptedKey = $team->{$keyField};

        if (!$encryptedKey) {
            return null;
        }

        try {
            return Crypt::decryptString($encryptedKey);
        } catch (\Exception) {
            // If decryption fails, the key might be stored in plain text (legacy)
            return $encryptedKey;
        }
    }

    /**
     * Get the global API key from config.
     */
    protected function getGlobalKey(string $provider): ?string
    {
        return match ($provider) {
            'openai' => config('ai.providers.openai.api_key'),
            'anthropic' => config('ai.providers.anthropic.api_key'),
            'gemini' => config('ai.providers.gemini.api_key'),
            default => null,
        };
    }

    /**
     * Get the preferred provider for a team.
     */
    public function getPreferredProvider(?Team $team = null): string
    {
        // Check team preference
        if ($team && $team->ai_provider) {
            return $team->ai_provider;
        }

        // Fall back to global default
        return config('ai.default_provider', 'openai');
    }

    /**
     * Get all available providers for a team (those with valid keys).
     */
    public function getAvailableProviders(?Team $team = null): array
    {
        $providers = [];

        foreach (['openai', 'anthropic', 'gemini'] as $provider) {
            if ($this->resolve($provider, $team)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Check if AI is enabled for a team.
     */
    public function isEnabled(?Team $team = null): bool
    {
        // Check global toggle
        if (!config('ai.enabled', true)) {
            return false;
        }

        // Check team toggle
        if ($team && !$team->ai_enabled) {
            return false;
        }

        // Check if any provider is available
        return count($this->getAvailableProviders($team)) > 0;
    }

    /**
     * Get a detailed reason why AI is disabled.
     */
    public function getDisabledReason(?Team $team = null): string
    {
        // Check global toggle
        if (!config('ai.enabled', true)) {
            return 'AI features are disabled globally by the administrator.';
        }

        // Check team toggle
        if ($team && !$team->ai_enabled) {
            return 'AI features are disabled for your team. Go to Team Settings to enable them.';
        }

        // Check if any provider is available
        $teamHasKeys = false;
        $globalHasKeys = false;

        foreach (['openai', 'anthropic', 'gemini'] as $provider) {
            if ($this->getGlobalKey($provider)) {
                $globalHasKeys = true;
            }
            if ($team && $this->getTeamKey($team, $provider)) {
                $teamHasKeys = true;
            }
        }

        if (!$globalHasKeys && !$teamHasKeys) {
            return 'No AI API keys configured. Add your API key in Team Settings → AI Configuration to enable AI features.';
        }

        return 'AI features are not available. Please check your configuration.';
    }

    /**
     * Check if team has their own keys (BYOK mode).
     */
    public function teamHasOwnKeys(?Team $team = null): bool
    {
        if (!$team) {
            return false;
        }

        foreach (['openai', 'anthropic', 'gemini'] as $provider) {
            if ($this->getTeamKey($team, $provider)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if global keys are configured by SaaS owner.
     */
    public function hasGlobalKeys(): bool
    {
        foreach (['openai', 'anthropic', 'gemini'] as $provider) {
            if ($this->getGlobalKey($provider)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Encrypt and store a team's API key.
     */
    public function storeTeamKey(Team $team, string $provider, string $apiKey): void
    {
        $keyField = match ($provider) {
            'openai' => 'ai_openai_key',
            'anthropic' => 'ai_anthropic_key',
            'gemini' => 'ai_gemini_key',
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };

        $team->update([
            $keyField => Crypt::encryptString($apiKey),
        ]);
    }

    /**
     * Remove a team's API key.
     */
    public function removeTeamKey(Team $team, string $provider): void
    {
        $keyField = match ($provider) {
            'openai' => 'ai_openai_key',
            'anthropic' => 'ai_anthropic_key',
            'gemini' => 'ai_gemini_key',
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };

        $team->update([
            $keyField => null,
        ]);
    }
}
