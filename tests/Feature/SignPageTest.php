<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Signer;
use App\Models\SignField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SignPageTest extends TestCase
{
    use RefreshDatabase;

    private Document $document;

    private Signer $carlos;

    private Signer $lucia;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::disk('local')->put('documents/a.pdf', '%PDF-1.4 contenido');

        $this->document = Document::factory()->pendiente()->create([
            'name' => 'Acuerdo.pdf',
            'original_path' => 'documents/a.pdf',
        ]);
        $this->carlos = Signer::factory()->for($this->document)->withToken()->create([
            'name' => 'Carlos Ruiz', 'siglas' => 'CR', 'email' => 'carlos@x.com', 'position' => 0,
        ]);
        $this->lucia = Signer::factory()->for($this->document)->withToken()->create([
            'name' => 'Lucía Fernández', 'siglas' => 'LF', 'email' => 'lucia@x.com', 'color' => '#7a4fc9', 'position' => 1,
        ]);
    }

    public function test_a_signer_sees_their_screen_without_logging_in()
    {
        SignField::factory()->for($this->document)->for($this->carlos)->create(['page' => 1]);
        SignField::factory()->for($this->document)->for($this->lucia)->create(['page' => 1, 'x' => 56.9]);

        $this->get(route('sign.show', $this->carlos->token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sign/Show')
                ->where('document.name', 'Acuerdo.pdf')
                ->where('document.status', 'pendiente')
                ->has('document.signers', 2)
                ->has('document.fields', 2)
                ->where('signer.id', $this->carlos->uuid)
                ->where('signer.name', 'Carlos Ruiz')
                ->where('signer.email', 'carlos@x.com')
                ->where('signer.link', url('/sign/'.$this->carlos->token))
                ->where('pdfUrl', "/sign/{$this->carlos->token}/file"));
    }

    public function test_the_screen_does_not_expose_other_signers_emails_or_links()
    {
        SignField::factory()->for($this->document)->for($this->lucia)->create();

        $response = $this->get(route('sign.show', $this->carlos->token))->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString('lucia@x.com', $body);
        $this->assertStringNotContainsString($this->lucia->token, $body);
    }

    public function test_an_unknown_token_is_not_found()
    {
        $this->get('/sign/'.str_repeat('a', 64))->assertNotFound();
        $this->get('/sign/'.str_repeat('a', 64).'/file')->assertNotFound();
    }

    public function test_a_draft_signer_without_token_cannot_be_reached()
    {
        Signer::factory()->for(Document::factory()->create())->create();

        $this->get('/sign/0')->assertNotFound();
    }

    public function test_a_signer_can_read_the_pdf()
    {
        $response = $this->get(route('sign.file', $this->carlos->token));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_a_signature_image_is_served_to_signers_of_the_same_document()
    {
        Storage::disk('local')->put('signatures/s.png', 'png-bytes');
        $field = SignField::factory()->for($this->document)->for($this->lucia)->create(['value_path' => 'signatures/s.png']);

        $response = $this->get(route('sign.image', [$this->carlos->token, $field]));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
    }

    public function test_a_field_without_image_or_from_another_document_has_no_image()
    {
        Storage::disk('local')->put('signatures/s.png', 'png-bytes');
        $empty = SignField::factory()->for($this->document)->for($this->lucia)->create();
        $foreign = SignField::factory()->create(['value_path' => 'signatures/s.png']);

        $this->get(route('sign.image', [$this->carlos->token, $empty]))->assertNotFound();
        $this->get(route('sign.image', [$this->carlos->token, $foreign]))->assertNotFound();
    }

    public function test_signed_fields_are_shown_with_the_url_of_their_image_or_their_text()
    {
        Storage::disk('local')->put('signatures/s.png', 'png-bytes');
        $image = SignField::factory()->for($this->document)->for($this->lucia)->create(['value_path' => 'signatures/s.png']);
        SignField::factory()->for($this->document)->for($this->carlos)->create(['value_text' => 'Carlos Ruiz']);

        $this->get(route('sign.show', $this->carlos->token))
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.fields.0.value', "/sign/{$this->lucia->token}/fields/{$image->uuid}/image")
                ->where('document.fields.1.value', 'Carlos Ruiz'));
    }
}
