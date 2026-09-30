<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Models\Signer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreSignerRequest extends FormRequest
{
    /** Only drafts can be edited. */
    public function authorize(): bool
    {
        return $this->document()->isDraft();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'siglas' => ['nullable', 'string', 'max:4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the signer\'s name.',
            'email.required' => 'Enter the signer\'s email.',
            'email.email' => 'Enter a valid email.',
            'siglas.max' => 'Initials can have up to 4 characters.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->document()->signers()->count() >= count(Signer::COLORS)) {
                    $validator->errors()->add('name', 'A document allows up to '.count(Signer::COLORS).' signers.');
                }
            },
        ];
    }

    /** Initials for the signer badge: the given siglas or the first letters of the name. */
    public function siglas(): string
    {
        $given = trim((string) $this->input('siglas'));

        if ($given !== '') {
            return Str::upper(Str::substr($given, 0, 4));
        }

        $initials = collect(preg_split('/\s+/', trim((string) $this->input('name'))) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    private function document(): Document
    {
        /** @var Document $document */
        $document = $this->route('document');

        return $document;
    }
}
