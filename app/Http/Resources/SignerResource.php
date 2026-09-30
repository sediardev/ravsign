<?php

namespace App\Http\Resources;

use App\Models\Signer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Signer
 */
class SignerResource extends JsonResource
{
    /**
     * Shape of the `Signer` TypeScript type.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'siglas' => $this->siglas,
            'email' => $this->email,
            'color' => $this->color,
        ];
    }
}
