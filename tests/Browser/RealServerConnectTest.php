<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Real end-to-end test for server connection flow.
 *
 * Configure in .env:
 * - DUSK_TEST_USER_EMAIL
 * - DUSK_TEST_SERVER_IP
 */
class RealServerConnectTest extends DuskTestCase
{
    protected string $testEmail;
    protected string $serverIp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testEmail = $this->getTestUserEmail();
        $this->serverIp = $this->getTestServerIp();
    }

    /**
     * Step 1: Delete existing server
     */
    public function test_step1_delete_existing_server(): void
    {
        $testEmail = $this->testEmail;

        $this->browse(function (Browser $browser) use ($testEmail) {
            // Login
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

            $browser->pause(500)
                ->screenshot('connect-01-login');

            $browser->script("
                const btn = document.querySelector('button[type=\"submit\"]');
                if (btn) btn.click();
            ");

            $browser->pause(5000)
                ->screenshot('connect-02-after-login');

            echo "\n Logged in as {$testEmail}\n";

            // Go to servers list
            $browser->visit("/app/{$this->teamId}/servers")
                ->pause(3000)
                ->screenshot('connect-03-servers-list');

            // Check if server exists
            $html = $browser->driver->getPageSource();
            $serverExists = strpos($html, $this->serverIp) !== false || strpos($html, 'max10') !== false;

            if (!$serverExists) {
                echo " No existing server found - skipping delete\n";
                return;
            }

            echo " Found existing server - proceeding to delete\n";

            // Click on the server row to go to detail page
            $serverIp = $this->serverIp;
            $browser->script("
                const rows = document.querySelectorAll('tr, [class*=\"table\"] a');
                for (const row of rows) {
                    if (row.textContent.includes('{$serverIp}')) {
                        const link = row.querySelector('a') || row.closest('a') || row;
                        if (link.href) {
                            window.location.href = link.href;
                            return 'navigating';
                        }
                        link.click();
                        return 'clicked';
                    }
                }
                return 'not found';
            ");

            $browser->pause(3000)
                ->screenshot('connect-04-server-detail');

            // Look for delete action in the dropdown/actions
            $browser->script("
                // Try to find and click the actions dropdown
                const actionsBtn = document.querySelector('[class*=\"actions\"] button, button[class*=\"dropdown\"]');
                if (actionsBtn) actionsBtn.click();
            ");

            $browser->pause(1000)
                ->screenshot('connect-05-actions-dropdown');

            // Click delete button
            $deleteResult = $browser->script("
                const buttons = document.querySelectorAll('button, a, [wire\\\\:click]');
                for (const btn of buttons) {
                    const text = btn.textContent.toLowerCase();
                    if (text.includes('delete') && !text.includes('backup')) {
                        btn.click();
                        return 'clicked delete: ' + btn.textContent.trim();
                    }
                }
                return 'delete not found';
            ");

            echo " Delete action: {$deleteResult[0]}\n";

            $browser->pause(2000)
                ->screenshot('connect-06-delete-modal');

            // Confirm deletion if modal appeared
            $browser->script("
                const confirmBtn = document.querySelectorAll('button');
                for (const btn of confirmBtn) {
                    const text = btn.textContent.toLowerCase();
                    if (text.includes('confirm') || text.includes('yes') || text === 'delete') {
                        btn.click();
                        return 'confirmed';
                    }
                }
            ");

            $browser->pause(3000)
                ->screenshot('connect-07-after-delete');

            echo " Server deletion initiated\n";
        });
    }

    /**
     * Step 2: Connect new server and get provisioning command
     */
    public function test_step2_connect_server_get_command(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $browser->visit('/app/login')
                ->pause(2000);

            $browser->script("
                const emailInput = document.querySelector('input[type=\"email\"]');
                if (emailInput) {
                    emailInput.value = '{$this->testEmail}';
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
                ->screenshot('connect-10-logged-in');

            echo "\n Logged in as {$this->testEmail}\n";

            // Go to servers list
            $browser->visit("/app/{$this->teamId}/servers")
                ->pause(3000)
                ->screenshot('connect-11-servers-page');

            // Click "Connect Server" button
            $connectResult = $browser->script("
                const buttons = document.querySelectorAll('a, button');
                for (const btn of buttons) {
                    const text = btn.textContent.toLowerCase();
                    if (text.includes('connect server') || text.includes('add server') || text.includes('new server')) {
                        btn.click();
                        return 'clicked: ' + btn.textContent.trim();
                    }
                }
                return 'connect button not found';
            ");

            echo " Connect button: {$connectResult[0]}\n";

            $browser->pause(3000)
                ->screenshot('connect-12-connect-form');

            // Fill in server name
            $browser->script("
                const inputs = document.querySelectorAll('input[type=\"text\"]');
                for (const input of inputs) {
                    if (input.placeholder && input.placeholder.includes('Production')) {
                        input.value = 'aws-test-server';
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        break;
                    }
                }
                // Try by name attribute
                const nameInput = document.querySelector('input[name*=\"name\"]');
                if (nameInput && !nameInput.value) {
                    nameInput.value = 'aws-test-server';
                    nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");

            $browser->pause(1000)
                ->screenshot('connect-13-form-filled');

            // Click Create button
            $browser->script("
                const buttons = document.querySelectorAll('button');
                for (const btn of buttons) {
                    if (btn.textContent.trim() === 'Create') {
                        btn.click();
                        return 'clicked create';
                    }
                }
            ");

            $browser->pause(5000)
                ->screenshot('connect-14-after-create');

            // We should now be on the server view page with provisioning command
            // Wait a bit more for page to fully load
            $browser->pause(3000)
                ->screenshot('connect-14b-server-page');

            // Look for provisioning command
            $browser->pause(2000);

            // Try to find the provisioning command in the page
            $commandResult = $browser->script("
                // Look for code blocks or pre elements
                const codeElements = document.querySelectorAll('pre, code, [class*=\"command\"], textarea[readonly], input[readonly]');
                for (const el of codeElements) {
                    const text = el.textContent || el.value;
                    if (text && (text.includes('curl') || text.includes('wget') || text.includes('bash'))) {
                        return text.trim();
                    }
                }
                // Also check for any element containing the provision URL
                const allText = document.body.innerText;
                const match = allText.match(/curl[^\\n]+provision[^\\n]+/);
                if (match) return match[0];
                return 'Command not found on page';
            ");

            echo "\n==========================================\n";
            echo "PROVISIONING COMMAND:\n";
            echo "==========================================\n";
            echo $commandResult[0] . "\n";
            echo "==========================================\n";

            $browser->screenshot('connect-15-provisioning-command');

            // Save command to file for later use
            file_put_contents('/tmp/provisioning_command.txt', $commandResult[0]);
            echo "\nCommand saved to /tmp/provisioning_command.txt\n";
        });
    }
}
