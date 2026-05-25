<?php

namespace PDMFC\ImageEditor\Support;

class ImageEditorSession
{
    public static function primeBroadcastUser(string|int $userId): void
    {
        session([
            (string) config('image-editor.broadcasting.session_user_id_key', 'image_editor_broadcast_user_id') => (string) $userId,
        ]);
    }
}
