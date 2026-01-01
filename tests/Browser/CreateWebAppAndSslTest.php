<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Test creating a web app and issuing SSL certificate.
 */
class CreateWebAppAndSslTest extends DuskTestCase
{
    protected string $testEmail;
    protected ?string $teamId = null;
    protected ?string $serverId = null;
    protected ?string $webAppId = null;
    protected string $domain = 'test.example.com';

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

            // Get first webapp (for SSL test)
            $webApp = \App\Models\WebApp::whereHas('server', fn($q) => $q->where('team_id', $this->teamId))->first();
            $this->webAppId = $webApp?->id;
            if ($webApp) {
                $this->domain = $webApp->domain;
            }
        }

        if (!$this->teamId || !$this->serverId) {
            $this->markTestSkipped('Test user, team, or server not found. Configure DUSK_TEST_USER_EMAIL in .env');
        }
    }

    /**
     * Step 1: Create a web app via the UI.
     */
    public function test_step1_create_webapp(): void
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
                ->screenshot('webapp-01-logged-in');

            echo "\n Logged in as {$testEmail}\n";

            // Go directly to web apps list
            $browser->visit("/app/{$this->teamId}/web-apps")
                ->pause(3000)
                ->screenshot('webapp-02-webapps-list');

            echo " Viewing web apps list\n";

            // Click "New web app" button
            $newBtnResult = $browser->script("
                const links = document.querySelectorAll('a, button');
                for (const link of links) {
                    const text = link.textContent.toLowerCase();
                    if (text.includes('new web app') || text.includes('create web app') ||
                        (text.includes('new') && window.location.href.includes('web-apps'))) {
                        link.click();
                        return 'clicked: ' + link.textContent.trim();
                    }
                }
                return 'no new button found';
            ");

            echo " New button: {$newBtnResult[0]}\n";

            $browser->pause(3000)
                ->screenshot('webapp-03-create-page');

            // Fill in the create form - Name, Domain, Server

            // Fill Name field (placeholder "My Laravel App")
            $browser->script("
                const inputs = document.querySelectorAll('input');
                for (const input of inputs) {
                    const placeholder = (input.placeholder || '').toLowerCase();
                    if (placeholder.includes('laravel') || placeholder.includes('my ')) {
                        input.value = 'test6';
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        console.log('Filled name');
                        break;
                    }
                }
            ");

            $browser->pause(500);

            // Fill Domain field (placeholder "app.example.com")
            $browser->script("
                const inputs = document.querySelectorAll('input');
                for (const input of inputs) {
                    const placeholder = (input.placeholder || '').toLowerCase();
                    if (placeholder.includes('example.com')) {
                        input.value = '{$this->domain}';
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        console.log('Filled domain');
                        break;
                    }
                }
            ");

            $browser->pause(500)
                ->screenshot('webapp-04-name-domain-filled');

            // Click on Server dropdown to open it
            $browser->script("
                const selectButtons = document.querySelectorAll('button');
                for (const btn of selectButtons) {
                    const span = btn.querySelector('span');
                    if (span && span.textContent.trim() === 'Select an option') {
                        btn.click();
                        break;
                    }
                }
            ");

            $browser->pause(1000)
                ->screenshot('webapp-05-server-dropdown-open');

            // Type in the search box to filter, then select
            $browser->script("
                const searchInput = document.querySelector('input[type=\"search\"], input[placeholder*=\"search\"], .fi-select-input input');
                if (searchInput) {
                    searchInput.value = 'aws';
                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");

            $browser->pause(1000)
                ->screenshot('webapp-05b-typed-search');

            // Use keyboard to select - press Down Arrow then Enter
            $browser->script("
                const searchInput = document.querySelector('input[placeholder*=\"search\"], input[type=\"text\"]:focus');
                if (searchInput) {
                    // Press Down Arrow to highlight first option
                    searchInput.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
                }
            ");

            $browser->pause(500);

            $browser->script("
                // Press Enter to select
                const activeElement = document.activeElement;
                if (activeElement) {
                    activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
                }
            ");

            $browser->pause(1000)
                ->screenshot('webapp-06-after-keyboard');

            // Check if still not selected, try direct click on the option div
            $serverResult = $browser->script("
                // Check if dropdown is still open
                const listbox = document.querySelector('[role=\"listbox\"]');
                if (listbox) {
                    // Find and click option with pointer events
                    const opt = Array.from(listbox.querySelectorAll('div, span')).find(el =>
                        el.textContent.trim() === 'aws-test-server'
                    );
                    if (opt) {
                        // Simulate a real click
                        const rect = opt.getBoundingClientRect();
                        const clickEvent = new MouseEvent('click', {
                            bubbles: true,
                            cancelable: true,
                            view: window,
                            clientX: rect.left + rect.width / 2,
                            clientY: rect.top + rect.height / 2
                        });
                        opt.dispatchEvent(clickEvent);
                        return 'Simulated real click on option';
                    }
                }
                return 'Dropdown closed or option not found';
            ");

            echo " Server select: {$serverResult[0]}\n";

            $browser->pause(1500)
                ->screenshot('webapp-06-after-select');

            // Verify selection
            $verifyResult = $browser->script("
                const buttons = document.querySelectorAll('button');
                for (const btn of buttons) {
                    if (btn.textContent.includes('aws-test-server')) {
                        return 'Verified: Server selected';
                    }
                }
                return 'Server not selected';
            ");

            echo " Verify: {$verifyResult[0]}\n";

            // Submit the form - scroll down first to find Create button
            $browser->script("window.scrollTo(0, document.body.scrollHeight);");
            $browser->pause(500);

            $submitResult = $browser->script("
                const buttons = document.querySelectorAll('button');
                for (const btn of buttons) {
                    const text = btn.textContent.trim().toLowerCase();
                    if (text === 'create' || text.includes('create')) {
                        btn.click();
                        return 'clicked: ' + btn.textContent.trim();
                    }
                }
                return 'no create button found';
            ");

            echo " Submit: {$submitResult[0]}\n";

            $browser->pause(5000)
                ->screenshot('webapp-07-after-submit');

            // Verify we're on the web app detail page or list shows our domain
            $result = $browser->script("
                const body = document.body.textContent;
                const url = window.location.href;
                if (body.includes('{$this->domain}') && !url.includes('/create')) {
                    return 'Success - Domain found on page';
                }
                if (url.includes('/create')) {
                    // Check for validation errors
                    const errors = document.querySelectorAll('[class*=\"error\"], .text-danger-600');
                    if (errors.length > 0) {
                        return 'Validation error: ' + errors[0].textContent;
                    }
                    return 'Still on create page - URL: ' + url;
                }
                return 'URL: ' + url;
            ");

            echo " Result: {$result[0]}\n";

            $browser->screenshot('webapp-08-final');
        });
    }

    /**
     * Step 2: Issue SSL certificate for the web app.
     */
    public function test_step2_issue_ssl(): void
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
                ->screenshot('ssl-01-logged-in');

            echo "\n Logged in as {$testEmail}\n";

            // Go directly to web app detail page
            $browser->visit("/app/{$this->teamId}/web-apps/{$this->webAppId}")
                ->pause(3000)
                ->screenshot('ssl-02-webapp-detail');

            echo " Viewing web app: {$this->domain}\n";

            // Look for SSL/Issue Certificate action
            $sslButton = $browser->script("
                const buttons = document.querySelectorAll('button, a');
                for (const btn of buttons) {
                    const text = btn.textContent.toLowerCase();
                    if (text.includes('ssl') || text.includes('certificate')) {
                        return btn.textContent.trim();
                    }
                }
                return 'No SSL button found';
            ");

            echo " SSL button found: {$sslButton[0]}\n";

            // Click the SSL button
            $clickSsl = $browser->script("
                const buttons = document.querySelectorAll('button, a');
                for (const btn of buttons) {
                    const text = btn.textContent.toLowerCase();
                    if (text.includes('issue ssl') || text.includes('ssl certificate')) {
                        btn.click();
                        return 'clicked: ' + btn.textContent.trim();
                    }
                }
                return 'no ssl action found';
            ");

            echo " SSL action: {$clickSsl[0]}\n";

            $browser->pause(2000)
                ->screenshot('ssl-03-after-ssl-click');

            // Check for modal and confirm
            $modalCheck = $browser->script("
                const modal = document.querySelector('.fi-modal, [x-data*=\"modal\"], .modal, [role=\"dialog\"]');
                return modal ? 'Modal found' : 'No modal';
            ");

            echo " Modal: {$modalCheck[0]}\n";

            if (strpos($modalCheck[0], 'Modal found') !== false) {
                $browser->screenshot('ssl-04-modal-open');

                // Click the green Confirm button - it's a primary colored button
                $confirmResult = $browser->script("
                    // Look for the Confirm button specifically
                    const buttons = document.querySelectorAll('button');
                    for (const btn of buttons) {
                        const text = btn.textContent.trim();
                        if (text === 'Confirm') {
                            // Multiple click approaches
                            btn.focus();
                            btn.click();
                            // Also try dispatching events
                            btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
                            return 'Clicked Confirm button';
                        }
                    }
                    return 'Confirm button not found';
                ");

                echo " Confirm: {$confirmResult[0]}\n";

                $browser->pause(3000)
                    ->screenshot('ssl-05-after-first-click');

                // Check if modal closed
                $modalStillOpen = $browser->script("
                    const modal = document.querySelector('.fi-modal, [role=\"dialog\"]');
                    return modal ? 'Modal still open' : 'Modal closed';
                ");

                echo " After click: {$modalStillOpen[0]}\n";

                // If modal still open, try pressing Enter or clicking again
                if (strpos($modalStillOpen[0], 'still open') !== false) {
                    $browser->script("
                        // Try finding and clicking the submit button with type submit
                        const submitBtn = document.querySelector('.fi-modal button[type=\"submit\"], .fi-modal button.fi-btn-color-primary');
                        if (submitBtn) {
                            submitBtn.click();
                        }
                    ");

                    $browser->pause(3000);
                }

                $browser->screenshot('ssl-06-after-confirm');
            }

            // Check result - look for SSL status specifically
            $result = $browser->script("
                // Look for SSL status badge or text
                const sslElements = document.querySelectorAll('[class*=\"badge\"], span, div');
                for (const el of sslElements) {
                    const text = el.textContent.toLowerCase();
                    if (text === 'pending' || text === 'issuing') {
                        return 'SSL Status: pending';
                    }
                }
                // Check the SSL Status field
                const body = document.body.textContent;
                if (body.includes('SSL Status') && body.includes('pending')) return 'SSL Status: pending';
                if (body.includes('SSL Status') && body.includes('none')) return 'SSL Status: none';
                // Check for success notification
                const notification = document.querySelector('.fi-notification');
                if (notification) return 'Notification: ' + notification.textContent.trim().substring(0, 50);
                return 'Could not determine SSL status';
            ");

            echo " Result: {$result[0]}\n";

            $browser->screenshot('ssl-07-final');
        });
    }
}
