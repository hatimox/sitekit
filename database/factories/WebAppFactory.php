<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\Team;
use App\Models\WebApp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WebAppFactory extends Factory
{
    protected $model = WebApp::class;

    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true) . ' App',
            'domain' => fake()->unique()->domainName(),
            'system_user' => 'sitekit',
            'public_path' => 'public',
            'app_type' => WebApp::APP_TYPE_PHP,
            'web_server' => WebApp::WEB_SERVER_NGINX,
            'php_version' => '8.3',
            'status' => WebApp::STATUS_ACTIVE,
            'ssl_status' => WebApp::SSL_NONE,
            'webhook_secret' => Str::random(40),
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
        ];
    }

    public function nodejs(): static
    {
        return $this->state(fn (array $attributes) => [
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => fake()->numberBetween(3000, 3999),
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
            'php_version' => null,
        ]);
    }

    public function static(): static
    {
        return $this->state(fn (array $attributes) => [
            'app_type' => WebApp::APP_TYPE_STATIC,
            'php_version' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebApp::STATUS_PENDING,
        ]);
    }

    public function withSsl(): static
    {
        return $this->state(fn (array $attributes) => [
            'ssl_status' => WebApp::SSL_ACTIVE,
            'ssl_expires_at' => now()->addMonths(3),
        ]);
    }
}
