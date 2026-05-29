<?php

namespace PDMFC\ImageEditor\Services;

use Illuminate\Support\Facades\Storage;
use PDMFC\ImageEditor\Support\CallbackRoute;

class UserPhotoStorage
{
    public function disk(): string
    {
        return (string) config('image-editor.storage.disk', 'public');
    }

    public function sanitizeUserId(string|int $userId): string
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $userId);

        if ($id === '') {
            throw new \InvalidArgumentException('user_id inválido.');
        }

        return $id;
    }

    public function directory(string|int $userId): string
    {
        $base = trim((string) config('image-editor.storage.temp_path', 'photos/tmp'), '/');

        return $base.'/'.$this->sanitizeUserId($userId);
    }

    public function ensureDirectory(string|int $userId): void
    {
        $dir = $this->directory($userId);

        if (! Storage::disk($this->disk())->exists($dir)) {
            Storage::disk($this->disk())->makeDirectory($dir);
        }
    }

    public function safeFilename(string $filename): string
    {
        return basename($filename);
    }

    public function ensureImageExtension(string $filename, string $default = 'jpg'): string
    {
        $name = $this->safeFilename($filename);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return $name;
        }

        return $name.'.'.ltrim($default, '.');
    }

    public function photoUrl(string|int $userId, string $filename): string
    {
        $userId = $this->sanitizeUserId($userId);
        $filename = $this->safeFilename($filename);
        $driver = (string) config("filesystems.disks.{$this->disk()}.driver", 'local');

        if ($driver !== 'local') {
            return Storage::disk($this->disk())->url($this->filePath($userId, $filename));
        }

        return '/api/camera/photos/'.$userId.'/'.rawurlencode($filename);
    }

    public function filePath(string|int $userId, string $filename): string
    {
        return $this->directory($userId).'/'.$this->safeFilename($filename);
    }

    public function storagePath(string|int $userId, string $filename): string
    {
        $relative = $this->filePath($userId, $filename);
        $root = config("filesystems.disks.{$this->disk()}.root");

        if (is_string($root) && $root !== '') {
            return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        }

        return storage_path('app/public/'.$relative);
    }

    public function fileExists(string|int $userId, string $filename): bool
    {
        return is_file($this->storagePath($userId, $filename));
    }

    public function callbackUrl(string|int $userId): string
    {
        return CallbackRoute::resolvedUrl($userId);
    }
}
