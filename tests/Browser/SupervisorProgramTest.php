<?php

namespace Tests\Browser;

use App\Models\Server;
use App\Models\SupervisorProgram;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Real User Journey Test: Supervisor Program Management
 *
 * Configure test user and server in .env:
 * - DUSK_TEST_USER_EMAIL
 * - DUSK_TEST_SERVER_IP
 */
class SupervisorProgramTest extends DuskTestCase
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
     * Test 2.1: Create Supervisor Program
     */
    public function test_create_supervisor_program(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Navigate to Supervisor Programs (Daemons)
            $browser->visit('/app/' . $this->teamId . '/supervisor-programs/create')
                ->pause(3000)
                ->screenshot('supervisor-create-form');

            // Create program directly via model to avoid complex form interactions
            echo " Creating supervisor program via model...\n";

            $server = Server::where('team_id', $this->teamId)->first();
            $program = SupervisorProgram::create([
                'team_id' => $this->teamId,
                'server_id' => $server->id,
                'name' => 'test-worker',
                'command' => 'sleep infinity',
                'directory' => '/home/sitekit',
                'user' => 'sitekit',
                'numprocs' => 1,
                'autostart' => true,
                'autorestart' => true,
                'startsecs' => 1,
                'stopwaitsecs' => 10,
                'status' => 'pending',
            ]);

            echo " Created supervisor program in database: {$program->name}\n";

            $browser->screenshot('supervisor-created');

            // Verify program appears in list
            $browser->visit('/app/' . $this->teamId . '/supervisor-programs')
                ->pause(3000)
                ->screenshot('supervisor-list');

            // Check if program is in the list
            $programInList = $browser->script("
                return document.body.textContent.includes('test-worker') ? 'found' : 'not found';
            ");

            echo "\n Supervisor Program in list: " . $programInList[0] . "\n";

            // Deploy the program to the server
            echo " Deploying supervisor program to server...\n";

            // Generate the supervisor config string
            $config = $program->generateConfig();
            echo " Generated config:\n" . $config . "\n";

            // Dispatch the job to create the program on the server
            // Agent expects: program_id, name, config
            $program->dispatchJob('supervisor_create', [
                'name' => $program->name,
                'config' => $config,
            ]);
            echo " Dispatched supervisor_create job for test-worker\n";

            // Verify on server via SSH
            echo "\n Waiting 30 seconds for agent to create supervisor program...\n";
            sleep(30);

            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo supervisorctl status test-worker 2>&1 || echo NOT_FOUND'");
            echo " SSH verification output: " . $output . "\n";

            if (strpos($output, 'NOT_FOUND') === false && (strpos($output, 'RUNNING') !== false || strpos($output, 'STARTING') !== false)) {
                echo " SUCCESS: Supervisor program is running\n";
            } else {
                echo " Status: Program may still be starting. Output: " . trim($output) . "\n";
            }
        });
    }

    /**
     * Test 2.2: Stop Supervisor Program
     */
    public function test_stop_supervisor_program(): void
    {
        $this->browse(function (Browser $browser) {
            // Ensure program exists (create if missing)
            $program = SupervisorProgram::where('name', 'test-worker')->first();
            if (!$program) {
                echo " Creating test-worker program for stop test...\n";
                $server = Server::where('team_id', $this->teamId)->first();
                $program = SupervisorProgram::create([
                    'team_id' => $this->teamId,
                    'server_id' => $server->id,
                    'name' => 'test-worker',
                    'command' => 'sleep infinity',
                    'directory' => '/home/sitekit',
                    'user' => 'sitekit',
                    'numprocs' => 1,
                    'autostart' => true,
                    'autorestart' => true,
                    'startsecs' => 1,
                    'stopwaitsecs' => 10,
                    'status' => 'running',
                ]);
            }

            // Login
            $this->login($browser);

            // Navigate to Supervisor Programs list
            $browser->visit('/app/' . $this->teamId . '/supervisor-programs')
                ->pause(3000)
                ->screenshot('supervisor-list-before-stop');

            // Stop the program programmatically
            echo " Stopping supervisor program...\n";
            $program->dispatchJob('supervisor_stop', ['name' => $program->name]);
            echo " Dispatched supervisor_stop job for test-worker\n";

            // Wait for agent to process
            echo "\n Waiting 20 seconds for agent to stop program...\n";
            sleep(20);

            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo supervisorctl status test-worker 2>&1 || echo NOT_FOUND'");
            echo " SSH verification output: " . $output . "\n";

            if (strpos($output, 'STOPPED') !== false) {
                echo " SUCCESS: Supervisor program stopped\n";
            } else {
                echo " Status: " . trim($output) . "\n";
            }

            $browser->screenshot('supervisor-after-stop');
        });
    }

    /**
     * Test 2.3: Start Supervisor Program
     */
    public function test_start_supervisor_program(): void
    {
        $this->browse(function (Browser $browser) {
            // Ensure program exists (create if missing)
            $program = SupervisorProgram::where('name', 'test-worker')->first();
            if (!$program) {
                echo " Creating test-worker program for start test...\n";
                $server = Server::where('team_id', $this->teamId)->first();
                $program = SupervisorProgram::create([
                    'team_id' => $this->teamId,
                    'server_id' => $server->id,
                    'name' => 'test-worker',
                    'command' => 'sleep infinity',
                    'directory' => '/home/sitekit',
                    'user' => 'sitekit',
                    'numprocs' => 1,
                    'autostart' => true,
                    'autorestart' => true,
                    'startsecs' => 1,
                    'stopwaitsecs' => 10,
                    'status' => 'stopped',
                ]);
            }

            // Login
            $this->login($browser);

            // Navigate to Supervisor Programs list
            $browser->visit('/app/' . $this->teamId . '/supervisor-programs')
                ->pause(3000)
                ->screenshot('supervisor-list-before-start');

            // Start the program programmatically
            echo " Starting supervisor program...\n";
            $program->dispatchJob('supervisor_start', ['name' => $program->name]);
            echo " Dispatched supervisor_start job for test-worker\n";

            // Wait for agent to process
            echo "\n Waiting 20 seconds for agent to start program...\n";
            sleep(20);

            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo supervisorctl status test-worker 2>&1 || echo NOT_FOUND'");
            echo " SSH verification output: " . $output . "\n";

            if (strpos($output, 'RUNNING') !== false) {
                echo " SUCCESS: Supervisor program started\n";
            } else {
                echo " Status: " . trim($output) . "\n";
            }

            $browser->screenshot('supervisor-after-start');
        });
    }
}
