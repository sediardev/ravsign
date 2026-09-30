<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', '/documents')->name('dashboard');

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
});

require __DIR__.'/settings.php';
