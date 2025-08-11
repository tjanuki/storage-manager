<?php

use App\Http\Controllers\PublicVideoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Public video sharing route (no auth required)
Route::get('/share/{uuid}', [PublicVideoController::class, 'show'])->name('videos.public');

require __DIR__.'/videos.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
