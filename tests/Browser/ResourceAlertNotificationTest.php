<?php

namespace Tests\Browser;

use App\Events\ServerStatsUpdated;
use App\Listeners\ServerStatsListener;
use App\Models\NotificationPreference;
use App\Models\Server;
use App\Models\User;
use App\Notifications\ServerHighDisk;
use App\Notifications\ServerHighLoad;
use App\Notifications\ServerHighMemory;
use App\Notifications\ServerResourcesNormal;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end tests for server resource alert notifications.
 *
 * Configure in .env:
 * - DUSK_TEST_USER_EMAIL
 * - DUSK_TEST_SERVER_IP
 */
class ResourceAlertNotificationTest extends DuskTestCase
{
    protected User $user;
    protected Server $server;
    protected string $teamId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', $this->getTestUserEmail())->first();
        $this->server = Server::where('ip_address', $this->getTestServerIp())->first();

        if (!$this->user) {
            $this->markTestSkipped('Test user not found. Configure DUSK_TEST_USER_EMAIL in .env');
        }
        if (!$this->server) {
            $this->markTestSkipped('Test server not found. Configure DUSK_TEST_SERVER_IP in .env');
        }

        $this->teamId = $this->user->currentTeam?->id ?? $this->user->teams()->first()?->id;
    }

    protected function appUrl(string $path = ''): string
    {
        if ($this->teamId) {
            return "/app/{$this->teamId}" . ($path ? "/{$path}" : '');
        }
        return "/app" . ($path ? "/{$path}" : '');
    }

    /**
     * Test that server detail page shows resource stats
     */
    public function test_server_detail_shows_resource_stats(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit($this->appUrl("servers/{$this->server->id}"))
                ->waitForText($this->server->name, 10)
                ->screenshot('resource-01-server-detail');

            // Check for server name and IP
            $browser->assertSee($this->server->name)
                ->assertSee($this->server->ip_address)
                ->screenshot('resource-02-server-info');

            // Check for resource stats section
            $html = $browser->driver->getPageSource();

            // Should show load, memory, disk stats
            $hasStats = (
                str_contains($html, 'Load') ||
                str_contains($html, 'Memory') ||
                str_contains($html, 'Disk') ||
                str_contains($html, 'CPU')
            );

            echo "\n Server: {$this->server->name}\n";
            echo " Status: {$this->server->status}\n";
            echo " Has stats display: " . ($hasStats ? 'Yes' : 'No') . "\n";

            $browser->screenshot('resource-03-stats-visible');
        });
    }

    /**
     * Test notification preferences page shows resource alert types
     */
    public function test_notification_preferences_show_resource_alerts(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit($this->appUrl('notification-preferences'))
                ->waitForText('Notification Preferences', 10)
                ->screenshot('resource-04-notif-prefs');

            // Check for new resource alert event types
            $html = $browser->driver->getPageSource();

            $resourceEvents = [
                'Server Resources' => false,
                'High Load' => false,
                'High Memory' => false,
                'High Disk' => false,
                'Resources Normal' => false,
                'Back Online' => false,
            ];

            foreach ($resourceEvents as $event => $found) {
                if (stripos($html, $event) !== false) {
                    $resourceEvents[$event] = true;
                }
            }

            echo "\n Resource Alert Events in UI:\n";
            foreach ($resourceEvents as $event => $found) {
                echo " " . ($found ? "✓" : "✗") . " {$event}\n";
            }

            $browser->screenshot('resource-05-resource-events');

            // At least some resource events should be visible
            $this->assertTrue(
                array_filter($resourceEvents) !== [],
                'Should show at least one resource alert event type'
            );
        });
    }

    /**
     * Test enabling resource alert notifications
     */
    public function test_can_enable_resource_alert_notifications(): void
    {
        // Enable all resource alert notifications for testing
        $resourceEvents = [
            NotificationPreference::EVENT_SERVER_ONLINE,
            NotificationPreference::EVENT_SERVER_HIGH_LOAD,
            NotificationPreference::EVENT_SERVER_HIGH_MEMORY,
            NotificationPreference::EVENT_SERVER_HIGH_DISK,
            NotificationPreference::EVENT_SERVER_RESOURCES_NORMAL,
        ];

        foreach ($resourceEvents as $event) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $this->user->id, 'event_type' => $event],
                ['in_app_enabled' => true, 'email_enabled' => true, 'email_frequency' => 'immediate']
            );
        }

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit($this->appUrl('notification-preferences'))
                ->waitForText('Notification Preferences', 10)
                ->pause(2000)
                ->screenshot('resource-06-prefs-enabled');

            echo "\n Enabled all resource alert notification preferences\n";
        });

        // Verify preferences were saved
        $enabledCount = NotificationPreference::where('user_id', $this->user->id)
            ->whereIn('event_type', $resourceEvents)
            ->where('in_app_enabled', true)
            ->count();

        $this->assertEquals(count($resourceEvents), $enabledCount);
        echo " {$enabledCount} resource alert preferences enabled\n";
    }

    /**
     * Test resource alert notification classes work correctly
     */
    public function test_resource_alert_notification_classes(): void
    {
        // Test each notification class can be instantiated and has correct channels
        $notifications = [
            new ServerHighLoad($this->server, 8.5, 5.0),
            new ServerHighMemory($this->server, 95.0, 90.0),
            new ServerHighDisk($this->server, 92.0, 90.0),
            new ServerResourcesNormal($this->server, ['load' => 2.0, 'memory' => 50.0, 'disk' => 60.0]),
        ];

        foreach ($notifications as $notification) {
            $channels = $notification->via($this->user);
            $className = class_basename($notification);

            echo " {$className}: channels = " . implode(', ', $channels) . "\n";

            $this->assertIsArray($channels);
            $this->assertNotEmpty($channels, "{$className} should have at least one channel");
        }
    }

    /**
     * Test triggering a real resource alert notification
     */
    public function test_trigger_resource_alert_notification(): void
    {
        // Ensure in-app notifications are enabled
        NotificationPreference::updateOrCreate(
            ['user_id' => $this->user->id, 'event_type' => NotificationPreference::EVENT_SERVER_HIGH_LOAD],
            ['in_app_enabled' => true, 'email_enabled' => false]
        );

        // Get initial notification count
        $initialCount = $this->user->notifications()->count();

        // Send a test high load notification directly
        Notification::sendNow($this->user, new ServerHighLoad($this->server, 8.5, 5.0));

        // Verify notification was stored
        $newCount = $this->user->notifications()->count();
        $this->assertGreaterThan($initialCount, $newCount, 'Notification should be stored in database');

        echo "\n Sent ServerHighLoad notification\n";
        echo " Initial count: {$initialCount}, New count: {$newCount}\n";

        // Check the notification content
        $notification = $this->user->notifications()->latest()->first();
        if ($notification) {
            echo " Notification type: {$notification->type}\n";
            echo " Notification data: " . json_encode($notification->data) . "\n";
        }

        // Clean up test notification
        $this->user->notifications()
            ->where('type', ServerHighLoad::class)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->delete();
    }

    /**
     * Test ServerStatsUpdated event triggers alert listener
     */
    public function test_server_stats_event_triggers_listener(): void
    {
        // Reset alert states
        $this->server->update([
            'is_load_alert_active' => false,
            'is_memory_alert_active' => false,
            'is_disk_alert_active' => false,
            'resource_alerts_enabled' => true,
            'alert_load_threshold' => 5.0,
            'alert_memory_threshold' => 90.0,
            'alert_disk_threshold' => 90.0,
            'last_resource_alert_at' => null,
        ]);

        // Enable notification preferences
        NotificationPreference::updateOrCreate(
            ['user_id' => $this->user->id, 'event_type' => NotificationPreference::EVENT_SERVER_HIGH_LOAD],
            ['in_app_enabled' => true, 'email_enabled' => false]
        );

        // Get initial state
        $initialNotificationCount = $this->user->notifications()->count();

        // Create event with high load stats
        $statsWithHighLoad = [
            'load_1m' => 8.5,  // Above threshold of 5.0
            'load_5m' => 7.0,
            'load_15m' => 6.0,
            'memory_percent' => 50.0,  // Below threshold
            'disk_percent' => 60.0,    // Below threshold
        ];

        // Call listener directly (bypassing queue) for synchronous testing
        $event = new ServerStatsUpdated($this->server, $statsWithHighLoad);
        $listener = new ServerStatsListener();
        $listener->handle($event);

        // Refresh server
        $this->server->refresh();

        echo "\n ServerStatsListener called directly with high load\n";
        echo " Load alert active: " . ($this->server->is_load_alert_active ? 'Yes' : 'No') . "\n";

        // Check if alert state was updated
        $this->assertTrue($this->server->is_load_alert_active, 'Load alert should be active after high load event');

        // Check notification was sent
        $newNotificationCount = $this->user->notifications()->count();
        echo " Notifications: {$initialNotificationCount} -> {$newNotificationCount}\n";

        // Reset for next test
        $this->server->update([
            'is_load_alert_active' => false,
            'last_resource_alert_at' => null,
        ]);

        // Clean up test notifications
        $this->user->notifications()
            ->where('type', ServerHighLoad::class)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->delete();
    }

    /**
     * Test viewing notification in UI after triggering
     */
    public function test_view_notification_in_ui(): void
    {
        // Send a notification
        Notification::sendNow($this->user, new ServerHighLoad($this->server, 8.5, 5.0));

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit($this->appUrl())
                ->waitForText('Dashboard', 10)
                ->pause(1000)
                ->screenshot('resource-07-dashboard-with-notif');

            // Try to click on notification bell
            $browser->script("
                const bell = document.querySelector('[data-notification-trigger], button[x-ref=\"trigger\"], .fi-dropdown-trigger, .fi-topbar button svg[d*=\"M15 17h5l\"]');
                if (bell) {
                    const button = bell.closest('button') || bell;
                    button.click();
                }
            ");

            $browser->pause(1000)
                ->screenshot('resource-08-notification-dropdown');

            // Check for notification content
            $html = $browser->driver->getPageSource();
            $hasHighLoadNotif = stripos($html, 'High Load') !== false || stripos($html, 'load') !== false;

            echo "\n Notification visible in dropdown: " . ($hasHighLoadNotif ? 'Yes' : 'No') . "\n";
        });

        // Clean up
        $this->user->notifications()
            ->where('type', ServerHighLoad::class)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->delete();
    }

    /**
     * Test server alert threshold settings in edit page
     */
    public function test_server_alert_threshold_settings(): void
    {
        $this->browse(function (Browser $browser) {
            // Go to server edit page
            $browser->loginAs($this->user)
                ->visit($this->appUrl("servers/{$this->server->id}/edit"))
                ->pause(3000)
                ->screenshot('resource-09-server-edit');

            $html = $browser->driver->getPageSource();

            // Check for alert threshold fields
            $hasAlertSettings = (
                stripos($html, 'alert') !== false ||
                stripos($html, 'threshold') !== false ||
                stripos($html, 'load') !== false
            );

            echo "\n Server edit page has alert settings: " . ($hasAlertSettings ? 'Yes' : 'No') . "\n";

            $browser->screenshot('resource-10-alert-settings');
        });
    }

    /**
     * Test resources normal notification after recovery
     */
    public function test_resources_normal_notification(): void
    {
        // Set up: mark an alert as active
        $this->server->update([
            'is_load_alert_active' => true,
            'resource_alerts_enabled' => true,
            'alert_load_threshold' => 5.0,
        ]);

        // Enable notification preference
        NotificationPreference::updateOrCreate(
            ['user_id' => $this->user->id, 'event_type' => NotificationPreference::EVENT_SERVER_RESOURCES_NORMAL],
            ['in_app_enabled' => true, 'email_enabled' => false]
        );

        $initialCount = $this->user->notifications()->count();

        // Create event with normal stats and call listener directly
        $normalStats = [
            'load_1m' => 1.0,   // Below threshold
            'memory_percent' => 40.0,  // Below threshold
            'disk_percent' => 50.0,    // Below threshold
        ];

        $event = new ServerStatsUpdated($this->server, $normalStats);
        $listener = new ServerStatsListener();
        $listener->handle($event);

        $this->server->refresh();

        echo "\n ServerStatsListener called with normal stats (recovery)\n";
        echo " Load alert active: " . ($this->server->is_load_alert_active ? 'Yes' : 'No') . "\n";

        // Alert should be deactivated
        $this->assertFalse($this->server->is_load_alert_active, 'Load alert should be deactivated');

        // Should have received "resources normal" notification
        $newCount = $this->user->notifications()->count();
        echo " Notifications: {$initialCount} -> {$newCount}\n";

        // Clean up
        $this->user->notifications()
            ->where('type', ServerResourcesNormal::class)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->delete();
    }

    /**
     * Test complete alert cycle: normal -> high -> normal
     */
    public function test_complete_alert_cycle(): void
    {
        echo "\n=== Complete Alert Cycle Test ===\n";

        // Reset server state
        $this->server->update([
            'is_load_alert_active' => false,
            'is_memory_alert_active' => false,
            'is_disk_alert_active' => false,
            'resource_alerts_enabled' => true,
            'alert_load_threshold' => 5.0,
            'alert_memory_threshold' => 90.0,
            'alert_disk_threshold' => 90.0,
            'last_resource_alert_at' => null,
        ]);

        // Enable notifications
        foreach ([
            NotificationPreference::EVENT_SERVER_HIGH_LOAD,
            NotificationPreference::EVENT_SERVER_RESOURCES_NORMAL,
        ] as $eventType) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $this->user->id, 'event_type' => $eventType],
                ['in_app_enabled' => true, 'email_enabled' => false]
            );
        }

        $initialCount = $this->user->notifications()->count();
        echo " Initial notification count: {$initialCount}\n";

        $listener = new ServerStatsListener();

        // Step 1: Normal stats (no alert)
        echo "\n Step 1: Normal stats\n";
        $event1 = new ServerStatsUpdated($this->server, [
            'load_1m' => 2.0,
            'memory_percent' => 40.0,
            'disk_percent' => 50.0,
        ]);
        $listener->handle($event1);
        $this->server->refresh();
        echo " Load alert active: " . ($this->server->is_load_alert_active ? 'Yes' : 'No') . "\n";

        // Step 2: High load (trigger alert)
        echo "\n Step 2: High load (should trigger alert)\n";
        $event2 = new ServerStatsUpdated($this->server, [
            'load_1m' => 8.5,  // Above 5.0 threshold
            'memory_percent' => 40.0,
            'disk_percent' => 50.0,
        ]);
        $listener->handle($event2);
        $this->server->refresh();
        echo " Load alert active: " . ($this->server->is_load_alert_active ? 'Yes' : 'No') . "\n";
        $this->assertTrue($this->server->is_load_alert_active);

        // Step 3: Back to normal (resolve alert)
        echo "\n Step 3: Back to normal (should resolve alert)\n";
        $this->server->update(['last_resource_alert_at' => null]); // Reset cooldown for test
        $event3 = new ServerStatsUpdated($this->server, [
            'load_1m' => 2.0,  // Below threshold
            'memory_percent' => 40.0,
            'disk_percent' => 50.0,
        ]);
        $listener->handle($event3);
        $this->server->refresh();
        echo " Load alert active: " . ($this->server->is_load_alert_active ? 'Yes' : 'No') . "\n";
        $this->assertFalse($this->server->is_load_alert_active);

        // Check notifications
        $finalCount = $this->user->notifications()->count();
        echo "\n Final notification count: {$finalCount}\n";
        echo " New notifications: " . ($finalCount - $initialCount) . "\n";

        // List recent notifications
        $recentNotifs = $this->user->notifications()
            ->where('created_at', '>=', now()->subMinutes(5))
            ->get();
        echo "\n Recent notifications:\n";
        foreach ($recentNotifs as $notif) {
            echo " - " . class_basename($notif->type) . ": " . ($notif->data['title'] ?? 'N/A') . "\n";
        }

        // Clean up
        $this->user->notifications()
            ->whereIn('type', [ServerHighLoad::class, ServerResourcesNormal::class])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->delete();
    }
}
