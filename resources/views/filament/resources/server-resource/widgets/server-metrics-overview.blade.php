<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Server Metrics (Last 24 Hours)
        </x-slot>

        @if (!$hasStats)
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <x-heroicon-o-chart-bar class="w-12 h-12 mx-auto mb-4 opacity-50" />
                <p>No metrics data available yet.</p>
                <p class="text-sm mt-2">Metrics will appear once your server starts reporting data.</p>
            </div>
        @else
            {{-- Current Stats Summary --}}
            @if ($latestStats)
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">CPU</div>
                        <div class="text-2xl font-bold mt-1 @if($latestStats->isCpuHigh()) text-red-600 @else text-blue-600 @endif">
                            {{ number_format($latestStats->cpu_percent, 1) }}%
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Memory</div>
                        <div class="text-2xl font-bold mt-1 @if($latestStats->isMemoryWarning()) text-yellow-600 @elseif($latestStats->isMemoryCritical()) text-red-600 @else text-green-600 @endif">
                            {{ number_format($latestStats->memory_percent, 1) }}%
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ $latestStats->memory_used_formatted }} / {{ $latestStats->memory_total_formatted }}
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Disk</div>
                        <div class="text-2xl font-bold mt-1 @if($latestStats->isDiskWarning()) text-yellow-600 @elseif($latestStats->isDiskCritical()) text-red-600 @else text-amber-600 @endif">
                            {{ number_format($latestStats->disk_percent, 1) }}%
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ $latestStats->disk_used_formatted }} / {{ $latestStats->disk_total_formatted }}
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Load Average</div>
                        <div class="text-2xl font-bold mt-1 text-purple-600">
                            {{ number_format($latestStats->load_1m, 2) }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ number_format($latestStats->load_5m, 2) }}, {{ number_format($latestStats->load_15m, 2) }}
                        </div>
                    </div>
                </div>
            @endif

            {{-- Tabs for Charts --}}
            <x-filament::tabs>
                <x-filament::tabs.item
                    :active="$activeTab === 'all'"
                    wire:click="$set('activeTab', 'all')"
                >
                    All Metrics
                </x-filament::tabs.item>
                <x-filament::tabs.item
                    :active="$activeTab === 'cpu'"
                    wire:click="$set('activeTab', 'cpu')"
                >
                    CPU
                </x-filament::tabs.item>
                <x-filament::tabs.item
                    :active="$activeTab === 'memory'"
                    wire:click="$set('activeTab', 'memory')"
                >
                    Memory
                </x-filament::tabs.item>
                <x-filament::tabs.item
                    :active="$activeTab === 'disk'"
                    wire:click="$set('activeTab', 'disk')"
                >
                    Disk
                </x-filament::tabs.item>
                <x-filament::tabs.item
                    :active="$activeTab === 'load'"
                    wire:click="$set('activeTab', 'load')"
                >
                    Load
                </x-filament::tabs.item>
            </x-filament::tabs>

            <div class="mt-4">
                @livewire(\App\Filament\Resources\ServerResource\Widgets\ServerMetricsChart::class, [
                    'record' => $server,
                    'metric' => $activeTab,
                ], key('metrics-chart-' . $activeTab))
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
