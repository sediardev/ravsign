<?php

namespace App\Models;

use App\Enums\FieldType;
use Database\Factories\SignFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_id
 * @property int $signer_id
 * @property FieldType $type
 * @property int $page
 * @property float $x
 * @property float $y
 * @property string|null $value_path
 * @property string|null $value_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['document_id', 'signer_id', 'type', 'page', 'x', 'y', 'value_path', 'value_text'])]
class SignField extends Model
{
    /** @use HasFactory<SignFieldFactory> */
    use HasFactory;

    protected $table = 'sign_fields';

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
