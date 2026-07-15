<?php

namespace PDMFC\ImageEditor\Services;

use Illuminate\Support\Facades\Storage;
use PDMFC\ImageEditor\Events\PhotosUploadedFromMobile;
use PDMFC\ImageEditor\Support\GalleryFolders;
use PDMFC\ImageEditor\Support\GalleryUploadLimits;
use PDMFC\ImageEditor\Support\QrUploadFolder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

class CameraService
{
    public function __construct(
        protected UserPhotoStorage $storage,
        protected GalleryFolders $galleryFolders,
    ) {
    }

    private function disk(): string
    {
        return $this->storage->disk();
    }

    public function galleryMaxImages(): ?int
    {
        $max = (int) config('image-editor.gallery.max_images', 0);

        return $max > 0 ? $max : null;
    }

    public function galleryRemainingSlots(string|int $userId): ?int
    {
        $max = $this->galleryMaxImages();

        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->galleryPhotoCount($userId));
    }

    public function galleryPhotoCount(string|int $userId): int
    {
        $result = $this->listPhotosUnsorted($userId);

        if (isset($result['error'])) {
            return 0;
        }

        return count($result['photos'] ?? []);
    }

    private function galleryLimitError(int $max): array
    {
        return [
            'error' => "Limite da galeria atingido ({$max} imagens). Elimine imagens para adicionar novas.",
        ];
    }

    private function ensureGalleryHasRoom(string|int $userId, int $adding = 1): ?array
    {
        $max = $this->galleryMaxImages();

        if ($max === null) {
            return null;
        }

        if ($this->galleryPhotoCount($userId) + $adding > $max) {
            return $this->galleryLimitError($max);
        }

        return null;
    }

    public function getPhotos(string|int $userId): array
    {
        try {
            $result = $this->listPhotosUnsorted($userId);

            if (isset($result['error'])) {
                return $result;
            }

            $photos = $result['photos'] ?? [];

            if ($this->galleryFolders->enabled()) {
                $this->galleryFolders->syncFolderOrdersForKnownPhotos(
                    $userId,
                    array_column($photos, 'filename')
                );
                $photos = $this->galleryFolders->sortPhotos($userId, $photos);
                $payload = [
                    'photos' => $photos,
                    'gallery_folders_enabled' => true,
                    'folders' => $this->galleryFolders->listFolders($userId),
                ];
            } else {
                $photos = $this->sortPhotosByGalleryOrder($photos, $userId);
                $payload = ['photos' => $photos];
            }

            $max = $this->galleryMaxImages();
            if ($max !== null) {
                $payload['gallery_max_images'] = $max;
            }

            $payload['gallery_max_upload_mb'] = GalleryUploadLimits::maxMegabytes();

            return $payload;
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

                $entry = [
                    'filename' => $filename,
                    'url' => $this->storage->photoUrl($userId, $filename),
                    'path' => $file,
                    'timestamp' => $timestamp,
                    'is_blank_canvas' => str_starts_with($filename, 'canvas_'),
                ];

                if ($this->galleryFolders->enabled()) {
                    $entry['folder_id'] = $this->galleryFolders->folderIdForPhoto($userId, $filename);
                }

                $photos[] = $entry;
            }

            if ($this->galleryFolders->enabled()) {
                $this->galleryFolders->syncAssignmentsForKnownPhotos(
                    $userId,
                    array_column($photos, 'filename')
                );

                foreach ($photos as &$photo) {
                    $photo['folder_id'] = $this->galleryFolders->folderIdForPhoto($userId, $photo['filename']);
                }
                unset($photo);
            }

            return ['photos' => $photos];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @throws \JsonException
     */
    private function trackNewPhotoInGalleryOrder(string|int $userId, string $filename, ?string $folderId = null): void
    {
        if ($this->storage->readGalleryOrder($userId) !== []) {
            $this->storage->appendToGalleryOrder($userId, $filename);
        }

        $this->galleryFolders->assignNewPhoto($userId, $filename, $folderId);
    }

    public function storeCallbackFiles(string|int $userId, array $payload): array
    {
        try {
            $this->storage->ensureDirectory($userId);
            $files = $this->normalizeCallbackPayload($payload);
            $saved = 0;
            $newFilenames = [];
            $qrFolderId = QrUploadFolder::resolve($this->storage->sanitizeUserId($userId));

            foreach ($files as $file) {
                if (! is_array($file) || empty($file['name']) || empty($file['content'])) {
                    continue;
                }

                if ($limitError = $this->ensureGalleryHasRoom($userId)) {
                    if ($saved === 0) {
                        return $limitError;
                    }

                    break;
                }

                $name = $this->storage->ensureImageExtension((string) $file['name']);
                $binary = base64_decode(
                    (string) preg_replace('#^data:image/\w+;base64,#i', '', (string) $file['content']),
                    true
                );

                if ($binary === false || $binary === '') {
                    continue;
                }

                if (GalleryUploadLimits::exceedsLimit(strlen($binary))) {
                    if ($saved === 0) {
                        return ['error' => GalleryUploadLimits::errorMessage()];
                    }

                    break;
                }

                if (Storage::disk($this->disk())->put($this->storage->filePath($userId, $name), $binary)) {
                    $saved++;
                    $newFilenames[] = $name;
                    $this->trackNewPhotoInGalleryOrder($userId, $name, $qrFolderId);
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
            if ($limitError = $this->ensureGalleryHasRoom($userId)) {
                return $limitError;
            }

            $photoData = base64_decode(
                (string) preg_replace('#^data:image/\w+;base64,#i', '', (string) $data->photo),
                true
            );

            if (! $photoData) {
                return ['error' => 'Falha ao decodificar a foto'];
            }

            if (GalleryUploadLimits::exceedsLimit(strlen($photoData))) {
                return ['error' => GalleryUploadLimits::errorMessage()];
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

    public function uploadPhoto($file, string|int $userId, ?string $folderId = null): array
    {
        try {
            if ($limitError = $this->ensureGalleryHasRoom($userId)) {
                return $limitError;
            }

            if (! $file || ! $file->isValid()) {
                return ['error' => 'Ficheiro inválido'];
            }

            if (GalleryUploadLimits::exceedsLimit((int) $file->getSize())) {
                return ['error' => GalleryUploadLimits::errorMessage()];
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

            $this->trackNewPhotoInGalleryOrder($userId, $filename, $folderId);
            $fullPath = Storage::disk($this->disk())->path($stored);

            $photo = [
                'filename' => $filename,
                'url' => $this->storage->photoUrl($userId, $filename),
                'path' => $stored,
                'timestamp' => is_file($fullPath) ? filemtime($fullPath) : time(),
            ];

            if ($this->galleryFolders->enabled()) {
                $photo['folder_id'] = $this->galleryFolders->folderIdForPhoto($userId, $filename);
            }

            return [
                'success' => true,
                'photo' => $photo,
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function createBlankCanvas(
        int $width = 1600,
        int $height = 1200,
        string $background = '#ffffff',
        string|int $userId = 0,
        ?string $folderId = null
    ): array {
        try {
            if ($limitError = $this->ensureGalleryHasRoom($userId)) {
                return $limitError;
            }

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

            $this->trackNewPhotoInGalleryOrder($userId, $filename, $folderId);
            $fullPath = Storage::disk($this->disk())->path($path);

            $photo = [
                'filename' => $filename,
                'url' => $this->storage->photoUrl($userId, $filename),
                'path' => $path,
                'timestamp' => is_file($fullPath) ? filemtime($fullPath) : time(),
                'is_blank_canvas' => true,
            ];

            if ($this->galleryFolders->enabled()) {
                $photo['folder_id'] = $this->galleryFolders->folderIdForPhoto($userId, $filename);
            }

            return [
                'success' => true,
                'filename' => $filename,
                'url' => $this->storage->photoUrl($userId, $filename),
                'photo' => $photo,
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function duplicatePhoto(string $filename, string|int $userId): array
    {
        try {
            if ($limitError = $this->ensureGalleryHasRoom($userId)) {
                return $limitError;
            }

            $safeName = $this->storage->safeFilename($filename);
            $filepath = $this->storage->filePath($userId, $safeName);

            if (! Storage::disk($this->disk())->exists($filepath)) {
                return ['error' => 'Arquivo não encontrado'];
            }

            $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $extension = 'jpg';
            }

            $isBlankCanvas = str_starts_with($safeName, 'canvas_');
            $prefix = $isBlankCanvas ? 'canvas_' : 'photo_';
            $newFilename = $prefix.time().'_'.uniqid().'.'.$extension;
            $newPath = $this->storage->filePath($userId, $newFilename);

            if (! Storage::disk($this->disk())->copy($filepath, $newPath)) {
                return ['error' => 'Falha ao duplicar a foto'];
            }

            $folderId = $this->galleryFolders->folderIdForPhoto($userId, $safeName);
            if ($this->storage->readGalleryOrder($userId) !== []) {
                $this->storage->appendToGalleryOrder($userId, $newFilename);
            }
            $this->galleryFolders->assignNewPhoto($userId, $newFilename, $folderId);
            $fullPath = Storage::disk($this->disk())->path($newPath);

            $photo = [
                'filename' => $newFilename,
                'url' => $this->storage->photoUrl($userId, $newFilename),
                'path' => $newPath,
                'timestamp' => is_file($fullPath) ? filemtime($fullPath) : time(),
                'is_blank_canvas' => $isBlankCanvas,
            ];

            if ($this->galleryFolders->enabled()) {
                $photo['folder_id'] = $this->galleryFolders->folderIdForPhoto($userId, $newFilename);
            }

            return [
                'success' => true,
                'photo' => $photo,
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
                $this->galleryFolders->removePhoto($userId, $filename);

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
    public function reorderPhotos(string|int $userId, array $filenames, ?string $folderId = null): array
    {
        try {
            $filenames = array_values(array_unique(array_filter(array_map(
                fn ($name) => $this->storage->safeFilename((string) $name),
                $filenames
            ))));

            if ($filenames === []) {
                return ['error' => 'Nenhum ficheiro indicado para ordenar.'];
            }

            if ($this->galleryFolders->enabled()) {
                if ($folderId === null || $folderId === '') {
                    return ['error' => 'Indique a pasta para ordenar.'];
                }

                $this->galleryFolders->reorderPhotosInFolder($userId, $folderId, $filenames);

                $existing = $this->listPhotosUnsorted($userId);

                if (isset($existing['error'])) {
                    return ['error' => $existing['error']];
                }

                $photos = $this->galleryFolders->sortPhotos($userId, $existing['photos'] ?? []);

                return [
                    'success' => true,
                    'photos' => $photos,
                    'folders' => $this->galleryFolders->listFolders($userId),
                ];
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

    public function createGalleryFolder(string|int $userId, string $name, ?string $color = null): array
    {
        try {
            $folder = $this->galleryFolders->createFolder($userId, $name, $color);

            return [
                'success' => true,
                'folder' => $folder,
                'folders' => $this->galleryFolders->listFolders($userId),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function renameGalleryFolder(string|int $userId, string $folderId, string $name, ?string $color = null): array
    {
        try {
            $this->galleryFolders->renameFolder($userId, $folderId, $name, $color);

            return [
                'success' => true,
                'folders' => $this->galleryFolders->listFolders($userId),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function deleteGalleryFolder(string|int $userId, string $folderId): array
    {
        try {
            $filenames = $this->galleryFolders->listPhotoFilenamesInFolder($userId, $folderId);
            $deletedPhotosCount = 0;
            $photoErrors = [];

            if ($filenames !== []) {
                $deleteResult = $this->deletePhotos($filenames, $userId);
                $deletedPhotosCount = (int) ($deleteResult['deleted_count'] ?? 0);
                $photoErrors = $deleteResult['errors'] ?? [];

                if ($deletedPhotosCount === 0 && $photoErrors !== []) {
                    return [
                        'error' => 'Não foi possível eliminar as imagens da pasta.',
                        'errors' => $photoErrors,
                    ];
                }
            }

            $this->galleryFolders->deleteFolder($userId, $folderId);

            $payload = [
                'success' => true,
                'folders' => $this->galleryFolders->listFolders($userId),
                'deleted_photos_count' => $deletedPhotosCount,
            ];

            if ($photoErrors !== []) {
                $payload['partial'] = true;
                $payload['errors'] = $photoErrors;
            }

            return $payload;
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $filenames
     */
    public function movePhotosToFolder(string|int $userId, array $filenames, string $folderId): array
    {
        try {
            $this->galleryFolders->movePhotos($userId, $filenames, $folderId);

            return $this->getPhotos($userId);
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $folderIds
     */
    public function reorderGalleryFolders(string|int $userId, array $folderIds): array
    {
        try {
            $this->galleryFolders->reorderFolders($userId, $folderIds);

            return [
                'success' => true,
                'folders' => $this->galleryFolders->listFolders($userId),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
