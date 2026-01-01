<?php

namespace Tests\Feature;

use App\Filament\Resources\CronJobResource;
use App\Filament\Resources\HealthMonitorResource;
use App\Filament\Resources\ServiceResource;
use App\Filament\Resources\SupervisorProgramResource;
use App\Models\CronJob;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiUxAuditTest extends TestCase
{
    /**
     * Test 1: Verify ServiceResource is in Infrastructure group
     */
    public function test_service_resource_in_infrastructure_group(): void
    {
        $reflection = new \ReflectionClass(ServiceResource::class);
        $property = $reflection->getProperty('navigationGroup');
        $property->setAccessible(true);

        $this->assertEquals('Infrastructure', $property->getValue());
    }

    /**
     * Test 2: Verify SupervisorProgramResource is in Infrastructure group
     */
    public function test_supervisor_program_resource_in_infrastructure_group(): void
    {
        $reflection = new \ReflectionClass(SupervisorProgramResource::class);
        $property = $reflection->getProperty('navigationGroup');
        $property->setAccessible(true);

        $this->assertEquals('Infrastructure', $property->getValue());
    }

    /**
     * Test 3: Verify HealthMonitorResource is in Monitoring group
     */
    public function test_health_monitor_resource_in_monitoring_group(): void
    {
        $reflection = new \ReflectionClass(HealthMonitorResource::class);
        $property = $reflection->getProperty('navigationGroup');
        $property->setAccessible(true);

        $this->assertEquals('Monitoring', $property->getValue());
    }

    /**
     * Test 4: Verify CronJob model has runNow method
     */
    public function test_cronjob_has_run_now_method(): void
    {
        $this->assertTrue(method_exists(CronJob::class, 'runNow'));
    }

    /**
     * Test 5: Verify CronJob runNow creates an AgentJob
     * This test requires MySQL database
     */
    public function test_cronjob_run_now_creates_agent_job(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL database');
        }

        $user = User::where('email', env('DUSK_TEST_USER_EMAIL', 'test@example.com'))->first();
        if (!$user) {
            $this->markTestSkipped('Test user not found. Configure DUSK_TEST_USER_EMAIL in .env');
        }

        $team = $user->currentTeam;
        $server = Server::where('team_id', $team->id)->first();

        if (!$server) {
            $this->markTestSkipped('Test server not found');
        }

        $cronJob = CronJob::create([
            'team_id' => $team->id,
            'server_id' => $server->id,
            'name' => 'Test Cron for Unit Test',
            'command' => 'echo "test"',
            'schedule' => '* * * * *',
            'user' => 'sitekit',
            'is_active' => true,
        ]);

        $agentJob = $cronJob->runNow();

        $this->assertNotNull($agentJob);
        $this->assertEquals('run_script', $agentJob->type);
        $this->assertEquals($cronJob->command, $agentJob->payload['script']);
        $this->assertEquals($cronJob->user, $agentJob->payload['user']);

        // Cleanup
        $cronJob->delete();
        $agentJob->delete();
    }

    /**
     * Test 6: Verify notifications table exists
     * This test requires MySQL database
     */
    public function test_notifications_table_exists(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL database');
        }

        $this->assertTrue(\Schema::hasTable('notifications'));
    }

    /**
     * Test 7: Verify notifications table has correct structure
     * This test requires MySQL database
     */
    public function test_notifications_table_has_correct_columns(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL database');
        }

        $columns = \Schema::getColumnListing('notifications');

        $this->assertContains('id', $columns);
        $this->assertContains('type', $columns);
        $this->assertContains('notifiable_type', $columns);
        $this->assertContains('notifiable_id', $columns);
        $this->assertContains('data', $columns);
        $this->assertContains('read_at', $columns);
    }

    /**
     * Test 8: Verify User model has Notifiable trait
     */
    public function test_user_model_has_notifiable_trait(): void
    {
        $traits = class_uses_recursive(User::class);
        $this->assertContains('Illuminate\Notifications\Notifiable', $traits);
    }
}
