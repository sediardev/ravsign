<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true).'.pdf',
            'original_path' => 'documents/'.Str::uuid().'.pdf',
            'pages' => 2,
            'status' => DocumentStatus::Borrador,
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn () => [
            'status' => DocumentStatus::Pendiente,
            'sent_at' => now(),
        ]);
    }

    public function completado(): static
    {
        return $this->state(fn () => [
            'status' => DocumentStatus::Completado,
            'sent_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }
}
