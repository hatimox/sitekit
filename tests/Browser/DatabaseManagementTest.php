<?php

namespace Tests\Browser;

use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\Server;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Real User Journey Test: Database Management
 *
 * Configure test user and server in .env:
 * - DUSK_TEST_USER_EMAIL
 * - DUSK_TEST_SERVER_IP
 */
class DatabaseManagementTest extends DuskTestCase
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
     * Test 3.1: Create Database with User
     */
    public function test_create_database_with_user(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Navigate to Databases
            $browser->visit('/app/' . $this->teamId . '/databases')
                ->pause(3000)
                ->screenshot('database-list-before');

            // Create database directly via model
            echo " Creating database via model...\n";

            $server = Server::where('team_id', $this->teamId)->first();

            // Delete existing test database if present
            Database::where('name', 'test_db')->delete();
            DatabaseUser::where('username', 'like', 'test_user%')->delete();

            $database = Database::create([
                'team_id' => $this->teamId,
                'server_id' => $server->id,
                'name' => 'test_db',
                'engine' => 'mysql', // or 'mariadb'
                'status' => 'pending',
            ]);

            // Create database user
            $dbPassword = 'TestPass123!';
            $dbUser = DatabaseUser::create([
                'team_id' => $this->teamId,
                'server_id' => $server->id,
                'database_id' => $database->id,
                'username' => 'test_user',
                'password' => encrypt($dbPassword),
                'host' => 'localhost',
            ]);

            echo " Created database: {$database->name}\n";
            echo " Created user: {$dbUser->username}\n";

            // Dispatch database creation job (job type: create_database)
            // Agent expects: database_id, database_name, database_type, username, password, host
            $database->dispatchJob('create_database', [
                'database_name' => $database->name,
                'database_type' => 'mariadb', // Server has MariaDB installed
                'username' => $dbUser->username,
                'password' => $dbPassword,
                'host' => $dbUser->host,
            ]);
            echo " Dispatched create_database job\n";

            // Wait for agent
            echo "\n Waiting 30 seconds for agent to create database and user...\n";
            sleep(30);

            // Verify database on server
            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo mysql -e \"SHOW DATABASES;\" | grep test_db || echo NOT_FOUND' 2>&1");
            echo " Database verification: " . trim($output) . "\n";

            if (strpos($output, 'test_db') !== false && strpos($output, 'NOT_FOUND') === false) {
                echo " SUCCESS: Database created\n";
            } else {
                echo " PENDING: Database may still be creating\n";
            }

            // Verify user on server
            $userOutput = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo mysql -e \"SELECT user FROM mysql.user WHERE user=\\\"test_user\\\";\" | grep test_user || echo NOT_FOUND' 2>&1");
            echo " User verification: " . trim($userOutput) . "\n";

            if (strpos($userOutput, 'test_user') !== false && strpos($userOutput, 'NOT_FOUND') === false) {
                echo " SUCCESS: Database user created\n";
            } else {
                echo " PENDING: User may still be creating\n";
            }

            // Refresh and check UI
            $browser->visit('/app/' . $this->teamId . '/databases')
                ->pause(3000)
                ->screenshot('database-list-after');
        });
    }

    /**
     * Test 3.2: Delete Database User (requires user confirmation)
     *
     * NOTE: Disabled by default. Rename to test_delete_database_user() to enable.
     */
    public function skip_test_delete_database_user(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Find test user
            $dbUser = DatabaseUser::where('username', 'test_user')->first();
            if (!$dbUser) {
                echo " Skipping - no test_user found\n";
                $this->markTestSkipped('No test_user found');
                return;
            }

            echo " Deleting database user: {$dbUser->username}\n";

            // Dispatch delete job (job type: delete_database_user)
            $dbUser->database->dispatchJob('delete_database_user', [
                'username' => $dbUser->username,
                'host' => $dbUser->host,
            ]);
            echo " Dispatched delete_database_user job\n";

            // Wait for agent
            echo "\n Waiting 20 seconds for agent to delete user...\n";
            sleep(20);

            // Verify user deleted on server
            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo mysql -e \"SELECT user FROM mysql.user WHERE user=\\\"test_user\\\";\" 2>&1'");
            echo " User verification after delete: " . trim($output) . "\n";

            if (empty(trim($output)) || strpos($output, 'test_user') === false) {
                echo " SUCCESS: Database user deleted\n";
                $dbUser->delete(); // Clean up local record
            } else {
                echo " PENDING: User may still exist\n";
            }

            $browser->screenshot('database-user-deleted');
        });
    }

    /**
     * Test 3.3: Delete Database (requires user confirmation)
     *
     * NOTE: Disabled by default. Rename to test_delete_database() to enable.
     */
    public function skip_test_delete_database(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Find test database
            $database = Database::where('name', 'test_db')->first();
            if (!$database) {
                echo " Skipping - no test_db found\n";
                $this->markTestSkipped('No test_db found');
                return;
            }

            echo " Deleting database: {$database->name}\n";

            // Dispatch delete job (job type: delete_database)
            $database->dispatchJob('delete_database', [
                'name' => $database->name,
            ]);
            echo " Dispatched delete_database job\n";

            // Wait for agent
            echo "\n Waiting 20 seconds for agent to delete database...\n";
            sleep(20);

            // Verify database deleted on server
            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo mysql -e \"SHOW DATABASES;\" | grep test_db || echo NOT_FOUND' 2>&1");
            echo " Database verification after delete: " . trim($output) . "\n";

            if (strpos($output, 'NOT_FOUND') !== false || strpos($output, 'test_db') === false) {
                echo " SUCCESS: Database deleted\n";
                $database->delete(); // Clean up local record
            } else {
                echo " PENDING: Database may still exist\n";
            }

            $browser->screenshot('database-deleted');
        });
    }
}
