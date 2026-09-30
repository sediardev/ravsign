<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Signer;
use App\Models\SignField;
use App\Models\User;
use App\Notifications\SignerInviteNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentSendTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** A draft with two signers, each with one field. */
    private function readyDraft(): Document
    {
        $document = Document::factory()->for($this->user)->create();

        foreach ([0, 1] as $position) {
            $signer = Signer::factory()->for($document)->create(['position' => $position]);
            SignField::factory()->for($document)->for($signer)->create(['page' => 1]);
        }

        return $document;
    }

    public function test_sending_moves_the_document_to_pending_and_creates_the_links()
    {
        $document = $this->readyDraft();

        $response = $this->actingAs($this->user)->postJson(route('documents.send', $document));

        $response->assertOk()
            ->assertJsonPath('data.status', 'pendiente')
            ->assertJsonCount(2, 'data.links');

        $document->refresh();
        $this->assertSame(DocumentStatus::Pendiente, $document->status);
        $this->assertNotNull($document->sent_at);

        $tokens = $document->signers()->pluck('token');
        $this->assertCount(2, $tokens->unique());
        $tokens->each(fn ($token) => $this->assertSame(36, strlen($token)));

        $link = $response->json('data.links.0');
        $this->assertSame(url('/sign/'.$link['token']), $link['url']);
        $this->assertSame('pendiente', $link['status']);
    }

    public function test_sending_notifies_each_signer_by_email()
    {
        Notification::fake();

        $document = $this->readyDraft();
        $emails = $document->signers()->pluck('email')->all();

        $this->actingAs($this->user)->postJson(route('documents.send', $document))->assertOk();

        foreach ($emails as $email) {
            Notification::assertSentTo(
                new AnonymousNotifiable,
                SignerInviteNotification::class,
                fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $email,
            );
        }
    }

    public function test_sending_still_succeeds_even_if_a_signers_email_fails()
    {
        $document = $this->readyDraft();

        $this->app->bind(Dispatcher::class, fn () => new class implements Dispatcher
        {
            public function send($notifiables, $notification)
            {
                throw new \RuntimeException('Mailgun no responde.');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null) {}
        });

        $response = $this->actingAs($this->user)->postJson(route('documents.send', $document));

        $response->assertOk()->assertJsonPath('data.status', 'pendiente');
        $this->assertSame(DocumentStatus::Pendiente, $document->fresh()->status);
    }

    public function test_a_document_without_signers_cannot_be_sent()
    {
        $document = Document::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->postJson(route('documents.send', $document))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document' => 'Agrega un firmante primero.']);

        $this->assertSame(DocumentStatus::Borrador, $document->fresh()->status);
    }

    public function test_a_document_without_fields_cannot_be_sent()
    {
        $document = Document::factory()->for($this->user)->create();
        Signer::factory()->for($document)->create();

        $this->actingAs($this->user)
            ->postJson(route('documents.send', $document))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document' => 'Agrega al menos un campo al documento.']);

        $this->assertSame(DocumentStatus::Borrador, $document->fresh()->status);
        $this->assertNull($document->signers()->first()->token);
    }

    public function test_every_signer_needs_at_least_one_field()
    {
        $document = $this->readyDraft();
        Signer::factory()->for($document)->create(['position' => 2]);

        $this->actingAs($this->user)
            ->postJson(route('documents.send', $document))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document' => 'Cada firmante necesita al menos un campo.']);

        $this->assertSame(DocumentStatus::Borrador, $document->fresh()->status);
        $this->assertSame(0, $document->signers()->whereNotNull('token')->count());
    }

    public function test_a_document_that_was_already_sent_cannot_be_sent_again()
    {
        $document = Document::factory()->for($this->user)->pendiente()->create();
        $signer = Signer::factory()->for($document)->withToken()->create();
        SignField::factory()->for($document)->for($signer)->create();

        $this->actingAs($this->user)
            ->postJson(route('documents.send', $document))
            ->assertForbidden();

        $this->assertSame($signer->token, $signer->fresh()->token);
    }

    public function test_someone_elses_document_cannot_be_sent()
    {
        $foreign = Document::factory()->create();

        $this->actingAs($this->user)
            ->postJson(route('documents.send', $foreign))
            ->assertNotFound();
    }

    public function test_guests_cannot_send()
    {
        $this->postJson(route('documents.send', $this->readyDraft()))->assertUnauthorized();
    }

    public function test_the_list_opens_the_links_of_the_document_that_was_just_sent()
    {
        $document = $this->readyDraft();
        $this->actingAs($this->user);

        $this->postJson(route('documents.send', $document))->assertOk();

        $this->get(route('documents.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('openLinks', $document->uuid)
                ->where('linksGenerated', true)
                ->has('documents.0.links', 2));

        // The flash only lasts for one request.
        $this->get(route('documents.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('openLinks', null)
                ->where('linksGenerated', false));
    }
}
