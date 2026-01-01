<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Simple Test
 *
 * Configure in .env:
 * - DUSK_TEST_USER_EMAIL
 */
class SimpleTest extends DuskTestCase
{
    public function test_homepage(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                    ->screenshot('simple-01-homepage')
                    ->pause(1000);

            echo "\nPage title: " . $browser->driver->getTitle() . "\n";
            echo "Current URL: " . $browser->driver->getCurrentURL() . "\n";
        });
    }

    public function test_login_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/app/login')
                    ->screenshot('simple-02-login')
                    ->pause(1000);

            echo "\nPage title: " . $browser->driver->getTitle() . "\n";
            echo "Current URL: " . $browser->driver->getCurrentURL() . "\n";

            // Try to find the form
            $html = $browser->driver->getPageSource();
            if (strpos($html, 'email') !== false) {
                echo "Found 'email' in page source\n";
            }
            if (strpos($html, 'password') !== false) {
                echo "Found 'password' in page source\n";
            }
        });
    }

    public function test_login_with_credentials(): void
    {
        $user = User::where('email', $this->getTestUserEmail())->first();

        $this->browse(function (Browser $browser) use ($user) {
            // First try loginAs (Laravel's built-in)
            $browser->loginAs($user)
                    ->visit('/app')
                    ->screenshot('simple-03-after-login')
                    ->pause(2000);

            echo "\nAfter loginAs:\n";
            echo "Current URL: " . $browser->driver->getCurrentURL() . "\n";

            $html = $browser->driver->getPageSource();
            if (strpos($html, 'Dashboard') !== false) {
                echo "✅ Found 'Dashboard' - login successful!\n";
            } else {
                echo "❌ Dashboard not found\n";
            }
        });
    }
}
