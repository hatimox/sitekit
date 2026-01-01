<x-filament::section>
    <x-slot name="heading">
        Team Integrations
    </x-slot>
    <x-slot name="description">
        Configure Slack and Discord webhooks to receive team notifications.
    </x-slot>

    <form wire:submit="updateNotificationSettings">
        <div class="space-y-6">
            {{-- Email Preferences Link --}}
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">Email Notifications</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Control which events trigger email notifications and how often.
                        </p>
                    </div>
                    <a href="{{ route('filament.app.pages.notification-preferences', ['tenant' => $team->id]) }}"
                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                        Manage Preferences
                        <x-heroicon-o-arrow-right class="ml-1.5 h-4 w-4" />
                    </a>
                </div>
            </div>

            {{-- Slack Integration --}}
            <div>
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">Slack Integration</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Send notifications to a Slack channel
                        </p>
                    </div>
                    <label class="flex items-center">
                        <x-filament::input.checkbox wire:model="slack_enabled" />
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Enabled</span>
                    </label>
                </div>

                <div class="mt-4">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="url"
                            wire:model="slack_webhook_url"
                            placeholder="https://hooks.slack.com/services/..."
                        />
                    </x-filament::input.wrapper>
                    @error('slack_webhook_url')
                        <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                    @enderror
                    @if (session('slack_test_success'))
                        <p class="mt-1 text-sm text-success-600">{{ session('slack_test_success') }}</p>
                    @endif
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        <a href="https://api.slack.com/messaging/webhooks" target="_blank" class="text-primary-600 hover:underline">
                            Learn how to create a Slack webhook
                        </a>
                    </p>
                </div>

                @if ($slack_webhook_url)
                    <div class="mt-2">
                        <x-filament::button
                            type="button"
                            wire:click="testSlackWebhook"
                            size="sm"
                            color="gray"
                        >
                            Test Slack Webhook
                        </x-filament::button>
                    </div>
                @endif
            </div>

            {{-- Discord Integration --}}
            <div class="border-t pt-6 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">Discord Integration</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Send notifications to a Discord channel
                        </p>
                    </div>
                    <label class="flex items-center">
                        <x-filament::input.checkbox wire:model="discord_enabled" />
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Enabled</span>
                    </label>
                </div>

                <div class="mt-4">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="url"
                            wire:model="discord_webhook_url"
                            placeholder="https://discord.com/api/webhooks/..."
                        />
                    </x-filament::input.wrapper>
                    @error('discord_webhook_url')
                        <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                    @enderror
                    @if (session('discord_test_success'))
                        <p class="mt-1 text-sm text-success-600">{{ session('discord_test_success') }}</p>
                    @endif
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        <a href="https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks" target="_blank" class="text-primary-600 hover:underline">
                            Learn how to create a Discord webhook
                        </a>
                    </p>
                </div>

                @if ($discord_webhook_url)
                    <div class="mt-2">
                        <x-filament::button
                            type="button"
                            wire:click="testDiscordWebhook"
                            size="sm"
                            color="gray"
                        >
                            Test Discord Webhook
                        </x-filament::button>
                    </div>
                @endif
            </div>

            {{-- Save Button --}}
            <div class="flex items-center gap-4 border-t pt-6 dark:border-gray-700">
                <x-filament::button type="submit">
                    Save Notification Settings
                </x-filament::button>

                <x-action-message class="text-sm text-success-600" on="saved">
                    Saved.
                </x-action-message>
            </div>
        </div>
    </form>
</x-filament::section>
