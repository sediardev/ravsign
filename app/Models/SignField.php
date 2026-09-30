<?php

namespace App\Models;

use App\Enums\FieldType;
use App\Models\Concerns\HasUuid;
use Database\Factories\SignFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $document_id
 * @property int $signer_id
 * @property FieldType $type
 * @property int $page
 * @property float $x
 * @property float $y
 * @property float $width
 * @property float $height
 * @property string|null $value_path
 * @property string|null $value_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['document_id', 'signer_id', 'type', 'page', 'x', 'y', 'width', 'height', 'value_path', 'value_text'])]
class SignField extends Model
{
    /** @use HasFactory<SignFieldFactory> */
    use HasFactory, HasUuid;

    protected $table = 'sign_fields';

    protected static function booted(): void
    {
        static::creating(function (SignField $field) {
            $attributes = $field->getAttributes();

            if (! isset($attributes['width']) || ! isset($attributes['height'])) {
                [$width, $height] = $field->type->size();
                $field->width ??= $width;
                $field->height ??= $height;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'page' => 'integer',
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'height' => 'float',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<Signer, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(Signer::class);
    }

    public function isSigned(): bool
    {
        return $this->value_path !== null || $this->value_text !== null;
    }
}
