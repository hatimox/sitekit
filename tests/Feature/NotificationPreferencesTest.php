<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_preference_can_be_created(): void
    {
        $user = User::factory()->create();

        $preference = NotificationPreference::getOrCreate($user, NotificationPreference::EVENT_SSL_ISSUED);

        $this->assertNotNull($preference);
        $this->assertEquals($user->id, $preference->user_id);
        $this->assertEquals(NotificationPreference::EVENT_SSL_ISSUED, $preference->event_type);
    }

    public function test_should_send_email_returns_default_when_no_preference(): void
    {
        $user = User::factory()->create();

        // Service crashed has default_email = true (critical alert)
        $this->assertTrue(
            NotificationPreference::shouldSendEmail($user, NotificationPreference::EVENT_SERVICE_CRASHED)
        );

        // Deployment completed has default_email = false
        $this->assertFalse(
            NotificationPreference::shouldSendEmail($user, NotificationPreference::EVENT_DEPLOYMENT_COMPLETED)
        );
    }

    public function test_should_send_email_respects_user_preference(): void
    {
        $user = User::factory()->create();

        // Create preference with email disabled
        NotificationPreference::create([
            'user_id' => $user->id,
            'event_type' => NotificationPreference::EVENT_SSL_ISSUED,
            'in_app_enabled' => true,
            'email_enabled' => false,
            'email_frequency' => NotificationPreference::FREQUENCY_IMMEDIATE,
        ]);

        $this->assertFalse(
            NotificationPreference::shouldSendEmail($user, NotificationPreference::EVENT_SSL_ISSUED)
        );
    }

    public function test_should_send_email_respects_never_frequency(): void
    {
        $user = User::factory()->create();

        // Create preference with frequency = never
        NotificationPreference::create([
            'user_id' => $user->id,
            'event_type' => NotificationPreference::EVENT_DEPLOYMENT_FAILED,
            'in_app_enabled' => true,
            'email_enabled' => true,
            'email_frequency' => NotificationPreference::FREQUENCY_NEVER,
        ]);

        $this->assertFalse(
            NotificationPreference::shouldSendEmail($user, NotificationPreference::EVENT_DEPLOYMENT_FAILED)
        );
    }

    public function test_should_send_in_app_returns_default_when_no_preference(): void
    {
        $user = User::factory()->create();

        // All events default to in_app enabled
        $this->assertTrue(
            NotificationPreference::shouldSendInApp($user, NotificationPreference::EVENT_SSL_ISSUED)
        );
    }

    public function test_should_send_in_app_respects_user_preference(): void
    {
        $user = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id,
            'event_type' => NotificationPreference::EVENT_SSL_ISSUED,
            'in_app_enabled' => false,
            'email_enabled' => true,
            'email_frequency' => NotificationPreference::FREQUENCY_IMMEDIATE,
        ]);

        $this->assertFalse(
            NotificationPreference::shouldSendInApp($user, NotificationPreference::EVENT_SSL_ISSUED)
        );
    }

    public function test_get_event_types_returns_all_events(): void
    {
        $eventTypes = NotificationPreference::getEventTypes();

        $this->assertArrayHasKey(NotificationPreference::EVENT_SSL_ISSUED, $eventTypes);
        $this->assertArrayHasKey(NotificationPreference::EVENT_SSL_EXPIRING, $eventTypes);
        $this->assertArrayHasKey(NotificationPreference::EVENT_DEPLOYMENT_COMPLETED, $eventTypes);
        $this->assertArrayHasKey(NotificationPreference::EVENT_DEPLOYMENT_FAILED, $eventTypes);
        $this->assertArrayHasKey(NotificationPreference::EVENT_SERVER_PROVISIONED, $eventTypes);
    }

    public function test_get_frequency_options_returns_all_frequencies(): void
    {
        $frequencies = NotificationPreference::getFrequencyOptions();

        $this->assertArrayHasKey(NotificationPreference::FREQUENCY_IMMEDIATE, $frequencies);
        $this->assertArrayHasKey(NotificationPreference::FREQUENCY_DAILY, $frequencies);
        $this->assertArrayHasKey(NotificationPreference::FREQUENCY_WEEKLY, $frequencies);
        $this->assertArrayHasKey(NotificationPreference::FREQUENCY_NEVER, $frequencies);
    }
}
