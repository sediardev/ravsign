<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\SignController;
use App\Http\Controllers\SignerController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/documents')->name('dashboard');

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/editor', [DocumentController::class, 'editor'])->name('documents.editor');
    Route::post('documents/{document}/send', [DocumentController::class, 'send'])->name('documents.send');
    Route::get('documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');

    Route::scopeBindings()->group(function () {
        Route::post('documents/{document}/signers', [SignerController::class, 'store'])->name('signers.store');
        Route::delete('documents/{document}/signers/{signer}', [SignerController::class, 'destroy'])->name('signers.destroy');
        Route::post('documents/{document}/signers/{signer}/resend-invite', [SignerController::class, 'resendInvite'])->name('signers.resend-invite');

        Route::post('documents/{document}/fields', [FieldController::class, 'store'])->name('fields.store');
        Route::patch('documents/{document}/fields/{field}', [FieldController::class, 'update'])->name('fields.update');
        Route::delete('documents/{document}/fields/{field}', [FieldController::class, 'destroy'])->name('fields.destroy');
    });
});

// Public signing screen: the token in the link is the only credential.
Route::middleware('throttle:60,1')->prefix('sign/{token}')->group(function () {
    Route::get('/', [SignController::class, 'show'])->name('sign.show');
    Route::get('file', [SignController::class, 'file'])->name('sign.file');
    Route::get('fields/{field}/image', [SignController::class, 'image'])->name('sign.image');
    Route::post('fields/{field}', [SignController::class, 'field'])->name('sign.field');
    Route::post('finish', [SignController::class, 'finish'])->name('sign.finish');
});

require __DIR__.'/settings.php';
