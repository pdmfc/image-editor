<?php

namespace PDMFC\ImageEditor\Support;

class GalleryUploadLimits
{
    public static function maxMegabytes(): int
    {
        $mb = (int) config('image-editor.gallery.max_upload_mb', 10);

        return max(1, $mb);
    }

    public static function maxKilobytes(): int
    {
        return static::maxMegabytes() * 1024;
    }

    public static function maxBytes(): int
    {
        return static::maxMegabytes() * 1024 * 1024;
    }

    public static function exceedsLimit(int $bytes): bool
    {
        return $bytes > static::maxBytes();
    }

    public static function errorMessage(): string
    {
        return sprintf(
            'A imagem excede o tamanho máximo permitido (%d MB).',
            static::maxMegabytes()
        );
    }
}
