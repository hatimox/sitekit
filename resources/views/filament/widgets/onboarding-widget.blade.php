<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-rocket-launch class="h-5 w-5 text-primary-500" />
                <span>Getting Started</span>
            </div>
        </x-slot>

        <x-slot name="description">
            Complete these steps to set up your server management platform.
        </x-slot>

        <div class="space-y-4">
            {{-- Progress bar --}}
            <div class="flex items-center gap-3">
                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div
                        class="bg-primary-500 h-2 rounded-full transition-all duration-500"
                        style="width: {{ ($this->getCompletedCount() / $this->getTotalCount()) * 100 }}%"
                    ></div>
                </div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    {{ $this->getCompletedCount() }}/{{ $this->getTotalCount() }}
                </span>
            </div>

            {{-- Steps --}}
            <div class="grid gap-3 md:grid-cols-3">
                @foreach($this->getSteps() as $index => $step)
                    <div @class([
                        'relative p-4 rounded-lg border-2 transition-all',
                        'border-success-500 bg-success-50 dark:bg-success-900/20' => $step['completed'],
                        'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800' => !$step['completed'] && !($step['disabled'] ?? false),
                        'border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 opacity-60' => $step['disabled'] ?? false,
                    ])>
                        {{-- Step number or check --}}
                        <div @class([
                            'absolute -top-2 -left-2 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold',
                            'bg-success-500 text-white' => $step['completed'],
                            'bg-primary-500 text-white' => !$step['completed'] && !($step['disabled'] ?? false),
                            'bg-gray-300 dark:bg-gray-600 text-gray-500' => $step['disabled'] ?? false,
                        ])>
                            @if($step['completed'])
                                <x-heroicon-m-check class="w-4 h-4" />
                            @else
                                {{ $index + 1 }}
                            @endif
                        </div>

                        <div class="flex items-start gap-3 pt-1">
                            <div @class([
                                'p-2 rounded-lg',
                                'bg-success-100 dark:bg-success-900/40 text-success-600 dark:text-success-400' => $step['completed'],
                                'bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400' => !$step['completed'],
                            ])>
                                @svg($step['icon'], 'w-5 h-5')
                            </div>

                            <div class="flex-1 min-w-0">
                                <h4 @class([
                                    'font-medium',
                                    'text-success-700 dark:text-success-300 line-through' => $step['completed'],
                                    'text-gray-900 dark:text-white' => !$step['completed'],
                                ])>
                                    {{ $step['title'] }}
                                </h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ $step['description'] }}
                                </p>

                                @if(!$step['completed'] && $step['url'] && !($step['disabled'] ?? false))
                                    <a
                                        href="{{ $step['url'] }}"
                                        class="inline-flex items-center gap-1 mt-2 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                    >
                                        Get started
                                        <x-heroicon-m-arrow-right class="w-4 h-4" />
                                    </a>
                                @elseif($step['disabled'] ?? false)
                                    <span class="inline-flex items-center gap-1 mt-2 text-sm text-gray-400">
                                        <x-heroicon-m-lock-closed class="w-3 h-3" />
                                        Complete previous steps first
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
