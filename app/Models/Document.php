<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $name
 * @property string $original_path
 * @property string|null $signed_path
 * @property int $pages
 * @property DocumentStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'original_path', 'signed_path', 'pages', 'status', 'sent_at', 'completed_at'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasUuid;

    protected static function booted(): void
    {
        static::deleting(function (Document $document) {
            $paths = [$document->original_path, $document->signed_path];

            foreach ($document->fields as $field) {
                $paths[] = $field->value_path;
            }

            Storage::disk('local')->delete(array_filter($paths));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'pages' => 'integer',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Signer, $this> */
    public function signers(): HasMany
    {
        return $this->hasMany(Signer::class)->orderBy('position');
    }

    /** @return HasMany<SignField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(SignField::class);
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Borrador;
    }

    /**
     * Move to "completado" once every signer has signed.
     * Returns true when the document has just been completed.
     */
    public function refreshStatus(): bool
    {
        if ($this->status !== DocumentStatus::Pendiente) {
            return false;
        }

        $signers = $this->signers()->get();

        if ($signers->isEmpty() || $signers->contains(fn (Signer $s) => $s->signed_at === null)) {
            return false;
        }

        $this->update([
            'status' => DocumentStatus::Completado,
            'completed_at' => now(),
        ]);

        return true;
    }
}
