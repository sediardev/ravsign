<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Signer;
use App\Models\SignField;
use App\Models\User;
use App\Notifications\SignerInviteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function draft(): Document
    {
        return Document::factory()->for($this->user)->create();
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('documents.editor', $this->draft()))->assertRedirect(route('login'));
    }

    public function test_the_owner_sees_the_editor_with_signers_and_fields()
    {
        $document = $this->draft();
        $signer = Signer::factory()->for($document)->create(['name' => 'Carlos Ruiz']);
        SignField::factory()->for($document)->for($signer)->create(['page' => 1]);

        $this->actingAs($this->user)
            ->get(route('documents.editor', $document))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/Editor')
                ->where('document.id', $document->uuid)
                ->where('document.status', 'borrador')
                ->where('document.signers.0.name', 'Carlos Ruiz')
                ->where('document.fields.0.page', 1)
                ->where('pdfUrl', "/documents/{$document->uuid}/file"));
    }

    public function test_someone_elses_or_a_missing_document_is_not_found()
    {
        $foreign = Document::factory()->create();

        $this->actingAs($this->user)->get(route('documents.editor', $foreign))->assertNotFound();
        $this->actingAs($this->user)->get('/documents/9999/editor')->assertNotFound();
    }

    public function test_a_signer_can_be_added_with_default_siglas_and_the_first_color()
    {
        $document = $this->draft();

        $this->actingAs($this->user)
            ->postJson(route('signers.store', $document), ['name' => 'lucía fernández', 'email' => 'lucia@ravsign.com'])
            ->assertCreated()
            ->assertJsonPath('data.siglas', 'LF')
            ->assertJsonPath('data.color', '#1792bb')
            ->assertJsonPath('data.email', 'lucia@ravsign.com');

        $this->assertSame(1, $document->signers()->count());
    }

    public function test_siglas_can_be_given_and_are_uppercased_and_trimmed_to_four()
    {
        $document = $this->draft();

        $this->actingAs($this->user)
            ->postJson(route('signers.store', $document), ['name' => 'Ana', 'email' => 'ana@ravsign.com', 'siglas' => 'abcd'])
            ->assertCreated()
            ->assertJsonPath('data.siglas', 'ABCD');

        $this->actingAs($this->user)
            ->postJson(route('signers.store', $document), ['name' => 'Ana', 'email' => 'ana@ravsign.com', 'siglas' => 'abcde'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('siglas');
    }

    public function test_signers_get_consecutive_colors_and_a_freed_color_is_reused()
    {
        $document = $this->draft();
        $this->actingAs($this->user);

        $ids = [];
        foreach (['Uno', 'Dos', 'Tres'] as $name) {
            $ids[] = $this->postJson(route('signers.store', $document), ['name' => $name, 'email' => "{$name}@x.com"])
                ->json('data');
        }

        $this->assertSame(['#1792bb', '#7a4fc9', '#d27a1f'], array_column($ids, 'color'));

        $this->deleteJson(route('signers.destroy', [$document, $ids[1]['id']]))->assertNoContent();

        $this->postJson(route('signers.store', $document), ['name' => 'Cuatro', 'email' => 'c@x.com'])
            ->assertJsonPath('data.color', '#7a4fc9');
    }

    public function test_a_document_admits_at_most_four_signers()
    {
        $document = $this->draft();
        foreach (range(0, 3) as $i) {
            Signer::factory()->for($document)->create(['position' => $i]);
        }

        $this->actingAs($this->user)
            ->postJson(route('signers.store', $document), ['name' => 'Quinto', 'email' => 'q@x.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_signer_data_is_validated()
    {
        $this->actingAs($this->user)
            ->postJson(route('signers.store', $this->draft()), ['name' => '', 'email' => 'no-es-correo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_removing_a_signer_removes_their_fields()
    {
        $document = $this->draft();
        $signer = Signer::factory()->for($document)->create();
        $other = Signer::factory()->for($document)->create();
        SignField::factory()->for($document)->for($signer)->create();
        SignField::factory()->for($document)->for($other)->create();

        $this->actingAs($this->user)
            ->deleteJson(route('signers.destroy', [$document, $signer]))
            ->assertNoContent();

        $this->assertSame(1, $document->fields()->count());
        $this->assertSame($other->id, $document->fields()->first()->signer_id);
    }

    public function test_a_signer_of_another_document_cannot_be_removed_through_this_one()
    {
        $document = $this->draft();
        $foreign = Signer::factory()->create();

        $this->actingAs($this->user)
            ->deleteJson(route('signers.destroy', [$document, $foreign]))
            ->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_resending_the_invite_notifies_a_pending_signer()
    {
        Notification::fake();

        $document = Document::factory()->for($this->user)->pendiente()->create();
        $signer = Signer::factory()->for($document)->withToken()->create();

        $this->actingAs($this->user)
            ->postJson(route('signers.resend-invite', [$document, $signer]))
            ->assertNoContent();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            SignerInviteNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $signer->email,
        );
    }

    public function test_a_signer_who_already_signed_cannot_be_reinvited()
    {
        $document = Document::factory()->for($this->user)->pendiente()->create();
        $signer = Signer::factory()->for($document)->withToken()->signed()->create();

        $this->actingAs($this->user)
            ->postJson(route('signers.resend-invite', [$document, $signer]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signer' => 'Este firmante ya firmó.']);
    }

    public function test_the_invite_cannot_be_resent_while_the_document_is_a_draft()
    {
        $document = $this->draft();
        $signer = Signer::factory()->for($document)->create();

        $this->actingAs($this->user)
            ->postJson(route('signers.resend-invite', [$document, $signer]))
            ->assertForbidden();
    }

    public function test_a_field_can_be_created_moved_reassigned_and_removed()
    {
        $document = $this->draft();
        $first = Signer::factory()->for($document)->create();
        $second = Signer::factory()->for($document)->create();
        $this->actingAs($this->user);

        $id = $this->postJson(route('fields.store', $document), [
            'signer_id' => $first->uuid, 'page' => 1, 'x' => 11.1, 'y' => 63.6,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'firma')
            ->assertJsonPath('data.signerId', $first->uuid)
            ->assertJsonPath('data.page', 1)
            ->json('data.id');

        $this->patchJson(route('fields.update', [$document, $id]), ['page' => 0, 'x' => 25.5, 'y' => 40])
            ->assertOk()
            ->assertJsonPath('data.page', 0)
            ->assertJsonPath('data.x', 25.5);

        $this->patchJson(route('fields.update', [$document, $id]), ['signer_id' => $second->uuid])
            ->assertOk()
            ->assertJsonPath('data.signerId', $second->uuid)
            ->assertJsonPath('data.x', 25.5);

        $this->deleteJson(route('fields.destroy', [$document, $id]))->assertNoContent();
        $this->assertSame(0, $document->fields()->count());
    }

    public function test_field_data_is_validated()
    {
        $document = $this->draft();
        $signer = Signer::factory()->for($document)->create();
        $foreignSigner = Signer::factory()->create();
        $this->actingAs($this->user);

        $valid = ['signer_id' => $signer->uuid, 'page' => 0, 'x' => 10, 'y' => 10];

        $this->postJson(route('fields.store', $document), [...$valid, 'signer_id' => $foreignSigner->uuid])
            ->assertJsonValidationErrors('signer_id');
        $this->postJson(route('fields.store', $document), [...$valid, 'page' => 2])
            ->assertJsonValidationErrors('page');
        $this->postJson(route('fields.store', $document), [...$valid, 'x' => 101])
            ->assertJsonValidationErrors('x');
        $this->postJson(route('fields.store', $document), [...$valid, 'type' => 'sello'])
            ->assertJsonValidationErrors('type');

        $this->assertSame(0, $document->fields()->count());
    }

    public function test_a_field_of_another_document_cannot_be_changed_through_this_one()
    {
        $document = $this->draft();
        $foreign = SignField::factory()->create();

        $this->actingAs($this->user);
        $this->patchJson(route('fields.update', [$document, $foreign]), ['x' => 5])->assertNotFound();
        $this->deleteJson(route('fields.destroy', [$document, $foreign]))->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_only_drafts_can_be_edited()
    {
        $this->actingAs($this->user);

        foreach ([Document::factory()->for($this->user)->pendiente()->create(), Document::factory()->for($this->user)->completado()->create()] as $document) {
            $signer = Signer::factory()->for($document)->create();
            $field = SignField::factory()->for($document)->for($signer)->create();

            $this->postJson(route('signers.store', $document), ['name' => 'Ana', 'email' => 'a@x.com'])->assertForbidden();
            $this->deleteJson(route('signers.destroy', [$document, $signer]))->assertForbidden();
            $this->postJson(route('fields.store', $document), ['signer_id' => $signer->uuid, 'page' => 0, 'x' => 1, 'y' => 1])->assertForbidden();
            $this->patchJson(route('fields.update', [$document, $field]), ['x' => 5])->assertForbidden();
            $this->deleteJson(route('fields.destroy', [$document, $field]))->assertForbidden();

            $this->assertModelExists($signer);
            $this->assertModelExists($field);
        }
    }

    public function test_someone_elses_document_cannot_be_edited()
    {
        $foreign = Document::factory()->create();

        $this->actingAs($this->user)
            ->postJson(route('signers.store', $foreign), ['name' => 'Ana', 'email' => 'a@x.com'])
            ->assertNotFound();
        $this->assertSame(0, $foreign->signers()->count());
    }

    public function test_guests_cannot_edit()
    {
        $document = $this->draft();

        $this->postJson(route('signers.store', $document), ['name' => 'Ana', 'email' => 'a@x.com'])
            ->assertUnauthorized();
        $this->postJson(route('fields.store', $document), [])->assertUnauthorized();
    }
}
