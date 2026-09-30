<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Signer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Signer>
 */
class SignerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'document_id' => Document::factory(),
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'siglas' => Str::upper(Str::substr($name, 0, 2)),
            'color' => Signer::COLORS[0],
            'position' => 0,
        ];
    }

    public function withToken(): static
    {
        return $this->state(fn () => ['token' => Str::random(64)]);
    }

    public function signed(): static
    {
        return $this->state(fn () => ['signed_at' => now()]);
    }
}
