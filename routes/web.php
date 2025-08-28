<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicVideoController;
use App\Http\Controllers\ShareEmailController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Public video sharing routes (no auth required)
Route::get('/share/{uuid}', [PublicVideoController::class, 'show'])->name('videos.public');
Route::post('/share/{uuid}/email', [ShareEmailController::class, 'send'])->name('videos.share.email');

require __DIR__.'/videos.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
