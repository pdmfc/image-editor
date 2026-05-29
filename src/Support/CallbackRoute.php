<?php

namespace PDMFC\ImageEditor\Support;

class CallbackRoute
{
    public static function urlTemplate(): ?string
    {
        $url = config('image-editor.qr_code.callback_url');

        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        return $url !== '' ? $url : null;
    }

    public static function resolvedUrl(string|int $userId): string
    {
        $userId = self::sanitizeUserId($userId);
        $template = self::urlTemplate();

        if ($template !== null) {
            if (self::hasUserPlaceholder($template)) {
                return str_replace(['{userId}', '{user_id}'], $userId, $template);
            }

            return rtrim($template, '/').'/'.$userId;
        }

        return route('image-editor.callback.files', ['userId' => $userId], absolute: true);
    }

    public static function routePath(): string
    {
        $configured = config('image-editor.routes.callback_path');

        if (is_string($configured) && trim($configured) !== '') {
            return ltrim(trim($configured), '/');
        }

        $template = self::urlTemplate();

        if ($template !== null) {
            $path = parse_url($template, PHP_URL_PATH);

            if (is_string($path) && $path !== '' && $path !== '/') {
                $path = ltrim($path, '/');

                if (! self::hasUserPlaceholder($template)) {
                    return rtrim($path, '/').'/{userId}';
                }

                return $path;
            }
        }

        $prefix = trim((string) config('image-editor.routes.prefix', 'api'), '/');
        $default = 'camera/callback/files/{userId}';

        return $prefix !== '' ? $prefix.'/'.$default : $default;
    }

    public static function isConfigured(): bool
    {
        return self::urlTemplate() !== null;
    }

    public static function hasUserPlaceholder(?string $template = null): bool
    {
        $template ??= self::urlTemplate();

        if ($template === null) {
            return false;
        }

        return str_contains($template, '{userId}') || str_contains($template, '{user_id}');
    }

    public static function sanitizeUserId(string|int $userId): string
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $userId);

        if ($id === '') {
            throw new \InvalidArgumentException('user_id inválido.');
        }

        return $id;
    }
}
