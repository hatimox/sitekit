<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class HelpFeaturesTest extends DuskTestCase
{
    protected function getTenantSlug(): string
    {
        $user = User::first();
        return $user->currentTeam->slug ?? $user->currentTeam->id;
    }

    /**
     * Test that the help widget button is visible on the dashboard.
     */
    public function testHelpWidgetButtonVisible(): void
    {
        $user = User::first();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15)
                    ->assertPresent('[x-data*="open"]'); // Help widget with Alpine.js
        });
    }

    /**
     * Test that clicking the help button opens the help panel.
     */
    public function testHelpWidgetPanelOpens(): void
    {
        $user = User::first();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15)
                    ->pause(1000);

            // Use script to click the help button (script returns array, not chainable)
            $browser->script("document.querySelector('.fixed.bottom-6.right-6 button').click()");

            $browser->pause(500)
                    ->waitForText('Help & Support', 5)
                    ->assertSee('Help & Support')
                    ->assertSee('How can we help you?');
        });
    }

    /**
     * Test the Quick Help tab content.
     */
    public function testHelpWidgetQuickHelpTab(): void
    {
        $user = User::first();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15)
                    ->pause(1000);

            $browser->script("document.querySelector('.fixed.bottom-6.right-6 button').click()");

            $browser->pause(500)
                    ->waitForText('Help & Support', 5)
                    ->assertSee('Quick Help')
                    ->assertSee('Getting Started')
                    ->assertSee('Connect a Server')
                    ->assertSee('Deploy an App')
                    ->assertSee('Create a Database');
        });
    }

    /**
     * Test switching to the FAQ tab.
     */
    public function testHelpWidgetFaqTab(): void
    {
        $user = User::first();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15)
                    ->pause(1000);

            $browser->script("document.querySelector('.fixed.bottom-6.right-6 button').click()");

            $browser->pause(500)
                    ->waitForText('Help & Support', 5);

            // Click FAQ tab
            $browser->script("[...document.querySelectorAll('button')].find(b => b.textContent.includes('FAQ')).click()");

            $browser->pause(500)
                    ->assertSee('How do I connect a server?')
                    ->assertSee('What PHP versions are supported?');
        });
    }

    /**
     * Test the help widget footer links.
     */
    public function testHelpWidgetFooterLinks(): void
    {
        $user = User::first();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15)
                    ->pause(1000);

            $browser->script("document.querySelector('.fixed.bottom-6.right-6 button').click()");

            $browser->pause(500)
                    ->waitForText('Help & Support', 5)
                    ->assertSee('Contact Support')
                    ->assertSee('Feedback');
        });
    }

    /**
     * Test the Documentation page loads via sidebar.
     */
    public function testDocumentationPageLoadsViaSidebar(): void
    {
        $user = User::first();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15)
                    // Click Documentation in sidebar
                    ->clickLink('Documentation')
                    ->waitForText('Documentation', 10)
                    ->assertSee('Documentation');
        });
    }

    /**
     * Test Documentation page topics via direct URL.
     */
    public function testDocumentationPageTopics(): void
    {
        $user = User::first();
        $tenant = $this->getTenantSlug();

        $this->browse(function (Browser $browser) use ($user, $tenant) {
            $browser->loginAs($user)
                    ->visit("/app/{$tenant}/documentation")
                    ->waitForText('Documentation', 10)
                    ->assertSee('Getting Started')
                    ->assertSee('Servers')
                    ->assertSee('Web Apps')
                    ->assertSee('Databases')
                    ->assertSee('Cron Jobs')
                    ->assertSee('Firewall');
        });
    }

    /**
     * Test Documentation page default content.
     */
    public function testDocumentationDefaultContent(): void
    {
        $user = User::first();
        $tenant = $this->getTenantSlug();

        $this->browse(function (Browser $browser) use ($user, $tenant) {
            $browser->loginAs($user)
                    ->visit("/app/{$tenant}/documentation")
                    ->waitForText('Documentation', 10)
                    ->assertSee('Welcome to SiteKit');
        });
    }

    /**
     * Test Documentation page firewall topic with CIDR info.
     */
    public function testDocumentationFirewallTopic(): void
    {
        $user = User::first();
        $tenant = $this->getTenantSlug();

        $this->browse(function (Browser $browser) use ($user, $tenant) {
            $browser->loginAs($user)
                    ->visit("/app/{$tenant}/documentation?topic=firewall")
                    ->waitForText('Documentation', 10)
                    ->assertSee('Firewall Rules')
                    ->assertSee('CIDR Notation');
        });
    }

    /**
     * Test Documentation cron-jobs topic.
     */
    public function testDocumentationCronJobsTopic(): void
    {
        $user = User::first();
        $tenant = $this->getTenantSlug();

        $this->browse(function (Browser $browser) use ($user, $tenant) {
            $browser->loginAs($user)
                    ->visit("/app/{$tenant}/documentation?topic=cron-jobs")
                    ->waitForText('Documentation', 10)
                    ->assertSee('Cron Schedule Format');
        });
    }

    /**
     * Test documentation page has detailed help content.
     */
    public function testDocumentationHasDetailedContent(): void
    {
        $user = User::first();
        $tenant = $this->getTenantSlug();

        $this->browse(function (Browser $browser) use ($user, $tenant) {
            // Check the deployments topic has content about Git-based deployments
            $browser->loginAs($user)
                    ->visit("/app/{$tenant}/documentation?topic=deployments")
                    ->waitForText('Documentation', 10)
                    ->assertSee('Deployments')
                    ->assertSee('Git-based deployments')
                    ->assertSee('Zero-downtime');
        });
    }

    /**
     * Test help widget is present on servers page.
     */
    public function testHelpWidgetPresentOnServersPage(): void
    {
        $user = User::first();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15)
                    ->clickLink('Servers')
                    ->waitForText('Servers', 10)
                    ->assertPresent('[x-data*="open"]')
                    ->pause(500);

            $browser->script("document.querySelector('.fixed.bottom-6.right-6 button').click()");

            $browser->pause(500)
                    ->waitForText('Help & Support', 5);
        });
    }

    /**
     * Test help widget is present on web apps page.
     */
    public function testHelpWidgetPresentOnWebAppsPage(): void
    {
        $user = User::first();
        $tenant = $this->getTenantSlug();

        $this->browse(function (Browser $browser) use ($user, $tenant) {
            $browser->loginAs($user)
                    ->visit("/app/{$tenant}/web-apps")
                    ->waitForText('Web Apps', 10)
                    ->assertPresent('[x-data*="open"]');
        });
    }
}
