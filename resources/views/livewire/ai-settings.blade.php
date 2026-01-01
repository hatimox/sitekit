<x-filament::section>
    <x-slot name="heading">
        <div class="flex items-center gap-2">
            <x-heroicon-o-sparkles class="w-5 h-5 text-primary-500" />
            AI Assistant Configuration
        </div>
    </x-slot>

    <x-slot name="description">
        Configure AI features for your team. You can use your own API keys or rely on platform-provided keys if available.
    </x-slot>

    <form wire:submit="updateAiSettings" class="space-y-6">
        {{-- AI Enabled Toggle --}}
        <div class="flex items-center justify-between">
            <div>
                <x-filament::input.wrapper>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Enable AI Features
                    </label>
                </x-filament::input.wrapper>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Turn on AI-powered assistance for troubleshooting, explanations, and recommendations.
                </p>
            </div>
            <x-filament::input.checkbox
                wire:model="ai_enabled"
            />
        </div>

        @if($ai_enabled)
            {{-- Provider Selection --}}
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Preferred AI Provider
                </label>
                <select
                    wire:model="ai_provider"
                    class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="openai">OpenAI (GPT-4/GPT-5)</option>
                    <option value="anthropic">Anthropic (Claude)</option>
                    <option value="gemini">Google (Gemini)</option>
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Select which AI provider to use. Other providers will be used as fallback.
                </p>
            </div>

            {{-- Status Banner --}}
            @if($this->hasGlobalKeys)
                <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-4 border border-green-200 dark:border-green-800">
                    <div class="flex items-start gap-3">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 mt-0.5" />
                        <div>
                            <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                Platform AI keys are available
                            </p>
                            <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                You can use AI features without adding your own keys. Add your own keys below if you prefer to use your own API quota.
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 p-4 border border-amber-200 dark:border-amber-800">
                    <div class="flex items-start gap-3">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-500 mt-0.5" />
                        <div>
                            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                                API keys required
                            </p>
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                Add at least one API key below to enable AI features. Get your keys from
                                <a href="https://platform.openai.com/api-keys" target="_blank" class="underline">OpenAI</a>,
                                <a href="https://console.anthropic.com/settings/keys" target="_blank" class="underline">Anthropic</a>, or
                                <a href="https://aistudio.google.com/app/apikey" target="_blank" class="underline">Google AI Studio</a>.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- API Keys Section --}}
            <div class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Your API Keys (Optional)</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Keys are encrypted and stored securely. They are only used when making AI requests for your team.
                </p>

                {{-- OpenAI Key --}}
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        OpenAI API Key
                    </label>
                    <div class="mt-1 flex gap-2">
                        <input
                            type="{{ $showOpenAiKey ? 'text' : 'password' }}"
                            wire:model="openai_key"
                            placeholder="sk-..."
                            class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                        />
                        <button
                            type="button"
                            wire:click="$toggle('showOpenAiKey')"
                            class="px-3 py-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            @if($showOpenAiKey)
                                <x-heroicon-o-eye-slash class="w-5 h-5" />
                            @else
                                <x-heroicon-o-eye class="w-5 h-5" />
                            @endif
                        </button>
                        @if($openai_key && $openai_key === '••••••••••••••••')
                            <button
                                type="button"
                                wire:click="removeOpenAiKey"
                                wire:confirm="Are you sure you want to remove your OpenAI API key?"
                                class="px-3 py-2 text-red-500 hover:text-red-700"
                            >
                                <x-heroicon-o-trash class="w-5 h-5" />
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Anthropic Key --}}
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Anthropic API Key
                    </label>
                    <div class="mt-1 flex gap-2">
                        <input
                            type="{{ $showAnthropicKey ? 'text' : 'password' }}"
                            wire:model="anthropic_key"
                            placeholder="sk-ant-..."
                            class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                        />
                        <button
                            type="button"
                            wire:click="$toggle('showAnthropicKey')"
                            class="px-3 py-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            @if($showAnthropicKey)
                                <x-heroicon-o-eye-slash class="w-5 h-5" />
                            @else
                                <x-heroicon-o-eye class="w-5 h-5" />
                            @endif
                        </button>
                        @if($anthropic_key && $anthropic_key === '••••••••••••••••')
                            <button
                                type="button"
                                wire:click="removeAnthropicKey"
                                wire:confirm="Are you sure you want to remove your Anthropic API key?"
                                class="px-3 py-2 text-red-500 hover:text-red-700"
                            >
                                <x-heroicon-o-trash class="w-5 h-5" />
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Gemini Key --}}
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Google Gemini API Key
                    </label>
                    <div class="mt-1 flex gap-2">
                        <input
                            type="{{ $showGeminiKey ? 'text' : 'password' }}"
                            wire:model="gemini_key"
                            placeholder="AI..."
                            class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                        />
                        <button
                            type="button"
                            wire:click="$toggle('showGeminiKey')"
                            class="px-3 py-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            @if($showGeminiKey)
                                <x-heroicon-o-eye-slash class="w-5 h-5" />
                            @else
                                <x-heroicon-o-eye class="w-5 h-5" />
                            @endif
                        </button>
                        @if($gemini_key && $gemini_key === '••••••••••••••••')
                            <button
                                type="button"
                                wire:click="removeGeminiKey"
                                wire:confirm="Are you sure you want to remove your Gemini API key?"
                                class="px-3 py-2 text-red-500 hover:text-red-700"
                            >
                                <x-heroicon-o-trash class="w-5 h-5" />
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="flex items-center gap-4">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="updateAiSettings">Save AI Settings</span>
                <span wire:loading wire:target="updateAiSettings">Saving...</span>
            </x-filament::button>

            <x-filament::link
                :href="route('filament.app.pages.ai-demo', ['tenant' => $team->id])"
                color="gray"
            >
                Test AI Assistant
            </x-filament::link>
        </div>

        @if(session('ai_settings_saved'))
            <div class="text-sm text-green-600 dark:text-green-400">
                AI settings saved successfully.
            </div>
        @endif
    </form>
</x-filament::section>
