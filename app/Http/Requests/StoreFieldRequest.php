<?php

namespace App\Http\Requests;

use App\Enums\FieldType;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFieldRequest extends FormRequest
{
    /** Only drafts can be edited. */
    public function authorize(): bool
    {
        return $this->document()->isDraft();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $document = $this->document();

        return [
            'signer_id' => ['required', 'string', Rule::exists('signers', 'uuid')->where('document_id', $document->id)],
            'type' => ['nullable', Rule::enum(FieldType::class)],
            'page' => ['required', 'integer', 'min:0', 'max:'.max(0, $document->pages - 1)],
            'x' => ['required', 'numeric', 'between:0,100'],
            'y' => ['required', 'numeric', 'between:0,100'],
            'width' => ['nullable', 'numeric', 'between:24,400'],
            'height' => ['nullable', 'numeric', 'between:16,200'],
        ];
    }

    private function document(): Document
    {
        /** @var Document $document */
        $document = $this->route('document');

        return $document;
    }
}
