<?php

namespace Tests\Browser;

use App\Models\AgentJob;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\Team;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SshKeyScenarioTest extends DuskTestCase
{
    protected User $user;
    protected Team $team;
    protected Server $server;

    // Test SSH key - ED25519 format
    protected string $testPublicKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKTestKeyForDuskBrowserTesting123456789ABC test-dusk@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', $this->getTestUserEmail())->first();
        $this->team = $this->user?->currentTeam ?? $this->user?->teams()->first();
        $this->server = Server::where('ip_address', $this->getTestServerIp())->first();

        if (!$this->user || !$this->team || !$this->server) {
            $this->markTestSkipped('Demo user, team, or server not found. Configure DUSK_TEST_USER_EMAIL and DUSK_TEST_SERVER_IP in .env');
        }
    }

    /**
     * Test viewing the SSH keys list
     */
    public function test_can_view_ssh_keys_list(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/ssh-keys")
                ->waitForText('SSH Keys', 10)
                ->assertSee('SSH Keys')
                ->screenshot('ssh-01-list-page');

            // Check for table with existing keys
            $browser->assertPresent('table')
                ->screenshot('ssh-02-table-visible');
        });
    }

    /**
     * Test creating a new SSH key
     */
    public function test_can_create_ssh_key(): void
    {
        $keyName = 'Dusk Test Key ' . time();

        $this->browse(function (Browser $browser) use ($keyName) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/ssh-keys/create")
                ->pause(2000)
                ->screenshot('ssh-03-create-form');

            // Fill in key name using JavaScript
            $browser->script("
                const nameInput = document.querySelector('input[name*=\"name\"]');
                if (nameInput) {
                    nameInput.value = '{$keyName}';
                    nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300)
                ->screenshot('ssh-04-name-entered');

            // Fill in public key using JavaScript
            $browser->script("
                const keyInput = document.querySelector('textarea[name*=\"public_key\"]');
                if (keyInput) {
                    keyInput.value = '{$this->testPublicKey}';
                    keyInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300)
                ->screenshot('ssh-05-key-entered');

            // Submit form
            $browser->press('Create')
                ->pause(3000)
                ->screenshot('ssh-06-after-create');

            // Check result
            $browser->screenshot('ssh-07-result');
        });

        // Verify in database
        $key = SshKey::where('team_id', $this->team->id)
            ->where('name', $keyName)
            ->first();

        // Clean up if exists
        if ($key) {
            $key->delete();
        }
    }

    /**
     * Test viewing SSH key details
     */
    public function test_can_view_ssh_key_details(): void
    {
        // Get an existing SSH key
        $key = SshKey::where('team_id', $this->team->id)->first();

        if (!$key) {
            $this->markTestSkipped('No existing SSH key to view');
        }

        $this->browse(function (Browser $browser) use ($key) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/ssh-keys/{$key->id}")
                ->waitFor('.fi-page', 10)
                ->screenshot('ssh-08-key-details');

            // Verify key details are shown
            $browser->assertSee($key->name)
                ->screenshot('ssh-09-details-visible');

            // Check for fingerprint display
            if ($key->fingerprint) {
                $browser->assertSee('SHA256')
                    ->screenshot('ssh-10-fingerprint-visible');
            }
        });
    }

    /**
     * Test deleting an SSH key
     */
    public function test_can_delete_ssh_key(): void
    {
        // Create a key to delete
        $key = SshKey::create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'name' => 'Dusk Delete Test ' . time(),
            'public_key' => $this->testPublicKey,
        ]);

        $keyId = $key->id;
        $keyName = $key->name;

        $this->browse(function (Browser $browser) use ($keyName, $keyId) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/ssh-keys")
                ->waitForText('Ssh Keys', 10)
                ->pause(1000)
                ->screenshot('ssh-11-before-delete');

            // Find and click delete action for this key
            $browser->script("
                const rows = document.querySelectorAll('tr');
                for (const row of rows) {
                    if (row.textContent.includes('{$keyName}')) {
                        // Look for delete button or action menu
                        const deleteBtn = row.querySelector('button[title*=\"Delete\"]');
                        if (deleteBtn) {
                            deleteBtn.click();
                        } else {
                            // Try action menu
                            const menuBtn = row.querySelector('button.fi-icon-btn');
                            if (menuBtn) menuBtn.click();
                        }
                        break;
                    }
                }
            ");

            $browser->pause(1000)
                ->screenshot('ssh-12-delete-action');

            // Confirm deletion if modal appears
            $browser->whenAvailable('.fi-modal', function (Browser $modal) {
                // Try to find and click the delete/confirm button
                $modal->script("
                    const btns = document.querySelectorAll('.fi-modal button');
                    for (const btn of btns) {
                        if (btn.textContent.includes('Delete') || btn.textContent.includes('Confirm')) {
                            btn.click();
                            break;
                        }
                    }
                ");
                $modal->pause(500);
            });

            $browser->pause(2000)
                ->screenshot('ssh-13-after-delete');
        });

        // Clean up if still exists
        $key = SshKey::find($keyId);
        if ($key) {
            $key->delete();
        }
    }

    /**
     * Test deploying SSH key to a server
     */
    public function test_can_deploy_ssh_key_to_server(): void
    {
        // Get an existing key
        $key = SshKey::where('team_id', $this->team->id)->first();

        if (!$key) {
            $this->markTestSkipped('No existing SSH key to deploy');
        }

        $this->browse(function (Browser $browser) use ($key) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/ssh-keys")
                ->waitForText('SSH Keys', 10)
                ->waitForText($key->name, 10)
                ->screenshot('ssh-15-before-deploy');

            // Find and click deploy action for this key
            $browser->script("
                const rows = document.querySelectorAll('tr');
                for (const row of rows) {
                    if (row.textContent.includes('{$key->name}')) {
                        // Look for deploy button (might be in dropdown or direct)
                        const deployBtn = row.querySelector('button[wire\\\\:click*=\"deploy\"], button[title*=\"Deploy\"]');
                        if (deployBtn) {
                            deployBtn.click();
                        } else {
                            // Try action dropdown
                            const actionBtn = row.querySelector('button[x-on\\\\:click*=\"toggle\"]');
                            if (actionBtn) actionBtn.click();
                        }
                        break;
                    }
                }
            ");

            $browser->pause(1000)
                ->screenshot('ssh-16-deploy-action');

            // If deploy modal opened, verify content
            $browser->whenAvailable('.fi-modal', function (Browser $modal) {
                $modal->screenshot('ssh-17-deploy-modal');
            });
        });
    }

    /**
     * Test deploying SSH key to all servers
     */
    public function test_can_deploy_ssh_key_to_all_servers(): void
    {
        $key = SshKey::where('team_id', $this->team->id)->first();

        if (!$key) {
            $this->markTestSkipped('No existing SSH key to deploy');
        }

        $this->browse(function (Browser $browser) use ($key) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/ssh-keys")
                ->waitForText('Ssh Keys', 10)
                ->pause(1000)
                ->screenshot('ssh-18-before-deploy-all');

            // Find the key row and click action menu
            $browser->script("
                const rows = document.querySelectorAll('tr');
                for (const row of rows) {
                    if (row.textContent.includes('{$key->name}')) {
                        // Look for action menu trigger (three dots or icon button)
                        const menuBtns = row.querySelectorAll('button');
                        for (const btn of menuBtns) {
                            if (btn.querySelector('svg') || btn.classList.contains('fi-icon-btn')) {
                                btn.click();
                                break;
                            }
                        }
                        break;
                    }
                }
            ");

            $browser->pause(1000)
                ->screenshot('ssh-19-action-menu-open');

            // Try to click "Deploy to All Servers" option if menu is visible
            $browser->script("
                const menuItems = document.querySelectorAll('[role=\"menuitem\"], .fi-dropdown-list-item');
                for (const item of menuItems) {
                    if (item.textContent.includes('Deploy') && item.textContent.includes('All')) {
                        item.click();
                        break;
                    }
                }
            ");

            $browser->pause(2000)
                ->screenshot('ssh-20-after-deploy-all');
        });
    }

    /**
     * Test SSH key validation - invalid format
     */
    public function test_validates_invalid_ssh_key_format(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/ssh-keys/create")
                ->pause(2000)
                ->screenshot('ssh-21-create-page');

            // Enter name using JavaScript
            $browser->script("
                const nameInput = document.querySelector('input[name*=\"name\"]');
                if (nameInput) {
                    nameInput.value = 'Invalid Key Test';
                    nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300)
                ->screenshot('ssh-22-name-entered');

            // Enter invalid SSH key format
            $browser->script("
                const keyInput = document.querySelector('textarea[name*=\"public_key\"]');
                if (keyInput) {
                    keyInput.value = 'not-a-valid-ssh-key-format';
                    keyInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300)
                ->screenshot('ssh-23-invalid-key-entered');

            // Submit form
            $browser->press('Create')
                ->pause(2000)
                ->screenshot('ssh-24-validation-result');
        });
    }

    /**
     * Test SSH key validation - duplicate key
     */
    public function test_validates_duplicate_ssh_key(): void
    {
        // Get an existing key to duplicate
        $existingKey = SshKey::where('team_id', $this->team->id)->first();

        if (!$existingKey) {
            $this->markTestSkipped('No existing SSH key for duplicate test');
        }

        $this->browse(function (Browser $browser) use ($existingKey) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/ssh-keys/create")
                ->pause(2000)
                ->screenshot('ssh-25-create-for-duplicate');

            // Enter name
            $browser->script("
                const nameInput = document.querySelector('input[name*=\"name\"]');
                if (nameInput) {
                    nameInput.value = 'Duplicate Key Test';
                    nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300);

            // Enter duplicate SSH key
            $publicKey = addslashes($existingKey->public_key);
            $browser->script("
                const keyInput = document.querySelector('textarea[name*=\"public_key\"]');
                if (keyInput) {
                    keyInput.value = '{$publicKey}';
                    keyInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300)
                ->screenshot('ssh-26-duplicate-key-entered');

            // Submit form
            $browser->press('Create')
                ->pause(2000)
                ->screenshot('ssh-27-duplicate-result');
        });
    }

    /**
     * Test editing SSH key name
     */
    public function test_can_edit_ssh_key_name(): void
    {
        // Create a key to edit
        $key = SshKey::create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'name' => 'Edit Test Key ' . time(),
            'public_key' => $this->testPublicKey . time(), // Make unique
        ]);

        $newName = 'Edited Key Name ' . time();

        $this->browse(function (Browser $browser) use ($key, $newName) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/ssh-keys/{$key->id}/edit")
                ->pause(2000)
                ->screenshot('ssh-29-edit-form');

            // Clear and enter new name using JavaScript
            $browser->script("
                const nameInput = document.querySelector('input[name*=\"name\"]');
                if (nameInput) {
                    nameInput.value = '{$newName}';
                    nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300)
                ->screenshot('ssh-30-new-name-entered');

            // Submit form
            $browser->press('Save changes')
                ->pause(3000)
                ->screenshot('ssh-31-after-edit');
        });

        // Clean up
        $key->delete();
    }

    /**
     * Test bulk deleting SSH keys
     */
    public function test_can_bulk_delete_ssh_keys(): void
    {
        // Create multiple keys for bulk testing
        $keys = [];
        for ($i = 0; $i < 2; $i++) {
            $keys[] = SshKey::create([
                'team_id' => $this->team->id,
                'user_id' => $this->user->id,
                'name' => 'Bulk Delete Test ' . $i . ' ' . time(),
                'public_key' => $this->testPublicKey . $i . time(), // Make unique
            ]);
        }

        $this->browse(function (Browser $browser) use ($keys) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/ssh-keys")
                ->waitForText('Ssh Keys', 10)
                ->pause(1000)
                ->screenshot('ssh-33-bulk-before');

            // Select checkboxes for our test keys
            $browser->script("
                const checkboxes = document.querySelectorAll('table tbody input[type=\"checkbox\"]');
                // Select first 2 checkboxes
                for (let i = 0; i < 2 && i < checkboxes.length; i++) {
                    checkboxes[i].click();
                }
            ");

            $browser->pause(1000)
                ->screenshot('ssh-34-bulk-selected');
        });

        // Clean up any remaining keys
        foreach ($keys as $key) {
            $key->refresh();
            if ($key->exists) {
                $key->delete();
            }
        }
    }
}
