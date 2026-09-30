<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\FieldType;
use App\Models\Document;
use App\Models\User;
use App\Services\SignedPdfBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentSeeder extends Seeder
{
    /**
     * Seed the 4 example documents of the design for the demo user.
     */
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();

        if ($user === null || $user->documents()->exists()) {
            return;
        }

        $sample = public_path('samples/acuerdo-servicios.pdf');

        if (! File::exists($sample)) {
            $this->command?->warn('Run "php artisan app:make-sample-pdf" first: the sample PDF is missing.');

            return;
        }

        $contents = File::get($sample);

        foreach ($this->documents() as $data) {
            $date = Carbon::parse($data['date']);
            $status = $data['status'];
            $path = 'documents/'.Str::uuid().'.pdf';

            Storage::disk('local')->put($path, $contents);

            $document = Document::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'original_path' => $path,
                'pages' => 2,
                'status' => $status,
                'sent_at' => $status === DocumentStatus::Borrador ? null : $date,
                'completed_at' => $status === DocumentStatus::Completado ? $date : null,
            ]);
            $document->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

            $signers = [];
            foreach ($data['signers'] as $position => [$name, $siglas, $email, $color]) {
                $signers[] = $document->signers()->create([
                    'name' => $name,
                    'siglas' => $siglas,
                    'email' => $email,
                    'color' => $color,
                    'position' => $position,
                    'token' => $status === DocumentStatus::Borrador ? null : Str::random(64),
                    'signed_at' => $status === DocumentStatus::Completado ? $date : null,
                ]);
            }

            foreach ($data['fields'] as [$signerIndex, $page, $x, $y, $value]) {
                $document->fields()->create([
                    'signer_id' => $signers[$signerIndex]->id,
                    'type' => FieldType::Firma,
                    'page' => $page,
                    'x' => $x,
                    'y' => $y,
                    'value_text' => $value,
                ]);
            }

            // A completed example has its signed PDF too, so it can be downloaded.
            if ($status === DocumentStatus::Completado) {
                app(SignedPdfBuilder::class)->build($document);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documents(): array
    {
        return [
            [
                'name' => 'Acuerdo de servicios — Nómada Studio.pdf',
                'status' => DocumentStatus::Borrador,
                'date' => '2026-09-29',
                'signers' => [
                    ['Carlos Ruiz', 'CR', 'carlos@nomada.studio', '#1792bb'],
                    ['Lucía Fernández', 'LF', 'lucia@ravsign.com', '#7a4fc9'],
                ],
                'fields' => [
                    [0, 1, 11.1, 63.6, null],
                    [1, 1, 56.9, 63.6, null],
                ],
            ],
            [
                'name' => 'Contrato de arrendamiento Local 4B.pdf',
                'status' => DocumentStatus::Pendiente,
                'date' => '2026-09-27',
                'signers' => [
                    ['Marta Gil', 'MG', 'marta.gil@correo.com', '#1792bb'],
                ],
                'fields' => [
                    [0, 1, 11.1, 63.6, null],
                ],
            ],
            [
                'name' => 'NDA Proveedores 2026.pdf',
                'status' => DocumentStatus::Completado,
                'date' => '2026-09-21',
                'signers' => [
                    ['Jorge Peña', 'JP', 'jorge@logistica.es', '#1792bb'],
                ],
                'fields' => [
                    [0, 1, 11.1, 63.6, 'Jorge Peña'],
                ],
            ],
            [
                'name' => 'Propuesta comercial Q4.pdf',
                'status' => DocumentStatus::Borrador,
                'date' => '2026-09-18',
                'signers' => [
                    ['Ana Morales', 'AM', 'ana@ravsign.com', '#1792bb'],
                ],
                'fields' => [],
            ],
        ];
    }
}
