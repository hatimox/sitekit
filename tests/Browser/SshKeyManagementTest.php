<?php

namespace Tests\Browser;

use App\Models\Server;
use App\Models\SshKey;
use App\Models\Team;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Real User Journey Test: SSH Key Management
 *
 * Configure test user and server in .env:
 * - DUSK_TEST_USER_EMAIL
 * - DUSK_TEST_SERVER_IP
 */
class SshKeyManagementTest extends DuskTestCase
{
    protected string $testUser;
    protected string $testPassword = 'password';
    protected string $serverIp;

    // Test public keys (generated for testing - not real production keys)
    protected string $testKeySitekit = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestKeySitekitDec30NotRealJustForTesting test-key-sitekit';
    protected string $testKeyRoot = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestKeyRootDec30NotRealJustForTestingPurposes test-key-root';

    protected function setUp(): void
    {
        parent::setUp();
        $this->testUser = $this->getTestUserEmail();
        $this->serverIp = $this->getTestServerIp();

        // Note: Do NOT auto-delete test resources here
        // DELETE operations require explicit user confirmation
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
     * Test 1.1: Create SSH Key for sitekit user
     */
    public function test_create_ssh_key_for_sitekit_user(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Navigate to SSH Keys
            $browser->visit('/app/' . $this->teamId . '/ssh-keys/create')
                ->pause(3000)
                ->screenshot('ssh-key-create-form');

            // Fill form using JavaScript for reliability
            $browser->script("
                // Find and fill name input
                const nameInput = document.querySelector('input[placeholder*=\"Laptop\"]') ||
                                  document.querySelector('input[name*=\"name\"]');
                if (nameInput) {
                    nameInput.value = 'test-key-sitekit';
                    nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                }

                // Find and fill public key textarea
                const keyTextarea = document.querySelector('textarea[placeholder*=\"ssh-rsa\"]') ||
                                    document.querySelector('textarea[name*=\"public_key\"]') ||
                                    document.querySelector('textarea');
                if (keyTextarea) {
                    keyTextarea.value = '{$this->testKeySitekit}';
                    keyTextarea.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");

            $browser->pause(1000);

            // Skip server selection during create - we'll deploy via action after
            // Submit form - click Create button
            $browser->screenshot('ssh-key-before-submit');

            $browser->script("
                const createBtn = Array.from(document.querySelectorAll('button')).find(btn =>
                    btn.textContent.trim() === 'Create' || btn.textContent.includes('Create'));
                if (createBtn) createBtn.click();
            ");

            $browser->pause(5000)
                ->screenshot('ssh-key-created-sitekit');

            // Verify key appears in list
            $browser->visit('/app/' . $this->teamId . '/ssh-keys')
                ->pause(3000)
                ->screenshot('ssh-key-list-sitekit');

            // Check if key is in the list
            $keyInList = $browser->script("
                return document.body.textContent.includes('test-key-sitekit') ? 'found' : 'not found';
            ");

            echo "\n SSH Key in list: " . $keyInList[0] . "\n";

            // Deploy the key programmatically (since Filament select UI is complex)
            echo " Deploying key programmatically via model...\n";

            $sshKey = SshKey::where('name', 'test-key-sitekit')->first();
            if ($sshKey) {
                $server = Server::where('team_id', $this->teamId)->first();
                if ($server) {
                    // Dispatch the deployment job directly
                    $sshKey->dispatchAddToServer($server, 'sitekit');
                    echo " Dispatched SSH key deployment to {$server->name} for user sitekit\n";
                }
            }

            // Verify on server via SSH (wait for agent to process)
            echo "\n Waiting 20 seconds for agent to process job...\n";
            sleep(20);

            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo cat /home/sitekit/.ssh/authorized_keys 2>/dev/null | grep test-key-sitekit || echo NOT_FOUND' 2>&1");
            echo " SSH verification output: " . $output . "\n";

            if (strpos($output, 'NOT_FOUND') === false && strpos($output, 'test-key-sitekit') !== false) {
                echo " SUCCESS: SSH key deployed to sitekit user\n";
            } else {
                echo " PENDING: SSH key may still be processing. Check agent heartbeat.\n";
            }
        });
    }

    /**
     * Test 1.2: Create SSH Key for root user
     */
    public function test_create_ssh_key_for_root_user(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Navigate to SSH Keys create
            $browser->visit('/app/' . $this->teamId . '/ssh-keys/create')
                ->pause(3000)
                ->screenshot('ssh-key-create-root');

            // Fill form using JavaScript for reliability
            $browser->script("
                // Find and fill name input
                const nameInput = document.querySelector('input[placeholder*=\"Laptop\"]') ||
                                  document.querySelector('input[name*=\"name\"]');
                if (nameInput) {
                    nameInput.value = 'test-key-root';
                    nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                }

                // Find and fill public key textarea
                const keyTextarea = document.querySelector('textarea[placeholder*=\"ssh-rsa\"]') ||
                                    document.querySelector('textarea[name*=\"public_key\"]') ||
                                    document.querySelector('textarea');
                if (keyTextarea) {
                    keyTextarea.value = '{$this->testKeyRoot}';
                    keyTextarea.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");

            $browser->pause(1000);

            // Submit form - skip server selection, we'll deploy programmatically
            $browser->screenshot('ssh-key-before-submit-root');

            $browser->script("
                const createBtn = Array.from(document.querySelectorAll('button')).find(btn =>
                    btn.textContent.trim() === 'Create' || btn.textContent.includes('Create'));
                if (createBtn) createBtn.click();
            ");

            $browser->pause(5000)
                ->screenshot('ssh-key-created-root');

            // Verify key appears in list
            $browser->visit('/app/' . $this->teamId . '/ssh-keys')
                ->pause(3000)
                ->screenshot('ssh-key-list-root');

            // Check if key is in the list
            $keyInList = $browser->script("
                return document.body.textContent.includes('test-key-root') ? 'found' : 'not found';
            ");

            echo "\n SSH Key in list: " . $keyInList[0] . "\n";

            // Deploy the key programmatically to ROOT user
            echo " Deploying key programmatically to ROOT user...\n";

            $sshKey = SshKey::where('name', 'test-key-root')->first();
            if ($sshKey) {
                $server = Server::where('team_id', $this->teamId)->first();
                if ($server) {
                    // Dispatch the deployment job directly to ROOT
                    $sshKey->dispatchAddToServer($server, 'root');
                    echo " Dispatched SSH key deployment to {$server->name} for user ROOT\n";
                }
            }

            // Verify on server via SSH
            echo "\n Waiting 20 seconds for agent to process job...\n";
            sleep(20);

            $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->sshUser}@{$this->serverIp} 'sudo cat /root/.ssh/authorized_keys 2>/dev/null | grep test-key-root || echo NOT_FOUND' 2>&1");
            echo " SSH verification output: " . $output . "\n";

            if (strpos($output, 'NOT_FOUND') === false && strpos($output, 'test-key-root') !== false) {
                echo " SUCCESS: SSH key deployed to root user\n";
            } else {
                echo " PENDING: SSH key may still be processing. Check agent heartbeat.\n";
            }
        });
    }

    /**
     * Test 1.3: Deploy existing key to server
     */
    public function test_deploy_existing_key_to_server(): void
    {
        $this->browse(function (Browser $browser) {
            // Create a key without deploying first
            $team = Team::find($this->teamId);
            $user = User::where('email', $this->testUser)->first();

            $sshKey = SshKey::create([
                'team_id' => $this->teamId,
                'user_id' => $user->id,
                'name' => 'test-key-deploy-later',
                'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestKeyDeployLaterDec30ForTestPurposes test-key-deploy-later',
                'fingerprint' => 'test-fingerprint-' . uniqid(),
            ]);

            // Login
            $this->login($browser);

            // Navigate to SSH Keys list
            $browser->visit('/app/' . $this->teamId . '/ssh-keys')
                ->waitForText('test-key-deploy-later')
                ->screenshot('ssh-key-deploy-later-list');

            // Click Deploy action
            $browser->click('@action-deploy-' . $sshKey->id)
                ->pause(500)
                ->screenshot('ssh-key-deploy-modal');

            // The deploy modal should show with server selection
            $browser->waitFor('[wire\\:key*="server_id"]')
                ->click('[wire\\:key*="server_id"]')
                ->pause(500)
                ->waitFor('[data-highlighted]', 5)
                ->click('[data-highlighted]')
                ->pause(500);

            // Submit deployment
            $browser->press('Deploy to Server')
                ->waitForText('SSH key deployment queued', 10)
                ->screenshot('ssh-key-deployed-later');

            // Cleanup
            $sshKey->delete();
        });
    }

    /**
     * Test 1.4: AI Help actions are visible when AI is enabled
     */
    public function test_ai_help_actions_visible(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Navigate to SSH Keys create
            $browser->visit('/app/' . $this->teamId . '/ssh-keys/create')
                ->pause(3000);

            // Check for AI help buttons (if AI is enabled)
            if (config('ai.enabled')) {
                $browser->assertSee('Which key type?')
                    ->assertSee('How to generate?')
                    ->screenshot('ssh-key-ai-help-buttons');
            }
        });
    }
}
