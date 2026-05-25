<?php

namespace PDMFC\ImageEditor\Services;

use Illuminate\Support\Facades\Storage;
use PDMFC\ImageEditor\Events\PhotosUploadedFromMobile;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

class CameraService
{
    public function __construct(
        protected UserPhotoStorage $storage,
    ) {
    }

    private function disk(): string
    {
        return $this->storage->disk();
    }

    public function getPhotos(string|int $userId): array
    {
        try {
            $this->storage->ensureDirectory($userId);
            $dir = $this->storage->directory($userId);
            $files = Storage::disk($this->disk())->files($dir);
            $photoGroups = [];

            foreach ($files as $file) {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    continue;
                }

                $filename = basename($file);
                $baseName = preg_replace('/_edited\.(jpg|jpeg|png|gif|webp)$/i', '', $filename);
                $fullPath = $this->storage->storagePath($userId, $filename);
                $timestamp = is_file($fullPath) ? filemtime($fullPath) : time();

                if (! isset($photoGroups[$baseName]) || $timestamp > $photoGroups[$baseName]['timestamp']) {
                    $photoGroups[$baseName] = [
                        'filename' => $filename,
                        'url' => $this->storage->photoUrl($userId, $filename),
                        'path' => $file,
                        'timestamp' => $timestamp,
                        'is_blank_canvas' => str_starts_with($filename, 'canvas_'),
                    ];
                }
            }

            $photos = array_values($photoGroups);
            usort($photos, fn (array $a, array $b): int => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

            return ['photos' => $photos];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function storeCallbackFiles(string|int $userId, array $payload): array
    {
        try {
            $this->storage->ensureDirectory($userId);
            $files = $this->normalizeCallbackPayload($payload);
            $saved = 0;
            $newFilenames = [];

            foreach ($files as $file) {
                if (! is_array($file) || empty($file['name']) || empty($file['content'])) {
                    continue;
                }

                $name = $this->storage->ensureImageExtension((string) $file['name']);
                $binary = base64_decode(
                    (string) preg_replace('#^data:image/\w+;base64,#i', '', (string) $file['content']),
                    true
                );

                if ($binary === false || $binary === '') {
                    continue;
                }

                if (Storage::disk($this->disk())->put($this->storage->filePath($userId, $name), $binary)) {
                    $saved++;
                    $newFilenames[] = $name;
                }
            }

            if ($saved > 0) {
                $this->broadcastPhotosUploaded($userId, $saved, $newFilenames);
            }

            return ['status' => true, 'saved' => $saved];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function capturePhoto($data, string|int $userId): array
    {
        try {
            $photoData = base64_decode(
                (string) preg_replace('#^data:image/\w+;base64,#i', '', (string) $data->photo),
                true
            );

            if (! $photoData) {
                return ['error' => 'Falha ao decodificar a foto'];
            }

            $this->storage->ensureDirectory($userId);
            $filename = 'photo_'.time().'_'.uniqid().'.jpg';
            $path = $this->storage->filePath($userId, $filename);

            if (Storage::disk($this->disk())->put($path, $photoData)) {
                return [
                    'success' => true,
                    'filename' => $filename,
                    'url' => $this->storage->photoUrl($userId, $filename),
                ];
            }

            return ['error' => 'Falha ao salvar a foto'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function uploadPhoto($file, string|int $userId): array
    {
        try {
            if (! $file || ! $file->isValid()) {
                return ['error' => 'Ficheiro inválido'];
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: '');
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $mime = $file->getMimeType();
                $extension = match ($mime) {
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    default => 'jpg',
                };
            }
            if ($extension === 'jpeg') {
                $extension = 'jpg';
            }

            $this->storage->ensureDirectory($userId);
            $filename = 'photo_'.time().'_'.uniqid().'.'.$extension;
            $path = $this->storage->filePath($userId, $filename);
            $stored = $file->storeAs(dirname($path), basename($path), $this->disk());

            if (! $stored) {
                return ['error' => 'Falha ao guardar a imagem'];
            }

            $fullPath = Storage::disk($this->disk())->path($stored);

            return [
                'success' => true,
                'photo' => [
                    'filename' => $filename,
                    'url' => $this->storage->photoUrl($userId, $filename),
                    'path' => $stored,
                    'timestamp' => is_file($fullPath) ? filemtime($fullPath) : time(),
                ],
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function createBlankCanvas(
        int $width = 1600,
        int $height = 1200,
        string $background = '#ffffff',
        string|int $userId = 0
    ): array {
        try {
            $width = max(400, min(8000, $width));
            $height = max(300, min(8000, $height));

            $this->storage->ensureDirectory($userId);
            $filename = 'canvas_'.time().'_'.uniqid().'.jpg';
            $path = $this->storage->filePath($userId, $filename);

            $canvas = Image::createImage($width, $height);
            $canvas->fill($background);
            $binary = (string) $canvas->encode(new JpegEncoder(quality: 92));

            if (! Storage::disk($this->disk())->put($path, $binary)) {
                return ['error' => 'Falha ao criar a folha em branco'];
            }

            $fullPath = Storage::disk($this->disk())->path($path);

            return [
                'success' => true,
                'filename' => $filename,
                'url' => $this->storage->photoUrl($userId, $filename),
                'photo' => [
                    'filename' => $filename,
                    'url' => $this->storage->photoUrl($userId, $filename),
                    'path' => $path,
                    'timestamp' => is_file($fullPath) ? filemtime($fullPath) : time(),
                    'is_blank_canvas' => true,
                ],
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function duplicatePhoto(string $filename, string|int $userId): array
    {
        try {
            $safeName = $this->storage->safeFilename($filename);
            $filepath = $this->storage->filePath($userId, $safeName);

            if (! Storage::disk($this->disk())->exists($filepath)) {
                return ['error' => 'Arquivo não encontrado'];
            }

            $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $extension = 'jpg';
            }

            $newFilename = 'photo_'.time().'_'.uniqid().'.'.$extension;
            $newPath = $this->storage->filePath($userId, $newFilename);

            if (! Storage::disk($this->disk())->copy($filepath, $newPath)) {
                return ['error' => 'Falha ao duplicar a foto'];
            }

            $fullPath = Storage::disk($this->disk())->path($newPath);

            return [
                'success' => true,
                'photo' => [
                    'filename' => $newFilename,
                    'url' => $this->storage->photoUrl($userId, $newFilename),
                    'path' => $newPath,
                    'timestamp' => is_file($fullPath) ? filemtime($fullPath) : time(),
                ],
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function deletePhoto(string $filename, string|int $userId): array
    {
        try {
            $filepath = $this->storage->filePath($userId, $filename);

            if (! Storage::disk($this->disk())->exists($filepath)) {
                return ['error' => 'Arquivo não encontrado'];
            }

            if (Storage::disk($this->disk())->delete($filepath)) {
                return ['success' => true];
            }

            return ['error' => 'Falha ao excluir foto'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function broadcastPhotosUploaded(string|int $userId, int $saved, array $newFilenames): void
    {
        if (! config('image-editor.broadcasting.enabled', true)) {
            return;
        }

        try {
            $sanitizedId = $this->storage->sanitizeUserId($userId);
            $photos = $this->getPhotos($userId)['photos'] ?? [];

            PhotosUploadedFromMobile::dispatch($sanitizedId, $saved, $photos, $newFilenames);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function normalizeCallbackPayload(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        if (isset($payload['name'], $payload['content'])) {
            return [$payload];
        }

        $first = reset($payload);
        if (is_array($first) && isset($first['name'], $first['content'])) {
            return $payload;
        }

        return [];
    }
}
