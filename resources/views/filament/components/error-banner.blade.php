@props(['record'])

@if($record->hasError())
<div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
    <div class="flex items-start gap-3">
        <div class="flex-shrink-0">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-red-600 dark:text-red-400" />
        </div>
        <div class="flex-1">
            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                {{ $record->last_error }}
            </h3>
            @if($record->error_age)
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                    Occurred {{ $record->error_age }}
                </p>
            @endif
            @if($record->getSuggestedActionLabel())
                <p class="mt-2 text-sm text-red-700 dark:text-red-300">
                    <span class="font-medium">Suggested:</span> {{ $record->getSuggestedActionLabel() }}
                </p>
            @endif
        </div>
    </div>
</div>
@endif
