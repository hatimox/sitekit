<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FilamentLoginTest extends DuskTestCase
{
    /**
     * Test logging into the Filament admin panel.
     */
    public function testFilamentLogin(): void
    {
        $user = User::first();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/app/login')
                    ->waitFor('input[type="email"]', 10)
                    ->type('input[type="email"]', $user->email)
                    ->type('input[type="password"]', 'password')
                    ->press('Sign in')
                    ->waitForText('Dashboard', 15)
                    ->assertSee('Dashboard')
                    ->assertSee('Getting Started');
        });
    }

    /**
     * Test navigating to servers page after login.
     */
    public function testNavigateToServersPage(): void
    {
        $user = User::first();

        $this->browse(function (Browser $browser) use ($user) {
            // Use Dusk's loginAs helper
            $browser->loginAs($user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Servers
            $browser->clickLink('Servers')
                    ->waitForText('Servers', 10)
                    ->assertSee('Servers');
        });
    }
}
