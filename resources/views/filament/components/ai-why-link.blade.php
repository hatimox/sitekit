@props(['prompt'])

<button
    type="button"
    x-data
    x-on:click.prevent="openAiChat({{ Js::from($prompt) }})"
    class="ml-2 inline-flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 underline cursor-pointer"
>
    <x-heroicon-m-sparkles class="w-3 h-3" />
    Why?
</button>
