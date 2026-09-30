<?php

namespace App\Http\Requests;

use App\Services\SignatureImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SignFieldRequest extends FormRequest
{
    /** PNG bytes of the signature, available after validation. */
    public string $png = '';

    /**
     * Who may sign is decided by the token in the link, in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // A base64 image of 1 MB is about 1.4 million characters.
            'image' => ['required', 'string', 'max:1500000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Draw or upload your signature first.',
            'image.max' => 'The signature image is too large.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $png = app(SignatureImage::class)->fromDataUrl((string) $this->input('image'));

                if ($png === null) {
                    $validator->errors()->add('image', 'The signature must be a PNG or JPG image up to 1 MB.');

                    return;
                }

                $this->png = $png;
            },
        ];
    }
}
