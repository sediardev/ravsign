<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Signer;
use App\Models\SignField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignFieldTest extends TestCase
{
    use RefreshDatabase;

    private Document $document;

    private Signer $carlos;

    private Signer $lucia;

    private SignField $carlosField;

    private SignField $luciaField;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->document = Document::factory()->pendiente()->create();
        $this->carlos = Signer::factory()->for($this->document)->withToken()->create(['position' => 0]);
        $this->lucia = Signer::factory()->for($this->document)->withToken()->create(['position' => 1]);
        $this->carlosField = SignField::factory()->for($this->document)->for($this->carlos)->create();
        $this->luciaField = SignField::factory()->for($this->document)->for($this->lucia)->create();
    }

    /** A real 20x10 image as a data URL. */
    private function dataUrl(string $type = 'png'): string
    {
        $image = imagecreatetruecolor(20, 10);
        imagefill($image, 0, 0, imagecolorallocate($image, 26, 53, 96));

        ob_start();
        $type === 'png' ? imagepng($image) : imagejpeg($image);
        $bytes = ob_get_clean();

        return 'data:image/'.($type === 'png' ? 'png' : 'jpeg').';base64,'.base64_encode($bytes);
    }

    private function sign(?Signer $signer = null, ?SignField $field = null, ?string $image = null)
    {
        $signer ??= $this->carlos;
        $field ??= $this->carlosField;

        return $this->postJson(route('sign.field', [$signer->token, $field]), ['image' => $image ?? $this->dataUrl()]);
    }

    public function test_a_signer_can_sign_their_own_field_without_logging_in()
    {
        $response = $this->sign()->assertOk();

        $field = $this->carlosField->fresh();
        $this->assertMatchesRegularExpression('#^signatures/[0-9a-f-]{36}\.png$#', $field->value_path);
        Storage::disk('local')->assertExists($field->value_path);

        $response->assertJsonPath('data.id', (string) $field->id)
            ->assertJsonPath('data.value', "/sign/{$this->carlos->token}/fields/{$field->id}/image");
    }

    public function test_the_saved_file_is_a_png()
    {
        $this->sign();

        $bytes = Storage::disk('local')->get($this->carlosField->fresh()->value_path);
        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring($bytes)[2]);
    }

    public function test_a_jpg_is_converted_to_png()
    {
        $this->sign(image: $this->dataUrl('jpg'))->assertOk();

        $bytes = Storage::disk('local')->get($this->carlosField->fresh()->value_path);
        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring($bytes)[2]);
    }

    public function test_signing_again_replaces_the_previous_image()
    {
        $this->sign();
        $first = $this->carlosField->fresh()->value_path;

        $this->sign();
        $second = $this->carlosField->fresh()->value_path;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    public function test_a_signer_cannot_sign_someone_elses_field()
    {
        $this->sign($this->carlos, $this->luciaField)->assertForbidden();

        $this->assertNull($this->luciaField->fresh()->value_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_field_of_another_document_is_not_found()
    {
        $foreign = SignField::factory()->create();

        $this->sign($this->carlos, $foreign)->assertNotFound();
    }

    public function test_an_unknown_token_is_not_found()
    {
        $this->postJson(route('sign.field', [str_repeat('a', 64), $this->carlosField]), ['image' => $this->dataUrl()])
            ->assertNotFound();
    }

    public function test_nothing_is_saved_when_the_image_is_missing_or_not_an_image()
    {
        $this->postJson(route('sign.field', [$this->carlos->token, $this->carlosField]), [])
            ->assertJsonValidationErrors('image');

        foreach ([
            'not a data url',
            'data:text/html;base64,'.base64_encode('<script>alert(1)</script>'),
            'data:image/png;base64,'.base64_encode('esto no es un png'),
            'data:image/gif;base64,'.base64_encode('GIF89a'),
        ] as $bad) {
            $this->sign(image: $bad)->assertJsonValidationErrors('image');
        }

        $this->assertNull($this->carlosField->fresh()->value_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_an_image_larger_than_1_mb_is_rejected()
    {
        // Random-looking noise does not compress, so the PNG stays large.
        $image = imagecreatetruecolor(700, 700);
        for ($x = 0; $x < 700; $x++) {
            for ($y = 0; $y < 700; $y++) {
                imagesetpixel($image, $x, $y, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            }
        }
        ob_start();
        imagepng($image, null, 0);
        $bytes = ob_get_clean();
        $this->assertGreaterThan(1024 * 1024, strlen($bytes));

        $this->sign(image: 'data:image/png;base64,'.base64_encode($bytes))->assertJsonValidationErrors('image');
        $this->assertNull($this->carlosField->fresh()->value_path);
    }

    public function test_a_wide_image_is_scaled_down()
    {
        $image = imagecreatetruecolor(1600, 100);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();

        $this->sign(image: 'data:image/png;base64,'.base64_encode($bytes))->assertOk();

        $saved = Storage::disk('local')->get($this->carlosField->fresh()->value_path);
        $this->assertSame(1000, getimagesizefromstring($saved)[0]);
    }

    public function test_a_document_that_is_not_pending_cannot_be_signed()
    {
        $this->document->update(['status' => 'completado']);

        $this->sign()->assertForbidden();
        $this->assertNull($this->carlosField->fresh()->value_path);
    }

    public function test_a_signer_who_already_finished_cannot_sign_again()
    {
        $this->carlos->update(['signed_at' => now()]);

        $this->sign()->assertForbidden();
        $this->assertNull($this->carlosField->fresh()->value_path);
    }
}
