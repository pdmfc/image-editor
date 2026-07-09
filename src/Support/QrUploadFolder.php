<?php

declare(strict_types=1);

namespace PDMFC\ImageEditor\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Pasta de destino para uploads recebidos via QR (cache por utilizador).
 * Definida ao abrir o QR no browser; lida no callback sem sessão.
 */
class QrUploadFolder
{
    public static function cacheKey(string $sanitizedUserId): string
    {
        return 'image_editor.qr_upload_folder.'.$sanitizedUserId;
    }

    public static function remember(string $sanitizedUserId, string $folderId): void
    {
        $ttl = max(5, (int) config('image-editor.qr_code.upload_folder_ttl_minutes', 1440));

        Cache::put(
            self::cacheKey($sanitizedUserId),
            $folderId,
            now()->addMinutes($ttl)
        );
    }

    public static function resolve(string $sanitizedUserId): ?string
    {
        $folderId = Cache::get(self::cacheKey($sanitizedUserId));

        return is_string($folderId) && $folderId !== '' ? $folderId : null;
    }
}
