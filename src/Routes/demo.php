<?php

use Illuminate\Support\Facades\Route;
use PDMFC\ImageEditor\Support\ImageEditorSession;

/*
|--------------------------------------------------------------------------
| Rotas de demonstração (opcional)
|--------------------------------------------------------------------------
|
| Activadas com IMAGE_EDITOR_DEMO_ROUTES=true ou demo_routes na config.
| Em produção, defina as páginas Inertia no projeto host (auth, userId, etc.).
|
*/

Route::get('/camera', function () {
    $userId = request()->query('userId', 1);
    ImageEditorSession::primeBroadcastUser($userId);

    return inertia('Camera', ['userId' => $userId]);
})->name('image-editor');

Route::get('/camera/form-example', function () {
    $userId = request()->query('userId', 1);
    ImageEditorSession::primeBroadcastUser($userId);

    return inertia('FormExample', ['userId' => $userId]);
})->name('image-editor.form-example');
