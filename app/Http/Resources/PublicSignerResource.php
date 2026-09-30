<?php

namespace App\Http\Resources;

use App\Models\Signer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A signer as another signer may see them: no email and no link.
 *
 * @mixin Signer
 */
class PublicSignerResource extends JsonResource
{
    /**
     * Shape of the `PublicSigner` TypeScript type.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'siglas' => $this->siglas,
            'color' => $this->color,
            'signed' => $this->signed_at !== null,
        ];
    }
}
