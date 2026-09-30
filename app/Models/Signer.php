<?php

namespace App\Models;

use Database\Factories\SignerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_id
 * @property string $name
 * @property string $email
 * @property string $siglas
 * @property string $color
 * @property int $position
 * @property string|null $token
 * @property Carbon|null $signed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['document_id', 'name', 'email', 'siglas', 'color', 'position', 'token', 'signed_at'])]
class Signer extends Model
{
    /** @use HasFactory<SignerFactory> */
    use HasFactory;

    public const COLORS = ['#1792bb', '#7a4fc9', '#d27a1f', '#2f9e6b'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'signed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return HasMany<SignField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(SignField::class);
    }

    /** Link status shown in the links modal. */
    public function linkStatus(): string
    {
        return $this->signed_at !== null ? 'firmado' : 'pendiente';
    }
}
