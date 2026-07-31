<?php

use Illuminate\Support\Facades\Route;
use Peppermint\DocumentBuilder\Http\Controllers\DocumentBuilderImageController;

Route::middleware(config('document-builder.routes.middleware', ['web', 'auth']))
    ->prefix(config('document-builder.routes.prefix', 'document-builder'))
    ->name('document-builder.')
    ->group(function (): void {
        Route::post('images', [DocumentBuilderImageController::class, 'store'])->name('images.store');
    });
