<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFieldRequest extends FormRequest
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
            'signer_id' => ['sometimes', 'integer', Rule::exists('signers', 'id')->where('document_id', $document->id)],
            'page' => ['sometimes', 'integer', 'min:0', 'max:'.max(0, $document->pages - 1)],
            'x' => ['sometimes', 'numeric', 'between:0,100'],
            'y' => ['sometimes', 'numeric', 'between:0,100'],
        ];
    }

    private function document(): Document
    {
        /** @var Document $document */
        $document = $this->route('document');

        return $document;
    }
}
