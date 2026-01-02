<?php

namespace App\Services\ConfigGenerator;

use App\Models\WebApp;

class NodeNginxConfigGenerator
{
    /**
     * Framework-specific static asset paths and configurations.
     */
    public const FRAMEWORK_CONFIGS = [
        'nextjs' => [
            'name' => 'Next.js',
            'static_path' => '/_next/static/',
            'health_check' => '/api/health',
            'build_command' => 'npm run build',
            'start_command' => 'npm start',
        ],
        'nuxtjs' => [
            'name' => 'Nuxt.js',
            'static_path' => '/_nuxt/',
            'health_check' => '/api/health',
            'build_command' => 'npm run build',
            'start_command' => 'node .output/server/index.mjs',
        ],
        'nestjs' => [
            'name' => 'NestJS',
            'static_path' => null,
            'health_check' => '/health',
            'build_command' => 'npm run build',
            'start_command' => 'node dist/main',
        ],
        'express' => [
            'name' => 'Express.js',
            'static_path' => '/public/',
            'health_check' => '/health',
            'build_command' => null,
            'start_command' => 'node src/index.js',
        ],
        'remix' => [
            'name' => 'Remix',
            'static_path' => '/build/',
            'health_check' => '/healthcheck',
            'build_command' => 'npm run build',
            'start_command' => 'npm start',
        ],
        'astro' => [
            'name' => 'Astro',
            'static_path' => '/_astro/',
            'health_check' => null,
            'build_command' => 'npm run build',
            'start_command' => 'node ./dist/server/entry.mjs',
        ],
        'sveltekit' => [
            'name' => 'SvelteKit',
            'static_path' => '/_app/',
            'health_check' => '/health',
            'build_command' => 'npm run build',
            'start_command' => 'node build',
        ],
        'custom' => [
            'name' => 'Custom Node.js',
            'static_path' => null,
            'health_check' => null,
            'build_command' => 'npm run build',
            'start_command' => 'npm start',
        ],
    ];

    /**
     * Generate Nginx config for a Node.js application.
     */
    public function generate(WebApp $app): string
    {
        if (!empty($app->proxy_routes)) {
            return $this->generateWithRoutes($app);
        }

        return $this->generateSimple($app);
    }

    /**
     * Generate simple reverse proxy config for single-process Node.js app.
     */
    protected function generateSimple(WebApp $app): string
    {
        $domains = $this->getDomains($app);
        $port = $app->node_port;
        $staticBlock = $this->generateStaticAssetsBlock($app);
        $healthCheckBlock = $this->generateHealthCheckBlock($app);
        $rootPath = $app->root_path . '/current';

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domains};

    access_log /var/log/nginx/{$app->domain}.access.log;
    error_log /var/log/nginx/{$app->domain}.error.log;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;
{$staticBlock}{$healthCheckBlock}
    location / {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
        proxy_read_timeout 86400;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX;
    }

    /**
     * Generate Nginx config with path-based routing for monorepo apps.
     */
    public function generateWithRoutes(WebApp $app): string
    {
        $domains = $this->getDomains($app);
        $staticBlock = $this->generateStaticAssetsBlock($app);
        $healthCheckBlock = $this->generateHealthCheckBlock($app);
        $locationBlocks = $this->generateLocationBlocks($app);

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domains};

    access_log /var/log/nginx/{$app->domain}.access.log;
    error_log /var/log/nginx/{$app->domain}.error.log;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;
{$staticBlock}{$healthCheckBlock}{$locationBlocks}
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX;
    }

    /**
     * Generate Nginx config with SSL for Node.js application.
     */
    public function generateSSL(WebApp $app): string
    {
        $httpConfig = $this->generate($app);
        $domains = $this->getDomains($app);
        $domain = $app->domain;
        $port = $app->node_port;
        $staticBlock = $this->generateStaticAssetsBlock($app);
        $healthCheckBlock = $this->generateHealthCheckBlock($app);

        // For monorepo apps with routes
        if (!empty($app->proxy_routes)) {
            $locationBlocks = $this->generateLocationBlocks($app);
            $mainLocation = $locationBlocks;
        } else {
            $mainLocation = <<<NGINX

    location / {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
        proxy_read_timeout 86400;
    }
NGINX;
        }

        $sslBlock = <<<NGINX

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {$domains};

    ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_stapling on;
    ssl_stapling_verify on;

    access_log /var/log/nginx/{$domain}.access.log;
    error_log /var/log/nginx/{$domain}.error.log;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
{$staticBlock}{$healthCheckBlock}{$mainLocation}

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX;

        return $httpConfig . $sslBlock;
    }

    /**
     * Generate static assets location block for framework-specific paths.
     */
    public function generateStaticAssetsBlock(WebApp $app): string
    {
        $staticPath = $app->static_assets_path;
        $rootPath = $app->root_path . '/current';

        if (empty($staticPath)) {
            return '';
        }

        // Ensure path starts and ends with /
        $staticPath = '/' . trim($staticPath, '/') . '/';

        return <<<NGINX

    # Static assets with aggressive caching
    location {$staticPath} {
        alias {$rootPath}{$staticPath};
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
NGINX;
    }

    /**
     * Generate health check location block.
     */
    protected function generateHealthCheckBlock(WebApp $app): string
    {
        if (empty($app->health_check_path)) {
            return '';
        }

        $port = $app->node_port;

        return <<<NGINX

    # Health check endpoint (no logging)
    location = {$app->health_check_path} {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        access_log off;
    }
NGINX;
    }

    /**
     * Generate location blocks for path-based routing.
     */
    protected function generateLocationBlocks(WebApp $app): string
    {
        if (empty($app->proxy_routes)) {
            return '';
        }

        $blocks = '';
        foreach ($app->proxy_routes as $route) {
            // Route format: ['/api/' => 3000] or ['path' => '/api/', 'port' => 3000]
            if (is_array($route) && isset($route['path']) && isset($route['port'])) {
                $path = $route['path'];
                $port = $route['port'];
            } else {
                // Handle ['/api/' => 3000] format
                $path = array_key_first($route);
                $port = $route[$path];
            }

            $blocks .= <<<NGINX

    location {$path} {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
    }
NGINX;
        }

        return $blocks;
    }

    /**
     * Get framework options for UI dropdowns.
     */
    public static function getFrameworkOptions(): array
    {
        $options = [];
        foreach (self::FRAMEWORK_CONFIGS as $key => $config) {
            $options[$key] = $config['name'];
        }
        return $options;
    }

    /**
     * Get framework config by key.
     */
    public static function getFrameworkConfig(string $framework): ?array
    {
        return self::FRAMEWORK_CONFIGS[$framework] ?? null;
    }

    /**
     * Get default static assets path for a framework.
     */
    public static function getFrameworkStaticPath(string $framework): ?string
    {
        return self::FRAMEWORK_CONFIGS[$framework]['static_path'] ?? null;
    }

    /**
     * Get default health check path for a framework.
     */
    public static function getFrameworkHealthPath(string $framework): ?string
    {
        return self::FRAMEWORK_CONFIGS[$framework]['health_check'] ?? null;
    }

    /**
     * Get default start command for a framework.
     */
    public static function getFrameworkStartCommand(string $framework): ?string
    {
        return self::FRAMEWORK_CONFIGS[$framework]['start_command'] ?? null;
    }

    /**
     * Get default build command for a framework.
     */
    public static function getFrameworkBuildCommand(string $framework): ?string
    {
        return self::FRAMEWORK_CONFIGS[$framework]['build_command'] ?? null;
    }

    /**
     * Get domain string for Nginx server_name directive.
     */
    protected function getDomains(WebApp $app): string
    {
        return collect([$app->domain, ...($app->aliases ?? [])])->implode(' ');
    }
}
