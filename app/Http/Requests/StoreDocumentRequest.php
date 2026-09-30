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
            'file.required' => 'Selecciona un archivo PDF.',
            'file.file' => 'No se pudo subir el archivo.',
            'file.uploaded' => 'No se pudo subir el archivo. Comprueba que no supere los 10 MB.',
            'file.mimetypes' => 'El archivo debe ser un PDF.',
            'file.max' => 'El PDF no puede superar los 10 MB.',
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
                    $validator->errors()->add('file', 'No se pudo leer el PDF. Prueba con otro archivo o guárdalo como PDF 1.4.');

                    return;
                }

                if ($pages > self::MAX_PAGES) {
                    $validator->errors()->add('file', 'El PDF no puede tener más de '.self::MAX_PAGES.' páginas.');

                    return;
                }

                $this->pages = $pages;
            },
        ];
    }
}
