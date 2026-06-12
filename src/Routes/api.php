<?php

use Illuminate\Support\Facades\Route;
use PDMFC\ImageEditor\Http\Controllers\CameraController;
use PDMFC\ImageEditor\Http\Controllers\ImageController;

Route::middleware(config('image-editor.routes.browser_middleware', ['web']))->group(function () {
    Route::post('/camera/qrcode', [CameraController::class, 'getQrCode'])->name('image-editor.qrcode');
    Route::get('/camera/photos/{userId}/{filename}', [CameraController::class, 'showPhoto'])
        ->where('filename', '.*')
        ->name('image-editor.photo');
    Route::get('/camera/photos', [CameraController::class, 'getPhotos']);
    Route::post('/camera/capture', [CameraController::class, 'capture']);
    Route::post('/camera/upload', [CameraController::class, 'upload']);
    Route::post('/camera/blank', [CameraController::class, 'createBlank']);
    Route::delete('/camera/photos', [CameraController::class, 'deletePhoto']);
    Route::post('/camera/photos/duplicate', [CameraController::class, 'duplicatePhoto']);
    Route::post('/camera/photos/reorder', [CameraController::class, 'reorderPhotos']);
    Route::post('/camera/photos/move', [CameraController::class, 'movePhotosToFolder']);
    Route::post('/camera/folders', [CameraController::class, 'createGalleryFolder']);
    Route::patch('/camera/folders/{folderId}', [CameraController::class, 'renameGalleryFolder']);
    Route::delete('/camera/folders/{folderId}', [CameraController::class, 'deleteGalleryFolder']);
    Route::post('/image/edit', [ImageController::class, 'edit'])->name('image-editor.edit');
    Route::post('/image/preview', [ImageController::class, 'preview'])->name('image-editor.preview');
});
