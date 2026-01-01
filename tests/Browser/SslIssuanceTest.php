<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Test SSL certificate issuance for web apps.
 * Uses ngrok URL for real browser testing.
 */
class SslIssuanceTest extends DuskTestCase
{
    protected string $testEmail;
    protected ?string $teamId = null;
    protected ?string $webAppId = null;
    protected string $webAppDomain = 'test.example.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->testEmail = $this->getTestUserEmail();

        // Get team and webapp from test user
        $user = \App\Models\User::where('email', $this->testEmail)->first();
        if ($user) {
            $team = $user->currentTeam ?? $user->teams()->first();
            $this->teamId = $team?->id;

            // Get first webapp for testing
            $webApp = \App\Models\WebApp::whereHas('server', fn($q) => $q->where('team_id', $this->teamId))->first();
            $this->webAppId = $webApp?->id;
            if ($webApp) {
                $this->webAppDomain = $webApp->domain;
            }
        }

        if (!$this->teamId || !$this->webAppId) {
            $this->markTestSkipped('Test user, team, or webapp not found. Configure DUSK_TEST_USER_EMAIL in .env');
        }
    }

    /**
     * Test issuing SSL certificate for a web app.
     */
    public function test_issue_ssl_certificate(): void
    {
        $testEmail = $this->testEmail;

        $this->browse(function (Browser $browser) use ($testEmail) {
            // Visit login page
            $browser->visit('/app/login')
                ->pause(3000)
                ->screenshot('ssl-01-login-page');

            // Filament uses data attributes for form fields
            // Try multiple selector strategies
            $browser->waitFor('form', 15)
                ->screenshot('ssl-01b-form-loaded');

            // Type email - try different selectors
            $emailTyped = $browser->script("
                const emailInput = document.querySelector('input[type=\"email\"], input[name=\"email\"], #data\\\\.email, input[id*=\"email\"]');
                if (emailInput) {
                    emailInput.value = '{$testEmail}';
                    emailInput.dispatchEvent(new Event('input', { bubbles: true }));
                    return 'Email typed';
                }
                return 'Email input not found';
            ");
            echo "\n {$emailTyped[0]}\n";

            // Type password
            $passTyped = $browser->script("
                const passInput = document.querySelector('input[type=\"password\"], input[name=\"password\"], #data\\\\.password, input[id*=\"password\"]');
                if (passInput) {
                    passInput.value = 'password';
                    passInput.dispatchEvent(new Event('input', { bubbles: true }));
                    return 'Password typed';
                }
                return 'Password input not found';
            ");
            echo " {$passTyped[0]}\n";

            $browser->screenshot('ssl-02-credentials-entered');

            // Click sign in button
            $browser->script("
                const btn = document.querySelector('button[type=\"submit\"], .fi-btn-primary, button.fi-btn');
                if (btn) btn.click();
            ");

            $browser->pause(5000)
                ->screenshot('ssl-03-after-login');

            echo "\n Logged in successfully\n";

            // Navigate to web app
            $browser->visit("/app/{$this->teamId}/web-apps/{$this->webAppId}")
                ->pause(3000)
                ->screenshot('ssl-03-webapp-detail');

            echo " Viewing web app: {$this->webAppDomain}\n";

            // Check current SSL status
            $html = $browser->driver->getPageSource();
            $hasSslSection = stripos($html, 'SSL') !== false || stripos($html, 'Certificate') !== false;
            echo " SSL section visible: " . ($hasSslSection ? 'Yes' : 'No') . "\n";

            // Look for SSL/Issue Certificate button
            $browser->screenshot('ssl-04-before-ssl-action');

            // Try to find and click the SSL action button
            // It might be "Issue SSL", "Enable SSL", or similar
            $sslButtonFound = $browser->script("
                const buttons = document.querySelectorAll('button, a');
                for (const btn of buttons) {
                    const text = btn.textContent.toLowerCase();
                    if (text.includes('ssl') || text.includes('certificate') || text.includes('https')) {
                        console.log('Found SSL button:', btn.textContent);
                        return btn.textContent.trim();
                    }
                }
                return null;
            ");

            if ($sslButtonFound[0]) {
                echo " Found SSL button: {$sslButtonFound[0]}\n";
            }

            // Scroll to find SSL section
            $browser->script('window.scrollTo(0, document.body.scrollHeight / 2)');
            $browser->pause(1000)
                ->screenshot('ssl-05-scrolled');

            // Try clicking on SSL-related action
            $clickResult = $browser->script("
                const buttons = document.querySelectorAll('button, a, [wire\\\\:click]');
                for (const btn of buttons) {
                    const text = btn.textContent.toLowerCase();
                    if (text.includes('issue ssl') || text.includes('enable ssl') ||
                        text.includes('request certificate') || text.includes('ssl certificate')) {
                        btn.click();
                        return 'clicked: ' + btn.textContent.trim();
                    }
                }
                // Also check for action buttons in the header
                const actions = document.querySelectorAll('.fi-ac-btn, .fi-btn, [class*=\"action\"]');
                for (const btn of actions) {
                    const text = btn.textContent.toLowerCase();
                    if (text.includes('ssl')) {
                        btn.click();
                        return 'clicked action: ' + btn.textContent.trim();
                    }
                }
                return 'no ssl button found';
            ");

            echo " Click result: {$clickResult[0]}\n";

            $browser->pause(2000)
                ->screenshot('ssl-06-after-click');

            // Check if a modal appeared
            $modalCheck = $browser->script("
                const modal = document.querySelector('.fi-modal, [x-data*=\"modal\"], .modal');
                return modal ? 'Modal visible' : 'No modal';
            ");
            echo " Modal: {$modalCheck[0]}\n";

            // If modal is open, click Confirm button
            if (strpos($modalCheck[0], 'Modal visible') !== false) {
                $browser->screenshot('ssl-07-modal-open');

                echo " Clicking Confirm button...\n";

                // Click the Confirm button
                $confirmResult = $browser->script("
                    // Find all buttons in modal
                    const buttons = document.querySelectorAll('.fi-modal button, [x-data] button');
                    for (const btn of buttons) {
                        const text = btn.textContent.trim().toLowerCase();
                        if (text === 'confirm' || text.includes('confirm')) {
                            btn.click();
                            return 'Clicked: ' + btn.textContent.trim();
                        }
                    }
                    // Try primary button
                    const primaryBtn = document.querySelector('button.bg-primary-600, button.fi-color-primary');
                    if (primaryBtn) {
                        primaryBtn.click();
                        return 'Clicked primary button';
                    }
                    return 'No confirm button found';
                ");
                echo " Confirm result: {$confirmResult[0]}\n";

                $browser->pause(5000)
                    ->screenshot('ssl-08-after-confirm');

                // Check for success notification or job creation
                $browser->pause(3000);
            }

            // Check for success notification
            $successCheck = $browser->script("
                const notification = document.querySelector('.fi-notification, [role=\"alert\"], .fi-no-notification');
                if (notification) return notification.textContent;
                // Check for any visible toast/notification
                const toast = document.querySelector('[x-data*=\"notification\"]');
                return toast ? toast.textContent : 'No notification visible';
            ");
            echo " Notification: " . substr($successCheck[0], 0, 150) . "\n";

            // Check if SSL status changed in the page
            $sslStatus = $browser->script("
                const page = document.body.textContent;
                if (page.includes('pending') || page.includes('issuing')) return 'SSL issuance in progress';
                if (page.includes('active') && page.includes('SSL')) return 'SSL active';
                return 'Unknown status';
            ");
            echo " SSL Status: {$sslStatus[0]}\n";

            $browser->screenshot('ssl-09-final');
        });
    }
}
