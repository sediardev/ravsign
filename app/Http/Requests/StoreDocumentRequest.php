<?php

namespace App\Http\Requests;

use App\Services\PdfInspector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreDocumentRequest extends FormRequest
{
    public const MAX_KILOBYTES = 10240;

    public const MAX_PAGES = 50;

    /** Page count of the uploaded PDF, available after validation. */
    public int $pages = 0;

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
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Select a PDF file.',
            'file.file' => 'Could not upload the file.',
            'file.uploaded' => 'Could not upload the file. Make sure it is under 10 MB.',
            'file.mimetypes' => 'The file must be a PDF.',
            'file.max' => 'The PDF can\'t be larger than 10 MB.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $file = $this->file('file');

                if ($validator->errors()->isNotEmpty() || ! $file instanceof UploadedFile) {
                    return;
                }

                $pages = app(PdfInspector::class)->pageCount($file->getRealPath());

                if ($pages === null || $pages < 1) {
                    $validator->errors()->add('file', 'Could not read the PDF. Try another file or save it as PDF 1.4.');

                    return;
                }

                if ($pages > self::MAX_PAGES) {
                    $validator->errors()->add('file', 'The PDF can\'t have more than '.self::MAX_PAGES.' pages.');

                    return;
                }

                $this->pages = $pages;
            },
        ];
    }
}
