<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExternalClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExternalClient>
 */
class ExternalClientFactory extends Factory
{
    protected $model = ExternalClient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' Project';

        return [
            'name' => $name,
            'client_slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'api_key' => ExternalClient::generateApiKey(),
            'target_url' => fake()->url(),
            'description' => fake()->sentence(),
            'allowed_models' => ['*'],
            'status' => fake()->randomElement(['online', 'offline', 'unknown']),
            'last_status_code' => 200,
            'last_latency_ms' => fake()->numberBetween(10, 150),
            'target_server' => 'nginx/1.25',
            'last_ping_at' => now(),
            'last_error_message' => null,
            'inbound_requests_count' => 0,
            'last_inbound_at' => null,
            'last_inbound_endpoint' => null,
            'is_active' => true,
        ];
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'offline',
            'last_status_code' => 503,
            'last_error_message' => 'Connection refused',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
