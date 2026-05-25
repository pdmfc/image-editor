<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('image-editor.photos.{userId}', function ($user, string $userId): bool {
    $authorize = config('image-editor.broadcasting.authorize');

    if (is_callable($authorize)) {
        return (bool) $authorize($user, $userId);
    }

    $sessionKey = (string) config(
        'image-editor.broadcasting.session_user_id_key',
        'image_editor_broadcast_user_id'
    );

    if (session()->has($sessionKey)) {
        return (string) session($sessionKey) === (string) $userId;
    }

    return $user !== null;
});
