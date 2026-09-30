<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Signer;
use App\Services\SignedPdfBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentController extends Controller
{
    /**
     * Show the authenticated user's documents.
     */
    public function index(Request $request): Response
    {
        $documents = $request->user()
            ->documents()
            ->with(['signers', 'fields.signer'])
            ->latest()
            ->latest('id')
            ->get();

        return Inertia::render('documents/Index', [
            'documents' => DocumentResource::collection($documents)->resolve(),
            // Set for one request right after sending a document, to open its links.
            'openLinks' => session('open_links'),
            // True only right after sending, to announce the links that were created.
            'linksGenerated' => (bool) session('links_generated'),
        ]);
    }

    /**
     * Send a draft for signing: give each signer a link and mark it as pending.
     */
    public function send(Document $document): JsonResponse
    {
        abort_unless($document->isDraft(), 403);

        $signers = $document->signers()->get();
        $fieldsBySigner = $document->fields()->pluck('signer_id')->countBy();

        if ($signers->isEmpty()) {
            throw ValidationException::withMessages(['document' => 'Agrega un firmante primero.']);
        }

        if ($fieldsBySigner->isEmpty()) {
            throw ValidationException::withMessages(['document' => 'Agrega al menos un campo al documento.']);
        }

        if ($signers->contains(fn (Signer $signer) => ! $fieldsBySigner->has($signer->id))) {
            throw ValidationException::withMessages(['document' => 'Cada firmante necesita al menos un campo.']);
        }

        DB::transaction(function () use ($document, $signers) {
            foreach ($signers as $signer) {
                $signer->update(['token' => (string) Str::uuid()]);
            }

            $document->update([
                'status' => DocumentStatus::Pendiente,
                'sent_at' => now(),
            ]);
        });

        session()->flash('open_links', $document->uuid);
        session()->flash('links_generated', true);

        $document->load(['signers', 'fields.signer']);

        return (new DocumentResource($document))->response();
    }

    /**
     * Store an uploaded PDF as a new draft and open its editor.
     */
    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $path = 'documents/'.Str::uuid().'.pdf';

        Storage::disk('local')->putFileAs('documents', $file, basename($path));

        $document = $request->user()->documents()->create([
            'name' => Str::limit($file->getClientOriginalName(), 240, ''),
            'original_path' => $path,
            'pages' => $request->pages,
            'status' => DocumentStatus::Borrador,
        ]);

        return redirect()->route('documents.editor', $document);
    }

    /**
     * Show the field editor. Only drafts can be changed; the rest is read-only.
     */
    public function editor(Document $document): Response
    {
        $document->load(['signers', 'fields.signer']);

        return Inertia::render('documents/Editor', [
            'document' => (new DocumentResource($document))->resolve(),
            'pdfUrl' => route('documents.file', $document, false),
        ]);
    }

    /**
     * Download the signed PDF of a completed document. It is normally built
     * when the last signer finishes; if that failed, it is built now.
     */
    public function download(Document $document, SignedPdfBuilder $builder): StreamedResponse
    {
        abort_unless($document->status === DocumentStatus::Completado, 404);

        $disk = Storage::disk('local');
        $path = $document->signed_path;

        if ($path === null || ! $disk->exists($path)) {
            try {
                $path = $builder->build($document);
            } catch (Throwable $exception) {
                report($exception);
                abort(404);
            }
        }

        $name = preg_replace('/\.pdf$/i', '', $document->name).'-firmado.pdf';

        return $disk->download($path, $name, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Stream the original PDF to its owner.
     */
    public function file(Document $document): StreamedResponse
    {
        return Storage::disk('local')->response($document->original_path, $document->name, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ], 'inline');
    }
}
