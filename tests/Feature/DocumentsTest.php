<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Signer;
use App\Models\SignField;
use App\Models\User;
use Database\Seeders\DocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('documents.index'))->assertRedirect(route('login'));
    }

    public function test_a_new_user_sees_an_empty_list()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/Index')
                ->has('documents', 0));
    }

    public function test_the_list_only_contains_the_users_own_documents()
    {
        $user = User::factory()->create();
        Document::factory()->for($user)->count(2)->create();
        Document::factory()->count(3)->create();

        $this->actingAs($user);

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('documents', 2));
    }

    public function test_documents_are_serialized_with_signers_fields_and_links()
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->pendiente()->create([
            'name' => 'Contrato.pdf',
            'created_at' => '2026-09-29 10:00:00',
        ]);
        $signer = Signer::factory()->for($document)->withToken()->create([
            'name' => 'Marta Gil',
            'siglas' => 'MG',
        ]);
        SignField::factory()->for($document)->for($signer)->create(['page' => 1, 'x' => 11.1, 'y' => 63.6]);

        $this->actingAs($user);

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.0.id', $document->id)
                ->where('documents.0.name', 'Contrato.pdf')
                ->where('documents.0.status', 'pendiente')
                ->where('documents.0.date', '29 sep 2026')
                ->where('documents.0.pages', 2)
                ->where('documents.0.signers.0.id', (string) $signer->id)
                ->where('documents.0.signers.0.siglas', 'MG')
                ->where('documents.0.fields.0.signerId', (string) $signer->id)
                ->where('documents.0.fields.0.page', 1)
                ->where('documents.0.fields.0.x', 11.1)
                ->where('documents.0.fields.0.value', null)
                ->where('documents.0.links.0.token', $signer->token)
                ->where('documents.0.links.0.url', url('/sign/'.$signer->token))
                ->where('documents.0.links.0.status', 'pendiente'));
    }

    public function test_draft_documents_have_no_links()
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create();
        Signer::factory()->for($document)->create();

        $this->actingAs($user);

        $this->get(route('documents.index'))
            ->assertInertia(fn (Assert $page) => $page->has('documents.0.links', 0));
    }

    public function test_the_seeded_demo_user_sees_the_four_example_documents()
    {
        Storage::fake('local');
        User::factory()->create(['email' => 'test@example.com']);
        $this->seed(DocumentSeeder::class);

        $this->actingAs(User::where('email', 'test@example.com')->first());

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents', 4)
                ->where('documents.0.name', 'Acuerdo de servicios — Nómada Studio.pdf')
                ->where('documents.0.status', 'borrador')
                ->where('documents.0.date', '29 sep 2026')
                ->where('documents.2.status', 'completado')
                ->where('documents.2.fields.0.value', 'Jorge Peña'));
    }
}
