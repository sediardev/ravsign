<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Requests\SignFieldRequest;
use App\Http\Resources\PublicSignerResource;
use App\Http\Resources\SignFieldResource;
use App\Models\Document;
use App\Models\Signer;
use App\Models\SignField;
use App\Services\SignedPdfBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Public signing pages. A signer is identified only by the token in their link.
 */
class SignController extends Controller
{
    /**
     * The signing screen of one signer.
     */
    public function show(string $token): Response
    {
        $signer = $this->signer($token);
        $document = $signer->document;
        $document->load(['signers', 'fields.signer']);

        return Inertia::render('sign/Show', [
            'document' => [
                'id' => $document->uuid,
                'name' => $document->name,
                'status' => $document->status->value,
                'pages' => $document->pages,
                'signers' => PublicSignerResource::collection($document->signers)->resolve(),
                'fields' => SignFieldResource::collection($document->fields)->resolve(),
            ],
            'signer' => [
                ...(new PublicSignerResource($signer))->resolve(),
                'email' => $signer->email,
                'token' => $signer->token,
                'link' => url('/sign/'.$signer->token),
            ],
            'pdfUrl' => route('sign.file', $token, false),
        ]);
    }

    /**
     * The original PDF, for a signer to read.
     */
    public function file(string $token): StreamedResponse
    {
        $document = $this->signer($token)->document;

        return Storage::disk('local')->response($document->original_path, $document->name, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ], 'inline');
    }

    /**
     * The signature image of a field of the signer's document.
     */
    public function image(string $token, SignField $field): StreamedResponse
    {
        $signer = $this->signer($token);

        abort_unless($field->document_id === $signer->document_id && $field->value_path !== null, 404);

        return Storage::disk('local')->response($field->value_path, null, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ], 'inline');
    }

    /**
     * Save the signature image of one of the signer's own fields.
     */
    public function field(SignFieldRequest $request, string $token, SignField $field): SignFieldResource
    {
        $signer = $this->signer($token);

        // A document can only be signed while pending, and by who has not finished yet.
        abort_unless(
            $signer->document->status === DocumentStatus::Pendiente && $signer->signed_at === null,
            403,
        );

        abort_unless($field->document_id === $signer->document_id && $field->signer_id === $signer->id, 403);

        $previous = $field->value_path;
        $path = 'signatures/'.Str::uuid().'.png';

        Storage::disk('local')->put($path, $request->png);
        $field->update(['value_path' => $path, 'value_text' => null]);

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        $field->setRelation('signer', $signer);

        return new SignFieldResource($field);
    }

    /**
     * The signer is done: every field of theirs must be signed. When they were
     * the last one, the document becomes completed.
     */
    public function finish(string $token): JsonResponse
    {
        $signer = $this->signer($token);

        abort_unless(
            $signer->document->status === DocumentStatus::Pendiente && $signer->signed_at === null,
            403,
        );

        $hasEmptyFields = $signer->fields()
            ->whereNull('value_path')
            ->whereNull('value_text')
            ->exists();

        if ($hasEmptyFields) {
            throw ValidationException::withMessages([
                'fields' => 'Completa todos tus campos antes de finalizar.',
            ]);
        }

        // The document row is locked so two signers finishing at once cannot
        // both see the other one as pending and leave the document open.
        $completed = DB::transaction(function () use ($signer) {
            $document = Document::query()->lockForUpdate()->findOrFail($signer->document_id);

            $signer->update(['signed_at' => now()]);

            return $document->refreshStatus();
        });

        if ($completed) {
            $this->buildSignedPdf($signer->document_id);
        }

        // The list opens this document's links; the toast tells what happened.
        session()->flash('open_links', $signer->document->uuid);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $completed
                ? 'Document completed by all signers.'
                : 'Your signature was recorded. Waiting on the others to sign.',
        ]);

        return response()->json(['completed' => $completed]);
    }

    /**
     * Stamp the signatures on the finished document. A failure here must not
     * undo anyone's signature: it is reported, and the owner's download builds
     * the PDF again on demand.
     */
    private function buildSignedPdf(int $documentId): void
    {
        try {
            app(SignedPdfBuilder::class)->build(Document::query()->findOrFail($documentId));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function signer(string $token): Signer
    {
        return Signer::query()->where('token', $token)->with('document')->firstOrFail();
    }
}
