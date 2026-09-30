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
            'id' => $this->uuid,
            'type' => $this->type->value,
            'signerId' => $this->signer->uuid,
            'page' => $this->page,
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
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

        $token = $this->signer->token;

        return $token === null
            ? null
            : route('sign.image', ['token' => $token, 'field' => $this->uuid], false);
    }
}
