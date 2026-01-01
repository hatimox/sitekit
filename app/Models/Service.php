<?php

namespace App\Models;

use App\Models\Concerns\HasErrorTracking;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Service extends Model
{
    use HasFactory, HasUuids, LogsActivity, HasErrorTracking;

    // Service Types
    public const TYPE_PHP = 'php';
    public const TYPE_NODEJS = 'nodejs';
    public const TYPE_MYSQL = 'mysql';
    public const TYPE_MARIADB = 'mariadb';
    public const TYPE_POSTGRESQL = 'postgresql';
    public const TYPE_REDIS = 'redis';
    public const TYPE_MEMCACHED = 'memcached';
    public const TYPE_NGINX = 'nginx';
    public const TYPE_APACHE = 'apache';
    public const TYPE_SUPERVISOR = 'supervisor';
    public const TYPE_BEANSTALKD = 'beanstalkd';
    public const TYPE_COMPOSER = 'composer';

    // Conflict Groups - services that cannot run simultaneously
    public const CONFLICT_GROUP_MYSQL = 'mysql';

    // Service Categories
    public const CATEGORY_WEB_SERVER = 'Web Server';
    public const CATEGORY_DATABASE = 'Database';
    public const CATEGORY_CACHE = 'Cache';
    public const CATEGORY_PHP = 'PHP';
    public const CATEGORY_PROCESS = 'Process Manager';
    public const CATEGORY_QUEUE = 'Queue';
    public const CATEGORY_OTHER = 'Other';

    // Status
    public const STATUS_PENDING = 'pending';
    public const STATUS_INSTALLING = 'installing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REMOVING = 'removing';

    protected $fillable = [
        'server_id',
        'type',
        'version',
        'status',
        'is_default',
        'configuration',
        'installed_at',
        'error_message',
        'last_error',
        'last_error_at',
        'suggested_action',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'configuration' => 'array',
            'installed_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get the team that owns this service (through server).
     * Required for Filament tenant ownership.
     */
    public function team(): HasOneThrough
    {
        return $this->hasOneThrough(
            Team::class,
            Server::class,
            'id',           // Foreign key on servers table
            'id',           // Foreign key on teams table
            'server_id',    // Local key on services table
            'team_id'       // Local key on servers table
        );
    }

    /**
     * Get the stats for this service.
     */
    public function stats(): HasMany
    {
        return $this->hasMany(ServiceStat::class);
    }

    /**
     * Get the config backups for this service.
     */
    public function configBackups(): HasMany
    {
        return $this->hasMany(ConfigBackup::class);
    }

    /**
     * Get the latest stat record for this service.
     */
    public function latestStat()
    {
        return $this->hasOne(ServiceStat::class)->latestOfMany('recorded_at');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_INSTALLING]);
    }

    /**
     * Check if this is a core service that cannot be stopped.
     */
    public function isCoreService(): bool
    {
        return in_array($this->type, [self::TYPE_NGINX, self::TYPE_SUPERVISOR]);
    }

    /**
     * Check if this is a database engine service.
     */
    public function isDatabaseEngine(): bool
    {
        return in_array($this->type, [self::TYPE_MYSQL, self::TYPE_MARIADB, self::TYPE_POSTGRESQL]);
    }

    /**
     * Check if this service can be stopped.
     * Core services (nginx, supervisor) cannot be stopped.
     */
    public function canBeStopped(): bool
    {
        return !$this->isCoreService();
    }

    /**
     * Get databases on this server that depend on this engine.
     */
    public function getDependentDatabases(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->isDatabaseEngine()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // Map service type to database type
        $dbType = match ($this->type) {
            self::TYPE_MYSQL => Database::TYPE_MYSQL,
            self::TYPE_MARIADB => Database::TYPE_MARIADB,
            self::TYPE_POSTGRESQL => Database::TYPE_POSTGRESQL,
            default => null,
        };

        if (!$dbType) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return Database::where('server_id', $this->server_id)
            ->where('type', $dbType)
            ->get();
    }

    /**
     * Check if this service has dependent databases.
     */
    public function hasDependentDatabases(): bool
    {
        return $this->getDependentDatabases()->isNotEmpty();
    }

    /**
     * Get the conflict group for this service.
     * Services in the same conflict group cannot run simultaneously.
     */
    public function getConflictGroup(): ?string
    {
        return match ($this->type) {
            self::TYPE_MYSQL, self::TYPE_MARIADB => self::CONFLICT_GROUP_MYSQL,
            default => null,
        };
    }

    /**
     * Get conflicting services on the same server.
     * Returns services in the same conflict group that are active.
     */
    public function getConflictingServices(): \Illuminate\Database\Eloquent\Collection
    {
        $group = $this->getConflictGroup();

        if (!$group) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $conflictingTypes = match ($group) {
            self::CONFLICT_GROUP_MYSQL => [self::TYPE_MYSQL, self::TYPE_MARIADB],
            default => [],
        };

        return self::where('server_id', $this->server_id)
            ->whereIn('type', $conflictingTypes)
            ->where('id', '!=', $this->id)
            ->where('status', self::STATUS_ACTIVE)
            ->get();
    }

    /**
     * Get web apps using this PHP version.
     * Only applicable for PHP services.
     */
    public function getWebAppsUsingVersion(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->type !== self::TYPE_PHP) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return WebApp::where('server_id', $this->server_id)
            ->where('php_version', $this->version)
            ->get();
    }

    /**
     * Check if this service has web apps depending on it.
     */
    public function hasWebAppsDependingOnIt(): bool
    {
        return $this->getWebAppsUsingVersion()->isNotEmpty();
    }

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_PHP => 'PHP',
            self::TYPE_NODEJS => 'Node.js',
            self::TYPE_MYSQL => 'MySQL',
            self::TYPE_MARIADB => 'MariaDB',
            self::TYPE_POSTGRESQL => 'PostgreSQL',
            self::TYPE_REDIS => 'Redis',
            self::TYPE_MEMCACHED => 'Memcached',
            self::TYPE_NGINX => 'Nginx',
            self::TYPE_APACHE => 'Apache',
            self::TYPE_SUPERVISOR => 'Supervisor',
            self::TYPE_BEANSTALKD => 'Beanstalkd',
            self::TYPE_COMPOSER => 'Composer',
        ];
    }

    public static function getVersionsForType(string $type): array
    {
        return match ($type) {
            self::TYPE_PHP => ['8.4', '8.3', '8.2', '8.1', '8.0', '7.4'],
            self::TYPE_NODEJS => ['22', '20', '18', '16'],
            self::TYPE_MYSQL => ['8.4', '8.0', '5.7'],
            self::TYPE_MARIADB => ['11.4', '10.11', '10.6'],
            self::TYPE_POSTGRESQL => ['17', '16', '15', '14', '13'],
            self::TYPE_REDIS => ['7.4', '7.2', '6.2'],
            self::TYPE_MEMCACHED => ['1.6'],
            self::TYPE_NGINX => ['latest'],
            self::TYPE_APACHE => ['2.4'],
            self::TYPE_SUPERVISOR => ['latest'],
            self::TYPE_BEANSTALKD => ['latest'],
            self::TYPE_COMPOSER => ['latest'],
            default => [],
        };
    }

    public function getDisplayNameAttribute(): string
    {
        $types = self::getAvailableTypes();
        $name = $types[$this->type] ?? ucfirst($this->type);

        return $this->version !== 'latest'
            ? "{$name} {$this->version}"
            : $name;
    }

    /**
     * Get health status for this service.
     * For database services, this checks the server's database_health data.
     * Returns: 'healthy', 'unhealthy', 'degraded', or null
     */
    public function getHealthStatusAttribute(): ?string
    {
        // Only database services have health checks
        if (!$this->isDatabaseEngine()) {
            // For non-database services, use running status
            return $this->isActive() ? 'healthy' : null;
        }

        // Get database health from server
        $health = $this->server?->getDatabaseHealth($this->type);

        if ($health === null) {
            return null; // No health data available
        }

        $status = $health['status'] ?? null;
        $responseMs = $health['response_ms'] ?? 0;

        if ($status === 'ok') {
            // Check response time for degraded status
            if ($responseMs > 1000) {
                return 'degraded';
            }
            return 'healthy';
        }

        if ($status === 'error') {
            return 'unhealthy';
        }

        return null;
    }

    /**
     * Get the database health error message if any.
     */
    public function getDatabaseHealthErrorAttribute(): ?string
    {
        if (!$this->isDatabaseEngine()) {
            return null;
        }

        $health = $this->server?->getDatabaseHealth($this->type);
        return $health['error'] ?? null;
    }

    /**
     * Get the database health response time in ms.
     */
    public function getDatabaseHealthResponseMsAttribute(): ?int
    {
        if (!$this->isDatabaseEngine()) {
            return null;
        }

        $health = $this->server?->getDatabaseHealth($this->type);
        return $health['response_ms'] ?? null;
    }

    public function getCategoryAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_NGINX, self::TYPE_APACHE => self::CATEGORY_WEB_SERVER,
            self::TYPE_MYSQL, self::TYPE_MARIADB, self::TYPE_POSTGRESQL => self::CATEGORY_DATABASE,
            self::TYPE_REDIS, self::TYPE_MEMCACHED => self::CATEGORY_CACHE,
            self::TYPE_PHP => self::CATEGORY_PHP,
            self::TYPE_SUPERVISOR => self::CATEGORY_PROCESS,
            self::TYPE_BEANSTALKD => self::CATEGORY_QUEUE,
            default => self::CATEGORY_OTHER,
        };
    }

    public function dispatchInstall(): AgentJob
    {
        $this->update(['status' => self::STATUS_INSTALLING]);

        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->server->team_id,
            'type' => 'service_install',
            'payload' => [
                'service_id' => $this->id,
                'service_type' => $this->type,
                'version' => $this->version,
                'configuration' => $this->configuration ?? [],
                'is_default' => $this->is_default,
            ],
        ]);
    }

    public function dispatchUninstall(): AgentJob
    {
        $this->update(['status' => self::STATUS_REMOVING]);

        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->server->team_id,
            'type' => 'service_uninstall',
            'payload' => [
                'service_id' => $this->id,
                'service_type' => $this->type,
                'version' => $this->version,
            ],
        ]);
    }

    public function dispatchRestart(): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->server->team_id,
            'type' => 'service_restart',
            'payload' => [
                'service_id' => $this->id,
                'service_type' => $this->type,
                'version' => $this->version,
            ],
        ]);
    }

    public function dispatchReload(): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->server->team_id,
            'type' => 'service_reload',
            'payload' => [
                'service_id' => $this->id,
                'service_type' => $this->type,
                'version' => $this->version,
            ],
        ]);
    }

    public function dispatchStop(): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->server->team_id,
            'type' => 'service_stop',
            'payload' => [
                'service_id' => $this->id,
                'service_type' => $this->type,
                'version' => $this->version,
            ],
        ]);
    }

    public function dispatchStart(): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->server->team_id,
            'type' => 'service_start',
            'payload' => [
                'service_id' => $this->id,
                'service_type' => $this->type,
                'version' => $this->version,
            ],
        ]);
    }

    /**
     * Dispatch a repair/re-provision job for this service.
     * This will reset credentials and reconfigure the service.
     */
    public function dispatchRepair(): AgentJob
    {
        // Clear any existing error
        $this->clearError();

        // Map service type to provision job type
        $provisionType = match ($this->type) {
            self::TYPE_MARIADB => 'provision_mariadb',
            self::TYPE_MYSQL => 'provision_mysql',
            self::TYPE_POSTGRESQL => 'provision_postgresql',
            self::TYPE_REDIS => 'provision_redis',
            self::TYPE_NGINX => 'provision_nginx',
            self::TYPE_SUPERVISOR => 'provision_supervisor',
            self::TYPE_PHP => 'provision_php',
            self::TYPE_NODEJS => 'provision_nodejs',
            default => 'service_install',
        };

        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->server->team_id,
            'type' => $provisionType,
            'payload' => [
                'service_id' => $this->id,
                'version' => $this->version,
                'force' => true, // Force re-provision even if already configured
            ],
            'priority' => 3, // Higher priority for repair jobs
        ]);
    }

    /**
     * Check if this service can be repaired.
     */
    public function canBeRepaired(): bool
    {
        return in_array($this->type, [
            self::TYPE_MARIADB,
            self::TYPE_MYSQL,
            self::TYPE_POSTGRESQL,
            self::TYPE_REDIS,
            self::TYPE_NGINX,
            self::TYPE_PHP,
            self::TYPE_SUPERVISOR,
        ]);
    }

    /**
     * Dispatch a job for this service.
     */
    public function dispatchJob(string $type, array $payload = [], int $priority = 5): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->server->team_id,
            'type' => $type,
            'payload' => array_merge(['service_id' => $this->id], $payload),
            'priority' => $priority,
        ]);
    }

    public function markInstalled(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'installed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
        ]);
    }

    public function getServiceNameForSystemd(): string
    {
        return match ($this->type) {
            self::TYPE_PHP => "php{$this->version}-fpm",
            self::TYPE_NODEJS => 'node',
            self::TYPE_MYSQL => 'mysql',
            self::TYPE_MARIADB => 'mariadb',
            self::TYPE_POSTGRESQL => 'postgresql',
            self::TYPE_REDIS => 'redis-server',
            self::TYPE_MEMCACHED => 'memcached',
            self::TYPE_NGINX => 'nginx',
            self::TYPE_SUPERVISOR => 'supervisor',
            self::TYPE_BEANSTALKD => 'beanstalkd',
            default => $this->type,
        };
    }

    /**
     * Get the main config file path for this service.
     */
    public function getConfigFilePath(): ?string
    {
        return match ($this->type) {
            self::TYPE_PHP => "/etc/php/{$this->version}/fpm/php-fpm.conf",
            self::TYPE_NGINX => '/etc/nginx/nginx.conf',
            self::TYPE_MYSQL, self::TYPE_MARIADB => '/etc/mysql/mariadb.conf.d/50-server.cnf',
            self::TYPE_POSTGRESQL => "/etc/postgresql/{$this->version}/main/postgresql.conf",
            self::TYPE_REDIS => '/etc/redis/redis.conf',
            self::TYPE_MEMCACHED => '/etc/memcached.conf',
            self::TYPE_SUPERVISOR => '/etc/supervisor/supervisord.conf',
            default => null,
        };
    }

    /**
     * Get all editable config files for this service.
     */
    public function getEditableConfigFiles(): array
    {
        return match ($this->type) {
            self::TYPE_PHP => [
                "/etc/php/{$this->version}/fpm/php-fpm.conf" => 'PHP-FPM Main Config',
                "/etc/php/{$this->version}/fpm/php.ini" => 'PHP.ini',
                "/etc/php/{$this->version}/fpm/pool.d/www.conf" => 'Default Pool',
            ],
            self::TYPE_NGINX => [
                '/etc/nginx/nginx.conf' => 'Nginx Main Config',
            ],
            self::TYPE_MYSQL, self::TYPE_MARIADB => [
                '/etc/mysql/mariadb.conf.d/50-server.cnf' => 'Server Config',
            ],
            self::TYPE_POSTGRESQL => [
                "/etc/postgresql/{$this->version}/main/postgresql.conf" => 'PostgreSQL Config',
                "/etc/postgresql/{$this->version}/main/pg_hba.conf" => 'Host Auth Config',
            ],
            self::TYPE_REDIS => [
                '/etc/redis/redis.conf' => 'Redis Config',
            ],
            self::TYPE_MEMCACHED => [
                '/etc/memcached.conf' => 'Memcached Config',
            ],
            self::TYPE_SUPERVISOR => [
                '/etc/supervisor/supervisord.conf' => 'Supervisor Config',
            ],
            default => [],
        };
    }

    /**
     * Check if this service supports config editing.
     */
    public function supportsConfigEditing(): bool
    {
        return !empty($this->getEditableConfigFiles());
    }

    /**
     * Dispatch a job to read a config file.
     */
    public function dispatchReadConfig(string $filePath): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->server->team_id,
            'type' => 'read_file',
            'payload' => [
                'service_id' => $this->id,
                'file_path' => $filePath,
            ],
        ]);
    }

    /**
     * Dispatch a job to write a config file.
     */
    public function dispatchWriteConfig(string $filePath, string $content): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->server->team_id,
            'type' => 'write_file',
            'payload' => [
                'service_id' => $this->id,
                'file_path' => $filePath,
                'content' => $content,
                'backup' => true,
            ],
        ]);
    }

    /**
     * Get log files for this service.
     */
    public function getLogFiles(): array
    {
        return match ($this->type) {
            self::TYPE_PHP => [
                "/var/log/php{$this->version}-fpm.log" => 'PHP-FPM Main Log',
            ],
            self::TYPE_NGINX => [
                '/var/log/nginx/access.log' => 'Access Log',
                '/var/log/nginx/error.log' => 'Error Log',
            ],
            self::TYPE_MYSQL, self::TYPE_MARIADB => [
                '/var/log/mysql/error.log' => 'Error Log',
            ],
            self::TYPE_POSTGRESQL => [
                "/var/log/postgresql/postgresql-{$this->version}-main.log" => 'PostgreSQL Log',
            ],
            self::TYPE_REDIS => [
                '/var/log/redis/redis-server.log' => 'Redis Log',
            ],
            self::TYPE_SUPERVISOR => [
                '/var/log/supervisor/supervisord.log' => 'Supervisor Log',
            ],
            default => [],
        };
    }

    /**
     * Check if this service supports log viewing.
     */
    public function supportsLogViewing(): bool
    {
        return !empty($this->getLogFiles());
    }

    /**
     * Dispatch a job to read log file contents (tail).
     */
    public function dispatchReadLog(string $filePath, int $lines = 100): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->server->team_id,
            'type' => 'tail_log',
            'payload' => [
                'service_id' => $this->id,
                'path' => $filePath,
                'lines' => $lines,
            ],
        ]);
    }

    public function getInstallCommands(): array
    {
        return match ($this->type) {
            self::TYPE_PHP => $this->getPhpInstallCommands(),
            self::TYPE_NODEJS => $this->getNodeInstallCommands(),
            self::TYPE_MYSQL => $this->getMysqlInstallCommands(),
            self::TYPE_MARIADB => $this->getMariadbInstallCommands(),
            self::TYPE_POSTGRESQL => $this->getPostgresInstallCommands(),
            self::TYPE_REDIS => $this->getRedisInstallCommands(),
            self::TYPE_MEMCACHED => ['apt-get install -y memcached', 'systemctl enable memcached', 'systemctl start memcached'],
            self::TYPE_NGINX => ['apt-get install -y nginx', 'systemctl enable nginx', 'systemctl start nginx'],
            self::TYPE_SUPERVISOR => ['apt-get install -y supervisor', 'systemctl enable supervisor', 'systemctl start supervisor'],
            self::TYPE_BEANSTALKD => ['apt-get install -y beanstalkd', 'systemctl enable beanstalkd', 'systemctl start beanstalkd'],
            self::TYPE_COMPOSER => ['curl -sS https://getcomposer.org/installer | php', 'mv composer.phar /usr/local/bin/composer'],
            default => [],
        };
    }

    protected function getPhpInstallCommands(): array
    {
        $version = $this->version;
        $extensions = $this->configuration['extensions'] ?? [
            'cli', 'fpm', 'mysql', 'pgsql', 'sqlite3', 'gd', 'curl', 'mbstring',
            'xml', 'zip', 'bcmath', 'intl', 'readline', 'opcache', 'redis',
        ];

        $extString = implode(' ', array_map(fn ($ext) => "php{$version}-{$ext}", $extensions));

        return [
            'add-apt-repository -y ppa:ondrej/php',
            'apt-get update',
            "apt-get install -y php{$version} {$extString}",
            "systemctl enable php{$version}-fpm",
            "systemctl start php{$version}-fpm",
        ];
    }

    protected function getNodeInstallCommands(): array
    {
        $version = $this->version;

        return [
            "curl -fsSL https://deb.nodesource.com/setup_{$version}.x | bash -",
            'apt-get install -y nodejs',
            'npm install -g npm@latest',
            'npm install -g pm2',
        ];
    }

    protected function getMysqlInstallCommands(): array
    {
        return [
            'apt-get install -y mysql-server',
            'systemctl enable mysql',
            'systemctl start mysql',
            'mysql_secure_installation --use-default',
        ];
    }

    protected function getMariadbInstallCommands(): array
    {
        return [
            'apt-get install -y mariadb-server',
            'systemctl enable mariadb',
            'systemctl start mariadb',
            'mysql_secure_installation --use-default',
        ];
    }

    protected function getPostgresInstallCommands(): array
    {
        $version = $this->version;

        return [
            'sh -c \'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list\'',
            'wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | apt-key add -',
            'apt-get update',
            "apt-get install -y postgresql-{$version}",
            'systemctl enable postgresql',
            'systemctl start postgresql',
        ];
    }

    protected function getRedisInstallCommands(): array
    {
        return [
            'apt-get install -y redis-server',
            'systemctl enable redis-server',
            'systemctl start redis-server',
        ];
    }

    /**
     * Get list of common PHP extensions available for installation.
     */
    public static function getAvailablePhpExtensions(): array
    {
        return [
            // Core extensions (typically installed by default)
            'cli' => 'CLI (Command Line Interface)',
            'fpm' => 'FPM (FastCGI Process Manager)',
            'common' => 'Common Extensions',

            // Database
            'mysql' => 'MySQL/MariaDB',
            'pgsql' => 'PostgreSQL',
            'sqlite3' => 'SQLite3',
            'redis' => 'Redis',
            'memcached' => 'Memcached',
            'mongodb' => 'MongoDB',

            // Text & String
            'mbstring' => 'Multibyte String',
            'xml' => 'XML',
            'dom' => 'DOM',
            'json' => 'JSON',
            'intl' => 'Internationalization',
            'iconv' => 'iconv (Character Set Conversion)',

            // Image & Graphics
            'gd' => 'GD (Graphics)',
            'imagick' => 'ImageMagick',
            'exif' => 'EXIF',

            // Compression & Archive
            'zip' => 'ZIP',
            'bz2' => 'BZip2',
            'zlib' => 'Zlib',

            // Math & Crypto
            'bcmath' => 'BCMath (Arbitrary Precision)',
            'gmp' => 'GMP (GNU Multiple Precision)',
            'sodium' => 'Sodium (Cryptography)',
            'openssl' => 'OpenSSL',

            // Network & HTTP
            'curl' => 'cURL',
            'soap' => 'SOAP',
            'sockets' => 'Sockets',
            'ftp' => 'FTP',

            // Performance & Caching
            'opcache' => 'OPcache',
            'apcu' => 'APCu (User Cache)',

            // Misc
            'readline' => 'Readline',
            'fileinfo' => 'Fileinfo',
            'phar' => 'Phar',
            'tokenizer' => 'Tokenizer',
            'ctype' => 'Ctype',
            'calendar' => 'Calendar',
            'ldap' => 'LDAP',
            'imap' => 'IMAP',
            'tidy' => 'Tidy',
            'xdebug' => 'Xdebug (Debug)',
            'pcov' => 'PCOV (Code Coverage)',
        ];
    }

    /**
     * Get currently installed PHP extensions from configuration.
     */
    public function getInstalledExtensions(): array
    {
        if ($this->type !== self::TYPE_PHP) {
            return [];
        }

        return $this->configuration['extensions'] ?? [
            'cli', 'fpm', 'mysql', 'pgsql', 'sqlite3', 'gd', 'curl', 'mbstring',
            'xml', 'zip', 'bcmath', 'intl', 'readline', 'opcache', 'redis',
        ];
    }

    /**
     * Check if this is a PHP service.
     */
    public function isPhpService(): bool
    {
        return $this->type === self::TYPE_PHP;
    }

    /**
     * Dispatch a job to install a PHP extension.
     */
    public function dispatchInstallExtension(string $extension): AgentJob
    {
        if ($this->type !== self::TYPE_PHP) {
            throw new \RuntimeException('Can only install extensions on PHP services');
        }

        return $this->dispatchJob('php_install_extension', [
            'service_id' => $this->id,
            'version' => $this->version,
            'extension' => $extension,
            'package' => "php{$this->version}-{$extension}",
        ]);
    }

    /**
     * Dispatch a job to uninstall a PHP extension.
     */
    public function dispatchUninstallExtension(string $extension): AgentJob
    {
        if ($this->type !== self::TYPE_PHP) {
            throw new \RuntimeException('Can only uninstall extensions on PHP services');
        }

        // Don't allow uninstalling core extensions
        $coreExtensions = ['cli', 'fpm', 'common'];
        if (in_array($extension, $coreExtensions)) {
            throw new \RuntimeException("Cannot uninstall core extension: {$extension}");
        }

        return $this->dispatchJob('php_uninstall_extension', [
            'service_id' => $this->id,
            'version' => $this->version,
            'extension' => $extension,
            'package' => "php{$this->version}-{$extension}",
        ]);
    }

    /**
     * Update installed extensions list in configuration.
     */
    public function addExtensionToConfig(string $extension): void
    {
        $extensions = $this->getInstalledExtensions();
        if (!in_array($extension, $extensions)) {
            $extensions[] = $extension;
            $this->update([
                'configuration' => array_merge($this->configuration ?? [], [
                    'extensions' => $extensions,
                ]),
            ]);
        }
    }

    /**
     * Remove extension from configuration.
     */
    public function removeExtensionFromConfig(string $extension): void
    {
        $extensions = $this->getInstalledExtensions();
        $extensions = array_values(array_filter($extensions, fn ($e) => $e !== $extension));
        $this->update([
            'configuration' => array_merge($this->configuration ?? [], [
                'extensions' => $extensions,
            ]),
        ]);
    }
}
