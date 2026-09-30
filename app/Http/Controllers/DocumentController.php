<?php

namespace App\Http\Controllers;

use App\Support\MockData;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    /**
     * Show the documents list (example data until documents have a backend).
     */
    public function index(): Response
    {
        return Inertia::render('documents/Index', [
            'documents' => MockData::documents(),
        ]);
    }
}
