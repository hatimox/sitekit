<div class="space-y-4">
    <div class="flex items-center gap-2">
        @if($status === 'success')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                Success
            </span>
        @elseif($status === 'failed')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                Failed
            </span>
        @else
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100">
                {{ ucfirst($status ?? 'Unknown') }}
            </span>
        @endif

        @if(isset($exitCode))
            <span class="text-sm text-gray-500 dark:text-gray-400">
                Exit code: {{ $exitCode }}
            </span>
        @endif
    </div>

    <div class="bg-gray-900 text-gray-100 p-4 rounded-lg font-mono text-sm overflow-x-auto max-h-96 overflow-y-auto">
        <pre class="whitespace-pre-wrap break-words">{{ $output }}</pre>
    </div>
</div>
