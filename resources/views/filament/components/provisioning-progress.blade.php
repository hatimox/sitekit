<div class="space-y-4">
    {{-- Progress Bar --}}
    <div class="relative pt-1">
        <div class="flex mb-2 items-center justify-between">
            <div>
                <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-primary-600 bg-primary-200 dark:text-primary-400 dark:bg-primary-900">
                    Progress
                </span>
            </div>
            <div class="text-right">
                <span class="text-xs font-semibold inline-block text-primary-600 dark:text-primary-400">
                    {{ $progress }}%
                </span>
            </div>
        </div>
        <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-200 dark:bg-gray-700">
            <div style="width:{{ $progress }}%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-primary-500 transition-all duration-500"></div>
        </div>
    </div>

    {{-- Steps Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($steps->groupBy('category') as $category => $categorySteps)
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                    {{ ucfirst(str_replace('_', ' ', $category)) }}
                </h4>
                <div class="space-y-2">
                    @foreach($categorySteps as $step)
                        <div class="flex items-center gap-2 text-sm">
                            @switch($step->status)
                                @case('completed')
                                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                                    @break
                                @case('in_progress')
                                    <x-heroicon-o-arrow-path class="w-5 h-5 text-blue-500 flex-shrink-0 animate-spin" />
                                    @break
                                @case('queued')
                                    <x-heroicon-o-clock class="w-5 h-5 text-yellow-500 flex-shrink-0" />
                                    @break
                                @case('failed')
                                    <x-heroicon-o-x-circle class="w-5 h-5 text-red-500 flex-shrink-0" />
                                    @break
                                @case('skipped')
                                    <x-heroicon-o-minus-circle class="w-5 h-5 text-gray-400 flex-shrink-0" />
                                    @break
                                @default
                                    <x-heroicon-o-ellipsis-horizontal-circle class="w-5 h-5 text-gray-400 flex-shrink-0" />
                            @endswitch

                            <span class="flex-grow truncate {{ $step->status === 'completed' ? 'text-gray-600 dark:text-gray-400' : 'text-gray-900 dark:text-gray-100' }}">
                                {{ $step->step_name }}
                            </span>

                            @if($step->duration_seconds !== null && $step->status === 'completed')
                                <span class="text-xs text-gray-500 flex-shrink-0">
                                    {{ $step->getFormattedDuration() }}
                                </span>
                            @endif

                            @if($step->status === 'failed')
                                <button
                                    type="button"
                                    wire:click="retryStep({{ $step->id }})"
                                    class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 flex-shrink-0"
                                    title="Retry"
                                >
                                    <x-heroicon-o-arrow-path class="w-4 h-4" />
                                </button>
                            @endif
                        </div>

                        @if($step->status === 'failed' && $step->error_message)
                            <div class="ml-7 text-xs text-red-600 dark:text-red-400 truncate" title="{{ $step->error_message }}">
                                {{ Str::limit($step->error_message, 50) }}
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- Summary --}}
    <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-4">
            <span>
                <x-heroicon-o-check-circle class="w-4 h-4 inline text-green-500" />
                {{ $steps->where('status', 'completed')->count() }} completed
            </span>
            @if($steps->where('status', 'in_progress')->count() > 0)
                <span>
                    <x-heroicon-o-arrow-path class="w-4 h-4 inline text-blue-500 animate-spin" />
                    {{ $steps->where('status', 'in_progress')->count() }} in progress
                </span>
            @endif
            @if($steps->where('status', 'failed')->count() > 0)
                <span>
                    <x-heroicon-o-x-circle class="w-4 h-4 inline text-red-500" />
                    {{ $steps->where('status', 'failed')->count() }} failed
                </span>
            @endif
        </div>
        <div>
            {{ $steps->where('status', 'completed')->count() + $steps->where('status', 'skipped')->count() }} / {{ $steps->count() }} steps
        </div>
    </div>
</div>
