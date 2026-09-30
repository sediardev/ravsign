<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a `uuid` column as its public identity: routes, URLs and API
 * responses reference the uuid, never the auto-increment `id`.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * Route model binding (and `route()`/`url()` for this model) uses the uuid.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
