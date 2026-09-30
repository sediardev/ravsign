<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Requests\StoreSignerRequest;
use App\Http\Resources\SignerResource;
use App\Models\Document;
use App\Models\Signer;
use App\Notifications\SignerInviteNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SignerController extends Controller
{
    /**
     * Add a signer to a draft. It takes the first color no other signer uses.
     */
    public function store(StoreSignerRequest $request, Document $document): JsonResponse
    {
        $signers = $document->signers()->get();

        $signer = $document->signers()->create([
            'name' => trim($request->string('name')->toString()),
            'email' => trim($request->string('email')->toString()),
            'siglas' => $request->siglas(),
            'color' => collect(Signer::COLORS)->first(fn (string $color) => ! $signers->contains('color', $color)),
            'position' => $signers->isEmpty() ? 0 : $signers->max('position') + 1,
        ]);

        return (new SignerResource($signer))->response()->setStatusCode(201);
    }

    /**
     * Remove a signer from a draft together with their fields.
     */
    public function destroy(Document $document, Signer $signer): Response
    {
        abort_unless($document->isDraft(), 403);

        $signer->delete();

        return response()->noContent();
    }

    /**
     * Resend a signer their signing link by email.
     */
    public function resendInvite(Document $document, Signer $signer): Response
    {
        abort_unless($document->status === DocumentStatus::Pendiente, 403);

        if ($signer->signed_at !== null) {
            throw ValidationException::withMessages(['signer' => 'This signer already signed.']);
        }

        Notification::route('mail', $signer->email)->notify(new SignerInviteNotification($signer));

        return response()->noContent();
    }
}
