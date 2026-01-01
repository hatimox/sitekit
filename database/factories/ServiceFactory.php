<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'type' => fake()->randomElement([
                Service::TYPE_NGINX,
                Service::TYPE_PHP,
                Service::TYPE_MARIADB,
                Service::TYPE_REDIS,
            ]),
            'version' => 'latest',
            'status' => Service::STATUS_ACTIVE,
            'is_default' => true,
            'configuration' => [],
            'installed_at' => now(),
        ];
    }

    public function php(string $version = '8.3'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Service::TYPE_PHP,
            'version' => $version,
        ]);
    }

    public function nginx(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Service::TYPE_NGINX,
            'version' => 'latest',
        ]);
    }

    public function mariadb(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Service::TYPE_MARIADB,
            'version' => 'latest',
        ]);
    }

    public function redis(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Service::TYPE_REDIS,
            'version' => 'latest',
        ]);
    }

    public function stopped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Service::STATUS_STOPPED,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Service::STATUS_FAILED,
        ]);
    }
}
