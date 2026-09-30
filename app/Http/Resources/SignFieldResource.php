<?php

namespace App\Http\Resources;

use App\Models\SignField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SignField
 */
class SignFieldResource extends JsonResource
{
    /**
     * Shape of the `SignField` TypeScript type.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->type->value,
            'signerId' => (string) $this->signer_id,
            'page' => $this->page,
            'x' => $this->x,
            'y' => $this->y,
            'value' => $this->value(),
        ];
    }

    /**
     * The signed value: the URL of the signature image, or plain text.
     * Images are served through the signer's link, so the signer must be loaded.
     */
    private function value(): ?string
    {
        if ($this->value_path === null) {
            return $this->value_text;
        }

        $token = $this->relationLoaded('signer') ? $this->signer->token : null;

        return $token === null
            ? null
            : route('sign.image', ['token' => $token, 'field' => $this->id], false);
    }
}
