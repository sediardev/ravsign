<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Signer;
use App\Models\SignField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SignFinishTest extends TestCase
{
    use RefreshDatabase;

    private Document $document;

    private Signer $carlos;

    private Signer $lucia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->document = Document::factory()->pendiente()->create();
        $this->carlos = Signer::factory()->for($this->document)->withToken()->create(['position' => 0]);
        $this->lucia = Signer::factory()->for($this->document)->withToken()->create(['position' => 1]);
    }

    private function field(Signer $signer, bool $signed = true): SignField
    {
        return SignField::factory()->for($this->document)->for($signer)->create(
            $signed ? ['value_path' => 'signatures/x.png'] : [],
        );
    }

    private function finish(Signer $signer)
    {
        return $this->postJson(route('sign.finish', $signer->token));
    }

    public function test_a_signer_with_every_field_signed_can_finish_and_the_document_stays_pending()
    {
        $this->field($this->carlos);
        $this->field($this->carlos);
        $this->field($this->lucia, signed: false);

        $this->finish($this->carlos)->assertOk()->assertJson(['completed' => false]);

        $this->assertNotNull($this->carlos->fresh()->signed_at);
        $this->assertNull($this->lucia->fresh()->signed_at);
        $this->assertSame(DocumentStatus::Pendiente, $this->document->fresh()->status);
        $this->assertNull($this->document->fresh()->completed_at);
    }

    public function test_the_last_signer_completes_the_document()
    {
        $this->field($this->carlos);
        $this->field($this->lucia);

        $this->finish($this->carlos)->assertOk()->assertJson(['completed' => false]);
        $this->finish($this->lucia)->assertOk()->assertJson(['completed' => true]);

        $document = $this->document->fresh();
        $this->assertSame(DocumentStatus::Completado, $document->status);
        $this->assertNotNull($document->completed_at);
    }

    public function test_a_field_with_text_counts_as_signed()
    {
        SignField::factory()->for($this->document)->for($this->carlos)->create(['value_text' => 'Carlos Ruiz']);

        $this->finish($this->carlos)->assertOk();
    }

    public function test_empty_fields_block_finishing()
    {
        $this->field($this->carlos);
        $this->field($this->carlos, signed: false);

        $this->finish($this->carlos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fields' => 'Completa todos tus campos antes de finalizar.']);

        $this->assertNull($this->carlos->fresh()->signed_at);
        $this->assertSame(DocumentStatus::Pendiente, $this->document->fresh()->status);
    }

    public function test_someone_elses_empty_fields_do_not_block_finishing()
    {
        $this->field($this->carlos);
        $this->field($this->lucia, signed: false);

        $this->finish($this->carlos)->assertOk();
    }

    public function test_finishing_twice_is_refused()
    {
        $this->field($this->carlos);
        $this->field($this->lucia, signed: false);

        $this->finish($this->carlos)->assertOk();
        $signedAt = $this->carlos->fresh()->signed_at;

        $this->finish($this->carlos)->assertForbidden();
        $this->assertEquals($signedAt, $this->carlos->fresh()->signed_at);
    }

    public function test_a_document_that_is_not_pending_cannot_be_finished()
    {
        $this->field($this->carlos);
        $this->document->update(['status' => DocumentStatus::Completado]);

        $this->finish($this->carlos)->assertForbidden();
        $this->assertNull($this->carlos->fresh()->signed_at);
    }

    public function test_an_unknown_token_is_not_found()
    {
        $this->postJson(route('sign.finish', str_repeat('a', 64)))->assertNotFound();
    }

    public function test_finishing_flashes_the_document_to_open_and_a_toast()
    {
        $this->field($this->carlos);
        $this->field($this->lucia, signed: false);

        $this->finish($this->carlos)
            ->assertSessionHas('open_links', $this->document->uuid)
            ->assertSessionHas('inertia.flash_data.toast.type', 'success')
            ->assertSessionHas('inertia.flash_data.toast.message', 'Tu firma quedó registrada. Falta que firmen los demás.');
    }

    public function test_after_finishing_the_owner_sees_the_links_open_but_no_links_generated_notice()
    {
        $this->field($this->carlos);
        $this->field($this->lucia, signed: false);

        $this->finish($this->carlos)->assertOk();

        $this->actingAs($this->document->user)
            ->get(route('documents.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('openLinks', $this->document->uuid)
                ->where('linksGenerated', false)
                ->where('documents.0.links.0.status', 'firmado'));
    }

    public function test_the_last_signer_gets_the_completed_message()
    {
        $this->lucia->update(['signed_at' => now()]);
        $this->field($this->carlos);

        $this->finish($this->carlos)
            ->assertOk()
            ->assertSessionHas('inertia.flash_data.toast.message', 'Documento completado por todos los firmantes.');
    }
}
