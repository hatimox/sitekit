<?php

namespace Tests\Browser;

use App\Models\Database;
use App\Models\FirewallRule;
use App\Models\Server;
use App\Models\Service;
use App\Models\SshKey;
use App\Models\Team;
use App\Models\User;
use App\Models\WebApp;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AiChatWidgetTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $user;
    protected Team $team;
    protected Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and team
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->team->users()->attach($this->user->id, ['role' => 'owner']);

        // Create a test server
        $this->server = Server::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Test Server',
            'status' => Server::STATUS_ACTIVE,
            'ip_address' => '192.168.1.100',
        ]);
    }

    public function test_ai_chat_widget_loads_when_enabled(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/servers")
                ->waitFor('body', 10)
                ->assertPresent('#ai-chat-widget')
                ->assertPresent('#ai-chat-button')
                ->screenshot('ai-widget-loaded');
        });
    }

    public function test_ai_chat_widget_hidden_when_disabled(): void
    {
        config(['ai.enabled' => false]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/servers")
                ->waitFor('body', 10)
                ->assertMissing('#ai-chat-widget')
                ->screenshot('ai-widget-hidden');
        });
    }

    public function test_ai_chat_button_opens_widget(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/servers")
                ->waitFor('#ai-chat-button', 10)
                ->click('#ai-chat-button')
                ->waitFor('.ai-chat-container', 5)
                ->assertVisible('.ai-chat-container')
                ->screenshot('ai-widget-opened');
        });
    }

    public function test_open_ai_chat_function_with_message(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/servers")
                ->waitFor('#ai-chat-button', 10)
                ->script('window.openAiChat("Test message from Dusk")')
                ->pause(500)
                ->assertVisible('.ai-chat-container')
                ->screenshot('ai-chat-with-message');
        });
    }

    public function test_server_view_has_ai_actions(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/servers/{$this->server->id}")
                ->waitFor('body', 10)
                // Check for AI action buttons
                ->assertSee('Diagnose with AI')
                ->screenshot('server-view-ai-actions');
        });
    }

    public function test_server_ai_diagnose_opens_chat(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/servers/{$this->server->id}")
                ->waitFor('body', 10)
                ->click('@ai-diagnose-button') // Using dusk selector if available
                ->pause(500)
                ->assertVisible('.ai-chat-container')
                ->screenshot('server-ai-diagnose-clicked');
        });
    }

    public function test_ssh_key_resource_has_ai_triggers(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/ssh-keys/create")
                ->waitFor('body', 10)
                // Check for AI helper buttons in form section
                ->assertSee('Which key type?')
                ->assertSee('How to generate?')
                ->screenshot('ssh-key-create-ai-triggers');
        });
    }

    public function test_firewall_resource_has_ai_triggers(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/firewall-rules")
                ->waitFor('body', 10)
                // Check for AI header actions
                ->assertSee('Audit Rules')
                ->assertSee('Generate Rules')
                ->screenshot('firewall-list-ai-triggers');
        });
    }

    public function test_firewall_create_has_ai_helpers(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/firewall-rules/create")
                ->waitFor('body', 10)
                // Check for AI helper buttons
                ->assertSee('What ports do I need?')
                ->assertSee('Security tips')
                ->screenshot('firewall-create-ai-helpers');
        });
    }

    public function test_webapp_resource_has_ai_triggers(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/web-apps/create")
                ->waitFor('body', 10)
                // Check for AI helper buttons
                ->assertSee('Which stack?')
                ->assertSee('PHP version?')
                ->screenshot('webapp-create-ai-triggers');
        });
    }

    public function test_database_resource_has_ai_triggers(): void
    {
        config(['ai.enabled' => true]);

        $database = Database::factory()->create([
            'team_id' => $this->team->id,
            'server_id' => $this->server->id,
            'status' => Database::STATUS_ACTIVE,
        ]);

        $this->browse(function (Browser $browser) use ($database) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/databases/{$database->id}")
                ->waitFor('body', 10)
                // Check for AI Help action group
                ->assertSee('AI Help')
                ->screenshot('database-view-ai-triggers');
        });
    }

    public function test_cron_job_create_has_ai_helpers(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/cron-jobs/create")
                ->waitFor('body', 10)
                // Check for AI helper buttons
                ->assertSee('Cron syntax help')
                ->assertSee('Laravel scheduler')
                ->screenshot('cron-create-ai-helpers');
        });
    }

    public function test_supervisor_create_has_ai_helpers(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/daemons/create")
                ->waitFor('body', 10)
                // Check for AI helper buttons
                ->assertSee('Queue worker setup')
                ->assertSee('Supervisor basics')
                ->screenshot('supervisor-create-ai-helpers');
        });
    }

    public function test_ai_chat_can_be_closed(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/servers")
                ->waitFor('#ai-chat-button', 10)
                ->click('#ai-chat-button')
                ->waitFor('.ai-chat-container', 5)
                ->assertVisible('.ai-chat-container')
                // Click close button
                ->click('.ai-chat-close')
                ->pause(300)
                ->assertMissing('.ai-chat-container')
                ->screenshot('ai-chat-closed');
        });
    }

    public function test_ai_chat_persists_across_navigation(): void
    {
        config(['ai.enabled' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/servers")
                ->waitFor('#ai-chat-button', 10)
                ->click('#ai-chat-button')
                ->waitFor('.ai-chat-container', 5)
                ->script('window.openAiChat("Test persistence")')
                ->pause(500)
                // Navigate to another page
                ->visit("/app/{$this->team->id}/ssh-keys")
                ->waitFor('body', 10)
                // Widget should still be present
                ->assertPresent('#ai-chat-widget')
                ->screenshot('ai-chat-persistence');
        });
    }

    public function test_failed_service_shows_ai_diagnose(): void
    {
        config(['ai.enabled' => true]);

        $service = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_PHP,
            'version' => '8.3',
            'status' => Service::STATUS_FAILED,
            'error_message' => 'Test error',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/services")
                ->waitFor('body', 10)
                // Should see AI Diagnose for failed service
                ->assertSee('AI Diagnose')
                ->screenshot('failed-service-ai-diagnose');
        });
    }

    public function test_firewall_explain_action(): void
    {
        config(['ai.enabled' => true]);

        $rule = FirewallRule::factory()->create([
            'team_id' => $this->team->id,
            'server_id' => $this->server->id,
            'action' => FirewallRule::ACTION_ALLOW,
            'direction' => FirewallRule::DIRECTION_IN,
            'protocol' => FirewallRule::PROTOCOL_TCP,
            'port' => '22',
            'from_ip' => 'any',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/app/{$this->team->id}/firewall-rules")
                ->waitFor('body', 10)
                // Should see Explain action
                ->assertSee('Explain')
                ->screenshot('firewall-explain-action');
        });
    }
}
