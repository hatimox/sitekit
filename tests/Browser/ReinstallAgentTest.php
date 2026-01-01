<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Test reinstalling agent via the web UI.
 * Clicks "Reinstall Agent", captures the provisioning command, and runs it via SSH.
 */
class ReinstallAgentTest extends DuskTestCase
{
    protected string $testEmail;
    protected string $serverIp;
    protected ?string $teamId = null;
    protected ?string $serverId = null;
    protected string $sshUser = 'ubuntu';

    protected function setUp(): void
    {
        parent::setUp();
        $this->testEmail = $this->getTestUserEmail();
        $this->serverIp = $this->getTestServerIp();

        // Get team and server from test user
        $user = \App\Models\User::where('email', $this->testEmail)->first();
        if ($user) {
            $team = $user->currentTeam ?? $user->teams()->first();
            $this->teamId = $team?->id;

            // Get server by IP or first server
            $server = \App\Models\Server::where('ip_address', $this->serverIp)->first()
                ?? \App\Models\Server::where('team_id', $this->teamId)->first();
            $this->serverId = $server?->id;
        }

        if (!$this->teamId || !$this->serverId) {
            $this->markTestSkipped('Test user, team, or server not found. Configure DUSK_TEST_USER_EMAIL and DUSK_TEST_SERVER_IP in .env');
        }
    }

    /**
     * Test the complete reinstall agent flow.
     */
    public function test_reinstall_agent_flow(): void
    {
        $testEmail = $this->testEmail;

        $this->browse(function (Browser $browser) use ($testEmail) {
            // Step 1: Login
            $browser->visit('/app/login')
                ->pause(2000);

            $browser->script("
                const emailInput = document.querySelector('input[type=\"email\"]');
                if (emailInput) {
                    emailInput.value = '{$testEmail}';
                    emailInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                const passInput = document.querySelector('input[type=\"password\"]');
                if (passInput) {
                    passInput.value = 'password';
                    passInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");

            $browser->pause(500);

            $browser->script("
                const btn = document.querySelector('button[type=\"submit\"]');
                if (btn) btn.click();
            ");

            $browser->pause(5000)
                ->screenshot('reinstall-01-logged-in');

            echo "\n Logged in as {$testEmail}\n";

            // Step 2: Go to server detail page
            $browser->visit("/app/{$this->teamId}/servers/{$this->serverId}")
                ->pause(3000)
                ->screenshot('reinstall-02-server-page');

            echo " Viewing server detail page\n";

            // Step 3: Check if server is pending (command already visible) or active (need to click reinstall)
            $serverStatus = $browser->script("
                const badges = document.querySelectorAll('[class*=\"badge\"], [class*=\"Badge\"]');
                for (const badge of badges) {
                    const text = badge.textContent.toLowerCase();
                    if (text.includes('pending')) return 'pending';
                    if (text.includes('active')) return 'active';
                    if (text.includes('provisioning')) return 'provisioning';
                }
                return 'unknown';
            ");

            echo " Current server status: {$serverStatus[0]}\n";

            // Only click reinstall if server is not pending
            if ($serverStatus[0] !== 'pending') {
                $reinstallResult = $browser->script("
                    const buttons = document.querySelectorAll('button, a');
                    for (const btn of buttons) {
                        const text = btn.textContent.toLowerCase();
                        if (text.includes('reinstall agent')) {
                            btn.click();
                            return 'clicked: ' + btn.textContent.trim();
                        }
                    }
                    return 'reinstall button not found';
                ");

                echo " Reinstall button: {$reinstallResult[0]}\n";

                $browser->pause(2000)
                    ->screenshot('reinstall-03-modal-open');

                // Confirm the modal
                $confirmResult = $browser->script("
                    const modal = document.querySelector('.fi-modal, [role=\"dialog\"]');
                    if (modal) {
                        const buttons = modal.querySelectorAll('button');
                        for (const btn of buttons) {
                            const text = btn.textContent.toLowerCase().trim();
                            if (text === 'confirm' || text.includes('confirm')) {
                                btn.click();
                                return 'confirmed';
                            }
                        }
                        return 'confirm button not found in modal';
                    }
                    return 'no modal found';
                ");

                echo " Confirm result: {$confirmResult[0]}\n";

                $browser->pause(3000)
                    ->screenshot('reinstall-04-after-confirm');
            } else {
                echo " Server already pending - provisioning command should be visible\n";
            }

            // Step 5: Wait for page to refresh and show provisioning command
            $browser->pause(2000);

            // Step 6: Extract the provisioning command
            $commandResult = $browser->script("
                // Look for the provisioning command in the page
                // It should be in a copyable field or code block
                const codeElements = document.querySelectorAll('code, pre, [class*=\"font-mono\"], input[readonly], textarea[readonly]');
                for (const el of codeElements) {
                    const text = el.textContent || el.value || '';
                    if (text.includes('curl') && text.includes('/provision/')) {
                        return text.trim();
                    }
                }

                // Also check spans and divs with mono font
                const allElements = document.querySelectorAll('*');
                for (const el of allElements) {
                    const text = el.textContent || '';
                    if (text.includes('curl -sSL') && text.includes('/provision/') && text.includes('bash')) {
                        // Make sure we get just the command, not nested content
                        if (!el.querySelector('*[class*=\"font-mono\"]')) {
                            return text.trim();
                        }
                    }
                }

                return 'command not found';
            ");

            $provisioningCommand = $commandResult[0];

            // Replace localhost URL with tunnel URL if configured (for testing with real servers)
            $tunnelUrl = env('DUSK_TUNNEL_URL');
            if ($tunnelUrl) {
                $provisioningCommand = str_replace('http://localhost:8000', $tunnelUrl, $provisioningCommand);
                $provisioningCommand = str_replace('http://127.0.0.1:8000', $tunnelUrl, $provisioningCommand);
            }

            echo "\n==========================================\n";
            echo "PROVISIONING COMMAND:\n";
            echo "==========================================\n";
            echo $provisioningCommand . "\n";
            echo "==========================================\n";

            $browser->screenshot('reinstall-05-command-visible');

            if ($provisioningCommand === 'command not found' || empty($provisioningCommand) || !str_contains($provisioningCommand, 'curl')) {
                echo " ERROR: Could not extract provisioning command\n";
                return;
            }

            // Save command to file
            file_put_contents('/tmp/reinstall_command.txt', $provisioningCommand);
            echo "\n Command saved to /tmp/reinstall_command.txt\n";

            // Step 7: Run the command via SSH on the server
            echo "\n Running provisioning command on server via SSH...\n";
            echo "==========================================\n";

            // Build SSH command
            $sshCommand = sprintf(
                'ssh -o StrictHostKeyChecking=no %s@%s "%s"',
                $this->sshUser,
                $this->serverIp,
                addslashes($provisioningCommand)
            );

            // Execute SSH command
            $output = [];
            $returnCode = 0;
            exec($sshCommand . ' 2>&1', $output, $returnCode);

            foreach ($output as $line) {
                echo $line . "\n";
            }

            echo "==========================================\n";
            echo " SSH command exit code: {$returnCode}\n";

            // Step 8: Wait for server to come back online
            echo "\n Waiting for server to reconnect...\n";
            $browser->pause(10000);

            // Refresh and check status
            $browser->visit("/app/{$this->teamId}/servers/{$this->serverId}")
                ->pause(5000)
                ->screenshot('reinstall-06-final-status');

            $statusResult = $browser->script("
                const badges = document.querySelectorAll('[class*=\"badge\"], [class*=\"Badge\"]');
                for (const badge of badges) {
                    const text = badge.textContent.toLowerCase();
                    if (text.includes('active') || text.includes('provisioning') || text.includes('pending')) {
                        return badge.textContent.trim();
                    }
                }
                return 'unknown';
            ");

            echo " Final server status: {$statusResult[0]}\n";
        });
    }
}
