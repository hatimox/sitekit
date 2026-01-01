<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true) . ' Server',
            'ip_address' => fake()->ipv4(),
            'provider' => fake()->randomElement(['aws', 'digitalocean', 'linode', 'vultr', 'hetzner']),
            'status' => Server::STATUS_ACTIVE,
            'agent_token' => Str::random(64),
            'last_heartbeat_at' => now(),
        ];
    }

    public function connecting(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Server::STATUS_CONNECTING,
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Server::STATUS_OFFLINE,
        ]);
    }
}
