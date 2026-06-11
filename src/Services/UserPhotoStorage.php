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

    public function galleryOrderRelativePath(): string
    {
        return '.gallery-order.json';
    }

    public function galleryOrderPath(string|int $userId): string
    {
        return $this->directory($userId).'/'.$this->galleryOrderRelativePath();
    }

    /**
     * @return list<string>
     */
    public function readGalleryOrder(string|int $userId): array
    {
        $path = $this->galleryOrderPath($userId);

        if (! Storage::disk($this->disk())->exists($path)) {
            return [];
        }

        $json = Storage::disk($this->disk())->get($path);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($name) => $this->safeFilename((string) $name),
            $data
        )));
    }

    /**
     * @param list<string> $filenames
     * @throws \JsonException
     */
    public function writeGalleryOrder(string|int $userId, array $filenames): void
    {
        $this->ensureDirectory($userId);

        $filenames = array_values(array_unique(array_filter(array_map(
            fn ($name) => $this->safeFilename((string) $name),
            $filenames
        ))));

        Storage::disk($this->disk())->put(
            $this->galleryOrderPath($userId),
            json_encode($filenames, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );
    }

    public function deleteGalleryOrder(string|int $userId): void
    {
        $path = $this->galleryOrderPath($userId);

        if (Storage::disk($this->disk())->exists($path)) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    /**
     * @throws \JsonException
     */
    public function removeFromGalleryOrder(string|int $userId, string $filename): void
    {
        $filename = $this->safeFilename($filename);
        $order = $this->readGalleryOrder($userId);
        $order = array_values(array_filter($order, fn (string $name): bool => $name !== $filename));

        if ($order === []) {
            $this->deleteGalleryOrder($userId);

            return;
        }

        $this->writeGalleryOrder($userId, $order);
    }

    /**
     * @throws \JsonException
     */
    public function appendToGalleryOrder(string|int $userId, string $filename): void
    {
        $filename = $this->safeFilename($filename);
        $order = $this->readGalleryOrder($userId);

        if (in_array($filename, $order, true)) {
            return;
        }

        $order[] = $filename;
        $this->writeGalleryOrder($userId, $order);
    }
}
