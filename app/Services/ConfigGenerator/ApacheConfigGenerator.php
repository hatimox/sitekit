<?php

namespace App\Services\ConfigGenerator;

use App\Models\WebApp;

class ApacheConfigGenerator
{
    public function generate(WebApp $app): string
    {
        $domain = $app->domain;
        $documentRoot = $app->document_root;
        $username = $app->system_user;
        $phpVersion = $app->php_version;
        $aliases = $app->aliases ?? [];

        $serverAliases = '';
        if (!empty($aliases)) {
            $serverAliases = 'ServerAlias ' . implode(' ', $aliases);
        }

        return <<<APACHE
<VirtualHost 127.0.0.1:8080>
    ServerName {$domain}
    {$serverAliases}
    DocumentRoot {$documentRoot}

    <Directory {$documentRoot}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php{$phpVersion}-fpm-{$app->id}.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog /var/log/apache2/{$domain}-error.log
    CustomLog /var/log/apache2/{$domain}-access.log combined

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
</VirtualHost>
APACHE;
    }

    public function generateSSL(WebApp $app): string
    {
        $domain = $app->domain;
        $documentRoot = $app->document_root;
        $phpVersion = $app->php_version;
        $aliases = $app->aliases ?? [];

        $serverAliases = '';
        if (!empty($aliases)) {
            $serverAliases = 'ServerAlias ' . implode(' ', $aliases);
        }

        $httpConfig = $this->generate($app);

        $sslConfig = <<<APACHE

<VirtualHost 127.0.0.1:8443>
    ServerName {$domain}
    {$serverAliases}
    DocumentRoot {$documentRoot}

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/{$domain}/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/{$domain}/privkey.pem

    <Directory {$documentRoot}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php{$phpVersion}-fpm-{$app->id}.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog /var/log/apache2/{$domain}-ssl-error.log
    CustomLog /var/log/apache2/{$domain}-ssl-access.log combined

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</VirtualHost>
APACHE;

        return $httpConfig . $sslConfig;
    }
}
