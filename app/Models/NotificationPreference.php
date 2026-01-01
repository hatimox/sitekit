<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasUuids;

    // Event Types
    public const EVENT_SERVER_PROVISIONED = 'server_provisioned';
    public const EVENT_SERVER_OFFLINE = 'server_offline';
    public const EVENT_WEBAPP_CREATED = 'webapp_created';
    public const EVENT_DEPLOYMENT_COMPLETED = 'deployment_completed';
    public const EVENT_DEPLOYMENT_FAILED = 'deployment_failed';
    public const EVENT_SSL_ISSUED = 'ssl_issued';
    public const EVENT_SSL_FAILED = 'ssl_failed';
    public const EVENT_SSL_EXPIRING = 'ssl_expiring';
    public const EVENT_WEBAPP_FAILED = 'webapp_failed';
    public const EVENT_DATABASE_CREATED = 'database_created';
    public const EVENT_DATABASE_FAILED = 'database_failed';
    public const EVENT_BACKUP_COMPLETED = 'backup_completed';
    public const EVENT_BACKUP_FAILED = 'backup_failed';
    public const EVENT_HEALTH_MONITOR_DOWN = 'health_monitor_down';
    public const EVENT_HEALTH_MONITOR_RECOVERED = 'health_monitor_recovered';
    public const EVENT_CRON_JOB_FAILED = 'cron_job_failed';
    public const EVENT_SERVICE_RESTARTED = 'service_restarted';
    public const EVENT_SERVICE_STARTED = 'service_started';
    public const EVENT_SERVICE_STOPPED = 'service_stopped';
    public const EVENT_SERVICE_CRASHED = 'service_crashed';

    // Server Resource Events
    public const EVENT_SERVER_ONLINE = 'server_online';
    public const EVENT_SERVER_HIGH_LOAD = 'server_high_load';
    public const EVENT_SERVER_HIGH_MEMORY = 'server_high_memory';
    public const EVENT_SERVER_HIGH_DISK = 'server_high_disk';
    public const EVENT_SERVER_RESOURCES_NORMAL = 'server_resources_normal';

    // Frequency Options
    public const FREQUENCY_IMMEDIATE = 'immediate';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_NEVER = 'never';

    protected $fillable = [
        'user_id',
        'event_type',
        'in_app_enabled',
        'email_enabled',
        'email_frequency',
    ];

    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all available event types with their labels and default settings
     */
    public static function getEventTypes(): array
    {
        return [
            self::EVENT_SERVER_PROVISIONED => [
                'label' => 'Server Provisioned',
                'description' => 'When a new server is successfully connected',
                'category' => 'Servers',
                'default_email' => false,
                'critical' => false,
            ],
            self::EVENT_SERVER_OFFLINE => [
                'label' => 'Server Offline',
                'description' => 'When a server becomes unreachable',
                'category' => 'Servers',
                'default_email' => false,
                'critical' => true,
            ],
            self::EVENT_WEBAPP_CREATED => [
                'label' => 'Web App Created',
                'description' => 'When a new web application is created',
                'category' => 'Web Apps',
                'default_email' => false,
                'critical' => false,
            ],
            self::EVENT_DEPLOYMENT_COMPLETED => [
                'label' => 'Deployment Completed',
                'description' => 'When a deployment finishes successfully',
                'category' => 'Deployments',
                'default_email' => false,
                'critical' => false,
            ],
            self::EVENT_DEPLOYMENT_FAILED => [
                'label' => 'Deployment Failed',
                'description' => 'When a deployment fails',
                'category' => 'Deployments',
                'default_email' => false,
                'critical' => true,
            ],
            self::EVENT_SSL_ISSUED => [
                'label' => 'SSL Certificate Issued',
                'description' => 'When a new SSL certificate is issued',
                'category' => 'SSL Certificates',
                'default_email' => false,
                'critical' => false,
            ],
            self::EVENT_SSL_FAILED => [
                'label' => 'SSL Certificate Failed',
                'description' => 'When SSL certificate issuance fails',
                'category' => 'SSL Certificates',
                'default_email' => false,
                'critical' => true,
            ],
            self::EVENT_SSL_EXPIRING => [
                'label' => 'SSL Certificate Expiring',
                'description' => 'When an SSL certificate is expiring within 7 days',
                'category' => 'SSL Certificates',
                'default_email' => false,
                'critical' => true,
            ],
            self::EVENT_WEBAPP_FAILED => [
                'label' => 'Web App Creation Failed',
                'description' => 'When web application creation fails',
                'category' => 'Web Apps',
                'default_email' => false,
                'critical' => true,
            ],
            self::EVENT_DATABASE_CREATED => [
                'label' => 'Database Created',
                'description' => 'When a new database is created',
                'category' => 'Databases',
                'default_email' => false,
                'critical' => false,
            ],
            self::EVENT_DATABASE_FAILED => [
                'label' => 'Database Creation Failed',
                'description' => 'When database creation fails',
                'category' => 'Databases',
                'default_email' => false,
                'critical' => true,
            ],
            self::EVENT_BACKUP_COMPLETED => [
                'label' => 'Backup Completed',
                'description' => 'When a database backup completes successfully',
                'category' => 'Backups',
                'default_email' => false,
                'critical' => false,
            ],
            self::EVENT_BACKUP_FAILED => [
                'label' => 'Backup Failed',
                'description' => 'When a database backup fails',
                'category' => 'Backups',
                'default_email' => false,
                'critical' => true,
            ],
            self::EVENT_HEALTH_MONITOR_DOWN => [
                'label' => 'Health Monitor Down',
                'description' => 'When a health monitor detects a service is down',
                'category' => 'Monitoring',
                'default_email' => false,
                'critical' => true,
            ],
            self::EVENT_HEALTH_MONITOR_RECOVERED => [
                'label' => 'Health Monitor Recovered',
                'description' => 'When a previously down service recovers',
                'category' => 'Monitoring',
                'default_email' => false,
                'critical' => false,
            ],
            self::EVENT_CRON_JOB_FAILED => [
                'label' => 'Cron Job Failed',
                'description' => 'When a scheduled cron job fails',
                'category' => 'Cron Jobs',
                'default_email' => false,
                'critical' => true,
            ],
            self::EVENT_SERVICE_RESTARTED => [
                'label' => 'Service Restarted',
                'description' => 'When a service restart completes',
                'category' => 'Services',
                'default_email' => false,
                'critical' => false,
            ],
            self::EVENT_SERVICE_STARTED => [
                'label' => 'Service Started',
                'description' => 'When a stopped service is started',
                'category' => 'Services',
                'default_email' => false,
                'critical' => false,
            ],
            self::EVENT_SERVICE_STOPPED => [
                'label' => 'Service Stopped',
                'description' => 'When a service is manually stopped',
                'category' => 'Services',
                'default_email' => false,
                'critical' => false,
            ],
            self::EVENT_SERVICE_CRASHED => [
                'label' => 'Service Crashed',
                'description' => 'When a service unexpectedly fails or crashes',
                'category' => 'Services',
                'default_email' => true,
                'critical' => true,
            ],

            // Server Resources category
            self::EVENT_SERVER_ONLINE => [
                'label' => 'Server Back Online',
                'description' => 'When a previously offline server reconnects',
                'category' => 'Server Resources',
                'default_email' => true,
                'critical' => false,
            ],
            self::EVENT_SERVER_HIGH_LOAD => [
                'label' => 'High Server Load',
                'description' => 'When server load average exceeds threshold',
                'category' => 'Server Resources',
                'default_email' => true,
                'critical' => true,
            ],
            self::EVENT_SERVER_HIGH_MEMORY => [
                'label' => 'High Memory Usage',
                'description' => 'When server memory usage exceeds threshold',
                'category' => 'Server Resources',
                'default_email' => true,
                'critical' => true,
            ],
            self::EVENT_SERVER_HIGH_DISK => [
                'label' => 'High Disk Usage',
                'description' => 'When server disk usage exceeds threshold',
                'category' => 'Server Resources',
                'default_email' => true,
                'critical' => true,
            ],
            self::EVENT_SERVER_RESOURCES_NORMAL => [
                'label' => 'Server Resources Normal',
                'description' => 'When all server resources return to normal levels',
                'category' => 'Server Resources',
                'default_email' => true,
                'critical' => false,
            ],
        ];
    }

    /**
     * Get frequency options
     */
    public static function getFrequencyOptions(): array
    {
        return [
            self::FREQUENCY_IMMEDIATE => 'Immediately',
            self::FREQUENCY_DAILY => 'Daily digest (9am)',
            self::FREQUENCY_WEEKLY => 'Weekly digest (Mondays)',
            self::FREQUENCY_NEVER => 'Never',
        ];
    }

    /**
     * Get or create preference for a user and event type
     */
    public static function getOrCreate(User $user, string $eventType): self
    {
        $preference = self::where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->first();

        if (!$preference) {
            $eventConfig = self::getEventTypes()[$eventType] ?? [];
            $preference = self::create([
                'user_id' => $user->id,
                'event_type' => $eventType,
                'in_app_enabled' => true,
                'email_enabled' => $eventConfig['default_email'] ?? false,
                'email_frequency' => self::FREQUENCY_IMMEDIATE,
            ]);
        }

        return $preference;
    }

    /**
     * Check if user should receive email for this event
     */
    public static function shouldSendEmail(User $user, string $eventType): bool
    {
        $preference = self::where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->first();

        if (!$preference) {
            // Use defaults
            $eventConfig = self::getEventTypes()[$eventType] ?? [];
            return $eventConfig['default_email'] ?? false;
        }

        return $preference->email_enabled && $preference->email_frequency !== self::FREQUENCY_NEVER;
    }

    /**
     * Check if user should receive in-app notification for this event
     */
    public static function shouldSendInApp(User $user, string $eventType): bool
    {
        $preference = self::where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->first();

        if (!$preference) {
            return true; // Default to sending in-app notifications
        }

        return $preference->in_app_enabled;
    }
}
