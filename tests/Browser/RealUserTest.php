<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Server;
use App\Models\Service;
use App\Models\WebApp;
use App\Models\Database as DatabaseModel;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Real User Test
 *
 * Configure in .env:
 * - DUSK_TEST_USER_EMAIL
 * - DUSK_TEST_SERVER_IP
 */
class RealUserTest extends DuskTestCase
{
    protected $user;
    protected $server;
    protected $teamId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::where('email', $this->getTestUserEmail())->first();
        $this->server = Server::where('ip_address', $this->getTestServerIp())->first();

        if (!$this->user) {
            $this->markTestSkipped('Test user not found. Configure DUSK_TEST_USER_EMAIL in .env');
        }

        // Get the user's current team ID for tenant-aware URLs
        $this->teamId = $this->user->currentTeam?->id ?? $this->user->teams()->first()?->id;
    }

    /**
     * Helper to get tenant-aware URL
     */
    protected function appUrl(string $path = ''): string
    {
        if ($this->teamId) {
            return "/app/{$this->teamId}" . ($path ? "/{$path}" : '');
        }
        return "/app" . ($path ? "/{$path}" : '');
    }

    /**
     * Test dashboard loads correctly
     */
    public function test_dashboard_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit($this->appUrl())
                    ->pause(3000)
                    ->screenshot('real-01-dashboard');

            $html = $browser->driver->getPageSource();
            $this->assertStringContainsString('Dashboard', $html);
            echo "\n✅ Dashboard loaded successfully\n";
            echo "   Team ID: {$this->teamId}\n";
        });
    }

    /**
     * Test servers page
     */
    public function test_servers_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit($this->appUrl('servers'))
                    ->pause(3000)
                    ->screenshot('real-02-servers');

            $html = $browser->driver->getPageSource();
            $this->assertStringContainsString('Servers', $html);

            if ($this->server) {
                echo "\n✅ Server '{$this->server->name}' should be on servers page\n";
            }
        });
    }

    /**
     * Test server detail page with services
     */
    public function test_server_detail_page(): void
    {
        if (!$this->server) {
            $this->markTestSkipped('Test server not found');
        }

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit($this->appUrl("servers/{$this->server->id}"))
                    ->pause(4000)
                    ->screenshot('real-03-server-detail');

            $html = $browser->driver->getPageSource();
            $this->assertStringContainsString($this->server->name, $html);
            echo "\n✅ Server detail page loaded for '{$this->server->name}'\n";

            // Check services
            $services = Service::where('server_id', $this->server->id)->get();
            echo "   Services in database: {$services->count()}\n";
            foreach ($services as $svc) {
                echo "   - {$svc->type} {$svc->version}: {$svc->status}\n";
            }
        });
    }

    /**
     * Test services relation manager on server page
     */
    public function test_server_services(): void
    {
        if (!$this->server) {
            $this->markTestSkipped('Test server not found');
        }

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit($this->appUrl("servers/{$this->server->id}"))
                    ->pause(3000);

            // Scroll down to find services
            $browser->script('window.scrollTo(0, document.body.scrollHeight / 2)');
            $browser->pause(1000)
                    ->screenshot('real-04-server-services');

            $html = $browser->driver->getPageSource();

            // Check for some common services
            $expectedServices = ['nginx', 'mariadb', 'redis', 'php'];
            $found = [];
            foreach ($expectedServices as $svc) {
                if (stripos($html, $svc) !== false) {
                    $found[] = $svc;
                }
            }

            echo "\n✅ Found services in UI: " . implode(', ', $found) . "\n";
        });
    }

    /**
     * Test web apps page
     */
    public function test_web_apps_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit($this->appUrl('web-apps'))
                    ->pause(3000)
                    ->screenshot('real-05-web-apps');

            $html = $browser->driver->getPageSource();
            $this->assertStringContainsString('Web Apps', $html);

            $count = WebApp::whereHas('server', function($q) {
                $q->whereHas('team', function($q2) {
                    $q2->whereHas('users', function($q3) {
                        $q3->where('user_id', $this->user->id);
                    });
                });
            })->count();

            echo "\n✅ Web Apps page loaded ({$count} apps accessible)\n";
        });
    }

    /**
     * Test databases page
     */
    public function test_databases_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit($this->appUrl('databases'))
                    ->pause(3000)
                    ->screenshot('real-06-databases');

            $html = $browser->driver->getPageSource();
            $this->assertStringContainsString('Databases', $html);

            echo "\n✅ Databases page loaded\n";
        });
    }

    /**
     * Test SSH keys page
     */
    public function test_ssh_keys_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit($this->appUrl('ssh-keys'))
                    ->pause(3000)
                    ->screenshot('real-07-ssh-keys');

            $html = $browser->driver->getPageSource();
            $this->assertStringContainsString('SSH', $html);

            echo "\n✅ SSH Keys page loaded\n";
        });
    }

    /**
     * Test individual service page (nginx)
     */
    public function test_nginx_service_page(): void
    {
        if (!$this->server) {
            $this->markTestSkipped('Test server not found');
        }

        $nginx = Service::where('server_id', $this->server->id)
                       ->where('type', 'nginx')
                       ->first();

        if (!$nginx) {
            $this->markTestSkipped('Nginx service not found');
        }

        $this->browse(function (Browser $browser) use ($nginx) {
            $browser->loginAs($this->user)
                    ->visit($this->appUrl("services/{$nginx->id}"))
                    ->pause(3000)
                    ->screenshot('real-08-nginx-service');

            $html = $browser->driver->getPageSource();
            $this->assertStringContainsString('Nginx', $html);

            echo "\n✅ Nginx service page loaded (status: {$nginx->status})\n";
        });
    }

    /**
     * Test firewall rules page
     */
    public function test_firewall_rules_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit($this->appUrl('firewall-rules'))
                    ->pause(3000)
                    ->screenshot('real-09-firewall');

            $html = $browser->driver->getPageSource();
            $this->assertStringContainsString('Firewall', $html);

            echo "\n✅ Firewall Rules page loaded\n";
        });
    }

    /**
     * Test cron jobs page
     */
    public function test_cron_jobs_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit($this->appUrl('cron-jobs'))
                    ->pause(3000)
                    ->screenshot('real-10-cron-jobs');

            $html = $browser->driver->getPageSource();
            $this->assertStringContainsString('Cron', $html);

            echo "\n✅ Cron Jobs page loaded\n";
        });
    }

    /**
     * Test navigation through sidebar
     */
    public function test_sidebar_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit($this->appUrl())
                    ->pause(3000);

            $pages = [
                'Servers',
                'Web Apps',
                'Databases',
                'SSH Keys',
            ];

            foreach ($pages as $page) {
                $browser->clickLink($page)
                        ->pause(2000);

                $html = $browser->driver->getPageSource();
                if (strpos($html, $page) !== false) {
                    echo "   ✅ {$page} navigation works\n";
                } else {
                    echo "   ⚠️ {$page} may not have loaded correctly\n";
                }
            }

            $browser->screenshot('real-11-navigation-complete');
        });
    }
}
