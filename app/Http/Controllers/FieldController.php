<?php

namespace App\Http\Controllers;

use App\Enums\FieldType;
use App\Http\Requests\StoreFieldRequest;
use App\Http\Requests\UpdateFieldRequest;
use App\Http\Resources\SignFieldResource;
use App\Models\Document;
use App\Models\SignField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FieldController extends Controller
{
    /**
     * Place a new field on a page of a draft.
     */
    public function store(StoreFieldRequest $request, Document $document): JsonResponse
    {
        $signer = $document->signers()->where('uuid', $request->string('signer_id'))->firstOrFail();

        $field = $document->fields()->create([
            'signer_id' => $signer->id,
            'type' => $request->enum('type', FieldType::class) ?? FieldType::Firma,
            'page' => $request->integer('page'),
            'x' => round($request->float('x'), 4),
            'y' => round($request->float('y'), 4),
            'width' => $request->filled('width') ? round($request->float('width'), 2) : null,
            'height' => $request->filled('height') ? round($request->float('height'), 2) : null,
        ]);

        return (new SignFieldResource($field))->response()->setStatusCode(201);
    }

    /**
     * Move, resize or reassign a field.
     */
    public function update(UpdateFieldRequest $request, Document $document, SignField $field): SignFieldResource
    {
        $data = $request->validated();

        foreach (['x', 'y'] as $axis) {
            if (isset($data[$axis])) {
                $data[$axis] = round((float) $data[$axis], 4);
            }
        }

        foreach (['width', 'height'] as $dimension) {
            if (isset($data[$dimension])) {
                $data[$dimension] = round((float) $data[$dimension], 2);
            }
        }

        if (isset($data['signer_id'])) {
            $data['signer_id'] = $document->signers()->where('uuid', $data['signer_id'])->value('id');
        }

        $field->update($data);

        return new SignFieldResource($field);
    }

    /**
     * Remove a field from a draft.
     */
    public function destroy(Document $document, SignField $field): Response
    {
        abort_unless($document->isDraft(), 403);

        $field->delete();

        return response()->noContent();
    }
}
