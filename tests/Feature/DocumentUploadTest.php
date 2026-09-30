<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use FPDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function samplePdf(string $name = 'Contrato.pdf'): UploadedFile
    {
        return new UploadedFile(public_path('samples/acuerdo-servicios.pdf'), $name, 'application/pdf', null, true);
    }

    public function test_guests_cannot_upload()
    {
        $this->post(route('documents.store'), ['file' => $this->samplePdf()])
            ->assertRedirect(route('login'));

        $this->assertSame(0, Document::count());
    }

    public function test_a_valid_pdf_creates_a_draft_and_opens_the_editor()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('documents.store'), ['file' => $this->samplePdf()]);

        $document = Document::firstOrFail();
        $response->assertRedirect("/documents/{$document->id}/editor");

        $this->assertSame($user->id, $document->user_id);
        $this->assertSame('Contrato.pdf', $document->name);
        $this->assertSame(2, $document->pages);
        $this->assertSame(DocumentStatus::Borrador, $document->status);
        $this->assertMatchesRegularExpression('#^documents/[0-9a-f-]{36}\.pdf$#', $document->original_path);
        Storage::disk('local')->assertExists($document->original_path);
    }

    public function test_the_file_is_required()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('documents.store'), [])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_a_file_that_is_not_a_pdf_is_rejected()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('documents.store'), ['file' => UploadedFile::fake()->create('notas.txt', 10, 'text/plain')])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_pdf_larger_than_10_mb_is_rejected()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('documents.store'), ['file' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_a_corrupt_pdf_is_rejected()
    {
        $file = UploadedFile::fake()->createWithContent('roto.pdf', 'esto no es un pdf');

        $this->actingAs(User::factory()->create())
            ->post(route('documents.store'), ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_a_pdf_with_more_than_50_pages_is_rejected()
    {
        $pdf = new FPDF;
        for ($i = 0; $i < 51; $i++) {
            $pdf->AddPage();
        }
        $file = UploadedFile::fake()->createWithContent('largo.pdf', $pdf->Output('S'));

        $this->actingAs(User::factory()->create())
            ->post(route('documents.store'), ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_the_owner_can_read_the_original_pdf()
    {
        $user = User::factory()->create();
        Storage::disk('local')->put('documents/a.pdf', '%PDF-1.4 contenido');
        $document = Document::factory()->for($user)->create(['original_path' => 'documents/a.pdf', 'name' => 'Nómada.pdf']);

        $response = $this->actingAs($user)->get(route('documents.file', $document));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_the_pdf_of_someone_elses_document_is_not_found()
    {
        Storage::disk('local')->put('documents/a.pdf', '%PDF-1.4 contenido');
        $document = Document::factory()->create(['original_path' => 'documents/a.pdf']);

        $this->actingAs(User::factory()->create())
            ->get(route('documents.file', $document))
            ->assertNotFound();
    }

    public function test_guests_cannot_read_a_pdf()
    {
        $document = Document::factory()->create();

        $this->get(route('documents.file', $document))->assertRedirect(route('login'));
    }
}
