<?php

namespace Tests\Browser;

use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ApacheServiceTest extends DuskTestCase
{
    protected User $user;
    protected string $teamId;
    protected Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', $this->getTestUserEmail())->first();

        if (!$this->user) {
            $this->markTestSkipped('Test user not found. Configure DUSK_TEST_USER_EMAIL in .env');
        }

        $this->teamId = $this->user->currentTeam->id;
        $this->server = $this->user->currentTeam->servers()->firstOrFail();
    }

    public function test_apache_service_exists_in_database(): void
    {
        $apache = $this->server->services()
            ->where('type', Service::TYPE_APACHE)
            ->first();

        $this->assertNotNull($apache, 'Apache service should exist in database');
        $this->assertEquals('apache', $apache->type);
        $this->assertEquals('2.4', $apache->version);
    }

    public function test_apache_reported_in_heartbeat(): void
    {
        $this->server->refresh();

        $hasApache = collect($this->server->services_status)
            ->contains('name', 'apache2');

        $this->assertTrue($hasApache, 'Apache should be reported in heartbeat');
    }

    public function test_services_page_loads_with_apache(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->teamId}/services")
                ->waitForText('Services', 10)
                ->assertSee('apache')
                ->screenshot('services-page-with-apache');
        });
    }

    public function test_apache_shows_in_services_table(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->teamId}/services")
                ->waitForText('Services', 10)
                ->pause(2000);

            // Check page source for apache
            $pageSource = $browser->driver->getPageSource();
            $hasApache = str_contains(strtolower($pageSource), 'apache');

            $this->assertTrue($hasApache, 'Page should contain "apache" text');
        });
    }

    public function test_apache_can_be_managed(): void
    {
        // Verify Apache service exists and can be found
        $apache = $this->server->services()
            ->where('type', Service::TYPE_APACHE)
            ->first();

        $this->assertNotNull($apache);

        // Verify it has the correct attributes
        $this->assertEquals('apache', $apache->type);
        $this->assertContains($apache->status, ['active', 'stopped', 'starting', 'stopping']);

        // Verify the display name method works
        $this->assertNotEmpty($apache->display_name);
    }

    public function test_webapp_observer_triggers_apache_start(): void
    {
        // Get Apache and set it to stopped
        $apache = $this->server->services()
            ->where('type', Service::TYPE_APACHE)
            ->first();

        $this->assertNotNull($apache, 'Apache service must exist');

        $originalStatus = $apache->status;
        $apache->update(['status' => Service::STATUS_STOPPED]);

        // Count existing jobs before creating webapp
        $jobCountBefore = $this->server->agentJobs()->count();

        // Create a WebApp with nginx_apache mode
        $webApp = \App\Models\WebApp::create([
            'team_id' => $this->teamId,
            'server_id' => $this->server->id,
            'name' => 'test-apache-' . time(),
            'domain' => 'test-apache-' . time() . '.test',
            'web_server' => \App\Models\WebApp::WEB_SERVER_NGINX_APACHE,
            'php_version' => '8.3',
            'status' => 'pending',
            'system_user' => 'testuser',
            'deploy_path' => '/home/testuser/test-apache',
        ]);

        // Count jobs after - should have increased
        $jobCountAfter = $this->server->agentJobs()->count();

        // Check if a service_start job was created for Apache
        $apacheStartJob = $this->server->agentJobs()
            ->where('type', 'service_start')
            ->where('created_at', '>=', now()->subMinutes(1))
            ->get()
            ->filter(function ($job) {
                $payload = $job->payload;
                return isset($payload['service_type']) && $payload['service_type'] === 'apache';
            })
            ->first();

        // Clean up
        $webApp->forceDelete();
        $apache->update(['status' => $originalStatus]);

        $this->assertNotNull(
            $apacheStartJob,
            'Apache start job should be dispatched when WebApp uses nginx_apache mode'
        );
    }
}
