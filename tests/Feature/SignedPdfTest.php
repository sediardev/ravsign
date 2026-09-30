<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Signer;
use App\Models\SignField;
use App\Models\User;
use App\Services\SignedPdfBuilder;
use FPDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

class SignedPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /** An original PDF with the given page sizes, in points: [[width, height], ...]. */
    private function originalPdf(array $sizes): string
    {
        $pdf = new FPDF('P', 'pt');
        foreach ($sizes as $size) {
            $pdf->AddPage('P', $size);
        }

        $path = 'documents/original.pdf';
        Storage::disk('local')->put($path, $pdf->Output('S'));

        return $path;
    }

    private function png(int $width = 200, int $height = 50): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imageline($image, 0, 0, $width, $height, imagecolorallocate($image, 26, 53, 96));

        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    /** A pending document with one signer per entry of $fields: [page, x, y]. */
    private function documentWithSignature(array $sizes, array $fields, ?User $user = null): Document
    {
        $document = Document::factory()->for($user ?? User::factory()->create())->pendiente()->create([
            'original_path' => $this->originalPdf($sizes),
            'pages' => count($sizes),
        ]);

        foreach ($fields as $i => [$page, $x, $y]) {
            $signer = Signer::factory()->for($document)->withToken()->create(['position' => $i]);
            $path = "signatures/s{$i}.png";
            Storage::disk('local')->put($path, $this->png());
            SignField::factory()->for($document)->for($signer)->create([
                'page' => $page, 'x' => $x, 'y' => $y, 'value_path' => $path,
            ]);
        }

        return $document;
    }

    private function pagesOf(string $bytes): array
    {
        $pdf = new Fpdi('P', 'pt');
        $count = $pdf->setSourceFile(StreamReader::createByString($bytes));

        return array_map(fn ($n) => $pdf->getTemplateSize($pdf->importPage($n)), range(1, $count));
    }

    public function test_the_signed_pdf_keeps_the_pages_and_their_sizes()
    {
        $a4 = [595.28, 841.89];
        $letter = [612, 792];
        $document = $this->documentWithSignature([$a4, $letter], [[1, 11.1, 63.6]]);

        $path = app(SignedPdfBuilder::class)->build($document);

        $this->assertSame($path, $document->fresh()->signed_path);
        $pages = $this->pagesOf(Storage::disk('local')->get($path));
        $this->assertCount(2, $pages);
        $this->assertEqualsWithDelta(595.28, $pages[0]['width'], 0.5);
        $this->assertEqualsWithDelta(841.89, $pages[0]['height'], 0.5);
        $this->assertEqualsWithDelta(612, $pages[1]['width'], 0.5);
        $this->assertEqualsWithDelta(792, $pages[1]['height'], 0.5);
    }

    public function test_every_signature_is_stamped_as_an_image()
    {
        $document = $this->documentWithSignature([[612, 792], [612, 792]], [[0, 10, 10], [1, 11.1, 63.6]]);

        $path = app(SignedPdfBuilder::class)->build($document);

        $bytes = Storage::disk('local')->get($path);
        // Each transparent PNG becomes an image plus its soft mask.
        $this->assertSame(2, substr_count($bytes, '/SMask'));
        $this->assertSame(4, substr_count($bytes, '/Subtype /Image'));
    }

    public function test_text_values_are_stamped_without_images()
    {
        $document = Document::factory()->pendiente()->create([
            'original_path' => $this->originalPdf([[612, 792]]),
            'pages' => 1,
        ]);
        $signer = Signer::factory()->for($document)->create();
        SignField::factory()->for($document)->for($signer)->create(['value_text' => 'Jorge Peña']);

        $path = app(SignedPdfBuilder::class)->build($document);

        $bytes = Storage::disk('local')->get($path);
        $this->assertSame(0, substr_count($bytes, '/Subtype /Image'));
        $this->assertCount(1, $this->pagesOf($bytes));
    }

    public function test_the_signature_is_placed_inside_its_field_keeping_proportions()
    {
        $builder = app(SignedPdfBuilder::class);

        // Letter page, signature field at 11.1 % / 63.6 %: 176 x 58 pt with 3 pt of padding.
        $box = $builder->placement(11.1, 63.6, 176, 58, 612, 792, 200, 50);

        $this->assertEqualsWithDelta(170, $box['width'], 0.001);
        $this->assertEqualsWithDelta(42.5, $box['height'], 0.001);
        $left = 0.111 * 612;
        $top = 0.636 * 792;
        $this->assertEqualsWithDelta($left + (176 - 170) / 2, $box['x'], 0.001);
        $this->assertEqualsWithDelta($top + (58 - 42.5) / 2, $box['y'], 0.001);
        $this->assertLessThanOrEqual($left + 176, $box['x'] + $box['width']);
        $this->assertLessThanOrEqual($top + 58, $box['y'] + $box['height']);
    }

    public function test_a_tall_signature_is_limited_by_the_field_height()
    {
        $box = app(SignedPdfBuilder::class)->placement(0, 0, 176, 58, 612, 792, 50, 100);

        $this->assertEqualsWithDelta(52, $box['height'], 0.001);
        $this->assertEqualsWithDelta(26, $box['width'], 0.001);
    }

    public function test_the_position_follows_the_real_page_size_not_letter()
    {
        $box = app(SignedPdfBuilder::class)->placement(50, 50, 176, 58, 595.28, 841.89, 170, 52);

        $this->assertEqualsWithDelta(595.28 / 2 + 3, $box['x'], 0.01);
        $this->assertEqualsWithDelta(841.89 / 2 + 3, $box['y'], 0.01);
    }

    public function test_building_again_replaces_the_previous_file()
    {
        $document = $this->documentWithSignature([[612, 792]], [[0, 10, 10]]);
        $builder = app(SignedPdfBuilder::class);

        $first = $builder->build($document);
        $second = $builder->build($document->fresh());

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    public function test_the_last_signer_finishing_builds_the_signed_pdf()
    {
        $document = $this->documentWithSignature([[612, 792]], [[0, 10, 10], [0, 50, 50]]);
        [$first, $second] = $document->signers()->get()->all();

        $this->postJson(route('sign.finish', $first->token))->assertOk();
        $this->assertNull($document->fresh()->signed_path);

        $this->postJson(route('sign.finish', $second->token))->assertOk()->assertJson(['completed' => true]);

        $document->refresh();
        $this->assertSame(DocumentStatus::Completado, $document->status);
        Storage::disk('local')->assertExists($document->signed_path);
    }

    public function test_a_failure_building_the_pdf_does_not_undo_the_signature()
    {
        $document = $this->documentWithSignature([[612, 792]], [[0, 10, 10]]);
        Storage::disk('local')->delete($document->original_path);
        $signer = $document->signers()->first();

        $this->postJson(route('sign.finish', $signer->token))->assertOk()->assertJson(['completed' => true]);

        $document->refresh();
        $this->assertSame(DocumentStatus::Completado, $document->status);
        $this->assertNotNull($signer->fresh()->signed_at);
        $this->assertNull($document->signed_path);
    }

    public function test_a_jpg_signature_is_converted_to_png_and_stamped_in_the_final_pdf()
    {
        $document = Document::factory()->pendiente()->create([
            'original_path' => $this->originalPdf([[612, 792]]),
            'pages' => 1,
        ]);
        $signer = Signer::factory()->for($document)->withToken()->create();
        SignField::factory()->for($document)->for($signer)->create(['page' => 0, 'x' => 11.1, 'y' => 63.6]);

        $image = imagecreatetruecolor(200, 60);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 5, 50, 195, 10, imagecolorallocate($image, 26, 53, 96));
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();
        $dataUrl = 'data:image/jpeg;base64,'.base64_encode($jpeg);

        $field = $document->fields()->first();
        $this->postJson(route('sign.field', [$signer->token, $field]), ['image' => $dataUrl])->assertOk();

        $savedPath = $field->fresh()->value_path;
        $saved = Storage::disk('local')->get($savedPath);
        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring($saved)[2]);

        $this->postJson(route('sign.finish', $signer->token))->assertOk()->assertJson(['completed' => true]);

        $signedBytes = Storage::disk('local')->get($document->fresh()->signed_path);
        $this->assertSame(2, substr_count($signedBytes, '/Subtype /Image'));
    }

    public function test_the_owner_downloads_the_signed_pdf()
    {
        $user = User::factory()->create();
        $document = $this->documentWithSignature([[612, 792]], [[0, 10, 10]], $user);
        $document->update(['name' => 'Acuerdo — Nómada.pdf']);
        $this->postJson(route('sign.finish', $document->signers()->first()->token))->assertOk();

        $response = $this->actingAs($user)->get(route('documents.download', $document));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('firmado.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_the_download_builds_the_pdf_when_it_is_missing()
    {
        $user = User::factory()->create();
        $document = $this->documentWithSignature([[612, 792]], [[0, 10, 10]], $user);
        $document->update(['status' => DocumentStatus::Completado, 'completed_at' => now()]);

        $this->assertNull($document->signed_path);

        $this->actingAs($user)->get(route('documents.download', $document))->assertOk();

        Storage::disk('local')->assertExists($document->fresh()->signed_path);
    }

    public function test_a_document_that_is_not_completed_cannot_be_downloaded()
    {
        $user = User::factory()->create();
        $document = $this->documentWithSignature([[612, 792]], [[0, 10, 10]], $user);

        $this->actingAs($user)->get(route('documents.download', $document))->assertNotFound();
    }

    public function test_someone_elses_or_a_guests_download_is_refused()
    {
        $document = $this->documentWithSignature([[612, 792]], [[0, 10, 10]]);
        $document->update(['status' => DocumentStatus::Completado, 'completed_at' => now()]);

        $this->get(route('documents.download', $document))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->get(route('documents.download', $document))
            ->assertNotFound();
    }
}
