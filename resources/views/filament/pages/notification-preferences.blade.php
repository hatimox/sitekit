<x-filament-panels::page>
    <div class="flex gap-x-1 overflow-x-auto border-b border-gray-200 dark:border-gray-700">
        <button
            wire:click="$set('activeTab', 'preferences')"
            @class([
                'flex items-center gap-x-2 px-4 py-3 text-sm font-medium border-b-2 -mb-px transition-colors',
                'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' => $activeTab === 'preferences',
                'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== 'preferences',
            ])
        >
            <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
            Preferences
        </button>

        <button
            wire:click="$set('activeTab', 'history')"
            @class([
                'flex items-center gap-x-2 px-4 py-3 text-sm font-medium border-b-2 -mb-px transition-colors',
                'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' => $activeTab === 'history',
                'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== 'history',
            ])
        >
            <x-heroicon-o-clock class="w-5 h-5" />
            History
            @if($this->unreadCount > 0)
                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-medium rounded-full bg-danger-500 text-white">
                    {{ $this->unreadCount }}
                </span>
            @endif
        </button>
    </div>

    <div class="mt-6">
        @if($activeTab === 'preferences')
            {{-- Preferences Tab --}}
            <div class="space-y-6">
                @php
                    $eventTypes = \App\Models\NotificationPreference::getEventTypes();
                    $categories = collect($eventTypes)->mapWithKeys(fn ($config, $key) => [$key => array_merge($config, ['event_type' => $key])])
                        ->groupBy(fn ($config) => $config['category']);
                    $frequencyOptions = \App\Models\NotificationPreference::getFrequencyOptions();
                @endphp

                @foreach($categories as $category => $events)
                    <x-filament::section :heading="$category" collapsible>
                        <x-slot name="description">
                            @switch($category)
                                @case('Servers') Notifications about server status and provisioning @break
                                @case('Deployments') Notifications about code deployments @break
                                @case('SSL Certificates') Notifications about SSL certificate issuance and expiry @break
                                @case('Backups') Notifications about database backup operations @break
                                @case('Monitoring') Notifications about health monitor status changes @break
                                @case('Cron Jobs') Notifications about scheduled task failures @break
                                @case('Services') Notifications about service restarts and changes @break
                                @case('Server Resources') Notifications about CPU, memory, and disk usage thresholds @break
                            @endswitch
                        </x-slot>

                        <div class="space-y-4">
                            @foreach($events as $config)
                                @php $eventType = $config['event_type']; @endphp
                                <div wire:key="pref-{{ $eventType }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center py-3 border-b border-gray-100 dark:border-gray-800 last:border-0">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $config['label'] }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $config['description'] }}</div>
                                    </div>

                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            id="in_app_{{ $eventType }}"
                                            name="in_app_{{ $eventType }}"
                                            wire:model="preferences.{{ $eventType }}.in_app_enabled"
                                            x-data
                                            x-init="$el.checked = {{ ($this->preferences[$eventType]['in_app_enabled'] ?? false) ? 'true' : 'false' }}"
                                            class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="text-sm text-gray-600 dark:text-gray-400">In-App</span>
                                    </label>

                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            id="email_{{ $eventType }}"
                                            name="email_{{ $eventType }}"
                                            wire:model.live="preferences.{{ $eventType }}.email_enabled"
                                            x-data
                                            x-init="$el.checked = {{ ($this->preferences[$eventType]['email_enabled'] ?? false) ? 'true' : 'false' }}"
                                            class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Email</span>
                                    </label>

                                    <div>
                                        @if($this->preferences[$eventType]['email_enabled'] ?? false)
                                            <select
                                                id="freq_{{ $eventType }}"
                                                name="freq_{{ $eventType }}"
                                                wire:model.live="preferences.{{ $eventType }}.email_frequency"
                                                class="fi-select-input block w-full rounded-lg border-gray-300 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-primary-500 text-sm"
                                            >
                                                @foreach($frequencyOptions as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endforeach

                <div class="mt-6">
                    <x-filament::button wire:click="save">
                        Save Preferences
                    </x-filament::button>
                </div>
            </div>
        @else
            {{-- History Tab --}}
            <div class="space-y-4">
                {{-- Actions Bar --}}
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Showing your recent notifications
                    </div>
                    @if($this->unreadCount > 0)
                        <x-filament::button
                            wire:click="markAllAsRead"
                            color="gray"
                            size="sm"
                        >
                            Mark all as read
                        </x-filament::button>
                    @endif
                </div>

                {{-- Notifications List --}}
                @forelse($this->notifications as $notification)
                    <div
                        wire:key="notif-{{ $notification->id }}"
                        class="relative flex gap-4 p-4 rounded-xl border transition-colors {{ $notification->read_at ? 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700' : 'bg-primary-50 dark:bg-primary-900/20 border-primary-200 dark:border-primary-800' }}"
                    >
                        {{-- Icon --}}
                        <div class="flex-shrink-0">
                            @php
                                $type = $notification->data['type'] ?? 'info';
                                $iconClass = match(true) {
                                    str_contains($type, 'failed') || str_contains($type, 'offline') || str_contains($type, 'down') => 'text-danger-500 bg-danger-100 dark:bg-danger-900/50',
                                    str_contains($type, 'success') || str_contains($type, 'completed') || str_contains($type, 'recovered') || str_contains($type, 'issued') => 'text-success-500 bg-success-100 dark:bg-success-900/50',
                                    str_contains($type, 'warning') || str_contains($type, 'expiring') => 'text-warning-500 bg-warning-100 dark:bg-warning-900/50',
                                    default => 'text-primary-500 bg-primary-100 dark:bg-primary-900/50',
                                };
                            @endphp
                            <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $iconClass }}">
                                @switch(true)
                                    @case(str_contains($type, 'server'))
                                        <x-heroicon-o-server class="w-5 h-5" />
                                        @break
                                    @case(str_contains($type, 'deployment'))
                                        <x-heroicon-o-rocket-launch class="w-5 h-5" />
                                        @break
                                    @case(str_contains($type, 'ssl'))
                                        <x-heroicon-o-lock-closed class="w-5 h-5" />
                                        @break
                                    @case(str_contains($type, 'monitor') || str_contains($type, 'health'))
                                        <x-heroicon-o-heart class="w-5 h-5" />
                                        @break
                                    @case(str_contains($type, 'backup'))
                                        <x-heroicon-o-circle-stack class="w-5 h-5" />
                                        @break
                                    @case(str_contains($type, 'firewall'))
                                        <x-heroicon-o-shield-exclamation class="w-5 h-5" />
                                        @break
                                    @default
                                        <x-heroicon-o-bell class="w-5 h-5" />
                                @endswitch
                            </div>
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $this->getNotificationTitle($notification) }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $this->getNotificationBody($notification) }}
                                    </p>
                                </div>
                                <div class="flex-shrink-0 flex items-center gap-2">
                                    @if(!$notification->read_at)
                                        <span class="inline-flex items-center rounded-full bg-primary-100 dark:bg-primary-900 px-2 py-1 text-xs font-medium text-primary-700 dark:text-primary-300">
                                            New
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-2 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ $notification->created_at->diffForHumans() }}</span>
                                @if(!$notification->read_at)
                                    <button
                                        wire:click="markAsRead('{{ $notification->id }}')"
                                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400"
                                    >
                                        Mark as read
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <x-filament::section>
                        <div class="text-center py-12">
                            <x-heroicon-o-bell-slash class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">No notifications yet</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                When you receive notifications, they'll appear here.
                            </p>
                        </div>
                    </x-filament::section>
                @endforelse

                {{-- Pagination --}}
                @if($this->notifications->hasPages())
                    <div class="mt-4">
                        {{ $this->notifications->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
