<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Models\Team;
use App\Models\WebApp;
use App\Services\ConfigGenerator\NodeNginxConfigGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeNginxConfigGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private Server $server;
    private NodeNginxConfigGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->server = Server::factory()->create(['team_id' => $this->team->id]);
        $this->generator = new NodeNginxConfigGenerator();
    }

    /**
     * Test generating basic Nginx config for Node.js app.
     */
    public function test_generate_basic_nodejs_config(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'example.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $config = $this->generator->generate($app);

        $this->assertStringContainsString('server_name example.com', $config);
        $this->assertStringContainsString('proxy_pass http://127.0.0.1:3000', $config);
        $this->assertStringContainsString('listen 80', $config);
        $this->assertStringContainsString('proxy_http_version 1.1', $config);
        $this->assertStringContainsString('Upgrade', $config); // WebSocket support
    }

    /**
     * Test config includes domain aliases.
     */
    public function test_generate_includes_domain_aliases(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'example.com',
            'aliases' => ['www.example.com', 'api.example.com'],
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $config = $this->generator->generate($app);

        $this->assertStringContainsString('example.com www.example.com api.example.com', $config);
    }

    /**
     * Test generating config with static assets path.
     */
    public function test_generate_with_static_assets_path(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'nextjs-app.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
            'static_assets_path' => '/_next/static/',
        ]);

        $config = $this->generator->generate($app);

        $this->assertStringContainsString('location /_next/static/', $config);
        $this->assertStringContainsString('expires 1y', $config);
        $this->assertStringContainsString('Cache-Control "public, immutable"', $config);
    }

    /**
     * Test generating config with health check path.
     */
    public function test_generate_with_health_check_path(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'api.example.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
            'health_check_path' => '/api/health',
        ]);

        $config = $this->generator->generate($app);

        $this->assertStringContainsString('location = /api/health', $config);
        $this->assertStringContainsString('access_log off', $config);
    }

    /**
     * Test generating config with path-based routing.
     */
    public function test_generate_with_proxy_routes(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'monorepo.example.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
            'proxy_routes' => [
                ['path' => '/api/', 'port' => 3001],
                ['path' => '/', 'port' => 3002],
            ],
        ]);

        $config = $this->generator->generate($app);

        $this->assertStringContainsString('location /api/', $config);
        $this->assertStringContainsString('proxy_pass http://127.0.0.1:3001', $config);
        $this->assertStringContainsString('location /', $config);
        $this->assertStringContainsString('proxy_pass http://127.0.0.1:3002', $config);
    }

    /**
     * Test generating SSL config.
     */
    public function test_generate_ssl_config(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'secure.example.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $config = $this->generator->generateSSL($app);

        $this->assertStringContainsString('listen 443 ssl http2', $config);
        $this->assertStringContainsString('ssl_certificate /etc/letsencrypt/live/secure.example.com/fullchain.pem', $config);
        $this->assertStringContainsString('ssl_certificate_key /etc/letsencrypt/live/secure.example.com/privkey.pem', $config);
        $this->assertStringContainsString('ssl_protocols TLSv1.2 TLSv1.3', $config);
        $this->assertStringContainsString('ssl_stapling on', $config);
        // Should also include HTTP config
        $this->assertStringContainsString('listen 80', $config);
    }

    /**
     * Test SSL config with static assets.
     */
    public function test_generate_ssl_with_static_assets(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'nextjs-ssl.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
            'static_assets_path' => '/_next/static/',
        ]);

        $config = $this->generator->generateSSL($app);

        // Static assets block should appear in both HTTP and HTTPS sections
        $this->assertStringContainsString('location /_next/static/', $config);
    }

    /**
     * Test config includes gzip compression.
     */
    public function test_generate_includes_gzip_compression(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'gzip.example.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $config = $this->generator->generate($app);

        $this->assertStringContainsString('gzip on', $config);
        $this->assertStringContainsString('gzip_types', $config);
        $this->assertStringContainsString('application/json', $config);
    }

    /**
     * Test config denies hidden files.
     */
    public function test_generate_denies_hidden_files(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'secure.example.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $config = $this->generator->generate($app);

        $this->assertStringContainsString('location ~ /\.(?!well-known).*', $config);
        $this->assertStringContainsString('deny all', $config);
    }

    /**
     * Test framework options are available.
     */
    public function test_get_framework_options(): void
    {
        $options = NodeNginxConfigGenerator::getFrameworkOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('nextjs', $options);
        $this->assertArrayHasKey('nuxtjs', $options);
        $this->assertArrayHasKey('nestjs', $options);
        $this->assertArrayHasKey('express', $options);
        $this->assertArrayHasKey('remix', $options);
        $this->assertArrayHasKey('astro', $options);
        $this->assertArrayHasKey('sveltekit', $options);
        $this->assertArrayHasKey('custom', $options);

        $this->assertEquals('Next.js', $options['nextjs']);
        $this->assertEquals('Nuxt.js', $options['nuxtjs']);
    }

    /**
     * Test getting framework static path.
     */
    public function test_get_framework_static_path(): void
    {
        $this->assertEquals('/_next/static/', NodeNginxConfigGenerator::getFrameworkStaticPath('nextjs'));
        $this->assertEquals('/_nuxt/', NodeNginxConfigGenerator::getFrameworkStaticPath('nuxtjs'));
        $this->assertNull(NodeNginxConfigGenerator::getFrameworkStaticPath('nestjs'));
        $this->assertNull(NodeNginxConfigGenerator::getFrameworkStaticPath('unknown'));
    }

    /**
     * Test getting framework health check path.
     */
    public function test_get_framework_health_path(): void
    {
        $this->assertEquals('/api/health', NodeNginxConfigGenerator::getFrameworkHealthPath('nextjs'));
        $this->assertEquals('/health', NodeNginxConfigGenerator::getFrameworkHealthPath('nestjs'));
        $this->assertNull(NodeNginxConfigGenerator::getFrameworkHealthPath('unknown'));
    }

    /**
     * Test getting framework start command.
     */
    public function test_get_framework_start_command(): void
    {
        $this->assertEquals('npm start', NodeNginxConfigGenerator::getFrameworkStartCommand('nextjs'));
        $this->assertEquals('node .output/server/index.mjs', NodeNginxConfigGenerator::getFrameworkStartCommand('nuxtjs'));
        $this->assertEquals('node dist/main', NodeNginxConfigGenerator::getFrameworkStartCommand('nestjs'));
    }

    /**
     * Test getting framework build command.
     */
    public function test_get_framework_build_command(): void
    {
        $this->assertEquals('npm run build', NodeNginxConfigGenerator::getFrameworkBuildCommand('nextjs'));
        $this->assertNull(NodeNginxConfigGenerator::getFrameworkBuildCommand('express'));
    }

    /**
     * Test getting full framework config.
     */
    public function test_get_framework_config(): void
    {
        $config = NodeNginxConfigGenerator::getFrameworkConfig('nextjs');

        $this->assertIsArray($config);
        $this->assertEquals('Next.js', $config['name']);
        $this->assertEquals('/_next/static/', $config['static_path']);
        $this->assertEquals('/api/health', $config['health_check']);
        $this->assertEquals('npm run build', $config['build_command']);
        $this->assertEquals('npm start', $config['start_command']);
    }

    /**
     * Test unknown framework returns null.
     */
    public function test_get_unknown_framework_returns_null(): void
    {
        $this->assertNull(NodeNginxConfigGenerator::getFrameworkConfig('unknown'));
    }

    /**
     * Test static assets block normalizes path.
     */
    public function test_static_assets_path_normalization(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'test.example.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
            'static_assets_path' => '_next/static', // Missing leading/trailing slashes
        ]);

        $block = $this->generator->generateStaticAssetsBlock($app);

        $this->assertStringContainsString('location /_next/static/', $block);
    }

    /**
     * Test empty static path returns empty block.
     */
    public function test_empty_static_path_returns_empty(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'test.example.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
            'static_assets_path' => null,
        ]);

        $block = $this->generator->generateStaticAssetsBlock($app);

        $this->assertEmpty($block);
    }

    /**
     * Test access log and error log paths are correct.
     */
    public function test_log_paths_are_correct(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'logging.example.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $config = $this->generator->generate($app);

        $this->assertStringContainsString('access_log /var/log/nginx/logging.example.com.access.log', $config);
        $this->assertStringContainsString('error_log /var/log/nginx/logging.example.com.error.log', $config);
    }

    /**
     * Test WebSocket support headers are included.
     */
    public function test_websocket_support_headers(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'websocket.example.com',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $config = $this->generator->generate($app);

        $this->assertStringContainsString('proxy_set_header Upgrade $http_upgrade', $config);
        $this->assertStringContainsString("proxy_set_header Connection 'upgrade'", $config);
        $this->assertStringContainsString('proxy_cache_bypass $http_upgrade', $config);
    }
}
