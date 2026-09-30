<?php

namespace Database\Factories;

use App\Enums\FieldType;
use App\Models\Document;
use App\Models\Signer;
use App\Models\SignField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SignField>
 */
class SignFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$width, $height] = FieldType::Firma->size();

        return [
            'document_id' => Document::factory(),
            'signer_id' => Signer::factory(),
            'type' => FieldType::Firma,
            'page' => 0,
            'x' => 11.1,
            'y' => 63.6,
            'width' => $width,
            'height' => $height,
        ];
    }
}
