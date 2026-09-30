<?php

namespace App\Http\Resources;

use App\Models\Document;
use App\Models\Signer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    private const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /**
     * Shape of the `DocumentItem` TypeScript type.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'date' => $this->formattedDate(),
            'pages' => $this->pages,
            'signers' => SignerResource::collection($this->signers)->resolve($request),
            'fields' => SignFieldResource::collection($this->fields)->resolve($request),
            'links' => $this->signers
                ->filter(fn (Signer $signer) => $signer->token !== null)
                ->map(fn (Signer $signer) => [
                    'signerId' => (string) $signer->id,
                    'url' => url('/sign/'.$signer->token),
                    'token' => $signer->token,
                    'status' => $signer->linkStatus(),
                ])->values(),
        ];
    }

    /** Date such as "29 sep 2026", independent of the server locale. */
    private function formattedDate(): string
    {
        $date = $this->created_at;

        return $date->day.' '.self::MONTHS[$date->month - 1].' '.$date->year;
    }
}
