<?php

use App\Http\Controllers\CollectionEvidenceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/collection-actions/{collectionAction}/evidence', CollectionEvidenceController::class)
        ->name('collection-actions.evidence');
});
