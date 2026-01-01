<?php

namespace App\Filament\Pages;

use App\Models\NotificationPreference;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPreferences extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Notifications';

    protected static string $view = 'filament.pages.notification-preferences';

    public string $activeTab = 'preferences';

    public array $preferences = [];

    public function mount(): void
    {
        $user = auth()->user();
        $eventTypes = NotificationPreference::getEventTypes();

        foreach ($eventTypes as $eventType => $config) {
            $pref = NotificationPreference::getOrCreate($user, $eventType);
            $this->preferences[$eventType] = [
                'in_app_enabled' => (bool) $pref->in_app_enabled,
                'email_enabled' => (bool) $pref->email_enabled,
                'email_frequency' => $pref->email_frequency ?? NotificationPreference::FREQUENCY_IMMEDIATE,
            ];
        }
    }

    public function getNotificationsProperty(): LengthAwarePaginator
    {
        return auth()->user()
            ->notifications()
            ->latest()
            ->paginate(15);
    }

    public function getUnreadCountProperty(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = auth()->user()
            ->notifications()
            ->where('id', $notificationId)
            ->first();

        if ($notification) {
            $notification->markAsRead();
        }
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();

        Notification::make()
            ->title('All notifications marked as read')
            ->success()
            ->send();
    }

    public function getNotificationTitle(DatabaseNotification $notification): string
    {
        $data = $notification->data;
        $type = $data['type'] ?? '';

        // Try to get a title from the data
        if (!empty($data['title'])) {
            return $data['title'];
        }

        // Generate title based on type
        return match ($type) {
            'server_offline' => 'Server Offline: ' . ($data['server_name'] ?? 'Unknown'),
            'server_provisioned' => 'Server Provisioned: ' . ($data['server_name'] ?? 'Unknown'),
            'deployment_completed', 'deployment_successful' => 'Deployment Completed',
            'deployment_failed' => 'Deployment Failed',
            'ssl_issued', 'ssl_certificate_issued' => 'SSL Certificate Issued',
            'ssl_expiring' => 'SSL Certificate Expiring',
            'monitor_down', 'health_monitor_down' => 'Monitor Down: ' . ($data['monitor_name'] ?? $data['url'] ?? 'Unknown'),
            'monitor_recovered', 'health_monitor_recovered' => 'Monitor Recovered: ' . ($data['monitor_name'] ?? $data['url'] ?? 'Unknown'),
            'backup_completed' => 'Backup Completed',
            'backup_failed' => 'Backup Failed',
            'firewall_rollback' => 'Firewall Rule Rolled Back',
            default => ucwords(str_replace('_', ' ', $type)) ?: 'Notification',
        };
    }

    public function getNotificationBody(DatabaseNotification $notification): string
    {
        $data = $notification->data;
        $type = $data['type'] ?? '';

        // Try to get a body/message from the data
        if (!empty($data['body'])) {
            return $data['body'];
        }
        if (!empty($data['message'])) {
            return $data['message'];
        }

        // Generate body based on type and available data
        return match ($type) {
            'server_offline' => ($data['reason'] ?? 'No heartbeat received') . '. IP: ' . ($data['ip_address'] ?? 'Unknown'),
            'deployment_completed', 'deployment_successful' => 'Deployment to ' . ($data['domain'] ?? $data['app_name'] ?? 'your app') . ' completed successfully.',
            'deployment_failed' => 'Deployment to ' . ($data['domain'] ?? $data['app_name'] ?? 'your app') . ' failed. ' . ($data['error'] ?? ''),
            'ssl_issued', 'ssl_certificate_issued' => 'SSL certificate for ' . ($data['domain'] ?? 'your domain') . ' has been issued.',
            'ssl_expiring' => 'SSL certificate for ' . ($data['domain'] ?? 'your domain') . ' expires on ' . ($data['expires_at'] ?? 'soon') . '.',
            'monitor_down', 'health_monitor_down' => ($data['url'] ?? 'Service') . ' is down. ' . ($data['error'] ?? ''),
            'monitor_recovered', 'health_monitor_recovered' => ($data['url'] ?? 'Service') . ' is back online. Downtime: ' . ($data['downtime'] ?? 'unknown') . '.',
            'backup_completed' => 'Database backup for ' . ($data['database_name'] ?? 'your database') . ' completed.',
            'backup_failed' => 'Database backup for ' . ($data['database_name'] ?? 'your database') . ' failed.',
            'firewall_rollback' => 'Firewall rule was rolled back. ' . ($data['reason'] ?? ''),
            default => $this->buildGenericBody($data),
        };
    }

    protected function buildGenericBody(array $data): string
    {
        $parts = [];

        foreach (['domain', 'server_name', 'app_name', 'database_name', 'url'] as $key) {
            if (!empty($data[$key])) {
                $parts[] = $data[$key];
                break;
            }
        }

        if (!empty($data['error'])) {
            $parts[] = $data['error'];
        }

        return implode(' - ', $parts) ?: 'View details';
    }

    public function save(): void
    {
        $user = auth()->user();
        $eventTypes = NotificationPreference::getEventTypes();

        foreach ($eventTypes as $eventType => $config) {
            if (isset($this->preferences[$eventType])) {
                NotificationPreference::updateOrCreate(
                    ['user_id' => $user->id, 'event_type' => $eventType],
                    [
                        'in_app_enabled' => (bool) ($this->preferences[$eventType]['in_app_enabled'] ?? true),
                        'email_enabled' => (bool) ($this->preferences[$eventType]['email_enabled'] ?? false),
                        'email_frequency' => $this->preferences[$eventType]['email_frequency'] ?? NotificationPreference::FREQUENCY_IMMEDIATE,
                    ]
                );
            }
        }

        Notification::make()
            ->title('Preferences saved')
            ->body('Your notification preferences have been updated.')
            ->success()
            ->send();
    }
}
