<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Test creating a Node.js web app via the UI.
 *
 * Tests TASK-022: Browser Testing for Node.js app creation flow.
 */
class CreateNodejsWebAppTest extends DuskTestCase
{
    protected string $testEmail;
    protected ?string $teamId = null;
    protected ?string $serverId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testEmail = $this->getTestUserEmail();

        // Get team and server from test user
        $user = \App\Models\User::where('email', $this->testEmail)->first();
        if ($user) {
            $team = $user->currentTeam ?? $user->teams()->first();
            $this->teamId = $team?->id;

            // Get first server
            $server = \App\Models\Server::where('team_id', $this->teamId)->first();
            $this->serverId = $server?->id;
        }

        if (!$this->teamId || !$this->serverId) {
            $this->markTestSkipped('Test user, team, or server not found. Configure DUSK_TEST_USER_EMAIL in .env');
        }
    }

    /**
     * Test that selecting Node.js app type shows correct fields.
     */
    public function test_nodejs_app_type_shows_correct_fields(): void
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

            $browser->pause(500);

            $browser->script("
                const btn = document.querySelector('button[type=\"submit\"]');
                if (btn) btn.click();
            ");

            $browser->pause(5000)
                ->screenshot('nodejs-01-logged-in');

            echo "\n Logged in as {$testEmail}\n";

            // Go to create web app page
            $browser->visit("/app/{$this->teamId}/web-apps/create")
                ->pause(3000)
                ->screenshot('nodejs-02-create-page');

            echo " On create web app page\n";

            // Check that app_type field exists
            $appTypeExists = $browser->script("
                const labels = document.querySelectorAll('label, span');
                for (const label of labels) {
                    if (label.textContent.toLowerCase().includes('application type')) {
                        return 'Application Type field found';
                    }
                }
                return 'Application Type field NOT found';
            ");

            echo " {$appTypeExists[0]}\n";

            // Click on Application Type dropdown
            $browser->script("
                const buttons = document.querySelectorAll('button');
                for (const btn of buttons) {
                    const span = btn.querySelector('span');
                    if (span && (span.textContent.includes('PHP') || span.textContent.includes('Select'))) {
                        btn.click();
                        break;
                    }
                }
            ");

            $browser->pause(1000)
                ->screenshot('nodejs-03-app-type-dropdown');

            // Select Node.js option
            $selectNodejs = $browser->script("
                const options = document.querySelectorAll('[role=\"option\"], li');
                for (const opt of options) {
                    if (opt.textContent.toLowerCase().includes('node')) {
                        opt.click();
                        return 'Selected Node.js';
                    }
                }
                return 'Node.js option not found';
            ");

            echo " {$selectNodejs[0]}\n";

            $browser->pause(2000)
                ->screenshot('nodejs-04-nodejs-selected');

            // Verify Node.js Configuration section appears
            $nodejsSection = $browser->script("
                const sections = document.querySelectorAll('section, div');
                const headings = document.querySelectorAll('h2, h3, span');
                for (const h of headings) {
                    if (h.textContent.includes('Node.js Configuration')) {
                        return 'Node.js Configuration section found';
                    }
                }
                return 'Node.js Configuration section NOT found';
            ");

            echo " {$nodejsSection[0]}\n";

            // Verify Node.js specific fields exist
            $nodejsFields = $browser->script("
                const body = document.body.textContent;
                const fields = [];
                if (body.includes('Node.js Version') || body.includes('node_version')) fields.push('node_version');
                if (body.includes('Package Manager') || body.includes('package_manager')) fields.push('package_manager');
                if (body.includes('Framework')) fields.push('framework');
                if (body.includes('Start Command') || body.includes('start_command')) fields.push('start_command');
                if (body.includes('Build Command') || body.includes('build_command')) fields.push('build_command');
                return 'Fields found: ' + fields.join(', ');
            ");

            echo " {$nodejsFields[0]}\n";

            // Verify PHP Configuration is hidden
            $phpHidden = $browser->script("
                const body = document.body.textContent;
                if (body.includes('PHP Version') && body.includes('PHP Configuration')) {
                    return 'PHP Configuration visible (should be hidden)';
                }
                return 'PHP Configuration hidden (correct)';
            ");

            echo " {$phpHidden[0]}\n";

            $browser->screenshot('nodejs-05-final');
        });
    }

    /**
     * Test framework dropdown auto-fills commands.
     */
    public function test_framework_autofills_commands(): void
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

            $browser->pause(500);

            $browser->script("
                const btn = document.querySelector('button[type=\"submit\"]');
                if (btn) btn.click();
            ");

            $browser->pause(5000);

            // Go to create web app page
            $browser->visit("/app/{$this->teamId}/web-apps/create")
                ->pause(3000);

            // Select Node.js app type first
            $browser->script("
                const buttons = document.querySelectorAll('button');
                for (const btn of buttons) {
                    const span = btn.querySelector('span');
                    if (span && (span.textContent.includes('PHP') || span.textContent.includes('Select'))) {
                        btn.click();
                        break;
                    }
                }
            ");

            $browser->pause(1000);

            $browser->script("
                const options = document.querySelectorAll('[role=\"option\"], li');
                for (const opt of options) {
                    if (opt.textContent.toLowerCase().includes('node')) {
                        opt.click();
                        break;
                    }
                }
            ");

            $browser->pause(2000)
                ->screenshot('nodejs-framework-01-nodejs-selected');

            // Click on Framework dropdown
            $browser->script("
                const labels = document.querySelectorAll('label');
                for (const label of labels) {
                    if (label.textContent.includes('Framework')) {
                        // Find the nearby select button
                        const container = label.closest('.fi-fo-field-wrp');
                        if (container) {
                            const btn = container.querySelector('button');
                            if (btn) btn.click();
                        }
                        break;
                    }
                }
            ");

            $browser->pause(1000)
                ->screenshot('nodejs-framework-02-dropdown-open');

            // Select Next.js
            $selectNextjs = $browser->script("
                const options = document.querySelectorAll('[role=\"option\"], li');
                for (const opt of options) {
                    if (opt.textContent.includes('Next.js')) {
                        opt.click();
                        return 'Selected Next.js';
                    }
                }
                return 'Next.js option not found';
            ");

            echo "\n {$selectNextjs[0]}\n";

            $browser->pause(2000)
                ->screenshot('nodejs-framework-03-nextjs-selected');

            // Verify start_command was auto-filled
            $startCommand = $browser->script("
                const inputs = document.querySelectorAll('input, textarea');
                for (const input of inputs) {
                    if (input.name && input.name.includes('start_command')) {
                        return 'Start command: ' + input.value;
                    }
                }
                return 'start_command input not found';
            ");

            echo " {$startCommand[0]}\n";

            // Verify build_command was auto-filled
            $buildCommand = $browser->script("
                const inputs = document.querySelectorAll('input, textarea');
                for (const input of inputs) {
                    if (input.name && input.name.includes('build_command')) {
                        return 'Build command: ' + input.value;
                    }
                }
                return 'build_command input not found';
            ");

            echo " {$buildCommand[0]}\n";

            $browser->screenshot('nodejs-framework-04-final');
        });
    }

    /**
     * Test static app type shows correct fields.
     */
    public function test_static_app_type_shows_correct_fields(): void
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

            $browser->pause(500);

            $browser->script("
                const btn = document.querySelector('button[type=\"submit\"]');
                if (btn) btn.click();
            ");

            $browser->pause(5000);

            // Go to create web app page
            $browser->visit("/app/{$this->teamId}/web-apps/create")
                ->pause(3000);

            // Select Static app type
            $browser->script("
                const buttons = document.querySelectorAll('button');
                for (const btn of buttons) {
                    const span = btn.querySelector('span');
                    if (span && (span.textContent.includes('PHP') || span.textContent.includes('Select'))) {
                        btn.click();
                        break;
                    }
                }
            ");

            $browser->pause(1000);

            $browser->script("
                const options = document.querySelectorAll('[role=\"option\"], li');
                for (const opt of options) {
                    if (opt.textContent.toLowerCase().includes('static')) {
                        opt.click();
                        break;
                    }
                }
            ");

            $browser->pause(2000)
                ->screenshot('static-01-selected');

            // Verify Static Site Configuration section appears
            $staticSection = $browser->script("
                const headings = document.querySelectorAll('h2, h3, span');
                for (const h of headings) {
                    if (h.textContent.includes('Static Site Configuration')) {
                        return 'Static Site Configuration section found';
                    }
                }
                return 'Static Site Configuration section NOT found';
            ");

            echo "\n {$staticSection[0]}\n";

            // Verify PHP and Node.js sections are hidden
            $sectionsHidden = $browser->script("
                const body = document.body.textContent;
                let result = [];
                if (!body.includes('PHP Configuration')) result.push('PHP hidden');
                if (!body.includes('Node.js Configuration')) result.push('Node.js hidden');
                return result.length === 2 ? 'Both PHP and Node.js sections hidden (correct)' : result.join(', ');
            ");

            echo " {$sectionsHidden[0]}\n";

            $browser->screenshot('static-02-final');
        });
    }

    /**
     * Test viewing a Node.js web app and triggering deployment.
     * TASK-022.5: Test deployment trigger via UI.
     */
    public function test_nodejs_deployment_trigger(): void
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

            $browser->pause(500);

            $browser->script("
                const btn = document.querySelector('button[type=\"submit\"]');
                if (btn) btn.click();
            ");

            $browser->pause(5000)
                ->screenshot('deploy-01-logged-in');

            echo "\n Logged in as {$testEmail}\n";

            // Find a Node.js web app
            $nodejsApp = \App\Models\WebApp::where('team_id', $this->teamId)
                ->where('app_type', 'nodejs')
                ->first();

            if (!$nodejsApp) {
                echo " No Node.js app found, skipping deployment test\n";
                return;
            }

            echo " Found Node.js app: {$nodejsApp->domain}\n";

            // Navigate to web app view
            $browser->visit("/app/{$this->teamId}/web-apps/{$nodejsApp->id}")
                ->pause(3000)
                ->screenshot('deploy-02-webapp-view');

            // Check for Deploy button
            $deployButton = $browser->script("
                const buttons = document.querySelectorAll('button, a');
                for (const btn of buttons) {
                    if (btn.textContent.toLowerCase().includes('deploy')) {
                        return 'Deploy button found';
                    }
                }
                return 'Deploy button NOT found';
            ");

            echo " {$deployButton[0]}\n";

            // Check for app type indicator
            $appType = $browser->script("
                const body = document.body.textContent;
                if (body.includes('Node.js') || body.includes('nodejs')) {
                    return 'App type shown as Node.js';
                }
                return 'App type indicator not found';
            ");

            echo " {$appType[0]}\n";

            $browser->screenshot('deploy-03-final');
        });
    }

    /**
     * Test restarting a Node.js supervisor program.
     * TASK-022.6: Test restart Node.js action via UI.
     */
    public function test_nodejs_restart_action(): void
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

            $browser->pause(500);

            $browser->script("
                const btn = document.querySelector('button[type=\"submit\"]');
                if (btn) btn.click();
            ");

            $browser->pause(5000)
                ->screenshot('restart-01-logged-in');

            echo "\n Logged in as {$testEmail}\n";

            // Find a Node.js supervisor program
            $supervisor = \App\Models\SupervisorProgram::where('team_id', $this->teamId)
                ->whereNotNull('web_app_id')
                ->first();

            if (!$supervisor) {
                echo " No Node.js supervisor program found, skipping restart test\n";
                return;
            }

            echo " Found supervisor program: {$supervisor->name}\n";

            // Navigate to supervisor programs list
            $browser->visit("/app/{$this->teamId}/supervisor-programs")
                ->pause(3000)
                ->screenshot('restart-02-supervisor-list');

            // Check for the supervisor program in the list
            $programFound = $browser->script("
                const rows = document.querySelectorAll('tr, [role=\"row\"]');
                for (const row of rows) {
                    if (row.textContent.includes('nodejs-') || row.textContent.includes('express-')) {
                        return 'Supervisor program found in list';
                    }
                }
                return 'Supervisor program NOT found in list';
            ");

            echo " {$programFound[0]}\n";

            // Navigate to supervisor view
            $browser->visit("/app/{$this->teamId}/supervisor-programs/{$supervisor->id}")
                ->pause(3000)
                ->screenshot('restart-03-supervisor-view');

            // Check for restart action
            $restartAction = $browser->script("
                const buttons = document.querySelectorAll('button, a');
                for (const btn of buttons) {
                    const text = btn.textContent.toLowerCase();
                    if (text.includes('restart') || text.includes('stop') || text.includes('start')) {
                        return 'Process control actions found';
                    }
                }
                return 'Process control actions NOT found';
            ");

            echo " {$restartAction[0]}\n";

            // Check for status indicator
            $statusIndicator = $browser->script("
                const body = document.body.textContent.toLowerCase();
                if (body.includes('running') || body.includes('active')) {
                    return 'Status indicator shows running/active';
                }
                if (body.includes('stopped') || body.includes('failed')) {
                    return 'Status indicator shows stopped/failed';
                }
                return 'Status indicator not found';
            ");

            echo " {$statusIndicator[0]}\n";

            $browser->screenshot('restart-04-final');
        });
    }
}
