<?php

namespace Tests\Browser;

use App\Models\AgentJob;
use App\Models\Service;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Cache/Queue Services Test
 *
 * Configure in .env:
 * - DUSK_TEST_USER_EMAIL
 * - DUSK_TEST_SERVER_IP
 */
class CacheQueueServicesTest extends DuskTestCase
{
    protected User $user;
    protected $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', $this->getTestUserEmail())->first();

        if (!$this->user) {
            $this->markTestSkipped('Test user not found. Configure DUSK_TEST_USER_EMAIL in .env');
        }

        $this->server = $this->user->currentTeam->servers->first();

        if (!$this->server) {
            $this->markTestSkipped('No server found for user');
        }
    }

    protected function getService(string $type): ?Service
    {
        return Service::where('server_id', $this->server->id)
            ->where('type', $type)
            ->first();
    }

    protected function waitForJobCompletion(AgentJob $job, int $maxWait = 30): bool
    {
        $start = time();
        while (time() - $start < $maxWait) {
            $job->refresh();
            if ($job->status === 'completed') {
                return true;
            }
            if ($job->status === 'failed') {
                return false;
            }
            sleep(1);
        }
        return false;
    }

    public function test_redis_restart(): void
    {
        $service = $this->getService(Service::TYPE_REDIS);
        $this->assertNotNull($service, 'Redis service not found');

        $job = $service->dispatchRestart();
        $this->assertNotNull($job);

        $success = $this->waitForJobCompletion($job);
        $job->refresh();

        $this->assertTrue($success, "Redis restart failed: " . ($job->error ?? 'timeout'));
    }

    public function test_redis_stop_and_start(): void
    {
        $service = $this->getService(Service::TYPE_REDIS);
        $this->assertNotNull($service, 'Redis service not found');

        // Stop
        $stopJob = $service->dispatchStop();
        $stopSuccess = $this->waitForJobCompletion($stopJob);
        $stopJob->refresh();
        $this->assertTrue($stopSuccess, "Redis stop failed: " . ($stopJob->error ?? 'timeout'));

        // Start
        $startJob = $service->dispatchStart();
        $startSuccess = $this->waitForJobCompletion($startJob);
        $startJob->refresh();
        $this->assertTrue($startSuccess, "Redis start failed: " . ($startJob->error ?? 'timeout'));
    }

    public function test_memcached_restart(): void
    {
        $service = $this->getService(Service::TYPE_MEMCACHED);
        $this->assertNotNull($service, 'Memcached service not found');

        $job = $service->dispatchRestart();
        $this->assertNotNull($job);

        $success = $this->waitForJobCompletion($job);
        $job->refresh();

        $this->assertTrue($success, "Memcached restart failed: " . ($job->error ?? 'timeout'));
    }

    public function test_memcached_stop_and_start(): void
    {
        $service = $this->getService(Service::TYPE_MEMCACHED);
        $this->assertNotNull($service, 'Memcached service not found');

        // Stop
        $stopJob = $service->dispatchStop();
        $stopSuccess = $this->waitForJobCompletion($stopJob);
        $stopJob->refresh();
        $this->assertTrue($stopSuccess, "Memcached stop failed: " . ($stopJob->error ?? 'timeout'));

        // Start
        $startJob = $service->dispatchStart();
        $startSuccess = $this->waitForJobCompletion($startJob);
        $startJob->refresh();
        $this->assertTrue($startSuccess, "Memcached start failed: " . ($startJob->error ?? 'timeout'));
    }

    public function test_beanstalkd_restart(): void
    {
        $service = $this->getService(Service::TYPE_BEANSTALKD);
        $this->assertNotNull($service, 'Beanstalkd service not found');

        $job = $service->dispatchRestart();
        $this->assertNotNull($job);

        $success = $this->waitForJobCompletion($job);
        $job->refresh();

        $this->assertTrue($success, "Beanstalkd restart failed: " . ($job->error ?? 'timeout'));
    }

    public function test_beanstalkd_stop_and_start(): void
    {
        $service = $this->getService(Service::TYPE_BEANSTALKD);
        $this->assertNotNull($service, 'Beanstalkd service not found');

        // Stop
        $stopJob = $service->dispatchStop();
        $stopSuccess = $this->waitForJobCompletion($stopJob);
        $stopJob->refresh();
        $this->assertTrue($stopSuccess, "Beanstalkd stop failed: " . ($stopJob->error ?? 'timeout'));

        // Start
        $startJob = $service->dispatchStart();
        $startSuccess = $this->waitForJobCompletion($startJob);
        $startJob->refresh();
        $this->assertTrue($startSuccess, "Beanstalkd start failed: " . ($startJob->error ?? 'timeout'));
    }

    public function test_all_services_status_after_tests(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app/services')
                    ->waitForText('Services', 10)
                    ->assertSee('Redis')
                    ->assertSee('Memcached')
                    ->assertSee('Beanstalkd')
                    ->screenshot('cache-queue-services-list');
        });
    }
}
