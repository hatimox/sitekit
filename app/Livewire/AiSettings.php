<?php

namespace App\Livewire;

use App\Models\Team;
use App\Services\AI\KeyResolver;
use Illuminate\Support\Facades\Crypt;
use Livewire\Component;

class AiSettings extends Component
{
    public Team $team;

    public bool $ai_enabled = true;
    public string $ai_provider = 'openai';
    public string $openai_key = '';
    public string $anthropic_key = '';
    public string $gemini_key = '';

    public bool $showOpenAiKey = false;
    public bool $showAnthropicKey = false;
    public bool $showGeminiKey = false;

    protected KeyResolver $keyResolver;

    public function boot(KeyResolver $keyResolver): void
    {
        $this->keyResolver = $keyResolver;
    }

    public function mount(Team $team): void
    {
        $this->team = $team;
        $this->ai_enabled = $team->ai_enabled ?? true;
        $this->ai_provider = $team->ai_provider ?? config('ai.default_provider', 'openai');

        // Show masked keys if they exist
        $this->openai_key = $team->ai_openai_key ? '••••••••••••••••' : '';
        $this->anthropic_key = $team->ai_anthropic_key ? '••••••••••••••••' : '';
        $this->gemini_key = $team->ai_gemini_key ? '••••••••••••••••' : '';
    }

    public function updateAiSettings(): void
    {
        $this->validate([
            'ai_enabled' => 'boolean',
            'ai_provider' => 'required|in:openai,anthropic,gemini',
        ]);

        $updateData = [
            'ai_enabled' => $this->ai_enabled,
            'ai_provider' => $this->ai_provider,
        ];

        // Only update keys if they've been changed (not masked)
        if ($this->openai_key && $this->openai_key !== '••••••••••••••••') {
            if (str_starts_with($this->openai_key, 'sk-')) {
                $updateData['ai_openai_key'] = Crypt::encryptString($this->openai_key);
            }
        }

        if ($this->anthropic_key && $this->anthropic_key !== '••••••••••••••••') {
            if (str_starts_with($this->anthropic_key, 'sk-ant-')) {
                $updateData['ai_anthropic_key'] = Crypt::encryptString($this->anthropic_key);
            }
        }

        if ($this->gemini_key && $this->gemini_key !== '••••••••••••••••') {
            if (str_starts_with($this->gemini_key, 'AI')) {
                $updateData['ai_gemini_key'] = Crypt::encryptString($this->gemini_key);
            }
        }

        $this->team->update($updateData);

        // Re-mask the keys
        $this->openai_key = $this->team->ai_openai_key ? '••••••••••••••••' : '';
        $this->anthropic_key = $this->team->ai_anthropic_key ? '••••••••••••••••' : '';
        $this->gemini_key = $this->team->ai_gemini_key ? '••••••••••••••••' : '';

        session()->flash('ai_settings_saved', true);
        $this->dispatch('saved');
    }

    public function removeOpenAiKey(): void
    {
        $this->team->update(['ai_openai_key' => null]);
        $this->openai_key = '';
        $this->dispatch('saved');
    }

    public function removeAnthropicKey(): void
    {
        $this->team->update(['ai_anthropic_key' => null]);
        $this->anthropic_key = '';
        $this->dispatch('saved');
    }

    public function removeGeminiKey(): void
    {
        $this->team->update(['ai_gemini_key' => null]);
        $this->gemini_key = '';
        $this->dispatch('saved');
    }

    public function getHasGlobalKeysProperty(): bool
    {
        return $this->keyResolver->hasGlobalKeys();
    }

    public function getAvailableProvidersProperty(): array
    {
        return $this->keyResolver->getAvailableProviders($this->team);
    }

    public function render()
    {
        return view('livewire.ai-settings');
    }
}
