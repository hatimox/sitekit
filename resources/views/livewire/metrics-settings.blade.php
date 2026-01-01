<x-filament::section>
    <x-slot name="heading">
        Metrics Settings
    </x-slot>
    <x-slot name="description">
        Configure how service metrics are collected and stored for your team.
    </x-slot>

    <form wire:submit="updateMetricsSettings">
        <div class="space-y-6">
            {{-- Metrics Collection Interval --}}
            <div>
                <label class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    Collection Interval
                </label>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                    How often service metrics (CPU, memory, uptime) are collected from your servers.
                </p>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="metrics_interval_seconds">
                        <option value="60">Every 1 minute</option>
                        <option value="120">Every 2 minutes</option>
                        <option value="300">Every 5 minutes (Recommended)</option>
                        <option value="600">Every 10 minutes</option>
                        <option value="900">Every 15 minutes</option>
                        <option value="1800">Every 30 minutes</option>
                        <option value="3600">Every 1 hour</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                @error('metrics_interval_seconds')
                    <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Shorter intervals provide more detailed data but use more storage.
                </p>
            </div>

            {{-- Metrics Retention Period --}}
            <div class="border-t pt-6 dark:border-gray-700">
                <label class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    Retention Period
                </label>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                    How long to keep historical metrics data.
                </p>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="metrics_retention_days">
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="30">30 days (Recommended)</option>
                        <option value="60">60 days</option>
                        <option value="90">90 days</option>
                        <option value="180">180 days</option>
                        <option value="365">365 days</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                @error('metrics_retention_days')
                    <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Older metrics will be automatically deleted to save storage.
                </p>
            </div>

            {{-- Estimated Storage Usage --}}
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Estimated Storage Usage</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    With current settings, approximately
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                        {{ number_format((86400 / $metrics_interval_seconds) * $metrics_retention_days) }}
                    </span>
                    data points per service will be stored.
                </p>
            </div>

            {{-- Save Button --}}
            <div class="flex items-center gap-4 border-t pt-6 dark:border-gray-700">
                <x-filament::button type="submit">
                    Save Metrics Settings
                </x-filament::button>

                <x-action-message class="text-sm text-success-600" on="saved">
                    Saved.
                </x-action-message>
            </div>
        </div>
    </form>
</x-filament::section>
