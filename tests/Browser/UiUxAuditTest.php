<?php

namespace Tests\Browser;

use App\Models\CronJob;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class UiUxAuditTest extends DuskTestCase
{
    protected static ?User $user = null;
    protected static ?Team $team = null;
    protected static ?Server $server = null;
    protected static bool $setupComplete = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!static::$setupComplete) {
            static::$user = User::where('email', $this->getTestUserEmail())->first();
            if (static::$user) {
                static::$team = static::$user->currentTeam;
                static::$server = Server::where('team_id', static::$team->id)->first();
            }
            static::$setupComplete = true;
        }

        if (!static::$user) {
            $this->markTestSkipped('Test user not found. Configure DUSK_TEST_USER_EMAIL in .env');
        }
    }

    /**
     * Test 1: Verify navigation groups are correctly structured
     */
    public function test_01_navigation_groups_structure(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(static::$user)
                ->visitRoute('filament.app.pages.dashboard', ['tenant' => static::$team->id])
                ->waitForText('Dashboard', 10);

            // Check Infrastructure group exists with expected items
            $browser->assertSee('Infrastructure')
                ->assertSee('Servers')
                ->assertSee('SSH Keys')
                ->assertSee('Services')
                ->assertSee('Workers');

            // Check Applications group
            $browser->assertSee('Applications')
                ->assertSee('Web Apps')
                ->assertSee('Databases')
                ->assertSee('Cron Jobs');

            // Check Security group
            $browser->assertSee('Security')
                ->assertSee('Firewall Rules');

            // Check Monitoring group (Health Monitors should be here now)
            $browser->assertSee('Monitoring')
                ->assertSee('Health Monitors')
                ->assertSee('Agent Jobs');

            // Check Settings group
            $browser->assertSee('Settings')
                ->assertSee('Source Providers')
                ->assertSee('Activity Log');

            $browser->screenshot('test_01_navigation_groups');
        });
    }

    /**
     * Test 2: Verify database notifications bell icon is visible
     */
    public function test_02_database_notifications_visible(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(static::$user)
                ->visitRoute('filament.app.pages.dashboard', ['tenant' => static::$team->id])
                ->waitForText('Dashboard', 10);

            // The notifications bell should be in the header
            // Filament adds a button with notifications trigger
            $browser->assertPresent('[x-data*="notifications"]')
                ->screenshot('test_02_notifications_bell');
        });
    }

    /**
     * Test 3: Verify global search is accessible
     */
    public function test_03_global_search_accessible(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(static::$user)
                ->visitRoute('filament.app.pages.dashboard', ['tenant' => static::$team->id])
                ->waitForText('Dashboard', 10);

            // Global search input should be present
            $browser->assertPresent('[x-data*="globalSearch"]')
                ->screenshot('test_03_global_search');
        });
    }

    /**
     * Test 4: Verify CronJob Run Now action exists
     */
    public function test_04_cronjob_run_now_action(): void
    {
        $this->browse(function (Browser $browser) {
            // First ensure we have a cron job
            $cronJob = CronJob::where('team_id', static::$team->id)->first();

            if (!$cronJob && static::$server) {
                $cronJob = CronJob::create([
                    'team_id' => static::$team->id,
                    'server_id' => static::$server->id,
                    'name' => 'Test Cron Job',
                    'command' => 'echo "test"',
                    'schedule' => '* * * * *',
                    'user' => 'sitekit',
                    'is_active' => true,
                ]);
            }

            if (!$cronJob) {
                $this->markTestSkipped('No cron job available for testing');
                return;
            }

            $browser->loginAs(static::$user)
                ->visitRoute('filament.app.resources.cron-jobs.index', ['tenant' => static::$team->id])
                ->waitForText('Cron Jobs', 10);

            // Click the actions dropdown for the first row
            $browser->click('table tbody tr:first-child [data-identifier="actions"]')
                ->waitFor('[x-ref="panel"]')
                ->assertSee('Run Now')
                ->screenshot('test_04_cronjob_run_now_action');
        });
    }

    /**
     * Test 5: Verify Health Monitors are under Monitoring group
     */
    public function test_05_health_monitors_in_monitoring_group(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(static::$user)
                ->visitRoute('filament.app.resources.health-monitors.index', ['tenant' => static::$team->id])
                ->waitForText('Health Monitors', 10);

            // The breadcrumb or navigation should show Monitoring
            $browser->assertSee('Monitoring')
                ->screenshot('test_05_health_monitors_monitoring');
        });
    }

    /**
     * Test 6: Verify Services are under Infrastructure group
     */
    public function test_06_services_in_infrastructure_group(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(static::$user)
                ->visitRoute('filament.app.resources.services.index', ['tenant' => static::$team->id])
                ->waitForText('Services', 10);

            // The navigation should show Infrastructure
            $browser->assertSee('Infrastructure')
                ->screenshot('test_06_services_infrastructure');
        });
    }

    /**
     * Test 7: Verify Workers are under Infrastructure group
     */
    public function test_07_workers_in_infrastructure_group(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(static::$user)
                ->visitRoute('filament.app.resources.supervisor-programs.index', ['tenant' => static::$team->id])
                ->waitForText('Workers', 10);

            // The navigation should show Infrastructure
            $browser->assertSee('Infrastructure')
                ->screenshot('test_07_workers_infrastructure');
        });
    }

    /**
     * Test 8: Verify SPA mode is working (page transitions without full reload)
     */
    public function test_08_spa_mode_working(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(static::$user)
                ->visitRoute('filament.app.pages.dashboard', ['tenant' => static::$team->id])
                ->waitForText('Dashboard', 10);

            // Store the current page load time
            $browser->script('window.spaTestTime = Date.now()');

            // Click on Servers link
            $browser->clickLink('Servers')
                ->waitForText('Servers', 10);

            // Check that SPA navigation happened (Livewire wire:navigate)
            // The page should have wire:navigate attributes
            $browser->assertPresent('[wire\\:navigate]')
                ->screenshot('test_08_spa_mode');
        });
    }
}
