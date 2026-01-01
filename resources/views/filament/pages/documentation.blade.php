<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden sticky top-4">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Documentation</h3>
                </div>
                <nav class="p-2">
                    @foreach($this->getDocTopics() as $key => $topic)
                        <a
                            href="?topic={{ $key }}"
                            @class([
                                'flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors',
                                'bg-primary-50 text-primary-700 dark:bg-primary-900/50 dark:text-primary-400' => $this->topic === $key,
                                'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700' => $this->topic !== $key,
                            ])
                            wire:navigate
                        >
                            <x-dynamic-component :component="$topic['icon']" class="w-5 h-5" />
                            {{ $topic['title'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </div>

        <!-- Content -->
        <div class="lg:col-span-3">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <!-- Topic Header -->
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    @php
                        $currentTopic = $this->getDocTopics()[$this->topic] ?? $this->getDocTopics()['getting-started'];
                    @endphp
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary-100 dark:bg-primary-900 rounded-lg">
                            <x-dynamic-component :component="$currentTopic['icon']" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $currentTopic['title'] }}</h1>
                    </div>
                </div>

                <!-- Sections -->
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($this->getDocContent() as $sectionKey => $section)
                        <div class="p-6" id="{{ $sectionKey }}">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                {{ $section['title'] }}
                            </h2>
                            <div class="prose dark:prose-invert max-w-none">
                                {!! $section['content'] !!}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
