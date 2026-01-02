<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SiteKit Agent Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the SiteKit agent that runs on managed servers.
    |
    */

    // Agent version to install on new servers
    'agent_version' => env('SITEKIT_AGENT_VERSION', 'latest'),

    // Base URL for agent binary downloads
    'agent_download_url' => env('SITEKIT_AGENT_DOWNLOAD_URL', 'https://github.com/sitekit/agent/releases'),

    /*
    |--------------------------------------------------------------------------
    | Agent Communication
    |--------------------------------------------------------------------------
    */

    // Heartbeat interval in seconds
    'heartbeat_interval' => env('SITEKIT_HEARTBEAT_INTERVAL', 60),

    // Job polling interval in seconds
    'job_poll_interval' => env('SITEKIT_JOB_POLL_INTERVAL', 5),

    // Stats collection interval in seconds
    'stats_interval' => env('SITEKIT_STATS_INTERVAL', 60),

    // Server offline threshold in minutes (mark offline after this many missed heartbeats)
    'offline_threshold' => env('SITEKIT_OFFLINE_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | SSL Configuration
    |--------------------------------------------------------------------------
    */

    // Let's Encrypt staging mode (for testing)
    'ssl_staging' => env('SITEKIT_SSL_STAGING', false),

    // SSL renewal threshold in days (renew if expiring within this many days)
    'ssl_renewal_days' => env('SITEKIT_SSL_RENEWAL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Deployment Configuration
    |--------------------------------------------------------------------------
    */

    // Number of releases to keep for rollback
    'releases_to_keep' => env('SITEKIT_RELEASES_TO_KEEP', 5),

    // Default deploy script for Laravel apps
    'default_deploy_script' => <<<'BASH'
cd $RELEASE_PATH
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
BASH,

    // Default shared directories
    'default_shared_directories' => [
        'storage',
        'node_modules',
    ],

    // Default shared files
    'default_shared_files' => [
        '.env',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported PHP Versions
    |--------------------------------------------------------------------------
    */

    'php_versions' => [
        '8.4' => 'PHP 8.4 (Latest)',
        '8.3' => 'PHP 8.3 (Recommended)',
        '8.2' => 'PHP 8.2',
        '8.1' => 'PHP 8.1',
        '8.0' => 'PHP 8.0',
        '7.4' => 'PHP 7.4 (Legacy)',
    ],

    'default_php_version' => env('SITEKIT_DEFAULT_PHP_VERSION', '8.3'),

    /*
    |--------------------------------------------------------------------------
    | Node.js Configuration
    |--------------------------------------------------------------------------
    */

    'node_versions' => [
        '24' => 'Node.js 24 (Latest)',
        '22' => 'Node.js 22 LTS (Recommended)',
        '20' => 'Node.js 20 LTS',
        '18' => 'Node.js 18 LTS',
    ],

    'default_node_version' => env('SITEKIT_DEFAULT_NODE_VERSION', '22'),

    // Port range for Node.js applications
    'nodejs_port_min' => env('SITEKIT_NODEJS_PORT_MIN', 3000),
    'nodejs_port_max' => env('SITEKIT_NODEJS_PORT_MAX', 3999),

    // Package managers
    'package_managers' => [
        'npm' => 'npm (Default)',
        'yarn' => 'Yarn',
        'pnpm' => 'pnpm',
    ],

    'default_package_manager' => env('SITEKIT_DEFAULT_PACKAGE_MANAGER', 'npm'),

    /*
    |--------------------------------------------------------------------------
    | Web Server Configuration
    |--------------------------------------------------------------------------
    */

    'web_servers' => [
        'nginx' => 'Nginx (Pure)',
        'nginx_apache' => 'Nginx + Apache (.htaccess support)',
    ],

    'default_web_server' => env('SITEKIT_DEFAULT_WEB_SERVER', 'nginx'),

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    */

    'database_engines' => [
        'mysql' => 'MySQL 8.0',
        'mariadb' => 'MariaDB 10.11',
        'postgresql' => 'PostgreSQL 16',
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Installation Defaults
    |--------------------------------------------------------------------------
    */

    // Default services to install on new servers
    'default_services' => [
        'nginx',
        'php-fpm',
        'mysql',
        'redis',
        'supervisor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Firewall Defaults
    |--------------------------------------------------------------------------
    */

    // Default firewall rules for new servers
    'default_firewall_rules' => [
        ['port' => 22, 'protocol' => 'tcp', 'action' => 'allow', 'description' => 'SSH'],
        ['port' => 80, 'protocol' => 'tcp', 'action' => 'allow', 'description' => 'HTTP'],
        ['port' => 443, 'protocol' => 'tcp', 'action' => 'allow', 'description' => 'HTTPS'],
    ],

    // Firewall confirmation timeout in seconds
    'firewall_confirmation_timeout' => env('SITEKIT_FIREWALL_CONFIRMATION_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    */

    // Health check interval in minutes
    'health_check_interval' => env('SITEKIT_HEALTH_CHECK_INTERVAL', 5),

    // Stats retention in days
    'stats_retention_days' => env('SITEKIT_STATS_RETENTION_DAYS', 30),

    // Alert thresholds
    'alert_thresholds' => [
        'cpu_percent' => env('SITEKIT_ALERT_CPU_THRESHOLD', 90),
        'memory_percent' => env('SITEKIT_ALERT_MEMORY_THRESHOLD', 90),
        'disk_percent' => env('SITEKIT_ALERT_DISK_THRESHOLD', 85),
    ],

    /*
    |--------------------------------------------------------------------------
    | Git Providers
    |--------------------------------------------------------------------------
    */

    'git_providers' => [
        'github' => [
            'name' => 'GitHub',
            'client_id' => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            'redirect' => env('GITHUB_REDIRECT_URI'),
        ],
        'gitlab' => [
            'name' => 'GitLab',
            'client_id' => env('GITLAB_CLIENT_ID'),
            'client_secret' => env('GITLAB_CLIENT_SECRET'),
            'redirect' => env('GITLAB_REDIRECT_URI'),
            'host' => env('GITLAB_HOST', 'https://gitlab.com'),
        ],
        'bitbucket' => [
            'name' => 'Bitbucket',
            'client_id' => env('BITBUCKET_CLIENT_ID'),
            'client_secret' => env('BITBUCKET_CLIENT_SECRET'),
            'redirect' => env('BITBUCKET_REDIRECT_URI'),
        ],
    ],
];
