<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Signer;
use App\Models\SignField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_relations_and_casts_work()
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create();
        $signer = Signer::factory()->for($document)->create();
        SignField::factory()->for($document)->for($signer)->create();

        $this->assertCount(1, $user->documents);
        $this->assertSame(DocumentStatus::Borrador, $document->status);
        $this->assertCount(1, $document->signers);
        $this->assertIsFloat($document->fields->first()->x);
        $this->assertSame('pendiente', $signer->linkStatus());
    }

    public function test_refresh_status_completes_the_document_when_every_signer_signed()
    {
        $document = Document::factory()->pendiente()->create();
        Signer::factory()->for($document)->signed()->create();
        $pending = Signer::factory()->for($document)->create();

        $this->assertFalse($document->refreshStatus());

        $pending->update(['signed_at' => now()]);

        $this->assertTrue($document->refreshStatus());
        $this->assertSame(DocumentStatus::Completado, $document->fresh()->status);
        $this->assertNotNull($document->fresh()->completed_at);
    }

    public function test_deleting_a_document_removes_its_files_and_children()
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/a.pdf', 'x');
        Storage::disk('local')->put('signatures/a.png', 'x');

        $document = Document::factory()->create(['original_path' => 'documents/a.pdf']);
        $signer = Signer::factory()->for($document)->create();
        SignField::factory()->for($document)->for($signer)->create(['value_path' => 'signatures/a.png']);

        $document->delete();

        Storage::disk('local')->assertMissing('documents/a.pdf');
        Storage::disk('local')->assertMissing('signatures/a.png');
        $this->assertSame(0, Signer::count());
        $this->assertSame(0, SignField::count());
    }
}
