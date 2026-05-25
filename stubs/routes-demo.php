<?php

use Illuminate\Support\Facades\Route;
use PDMFC\ImageEditor\Support\ImageEditorSession;

/*
|--------------------------------------------------------------------------
| Image Editor — rotas de demonstração (host)
|--------------------------------------------------------------------------
|
| Publicado com: php artisan vendor:publish --tag=image-editor-demo-routes
|
| Registe este ficheiro em routes/web.php do host, por exemplo:
|
|   Route::middleware('web')->group(base_path('routes/image-editor-demo.php'));
|
| Ajuste middleware (auth, etc.) conforme o seu projeto.
| Para testes rápidos sem publicar, use IMAGE_EDITOR_DEMO_ROUTES=true no .env.
|
*/

Route::get('/camera', function () {
    $userId = request()->query('userId', 1);
    ImageEditorSession::primeBroadcastUser($userId);

    return inertia('Camera', ['userId' => $userId]);
})->name('image-editor.demo.camera');

Route::get('/camera/form-example', function () {
    $userId = request()->query('userId', 1);
    ImageEditorSession::primeBroadcastUser($userId);

    return inertia('FormExample', ['userId' => $userId]);
})->name('image-editor.demo.form-example');
