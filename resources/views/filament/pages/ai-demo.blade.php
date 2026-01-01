<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Introduction --}}
        <div class="p-4 bg-primary-50 dark:bg-primary-900/20 rounded-xl border border-primary-200 dark:border-primary-800">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-primary-100 dark:bg-primary-800 rounded-lg">
                    <x-heroicon-o-sparkles class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="font-semibold text-primary-900 dark:text-primary-100">AI Assistant Demo</h3>
                    <p class="text-sm text-primary-700 dark:text-primary-300 mt-1">
                        This page demonstrates how AI is integrated throughout SiteKit.
                        Click any AI trigger button or press <kbd class="px-1.5 py-0.5 bg-primary-200 dark:bg-primary-700 rounded text-xs">Cmd+/</kbd> to open the chat.
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Left Column: Mock Dashboard with Inline Triggers --}}
            <div class="space-y-6">
                {{-- Server Health Card --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Server Health</h3>
                            <button
                                x-data x-on:click.prevent="openAiChat('Analyze the health of my server and identify any issues. Check CPU, memory, disk usage and running processes.')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
                            >
                                <x-heroicon-m-sparkles class="w-3.5 h-3.5" />
                                Diagnose with AI
                            </button>
                        </div>
                    </div>
                    <div class="p-4 space-y-4">
                        {{-- CPU Warning --}}
                        <div class="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-yellow-100 dark:bg-yellow-800 rounded-lg">
                                    <x-heroicon-o-cpu-chip class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                                </div>
                                <div>
                                    <p class="font-medium text-yellow-900 dark:text-yellow-100">CPU Usage High</p>
                                    <p class="text-sm text-yellow-700 dark:text-yellow-300">87% average over last hour</p>
                                </div>
                            </div>
                            <button
                                x-data x-on:click.prevent="openAiChat('My server CPU is at 87%. What processes are consuming the most CPU and how can I reduce usage?')"
                                class="text-xs text-yellow-700 dark:text-yellow-300 hover:text-yellow-900 dark:hover:text-yellow-100 underline"
                            >
                                Why?
                            </button>
                        </div>

                        {{-- Memory OK --}}
                        <div class="flex items-center justify-between p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-green-100 dark:bg-green-800 rounded-lg">
                                    <x-heroicon-o-circle-stack class="w-5 h-5 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <p class="font-medium text-green-900 dark:text-green-100">Memory</p>
                                    <p class="text-sm text-green-700 dark:text-green-300">2.1 GB / 4 GB (52%)</p>
                                </div>
                            </div>
                            <span class="text-xs text-green-600 dark:text-green-400 font-medium">Healthy</span>
                        </div>

                        {{-- Disk Warning --}}
                        <div class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-red-100 dark:bg-red-800 rounded-lg">
                                    <x-heroicon-o-server class="w-5 h-5 text-red-600 dark:text-red-400" />
                                </div>
                                <div>
                                    <p class="font-medium text-red-900 dark:text-red-100">Disk Space Critical</p>
                                    <p class="text-sm text-red-700 dark:text-red-300">38 GB / 40 GB (95%)</p>
                                </div>
                            </div>
                            <button
                                x-data x-on:click.prevent="openAiChat('My disk is 95% full at 38GB/40GB. Find the largest files and directories and suggest what can be safely deleted to free up space.')"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-800 rounded hover:bg-red-200 dark:hover:bg-red-700 transition-colors"
                            >
                                <x-heroicon-m-sparkles class="w-3 h-3" />
                                Fix with AI
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Recent Errors Card --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Recent Errors</h3>
                            <button
                                x-data x-on:click.prevent="openAiChat('Analyze all my recent server errors and provide a summary with recommended fixes for each one.')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
                            >
                                <x-heroicon-m-sparkles class="w-3.5 h-3.5" />
                                Analyze All
                            </button>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- Error 1 --}}
                        <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 text-xs font-medium bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded">ERROR</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">2 min ago</span>
                                    </div>
                                    <p class="mt-1 text-sm font-mono text-gray-900 dark:text-white truncate">
                                        SQLSTATE[HY000] [2002] Connection refused
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">laravel.log - myapp.com</p>
                                </div>
                                <button
                                    x-data x-on:click.prevent="openAiChat('Explain this error and how to fix it: SQLSTATE[HY000] [2002] Connection refused. This is from Laravel connecting to MySQL.')"
                                    class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-1 text-xs text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                                >
                                    <x-heroicon-m-sparkles class="w-3.5 h-3.5" />
                                    Explain
                                </button>
                            </div>
                        </div>

                        {{-- Error 2 --}}
                        <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 text-xs font-medium bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded">ERROR</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">15 min ago</span>
                                    </div>
                                    <p class="mt-1 text-sm font-mono text-gray-900 dark:text-white truncate">
                                        PHP Fatal error: Allowed memory size of 134217728 bytes exhausted
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">php-fpm.log - api.myapp.com</p>
                                </div>
                                <button
                                    x-data x-on:click.prevent="openAiChat('PHP Fatal error: Allowed memory size of 134217728 bytes exhausted. How do I increase the PHP memory limit and optimize memory usage in PHP-FPM?')"
                                    class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-1 text-xs text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                                >
                                    <x-heroicon-m-sparkles class="w-3.5 h-3.5" />
                                    Explain
                                </button>
                            </div>
                        </div>

                        {{-- Warning --}}
                        <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 rounded">WARN</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">1 hour ago</span>
                                    </div>
                                    <p class="mt-1 text-sm font-mono text-gray-900 dark:text-white truncate">
                                        SSL certificate expires in 7 days
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">certbot - shop.myapp.com</p>
                                </div>
                                <button
                                    x-data x-on:click.prevent="openAiChat('SSL certificate is expiring in 7 days for shop.myapp.com. How do I renew it with Let\'s Encrypt and certbot?')"
                                    class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-1 text-xs text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                                >
                                    <x-heroicon-m-sparkles class="w-3.5 h-3.5" />
                                    Help
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Deployment Card --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Recent Deployment</h3>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                <span class="text-sm font-medium text-red-600 dark:text-red-400">Failed</span>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">5 min ago</span>
                        </div>
                        <div class="p-3 bg-gray-900 rounded-lg font-mono text-xs text-gray-300 overflow-x-auto">
                            <div class="text-green-400">$ composer install --no-dev</div>
                            <div class="text-gray-400 mt-1">Installing dependencies...</div>
                            <div class="text-red-400 mt-1">PHP Fatal error: composer require ext-redis</div>
                            <div class="text-red-400">Script @php artisan package:discover handling returned error</div>
                            <div class="text-red-400 mt-1">Deployment failed with exit code 1</div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button
                                x-data x-on:click.prevent="openAiChat('My Laravel deployment failed with error: composer require ext-redis. The full error is: PHP Fatal error: composer require ext-redis, Script @php artisan package:discover handling returned error. How do I install the redis PHP extension and fix this deployment?')"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
                            >
                                <x-heroicon-m-sparkles class="w-4 h-4" />
                                Diagnose Failure
                            </button>
                            <button class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                View Full Log
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Quick Actions & AI Suggestions --}}
            <div class="space-y-6">
                {{-- AI Quick Actions --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">AI Quick Actions</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Common tasks you can ask AI to help with</p>
                    </div>
                    <div class="p-4 grid grid-cols-2 gap-3">
                        <button
                            x-data x-on:click.prevent="openAiChat('Run a security audit on my server. Check for common vulnerabilities, open ports, outdated packages, weak SSH configuration, and suggest security improvements.')"
                            class="p-3 text-left bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group"
                        >
                            <x-heroicon-o-shield-check class="w-5 h-5 text-gray-400 group-hover:text-primary-500 mb-2" />
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Security Audit</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Check vulnerabilities</p>
                        </button>

                        <button
                            x-data x-on:click.prevent="openAiChat('Analyze my server performance. Check what is using the most resources, identify bottlenecks, and suggest optimizations for CPU, memory, disk I/O, and network.')"
                            class="p-3 text-left bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group"
                        >
                            <x-heroicon-o-rocket-launch class="w-5 h-5 text-gray-400 group-hover:text-primary-500 mb-2" />
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Optimize</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Improve performance</p>
                        </button>

                        <button
                            x-data x-on:click.prevent="openAiChat('Show me what is using the most disk space on my server. List the largest files and directories, and suggest what can be safely cleaned up to free space.')"
                            class="p-3 text-left bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group"
                        >
                            <x-heroicon-o-trash class="w-5 h-5 text-gray-400 group-hover:text-primary-500 mb-2" />
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Cleanup</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Free disk space</p>
                        </button>

                        <button
                            x-data x-on:click.prevent="openAiChat('Generate an optimized nginx configuration for my Laravel application. Include proper caching headers, gzip compression, PHP-FPM connection settings, and security headers.')"
                            class="p-3 text-left bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group"
                        >
                            <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-gray-400 group-hover:text-primary-500 mb-2" />
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Generate Config</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Nginx, PHP, etc.</p>
                        </button>
                    </div>
                </div>

                {{-- Proactive AI Suggestions --}}
                <div class="bg-gradient-to-br from-primary-50 to-indigo-50 dark:from-primary-900/20 dark:to-indigo-900/20 rounded-xl border border-primary-200 dark:border-primary-800">
                    <div class="p-4 border-b border-primary-200 dark:border-primary-800">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-light-bulb class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                            <h3 class="font-semibold text-primary-900 dark:text-primary-100">AI Suggestions</h3>
                        </div>
                        <p class="text-sm text-primary-700 dark:text-primary-300 mt-1">Proactive insights based on your server</p>
                    </div>
                    <div class="p-4 space-y-3">
                        <div
                            x-data x-on:click.prevent="openAiChat('Help me set up automated backups for my MySQL databases. What are the best practices for backup schedules, retention policies, and how to store backups securely off-site?')"
                            class="p-3 bg-white/60 dark:bg-gray-800/60 rounded-lg cursor-pointer hover:bg-white dark:hover:bg-gray-800 transition-colors"
                        >
                            <div class="flex items-start gap-3">
                                <div class="p-1.5 bg-blue-100 dark:bg-blue-900 rounded">
                                    <x-heroicon-o-arrow-path class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">No backup configured</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">Your database has no automated backups. Click to set up.</p>
                                </div>
                            </div>
                        </div>

                        <div
                            x-data x-on:click.prevent="openAiChat('I want to upgrade PHP from 8.1 to 8.3 on my Ubuntu server. Guide me through the process step by step, including how to update php-fpm, update my web server config, and verify everything works.')"
                            class="p-3 bg-white/60 dark:bg-gray-800/60 rounded-lg cursor-pointer hover:bg-white dark:hover:bg-gray-800 transition-colors"
                        >
                            <div class="flex items-start gap-3">
                                <div class="p-1.5 bg-purple-100 dark:bg-purple-900 rounded">
                                    <x-heroicon-o-arrow-up-circle class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">PHP 8.3 available</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">You're using PHP 8.1. Upgrade for better performance.</p>
                                </div>
                            </div>
                        </div>

                        <div
                            x-data x-on:click.prevent="openAiChat('I have 142 failed SSH login attempts in the last 24 hours. Show me the suspicious login attempts, help me identify the attacking IPs, and set up fail2ban to automatically block them.')"
                            class="p-3 bg-white/60 dark:bg-gray-800/60 rounded-lg cursor-pointer hover:bg-white dark:hover:bg-gray-800 transition-colors"
                        >
                            <div class="flex items-start gap-3">
                                <div class="p-1.5 bg-red-100 dark:bg-red-900 rounded">
                                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-red-600 dark:text-red-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">142 failed SSH attempts</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">Detected in last 24h. Click to investigate.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Keyboard Shortcut Hint --}}
                <div class="bg-gray-900 dark:bg-gray-950 rounded-xl p-4 text-center">
                    <p class="text-gray-400 text-sm">Press <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-300 font-mono text-xs">Cmd</kbd> + <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-300 font-mono text-xs">/</kbd> to open AI chat</p>
                    <p class="text-gray-500 text-xs mt-2">e.g., "why is mysql slow" or "show nginx config"</p>
                </div>

                {{-- Provider Info --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">AI Provider Status</h4>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Default Provider</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ ucfirst(config('ai.default_provider', 'anthropic')) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Model</span>
                            <span class="font-mono text-xs text-gray-900 dark:text-white">{{ config('ai.providers.' . config('ai.default_provider', 'anthropic') . '.model') }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Status</span>
                            <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                Connected
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
