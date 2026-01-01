<?php

namespace Tests\Browser;

use App\Models\Team;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class NotificationPreferencesTest extends DuskTestCase
{
    protected User $user;
    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', $this->getTestUserEmail())->first();
        $this->team = $this->user?->currentTeam ?? $this->user?->teams()->first();

        if (!$this->user || !$this->team) {
            $this->markTestSkipped('Test user or team not found. Configure DUSK_TEST_USER_EMAIL in .env');
        }
    }

    public function test_can_view_notification_preferences_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/notification-preferences")
                ->assertSee('Notification Preferences')
                ->assertSee('Servers')
                ->assertSee('Deployments')
                ->assertSee('SSL Certificates')
                ->screenshot('np-01-page-loaded');
        });
    }

    public function test_toggles_show_correct_initial_state(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/notification-preferences")
                ->pause(1000)
                ->screenshot('np-02-initial-state');

            // Check that checkboxes exist
            $browser->assertPresent('input[type="checkbox"]');
        });
    }

    public function test_can_toggle_notifications_via_click(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/notification-preferences")
                ->pause(1000)
                ->screenshot('np-03-before-toggle');

            // Click the first checkbox
            $browser->script("
                const checkboxes = document.querySelectorAll('input[type=\"checkbox\"]');
                if (checkboxes.length > 0) {
                    checkboxes[0].click();
                }
            ");
            $browser->pause(500)
                ->screenshot('np-04-after-first-toggle');

            // Click another checkbox
            $browser->script("
                const checkboxes = document.querySelectorAll('input[type=\"checkbox\"]');
                if (checkboxes.length > 2) {
                    checkboxes[2].click();
                }
            ");
            $browser->pause(500)
                ->screenshot('np-05-after-second-toggle');
        });
    }

    public function test_can_save_preferences(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/notification-preferences")
                ->pause(1000);

            // Toggle a few items
            $browser->script("
                const checkboxes = document.querySelectorAll('input[type=\"checkbox\"]');
                if (checkboxes.length >= 4) {
                    checkboxes[1].click();
                    checkboxes[3].click();
                }
            ");
            $browser->pause(500)
                ->screenshot('np-06-before-save');

            // Click save button
            $browser->press('Save Preferences')
                ->pause(1500)
                ->screenshot('np-07-after-save');

            // Check for success notification
            $browser->assertSee('Preferences saved');
        });
    }

    public function test_preferences_persist_after_reload(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/notification-preferences")
                ->pause(1000);

            // Get initial state of first checkbox
            $initialState = $browser->script("
                const cb = document.querySelector('input[type=\"checkbox\"]');
                return cb ? cb.checked : null;
            ");

            // Toggle it
            $browser->script("
                const cb = document.querySelector('input[type=\"checkbox\"]');
                if (cb) cb.click();
            ");
            $browser->pause(500);

            // Save
            $browser->press('Save Preferences')
                ->pause(1500)
                ->screenshot('np-08-saved');

            // Reload
            $browser->refresh()
                ->pause(1500)
                ->screenshot('np-09-after-reload');

            // Verify the checkbox state changed
            $newState = $browser->script("
                const cb = document.querySelector('input[type=\"checkbox\"]');
                return cb ? cb.checked : null;
            ");

            // States should be different (toggled)
            $this->assertNotEquals($initialState, $newState);
        });
    }

    public function test_multiple_toggle_combinations(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/notification-preferences")
                ->pause(1000)
                ->screenshot('np-10-multi-start');

            // Toggle multiple items in different combinations
            $browser->script("
                const checkboxes = document.querySelectorAll('input[type=\"checkbox\"]');
                // Toggle every other one
                for (let i = 0; i < checkboxes.length && i < 10; i += 2) {
                    checkboxes[i].click();
                }
            ");
            $browser->pause(500)
                ->screenshot('np-11-multi-toggled');

            // Save
            $browser->press('Save Preferences')
                ->pause(1500)
                ->assertSee('Preferences saved')
                ->screenshot('np-12-multi-saved');

            // Toggle them back
            $browser->script("
                const checkboxes = document.querySelectorAll('input[type=\"checkbox\"]');
                for (let i = 0; i < checkboxes.length && i < 10; i += 2) {
                    checkboxes[i].click();
                }
            ");
            $browser->pause(500)
                ->screenshot('np-13-multi-restored');

            // Save again
            $browser->press('Save Preferences')
                ->pause(1500)
                ->assertSee('Preferences saved')
                ->screenshot('np-14-final-state');
        });
    }
}
