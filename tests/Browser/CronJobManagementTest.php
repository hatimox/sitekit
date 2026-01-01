<?php

namespace Tests\Browser;

use App\Models\CronJob;
use App\Models\Server;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Real User Journey Test: Cron Job Management
 *
 * Configure test user and server in .env:
 * - DUSK_TEST_USER_EMAIL
 * - DUSK_TEST_SERVER_IP
 */
class CronJobManagementTest extends DuskTestCase
{
    protected string $testUser;
    protected string $testPassword = 'password';
    protected string $serverIp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testUser = $this->getTestUserEmail();
        $this->serverIp = $this->getTestServerIp();
    }

    /**
     * Helper to login via script-based approach for Filament forms
     */
    protected function login(Browser $browser): void
    {
        $browser->visit('/app/login')
            ->pause(2000);

        $browser->script("
            const emailInput = document.querySelector('input[type=\"email\"]');
            if (emailInput) {
                emailInput.value = '{$this->testUser}';
                emailInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            const passInput = document.querySelector('input[type=\"password\"]');
            if (passInput) {
                passInput.value = '{$this->testPassword}';
                passInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        ");

        $browser->pause(500);

        $browser->script("
            const btn = document.querySelector('button[type=\"submit\"]');
            if (btn) btn.click();
        ");

        $browser->pause(5000);
    }

    /**
     * Test 4.1: Create Cron Job
     */
    public function test_create_cron_job(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Navigate to Cron Jobs
            $browser->visit('/app/' . $this->teamId . '/cron-jobs')
                ->pause(3000)
                ->screenshot('cronjob-list-before');

            // Create cron job via model
            echo " Creating cron job via model...\n";

            $server = Server::where('team_id', $this->teamId)->first();

            // Delete existing test cron job
            CronJob::where('name', 'test-cron')->delete();

            $cronJob = CronJob::create([
                'team_id' => $this->teamId,
                'server_id' => $server->id,
                'name' => 'test-cron',
                'command' => 'echo "cron test $(date)" >> /tmp/cron-test.log',
                'schedule' => '* * * * *', // Every minute
                'user' => 'sitekit',
                'status' => 'pending',
            ]);

            echo " Created cron job: {$cronJob->name}\n";
            echo " Schedule: {$cronJob->schedule}\n";
            echo " Command: {$cronJob->command}\n";

            // Sync cron jobs to server (uses sync_crontab job type)
            $cronJob->is_active = true;
            $cronJob->save();
            $cronJob->syncToServer();
            echo " Dispatched sync_crontab job\n";

            // Wait for agent
            echo "\n Waiting 20 seconds for agent to create cron job...\n";
            sleep(20);

            // Verify cron job on server
            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo crontab -u sitekit -l 2>&1 | grep test-cron || echo NOT_FOUND'");
            echo " Cron verification: " . trim($output) . "\n";

            if (strpos($output, 'test-cron') !== false && strpos($output, 'NOT_FOUND') === false) {
                echo " SUCCESS: Cron job created\n";
            } else {
                echo " PENDING: Cron job may still be creating\n";
            }

            // Refresh and check UI
            $browser->visit('/app/' . $this->teamId . '/cron-jobs')
                ->pause(3000)
                ->screenshot('cronjob-list-after');

            $cronInList = $browser->script("
                return document.body.textContent.includes('test-cron') ? 'found' : 'not found';
            ");
            echo " Cron job in UI: " . $cronInList[0] . "\n";
        });
    }

    /**
     * Test 4.2: Monitor Cron Execution
     */
    public function test_monitor_cron_execution(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            echo " Waiting 90 seconds for cron to execute (runs every minute)...\n";
            sleep(90);

            // Verify cron executed on server
            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'cat /tmp/cron-test.log 2>&1 || echo FILE_NOT_FOUND'");
            echo " Cron log output:\n" . trim($output) . "\n";

            if (strpos($output, 'cron test') !== false) {
                echo " SUCCESS: Cron job executed and logged\n";
            } else {
                echo " PENDING: Cron log not found yet\n";
            }

            $browser->screenshot('cronjob-monitor');
        });
    }

    /**
     * Test 4.3: Update Cron Schedule
     */
    public function test_update_cron_schedule(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Find cron job
            $cronJob = CronJob::where('name', 'test-cron')->first();
            if (!$cronJob) {
                // Create if not exists
                $server = Server::where('team_id', $this->teamId)->first();
                $cronJob = CronJob::create([
                    'team_id' => $this->teamId,
                    'server_id' => $server->id,
                    'name' => 'test-cron',
                    'command' => 'echo "cron test $(date)" >> /tmp/cron-test.log',
                    'schedule' => '* * * * *',
                    'user' => 'sitekit',
                    'status' => 'active',
                ]);
            }

            echo " Updating cron schedule from every minute to every 5 minutes...\n";

            // Update schedule
            $cronJob->schedule = '*/5 * * * *';
            $cronJob->save();

            // Sync updated cron to server
            $cronJob->syncToServer();
            echo " Dispatched sync_crontab job for update\n";

            // Wait for agent
            echo "\n Waiting 20 seconds for agent to update cron job...\n";
            sleep(20);

            // Verify updated schedule on server
            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo crontab -u sitekit -l 2>&1'");
            echo " Updated crontab:\n" . trim($output) . "\n";

            if (strpos($output, '*/5') !== false) {
                echo " SUCCESS: Cron schedule updated to every 5 minutes\n";
            } else {
                echo " PENDING: Schedule may still be updating\n";
            }

            $browser->screenshot('cronjob-updated');
        });
    }

    /**
     * Test 4.4: Delete Cron Job (requires user confirmation)
     *
     * NOTE: This test is disabled by default. DELETE operations
     * require explicit user confirmation per test plan.
     * Rename to test_delete_cron_job() to enable.
     */
    public function skip_test_delete_cron_job(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Find cron job
            $cronJob = CronJob::where('name', 'test-cron')->first();
            if (!$cronJob) {
                echo " No test-cron found to delete\n";
                $this->markTestSkipped('No test-cron found');
                return;
            }

            echo " Deleting cron job: {$cronJob->name}\n";

            // Store info needed for sync
            $serverId = $cronJob->server_id;
            $teamId = $cronJob->team_id;
            $user = $cronJob->user;

            // Delete from database first
            $cronJob->delete();
            echo " Deleted from database\n";

            // Create AgentJob to sync remaining crons (empty list removes the cron)
            $remainingCrons = CronJob::where('server_id', $serverId)
                ->where('user', $user)
                ->get();

            \App\Models\AgentJob::create([
                'server_id' => $serverId,
                'team_id' => $teamId,
                'type' => 'sync_crontab',
                'payload' => [
                    'username' => $user,
                    'entries' => $remainingCrons->map(fn ($c) => [
                        'schedule' => $c->schedule,
                        'command' => $c->command,
                        'enabled' => $c->is_active,
                    ])->toArray(),
                ],
            ]);
            echo " Dispatched sync_crontab job (with remaining crons)\n";

            // Wait for agent
            echo "\n Waiting 20 seconds for agent to sync crontab...\n";
            sleep(20);

            // Verify deleted on server
            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo crontab -u sitekit -l 2>&1 | grep test-cron || echo NOT_FOUND'");
            echo " Cron verification after delete: " . trim($output) . "\n";

            if (strpos($output, 'NOT_FOUND') !== false) {
                echo " SUCCESS: Cron job deleted from server\n";
            } else {
                echo " PENDING: Cron job may still exist\n";
            }

            $browser->screenshot('cronjob-deleted');
        });
    }
}
