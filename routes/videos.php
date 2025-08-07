<?php

use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('videos')->name('videos.')->group(function () {
    Route::get('/', [VideoController::class, 'index'])->name('index');
    Route::get('/upload', [VideoController::class, 'create'])->name('create');
    Route::get('/{video}', [VideoController::class, 'show'])->name('show');
    
    // API routes for multipart upload
    Route::post('/initiate-upload', [VideoController::class, 'initiateUpload'])->name('initiate-upload');
    Route::post('/get-upload-url', [VideoController::class, 'getUploadUrl'])->name('get-upload-url');
    Route::post('/complete-upload', [VideoController::class, 'completeUpload'])->name('complete-upload');
    Route::post('/abort-upload', [VideoController::class, 'abortUpload'])->name('abort-upload');
    Route::delete('/{video}', [VideoController::class, 'destroy'])->name('destroy');
});