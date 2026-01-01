<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Status Timeline (24h)
        </x-slot>

        <div class="space-y-4">
            {{-- Uptime Stats --}}
            <div class="grid grid-cols-4 gap-4 text-center text-sm">
                <div>
                    <div class="text-gray-500 dark:text-gray-400">24h</div>
                    <div class="text-lg font-semibold {{ $uptime_24h !== null ? ($uptime_24h >= 99 ? 'text-green-600' : ($uptime_24h >= 90 ? 'text-yellow-600' : 'text-red-600')) : 'text-gray-400' }}">
                        {{ $uptime_24h !== null ? number_format($uptime_24h, 2) . '%' : '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500 dark:text-gray-400">7d</div>
                    <div class="text-lg font-semibold {{ $uptime_7d !== null ? ($uptime_7d >= 99 ? 'text-green-600' : ($uptime_7d >= 90 ? 'text-yellow-600' : 'text-red-600')) : 'text-gray-400' }}">
                        {{ $uptime_7d !== null ? number_format($uptime_7d, 2) . '%' : '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500 dark:text-gray-400">30d</div>
                    <div class="text-lg font-semibold {{ $uptime_30d !== null ? ($uptime_30d >= 99 ? 'text-green-600' : ($uptime_30d >= 90 ? 'text-yellow-600' : 'text-red-600')) : 'text-gray-400' }}">
                        {{ $uptime_30d !== null ? number_format($uptime_30d, 2) . '%' : '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500 dark:text-gray-400">Avg Response</div>
                    <div class="text-lg font-semibold {{ $avg_response_time !== null ? 'text-blue-600' : 'text-gray-400' }}">
                        {{ $avg_response_time !== null ? number_format($avg_response_time, 0) . 'ms' : '-' }}
                    </div>
                </div>
            </div>

            {{-- Timeline Bars --}}
            <div class="flex gap-0.5 h-8 items-end">
                @foreach($slots as $slot)
                    <div
                        class="flex-1 rounded-sm transition-all cursor-pointer hover:opacity-80 {{
                            $slot['status'] === 'up' ? 'bg-green-500' :
                            ($slot['status'] === 'degraded' ? 'bg-yellow-500' :
                            ($slot['status'] === 'down' ? 'bg-red-500' : 'bg-gray-300 dark:bg-gray-600'))
                        }}"
                        style="height: {{ $slot['total'] > 0 ? max(20, min(100, $slot['total'] * 5)) : 30 }}%"
                        title="{{ $slot['date'] }} {{ $slot['hour'] }}: {{ $slot['up'] }} up, {{ $slot['down'] }} down"
                    ></div>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="flex gap-4 justify-center text-xs text-gray-500 dark:text-gray-400">
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 rounded-sm bg-green-500"></div>
                    <span>Up</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 rounded-sm bg-yellow-500"></div>
                    <span>Degraded</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 rounded-sm bg-red-500"></div>
                    <span>Down</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 rounded-sm bg-gray-300 dark:bg-gray-600"></div>
                    <span>No data</span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
