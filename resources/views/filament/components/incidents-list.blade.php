<div class="space-y-3">
    @forelse($incidents as $incident)
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    @if($incident->status === 'resolved')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                            Resolved
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                            Open
                        </span>
                    @endif
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $incident->created_at->format('M j, Y g:i A') }}
                    </span>
                </div>
                @if($incident->resolved_at)
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Duration: {{ $incident->created_at->diffForHumans($incident->resolved_at, true) }}
                    </span>
                @elseif($incident->status !== 'resolved')
                    <span class="text-sm text-red-600 dark:text-red-400">
                        Ongoing for {{ $incident->created_at->diffForHumans(null, true) }}
                    </span>
                @endif
            </div>
            @if($incident->error_message)
                <p class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 p-2 rounded font-mono">
                    {{ $incident->error_message }}
                </p>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
            No incidents recorded.
        </p>
    @endforelse
</div>
