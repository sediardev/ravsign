<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

/**
 * The whole product in one pass, over HTTP: a new user uploads a PDF, prepares
 * it for two signers, sends it, both sign from their links, and the owner
 * downloads the signed PDF.
 */
class FullSigningFlowTest extends TestCase
{
    use RefreshDatabase;

    private function signatureDataUrl(): string
    {
        $image = imagecreatetruecolor(240, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 10, 70, 230, 10, imagecolorallocate($image, 26, 53, 96));

        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }

    public function test_from_sign_up_to_the_signed_pdf()
    {
        Storage::fake('local');

        // 1. A new user registers, confirms their email, and lands on an empty list.
        $this->post('/register', [
            'name' => 'Ana Dueña',
            'email' => 'ana@ravsign.test',
            'password' => 'a-long-enough-password-1',
            'password_confirmation' => 'a-long-enough-password-1',
        ])->assertRedirect('/documents');

        $user = User::where('email', 'ana@ravsign.test')->firstOrFail();

        $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]))->assertRedirect();

        $this->get(route('documents.index'))
            ->assertInertia(fn (Assert $page) => $page->has('documents', 0));

        // 2. They upload a PDF and get its editor.
        $pdf = new UploadedFile(public_path('samples/acuerdo-servicios.pdf'), 'Contrato.pdf', 'application/pdf', null, true);
        $this->post(route('documents.store'), ['file' => $pdf])->assertRedirect();

        $document = Document::firstOrFail();
        $this->get(route('documents.editor', $document))->assertOk();

        // 3. Two signers, one signature field each on page 2.
        $ids = [];
        foreach ([['Carlos Ruiz', 'carlos@x.com'], ['Lucía Fernández', 'lucia@x.com']] as $i => [$name, $email]) {
            $signerId = $this->postJson(route('signers.store', $document), ['name' => $name, 'email' => $email])
                ->assertCreated()
                ->json('data.id');

            $this->postJson(route('fields.store', $document), [
                'signer_id' => $signerId,
                'page' => 1,
                'x' => $i === 0 ? 11.1 : 56.9,
                'y' => 63.6,
            ])->assertCreated();

            $ids[] = $signerId;
        }

        // 4. Sending gives each signer a link and the document becomes pending.
        $links = $this->postJson(route('documents.send', $document))
            ->assertOk()
            ->assertJsonPath('data.status', 'pendiente')
            ->json('data.links');

        $this->assertCount(2, $links);

        // 5. Each signer opens their link without a session, signs and finishes.
        $this->app['auth']->guard('web')->logout();
        $this->flushSession();

        foreach ($links as $i => $link) {
            $token = $link['token'];

            $this->get(route('sign.show', $token))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('signer.id', $ids[$i]));

            $this->get(route('sign.file', $token))->assertOk();

            $fieldId = $document->fields()->whereHas('signer', fn ($q) => $q->where('uuid', $ids[$i]))->value('uuid');

            $this->postJson(route('sign.finish', $token))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('fields');

            $this->postJson(route('sign.field', [$token, $fieldId]), ['image' => $this->signatureDataUrl()])
                ->assertOk();

            $this->postJson(route('sign.finish', $token))
                ->assertOk()
                ->assertJson(['completed' => $i === 1]);
        }

        // 6. The document is completed and its signed PDF exists.
        $document->refresh();
        $this->assertSame(DocumentStatus::Completado, $document->status);
        $this->assertNotNull($document->completed_at);
        Storage::disk('local')->assertExists($document->signed_path);

        // 7. The owner downloads it: same pages, with the signatures on it.
        $this->post('/login', ['email' => 'ana@ravsign.test', 'password' => 'a-long-enough-password-1']);
        $response = $this->get(route('documents.download', $document))->assertOk();

        $bytes = Storage::disk('local')->get($document->signed_path);
        $this->assertSame(2, (new Fpdi('P', 'pt'))->setSourceFile(StreamReader::createByString($bytes)));
        // Two signatures, plus a soft mask each when the PNG keeps its alpha channel.
        $this->assertGreaterThanOrEqual(2, substr_count($bytes, '/Subtype /Image'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        // 8. The list shows it as completed with both signers done.
        $this->get(route('documents.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.0.status', 'completado')
                ->where('documents.0.links.0.status', 'firmado')
                ->where('documents.0.links.1.status', 'firmado'));
    }

    public function test_the_landing_test_editor_button_target_requires_a_session()
    {
        // "Probar el editor" points to /documents: guests are sent to log in first.
        $this->get('/documents')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get('/documents')->assertOk();
    }
}
