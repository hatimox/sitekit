@php
    $server = $getRecord();
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    {{-- Security Audit --}}
    <button
        type="button"
        x-data
        x-on:click.prevent="openAiChat({{ Js::from("Run a security audit on my server '{$server->name}' ({$server->ip_address}). Check for common vulnerabilities, open ports, outdated packages, weak SSH configuration, and suggest security improvements. OS: {$server->os_name} {$server->os_version}.") }})"
        class="p-4 text-left bg-gray-50 dark:bg-gray-800 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group border border-gray-200 dark:border-gray-700"
    >
        <x-heroicon-o-shield-check class="w-6 h-6 text-gray-400 group-hover:text-primary-500 mb-2" />
        <p class="text-sm font-medium text-gray-900 dark:text-white">Security Audit</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Check for vulnerabilities</p>
    </button>

    {{-- Optimize Server --}}
    <button
        type="button"
        x-data
        x-on:click.prevent="openAiChat({{ Js::from("Analyze and optimize my server '{$server->name}' ({$server->ip_address}). Current stats: CPU " . ($server->latestStats()?->cpu_percent ?? 'N/A') . "%, Memory " . ($server->latestStats()?->memory_percent ?? 'N/A') . "%, Disk " . ($server->latestStats()?->disk_percent ?? 'N/A') . "%. Check what is using the most resources, identify bottlenecks, and suggest optimizations for CPU, memory, disk I/O, and network. OS: {$server->os_name} {$server->os_version}.") }})"
        class="p-4 text-left bg-gray-50 dark:bg-gray-800 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group border border-gray-200 dark:border-gray-700"
    >
        <x-heroicon-o-rocket-launch class="w-6 h-6 text-gray-400 group-hover:text-primary-500 mb-2" />
        <p class="text-sm font-medium text-gray-900 dark:text-white">Optimize Server</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Improve performance</p>
    </button>

    {{-- Cleanup Disk --}}
    <button
        type="button"
        x-data
        x-on:click.prevent="openAiChat({{ Js::from("Show me what is using the most disk space on my server '{$server->name}' ({$server->ip_address}). Current disk usage is " . ($server->latestStats()?->disk_percent ?? 'N/A') . "%. List the largest files and directories, identify old logs, cache files, and temporary files that can be safely cleaned up to free space.") }})"
        class="p-4 text-left bg-gray-50 dark:bg-gray-800 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group border border-gray-200 dark:border-gray-700"
    >
        <x-heroicon-o-trash class="w-6 h-6 text-gray-400 group-hover:text-primary-500 mb-2" />
        <p class="text-sm font-medium text-gray-900 dark:text-white">Cleanup Disk</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Free up disk space</p>
    </button>

    {{-- Analyze Logs --}}
    <button
        type="button"
        x-data
        x-on:click.prevent="openAiChat({{ Js::from("Analyze the logs on my server '{$server->name}' ({$server->ip_address}). What should I look for in /var/log? Show me how to find recent errors in syslog, auth.log, nginx error logs, and PHP-FPM logs. Summarize any issues you find.") }})"
        class="p-4 text-left bg-gray-50 dark:bg-gray-800 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group border border-gray-200 dark:border-gray-700"
    >
        <x-heroicon-o-document-text class="w-6 h-6 text-gray-400 group-hover:text-primary-500 mb-2" />
        <p class="text-sm font-medium text-gray-900 dark:text-white">Analyze Logs</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Find errors and issues</p>
    </button>

    {{-- Nginx Config --}}
    <button
        type="button"
        x-data
        x-on:click.prevent="openAiChat({{ Js::from("Generate an optimized nginx configuration for my server '{$server->name}'. Include proper caching headers, gzip compression, PHP-FPM connection settings, security headers, and best practices for Laravel/PHP applications. Current OS: {$server->os_name} {$server->os_version}.") }})"
        class="p-4 text-left bg-gray-50 dark:bg-gray-800 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group border border-gray-200 dark:border-gray-700"
    >
        <x-heroicon-o-cog-6-tooth class="w-6 h-6 text-gray-400 group-hover:text-primary-500 mb-2" />
        <p class="text-sm font-medium text-gray-900 dark:text-white">Nginx Config</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Generate optimized config</p>
    </button>

    {{-- PHP-FPM Tuning --}}
    <button
        type="button"
        x-data
        x-on:click.prevent="openAiChat({{ Js::from("Help me tune PHP-FPM settings for my server '{$server->name}' which has " . ($server->cpu_count ?? 'unknown') . " CPU cores and " . ($server->memory_mb ?? 'unknown') . " MB RAM. Suggest optimal values for pm.max_children, pm.start_servers, pm.min_spare_servers, pm.max_spare_servers, and explain the trade-offs.") }})"
        class="p-4 text-left bg-gray-50 dark:bg-gray-800 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group border border-gray-200 dark:border-gray-700"
    >
        <x-heroicon-o-adjustments-horizontal class="w-6 h-6 text-gray-400 group-hover:text-primary-500 mb-2" />
        <p class="text-sm font-medium text-gray-900 dark:text-white">PHP-FPM Tuning</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Optimize worker settings</p>
    </button>

    {{-- MySQL/PostgreSQL Optimization --}}
    <button
        type="button"
        x-data
        x-on:click.prevent="openAiChat({{ Js::from("Suggest MySQL/MariaDB optimization settings for my server '{$server->name}' with " . ($server->cpu_count ?? 'unknown') . " CPU cores and " . ($server->memory_mb ?? 'unknown') . " MB RAM. What values should I use for innodb_buffer_pool_size, max_connections, query_cache_size, and other key settings?") }})"
        class="p-4 text-left bg-gray-50 dark:bg-gray-800 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group border border-gray-200 dark:border-gray-700"
    >
        <x-heroicon-o-circle-stack class="w-6 h-6 text-gray-400 group-hover:text-primary-500 mb-2" />
        <p class="text-sm font-medium text-gray-900 dark:text-white">Database Tuning</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Optimize MySQL/PostgreSQL</p>
    </button>

    {{-- SSH Hardening --}}
    <button
        type="button"
        x-data
        x-on:click.prevent="openAiChat({{ Js::from("Help me harden SSH security on my server '{$server->name}' ({$server->ip_address}). Current SSH port is " . ($server->ssh_port ?? 22) . ". Suggest changes to sshd_config for better security, explain how to set up key-only authentication, and recommend fail2ban configuration.") }})"
        class="p-4 text-left bg-gray-50 dark:bg-gray-800 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group border border-gray-200 dark:border-gray-700"
    >
        <x-heroicon-o-key class="w-6 h-6 text-gray-400 group-hover:text-primary-500 mb-2" />
        <p class="text-sm font-medium text-gray-900 dark:text-white">SSH Hardening</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Secure SSH access</p>
    </button>
</div>
