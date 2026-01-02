<?php

namespace App\Services\ConfigGenerator;

use App\Models\WebApp;

class NginxConfigGenerator
{
    public function generate(WebApp $app): string
    {
        return match ($app->web_server) {
            'nginx' => $this->generatePureNginx($app),
            'nginx_apache' => $this->generateHybridNginx($app),
            default => $this->generatePureNginx($app),
        };
    }

    protected function generatePureNginx(WebApp $app): string
    {
        $domains = collect([$app->domain, ...($app->aliases ?? [])])->implode(' ');
        $documentRoot = $app->document_root;
        $phpVersion = $app->php_version;
        $appId = $app->id;

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domains};
    root {$documentRoot};

    index index.php index.html index.htm;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php{$phpVersion}-fpm-{$appId}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX;
    }

    protected function generateHybridNginx(WebApp $app): string
    {
        $domains = collect([$app->domain, ...($app->aliases ?? [])])->implode(' ');

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domains};

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX;
    }

    public function generateSSL(WebApp $app): string
    {
        $base = $this->generate($app);
        $domain = $app->domain;
        $domains = collect([$app->domain, ...($app->aliases ?? [])])->implode(' ');
        $documentRoot = $app->document_root;
        $phpVersion = $app->php_version;
        $appId = $app->id;

        $sslBlock = <<<NGINX

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {$domains};
    root {$documentRoot};

    ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;

    index index.php index.html index.htm;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php{$phpVersion}-fpm-{$appId}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX;

        return $base . $sslBlock;
    }
}
