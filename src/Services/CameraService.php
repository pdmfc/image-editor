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
            $result = $this->listPhotosUnsorted($userId);

            if (isset($result['error'])) {
                return $result;
            }

            return [
                'photos' => $this->sortPhotosByGalleryOrder($result['photos'] ?? [], $userId),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function listPhotosUnsorted(string|int $userId): array
    {
        try {
            $this->storage->ensureDirectory($userId);
            $dir = $this->storage->directory($userId);
            $files = Storage::disk($this->disk())->files($dir);
            $photos = [];

            foreach ($files as $file) {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    continue;
                }

                $filename = basename($file);
                $fullPath = $this->storage->storagePath($userId, $filename);
                $timestamp = is_file($fullPath) ? filemtime($fullPath) : time();

                $photos[] = [
                    'filename' => $filename,
                    'url' => $this->storage->photoUrl($userId, $filename),
                    'path' => $file,
                    'timestamp' => $timestamp,
                    'is_blank_canvas' => str_starts_with($filename, 'canvas_'),
                ];
            }

            return ['photos' => $photos];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @throws \JsonException
     */
    private function trackNewPhotoInGalleryOrder(string|int $userId, string $filename): void
    {
        if ($this->storage->readGalleryOrder($userId) === []) {
            return;
        }

        $this->storage->appendToGalleryOrder($userId, $filename);
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
                    $this->trackNewPhotoInGalleryOrder($userId, $name);
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
                $this->trackNewPhotoInGalleryOrder($userId, $filename);

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

            $this->trackNewPhotoInGalleryOrder($userId, $filename);
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

            $this->trackNewPhotoInGalleryOrder($userId, $filename);
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

            $this->trackNewPhotoInGalleryOrder($userId, $newFilename);
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
                $this->storage->removeFromGalleryOrder($userId, $filename);

                return ['success' => true];
            }

            return ['error' => 'Falha ao excluir foto'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $filenames
     */
    public function deletePhotos(array $filenames, string|int $userId): array
    {
        $filenames = array_values(array_unique(array_filter(array_map(
            static fn ($name) => is_string($name) ? trim($name) : '',
            $filenames
        ))));

        if ($filenames === []) {
            return ['error' => 'Nenhum ficheiro indicado para eliminar.'];
        }

        $deleted = [];
        $errors = [];

        foreach ($filenames as $filename) {
            $result = $this->deletePhoto($filename, $userId);

            if (isset($result['error'])) {
                $errors[] = [
                    'filename' => $filename,
                    'error' => $result['error'],
                ];
            } else {
                $deleted[] = $filename;
            }
        }

        return [
            'success' => $errors === [],
            'deleted' => $deleted,
            'errors' => $errors,
            'deleted_count' => count($deleted),
            'failed_count' => count($errors),
        ];
    }

    /**
     * @param  list<array{filename: string, url: string, path: string, timestamp: int|float, is_blank_canvas: bool}>  $photos
     * @return list<array{filename: string, url: string, path: string, timestamp: int|float, is_blank_canvas: bool}>
     */
    private function sortPhotosByGalleryOrder(array $photos, string|int $userId): array
    {
        $order = $this->storage->readGalleryOrder($userId);

        if ($order === []) {
            usort($photos, fn (array $a, array $b): int => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

            return $photos;
        }

        $byName = [];

        foreach ($photos as $photo) {
            $byName[$photo['filename']] = $photo;
        }

        $sorted = [];

        foreach ($order as $filename) {
            if (isset($byName[$filename])) {
                $sorted[] = $byName[$filename];
                unset($byName[$filename]);
            }
        }

        $remaining = array_values($byName);
        usort($remaining, fn (array $a, array $b): int => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

        return array_merge($sorted, $remaining);
    }

    /**
     * @param  list<string>  $filenames
     */
    public function reorderPhotos(string|int $userId, array $filenames): array
    {
        try {
            $filenames = array_values(array_unique(array_filter(array_map(
                fn ($name) => $this->storage->safeFilename((string) $name),
                $filenames
            ))));

            if ($filenames === []) {
                return ['error' => 'Nenhum ficheiro indicado para ordenar.'];
            }

            $existing = $this->listPhotosUnsorted($userId);

            if (isset($existing['error'])) {
                return ['error' => $existing['error']];
            }

            $photos = $existing['photos'] ?? [];
            $known = array_column($photos, 'filename');
            $knownSet = array_flip($known);

            foreach ($filenames as $filename) {
                if (! isset($knownSet[$filename])) {
                    return ['error' => "Ficheiro não encontrado: {$filename}"];
                }
            }

            if (count($filenames) !== count($known)) {
                return ['error' => 'A lista de ordenação tem de incluir todas as imagens.'];
            }

            $this->storage->writeGalleryOrder($userId, $filenames);

            return [
                'success' => true,
                'photos' => $this->sortPhotosByGalleryOrder($photos, $userId),
            ];
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
