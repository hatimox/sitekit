<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Server;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CreateFirewallRuleTest extends DuskTestCase
{
    /**
     * Test viewing server details and firewall page through Filament UI.
     */
    public function testViewServerFirewallPage(): void
    {
        // Use demo user who owns the test server
        $user = User::where('email', $this->getTestUserEmail())->first();
        $server = Server::where('ip_address', $this->getTestServerIp())->first();

        if (!$user || !$server) {
            $this->markTestSkipped('Test user or server not found. Configure DUSK_TEST_USER_EMAIL and DUSK_TEST_SERVER_IP in .env');
        }

        $this->browse(function (Browser $browser) use ($user, $server) {
            $browser->loginAs($user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15)
                    ->screenshot('01-dashboard');

            // Navigate to Servers
            $browser->clickLink('Servers')
                    ->waitForText('Servers', 10)
                    ->screenshot('02-servers-list');

            // Click on the server name to view details
            $browser->clickLink($server->name)
                    ->waitForText($server->name, 10)
                    ->screenshot('03-server-detail');

            // Click on Firewall tab/link
            $browser->clickLink('Firewall')
                    ->waitForText('Firewall', 10)
                    ->screenshot('04-firewall-page');

            // Verify we're on the firewall management page
            $browser->assertSee('Firewall');
        });
    }
}
