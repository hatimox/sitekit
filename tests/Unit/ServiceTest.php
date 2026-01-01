<?php

namespace Tests\Unit;

use App\Models\Database;
use App\Models\Server;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $team;
    protected Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
        $this->server = Server::factory()->create([
            'team_id' => $this->team->id,
            'status' => Server::STATUS_ACTIVE,
        ]);
    }

    public function test_can_be_stopped_returns_false_for_nginx(): void
    {
        $service = Service::factory()->nginx()->create([
            'server_id' => $this->server->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        $this->assertFalse($service->canBeStopped());
    }

    public function test_can_be_stopped_returns_false_for_supervisor(): void
    {
        $service = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_SUPERVISOR,
            'version' => 'latest',
            'status' => Service::STATUS_ACTIVE,
        ]);

        $this->assertFalse($service->canBeStopped());
    }

    public function test_can_be_stopped_returns_true_for_redis(): void
    {
        $service = Service::factory()->redis()->create([
            'server_id' => $this->server->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        $this->assertTrue($service->canBeStopped());
    }

    public function test_can_be_stopped_returns_true_for_mariadb(): void
    {
        $service = Service::factory()->mariadb()->create([
            'server_id' => $this->server->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        $this->assertTrue($service->canBeStopped());
    }

    public function test_is_database_engine_returns_true_for_database_types(): void
    {
        $mariadb = Service::factory()->mariadb()->create([
            'server_id' => $this->server->id,
        ]);

        $mysql = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_MYSQL,
            'version' => 'latest',
        ]);

        $postgresql = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_POSTGRESQL,
            'version' => 'latest',
        ]);

        $this->assertTrue($mariadb->isDatabaseEngine());
        $this->assertTrue($mysql->isDatabaseEngine());
        $this->assertTrue($postgresql->isDatabaseEngine());
    }

    public function test_is_database_engine_returns_false_for_non_database_types(): void
    {
        $nginx = Service::factory()->nginx()->create([
            'server_id' => $this->server->id,
        ]);

        $redis = Service::factory()->redis()->create([
            'server_id' => $this->server->id,
        ]);

        $this->assertFalse($nginx->isDatabaseEngine());
        $this->assertFalse($redis->isDatabaseEngine());
    }

    public function test_has_dependent_databases_returns_true_when_databases_exist(): void
    {
        $service = Service::factory()->mariadb()->create([
            'server_id' => $this->server->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        Database::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'type' => Database::TYPE_MARIADB,
        ]);

        $this->assertTrue($service->hasDependentDatabases());
    }

    public function test_has_dependent_databases_returns_false_when_no_databases(): void
    {
        $service = Service::factory()->mariadb()->create([
            'server_id' => $this->server->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        $this->assertFalse($service->hasDependentDatabases());
    }

    public function test_is_php_service(): void
    {
        $php = Service::factory()->php()->create([
            'server_id' => $this->server->id,
        ]);

        $nginx = Service::factory()->nginx()->create([
            'server_id' => $this->server->id,
        ]);

        $this->assertTrue($php->isPhpService());
        $this->assertFalse($nginx->isPhpService());
    }

    public function test_get_installed_extensions_returns_default_extensions(): void
    {
        $service = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_PHP,
            'version' => '8.3',
        ]);

        $extensions = $service->getInstalledExtensions();

        $this->assertContains('cli', $extensions);
        $this->assertContains('fpm', $extensions);
        $this->assertContains('mysql', $extensions);
    }

    public function test_get_installed_extensions_returns_custom_extensions(): void
    {
        $service = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_PHP,
            'version' => '8.3',
            'configuration' => ['extensions' => ['cli', 'fpm', 'imagick']],
        ]);

        $extensions = $service->getInstalledExtensions();

        $this->assertEquals(['cli', 'fpm', 'imagick'], $extensions);
    }

    public function test_get_installed_extensions_returns_empty_for_non_php(): void
    {
        $service = Service::factory()->nginx()->create([
            'server_id' => $this->server->id,
        ]);

        $this->assertEmpty($service->getInstalledExtensions());
    }

    public function test_supports_config_editing(): void
    {
        $nginx = Service::factory()->nginx()->create([
            'server_id' => $this->server->id,
        ]);

        $php = Service::factory()->php()->create([
            'server_id' => $this->server->id,
        ]);

        $this->assertTrue($nginx->supportsConfigEditing());
        $this->assertTrue($php->supportsConfigEditing());
    }

    public function test_supports_log_viewing(): void
    {
        $nginx = Service::factory()->nginx()->create([
            'server_id' => $this->server->id,
        ]);

        $php = Service::factory()->php()->create([
            'server_id' => $this->server->id,
        ]);

        $this->assertTrue($nginx->supportsLogViewing());
        $this->assertTrue($php->supportsLogViewing());
    }

    public function test_get_log_files_for_nginx(): void
    {
        $service = Service::factory()->nginx()->create([
            'server_id' => $this->server->id,
        ]);

        $logFiles = $service->getLogFiles();

        $this->assertArrayHasKey('/var/log/nginx/access.log', $logFiles);
        $this->assertArrayHasKey('/var/log/nginx/error.log', $logFiles);
    }

    public function test_get_log_files_for_php(): void
    {
        $service = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_PHP,
            'version' => '8.3',
        ]);

        $logFiles = $service->getLogFiles();

        $this->assertArrayHasKey('/var/log/php8.3-fpm.log', $logFiles);
    }

    public function test_get_editable_config_files_for_php(): void
    {
        $service = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_PHP,
            'version' => '8.3',
        ]);

        $configFiles = $service->getEditableConfigFiles();

        $this->assertArrayHasKey('/etc/php/8.3/fpm/php-fpm.conf', $configFiles);
        $this->assertArrayHasKey('/etc/php/8.3/fpm/php.ini', $configFiles);
    }

    public function test_is_active(): void
    {
        $activeService = Service::factory()->nginx()->create([
            'server_id' => $this->server->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        $stoppedService = Service::factory()->redis()->stopped()->create([
            'server_id' => $this->server->id,
        ]);

        $this->assertTrue($activeService->isActive());
        $this->assertFalse($stoppedService->isActive());
    }

    public function test_display_name(): void
    {
        $php = Service::factory()->php()->create([
            'server_id' => $this->server->id,
        ]);

        $nginx = Service::factory()->nginx()->create([
            'server_id' => $this->server->id,
        ]);

        $this->assertEquals('PHP 8.3', $php->display_name);
        $this->assertEquals('Nginx', $nginx->display_name);
    }

    public function test_get_available_php_extensions(): void
    {
        $extensions = Service::getAvailablePhpExtensions();

        $this->assertIsArray($extensions);
        $this->assertArrayHasKey('cli', $extensions);
        $this->assertArrayHasKey('fpm', $extensions);
        $this->assertArrayHasKey('mysql', $extensions);
        $this->assertArrayHasKey('imagick', $extensions);
        $this->assertArrayHasKey('redis', $extensions);
    }

    public function test_add_extension_to_config(): void
    {
        $service = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_PHP,
            'version' => '8.3',
            'configuration' => ['extensions' => ['cli', 'fpm']],
        ]);

        $service->addExtensionToConfig('imagick');

        $this->assertContains('imagick', $service->fresh()->getInstalledExtensions());
    }

    public function test_remove_extension_from_config(): void
    {
        $service = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_PHP,
            'version' => '8.3',
            'configuration' => ['extensions' => ['cli', 'fpm', 'imagick']],
        ]);

        $service->removeExtensionFromConfig('imagick');

        $this->assertNotContains('imagick', $service->fresh()->getInstalledExtensions());
    }

    public function test_health_status_returns_healthy_for_database_with_ok_status(): void
    {
        $service = Service::factory()->mariadb()->create([
            'server_id' => $this->server->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        // Set database health on the server
        $this->server->update([
            'database_health' => [
                'mysql' => [
                    'status' => 'ok',
                    'response_ms' => 15,
                ],
            ],
        ]);

        $this->assertEquals('healthy', $service->health_status);
    }

    public function test_health_status_returns_unhealthy_for_database_with_error(): void
    {
        $service = Service::factory()->mariadb()->create([
            'server_id' => $this->server->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        // Set database health with error on the server
        $this->server->update([
            'database_health' => [
                'mysql' => [
                    'status' => 'error',
                    'error' => 'Access denied for user',
                ],
            ],
        ]);

        $this->assertEquals('unhealthy', $service->health_status);
    }

    public function test_health_status_returns_healthy_for_active_non_database_services(): void
    {
        $service = Service::factory()->redis()->create([
            'server_id' => $this->server->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        // Non-database services return 'healthy' when active
        $this->assertEquals('healthy', $service->health_status);
    }

    public function test_health_status_returns_null_for_stopped_non_database_services(): void
    {
        $service = Service::factory()->redis()->stopped()->create([
            'server_id' => $this->server->id,
        ]);

        $this->assertNull($service->health_status);
    }

    public function test_database_health_error_returns_error_message(): void
    {
        $service = Service::factory()->mariadb()->create([
            'server_id' => $this->server->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        $this->server->update([
            'database_health' => [
                'mysql' => [
                    'status' => 'error',
                    'error' => 'Connection refused',
                ],
            ],
        ]);

        $this->assertEquals('Connection refused', $service->database_health_error);
    }

    public function test_database_health_response_ms_returns_response_time(): void
    {
        $service = Service::factory()->mariadb()->create([
            'server_id' => $this->server->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        $this->server->update([
            'database_health' => [
                'mysql' => [
                    'status' => 'ok',
                    'response_ms' => 25,
                ],
            ],
        ]);

        $this->assertEquals(25, $service->database_health_response_ms);
    }

    public function test_can_be_repaired_returns_true_for_repairable_services(): void
    {
        $mariadb = Service::factory()->mariadb()->create([
            'server_id' => $this->server->id,
        ]);

        $mysql = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_MYSQL,
            'version' => '8.4',
        ]);

        $postgresql = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_POSTGRESQL,
            'version' => '17',
        ]);

        $this->assertTrue($mariadb->canBeRepaired());
        $this->assertTrue($mysql->canBeRepaired());
        $this->assertTrue($postgresql->canBeRepaired());
    }

    public function test_can_be_repaired_returns_true_for_nginx_and_php(): void
    {
        // Nginx, PHP and Supervisor can also be repaired
        $nginx = Service::factory()->nginx()->create([
            'server_id' => $this->server->id,
        ]);

        $php = Service::factory()->php()->create([
            'server_id' => $this->server->id,
        ]);

        $this->assertTrue($nginx->canBeRepaired());
        $this->assertTrue($php->canBeRepaired());
    }

    public function test_can_be_repaired_returns_false_for_memcached(): void
    {
        // Memcached doesn't have a repair handler
        $memcached = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_MEMCACHED,
            'version' => '1.6',
        ]);

        $this->assertFalse($memcached->canBeRepaired());
    }
}
