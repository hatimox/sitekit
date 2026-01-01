<?php

namespace Database\Factories;

use App\Models\Database;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class DatabaseFactory extends Factory
{
    protected $model = Database::class;

    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'team_id' => Team::factory(),
            'name' => 'db_' . fake()->word() . '_' . fake()->randomNumber(4),
            'type' => Database::TYPE_MARIADB,
            'status' => Database::STATUS_ACTIVE,
        ];
    }

    public function mariadb(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Database::TYPE_MARIADB,
        ]);
    }

    public function mysql(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Database::TYPE_MYSQL,
        ]);
    }

    public function postgresql(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Database::TYPE_POSTGRESQL,
        ]);
    }
}
