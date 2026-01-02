<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-filament::section>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg">
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">GitHub</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Connect your GitHub account</p>
                    </div>
                </div>
                <a
                    href="{{ $this->getConnectUrl('github') }}"
                    x-on:click.prevent="window.location.href = '{{ $this->getConnectUrl('github') }}'"
                    class="inline-flex items-center px-4 py-2 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-sm font-medium rounded-lg hover:bg-gray-700 dark:hover:bg-gray-200 transition"
                >
                    Connect
                </a>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                        <svg class="w-8 h-8 text-orange-600" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.65 14.39L12 22.13 1.35 14.39a.84.84 0 0 1-.3-.94l1.22-3.78 2.44-7.51A.42.42 0 0 1 4.82 2a.43.43 0 0 1 .58 0 .42.42 0 0 1 .11.18l2.44 7.49h8.1l2.44-7.51A.42.42 0 0 1 18.6 2a.43.43 0 0 1 .58 0 .42.42 0 0 1 .11.18l2.44 7.51L23 13.45a.84.84 0 0 1-.35.94z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">GitLab</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Connect your GitLab account</p>
                    </div>
                </div>
                <a
                    href="{{ $this->getConnectUrl('gitlab') }}"
                    x-on:click.prevent="window.location.href = '{{ $this->getConnectUrl('gitlab') }}'"
                    class="inline-flex items-center px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-500 transition"
                >
                    Connect
                </a>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg class="w-8 h-8 text-blue-600" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M.778 1.211a.768.768 0 00-.768.892l3.263 19.81c.084.5.515.868 1.022.873H19.95a.772.772 0 00.77-.646l3.27-20.03a.768.768 0 00-.768-.893zM14.52 15.53H9.522L8.17 8.466h7.561z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">Bitbucket</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Connect your Bitbucket account</p>
                    </div>
                </div>
                <a
                    href="{{ $this->getConnectUrl('bitbucket') }}"
                    x-on:click.prevent="window.location.href = '{{ $this->getConnectUrl('bitbucket') }}'"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-500 transition"
                >
                    Connect
                </a>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">
            Connected Providers
        </x-slot>
        <x-slot name="description">
            Manage your connected Git providers for deployments.
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
