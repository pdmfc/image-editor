<?php

namespace PDMFC\ImageEditor\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDMFC\ImageEditor\Services\UserPhotoStorage;

class GalleryFolders
{
    public const SYSTEM_ENTRADA_ID = 'entrada';

    /** @var list<string> */
    private const FOLDER_COLORS = [
        '#6366F1',
        '#3B82F6',
        '#06B6D4',
        '#10B981',
        '#84CC16',
        '#EAB308',
        '#F97316',
        '#EF4444',
        '#EC4899',
        '#8B5CF6',
    ];

    public const SYSTEM_FOLDER_COLOR = '#94A3B8';

    public function __construct(
        protected UserPhotoStorage $storage,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('image-editor.gallery.folders_enabled', false);
    }

    public function metadataRelativePath(): string
    {
        return '.gallery-folders.json';
    }

    public function metadataPath(string|int $userId): string
    {
        return $this->storage->directory($userId).'/'.$this->metadataRelativePath();
    }

    /**
     * @return array{
     *     folders: list<array{id: string, name: string, system?: bool, color?: string}>,
     *     assignments: array<string, string>,
     *     folder_order: array<string, list<string>>
     * }
     */
    public function read(string|int $userId): array
    {
        $path = $this->metadataPath($userId);
        $disk = Storage::disk($this->storage->disk());

        if (! $disk->exists($path)) {
            return $this->defaultData();
        }

        $json = $disk->get($path);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return $this->defaultData();
        }

        $folders = [];
        foreach ($data['folders'] ?? [] as $folder) {
            if (! is_array($folder) || empty($folder['id']) || empty($folder['name'])) {
                continue;
            }
            $entry = [
                'id' => (string) $folder['id'],
                'name' => (string) $folder['name'],
            ];
            if (! empty($folder['system'])) {
                $entry['system'] = true;
            }
            $color = $this->normalizeColorOrNull($folder['color'] ?? null);
            if ($color !== null) {
                $entry['color'] = $color;
            } elseif (! empty($folder['system'])) {
                $entry['color'] = self::SYSTEM_FOLDER_COLOR;
            }
            $folders[] = $entry;
        }

        if ($folders === []) {
            $folders = $this->defaultData()['folders'];
        } elseif (! $this->hasFolderId($folders, self::SYSTEM_ENTRADA_ID)) {
            array_unshift($folders, $this->entradaFolder());
        }

        $this->assignMissingFolderColors($folders);

        $assignments = [];
        foreach ($data['assignments'] ?? [] as $filename => $folderId) {
            $safeName = $this->storage->safeFilename((string) $filename);
            $id = (string) $folderId;
            if ($safeName !== '' && $id !== '') {
                $assignments[$safeName] = $id;
            }
        }

        return [
            'folders' => $folders,
            'assignments' => $assignments,
            'folder_order' => $this->parseFolderOrder($data),
        ];
    }

    /**
     * @param  array{
     *     folders: list<array{id: string, name: string, system?: bool}>,
     *     assignments: array<string, string>,
     *     folder_order?: array<string, list<string>>
     * }  $data
     *
     * @throws \JsonException
     */
    public function write(string|int $userId, array $data): void
    {
        $this->storage->ensureDirectory($userId);

        Storage::disk($this->storage->disk())->put(
            $this->metadataPath($userId),
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );
    }

    public function ensureInitialized(string|int $userId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $path = $this->metadataPath($userId);
        $disk = Storage::disk($this->storage->disk());

        if (! $disk->exists($path)) {
            $this->write($userId, $this->defaultData());
        }
    }

    /**
     * @return list<array{id: string, name: string, system?: bool, color?: string}>
     */
    public function listFolders(string|int $userId): array
    {
        $this->ensureInitialized($userId);

        return $this->read($userId)['folders'];
    }

    public function folderIdForPhoto(string|int $userId, string $filename): string
    {
        if (! $this->enabled()) {
            return self::SYSTEM_ENTRADA_ID;
        }

        $this->ensureInitialized($userId);
        $filename = $this->storage->safeFilename($filename);
        $data = $this->read($userId);

        return $data['assignments'][$filename] ?? self::SYSTEM_ENTRADA_ID;
    }

    /**
     * @param  list<string>  $knownFilenames
     */
    public function syncAssignmentsForKnownPhotos(string|int $userId, array $knownFilenames): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->ensureInitialized($userId);
        $data = $this->read($userId);
        $changed = false;

        foreach ($knownFilenames as $filename) {
            $safeName = $this->storage->safeFilename((string) $filename);
            if ($safeName === '') {
                continue;
            }
            if (! isset($data['assignments'][$safeName])) {
                $data['assignments'][$safeName] = self::SYSTEM_ENTRADA_ID;
                $changed = true;
            }
        }

        if ($changed) {
            $this->write($userId, $data);
        }
    }

    public function assignNewPhoto(string|int $userId, string $filename, ?string $folderId = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->ensureInitialized($userId);
        $filename = $this->storage->safeFilename($filename);
        $folderId = $folderId ?: self::SYSTEM_ENTRADA_ID;
        $data = $this->read($userId);

        if (! $this->hasFolderId($data['folders'], $folderId)) {
            $folderId = self::SYSTEM_ENTRADA_ID;
        }

        $data['assignments'][$filename] = $folderId;
        $this->appendToFolderOrder($data, $folderId, $filename);
        $this->write($userId, $data);
    }

    public function assignDuplicateFromSource(string|int $userId, string $sourceFilename, string $newFilename): void
    {
        if (! $this->enabled()) {
            return;
        }

        $folderId = $this->folderIdForPhoto($userId, $sourceFilename);
        $this->assignNewPhoto($userId, $newFilename, $folderId);
    }

    public function removePhoto(string|int $userId, string $filename): void
    {
        if (! $this->enabled()) {
            return;
        }

        $filename = $this->storage->safeFilename($filename);
        $data = $this->read($userId);

        if (! isset($data['assignments'][$filename])) {
            return;
        }

        unset($data['assignments'][$filename]);
        $this->removeFromAllFolderOrders($data, $filename);
        $this->write($userId, $data);
    }

    /**
     * @param  list<array{filename: string, url: string, path: string, timestamp: int|float, is_blank_canvas: bool, folder_id?: string}>  $photos
     * @return list<array{filename: string, url: string, path: string, timestamp: int|float, is_blank_canvas: bool, folder_id?: string}>
     */
    public function sortPhotos(string|int $userId, array $photos): array
    {
        if (! $this->enabled() || $photos === []) {
            return $photos;
        }

        $data = $this->read($userId);
        $byFolder = [];

        foreach ($photos as $photo) {
            $folderId = (string) ($photo['folder_id'] ?? self::SYSTEM_ENTRADA_ID);
            $byFolder[$folderId][] = $photo;
        }

        $sorted = [];

        foreach ($data['folders'] as $folder) {
            $folderId = $folder['id'];
            $folderPhotos = $byFolder[$folderId] ?? [];

            if ($folderPhotos === []) {
                continue;
            }

            $sorted = array_merge(
                $sorted,
                $this->sortPhotosInFolder($folderId, $folderPhotos, $data['folder_order'])
            );
            unset($byFolder[$folderId]);
        }

        foreach ($byFolder as $folderPhotos) {
            usort(
                $folderPhotos,
                fn (array $a, array $b): int => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0)
            );
            $sorted = array_merge($sorted, $folderPhotos);
        }

        return $sorted;
    }

    /**
     * @param  list<string>  $knownFilenames
     */
    public function syncFolderOrdersForKnownPhotos(string|int $userId, array $knownFilenames): void
    {
        if (! $this->enabled()) {
            return;
        }

        $data = $this->read($userId);
        $changed = false;

        if (! isset($data['folder_order']) || ! is_array($data['folder_order'])) {
            $data['folder_order'] = [];
            $changed = true;
        }

        $byFolder = [];

        foreach ($knownFilenames as $filename) {
            $safeName = $this->storage->safeFilename((string) $filename);
            if ($safeName === '') {
                continue;
            }

            $folderId = $data['assignments'][$safeName] ?? self::SYSTEM_ENTRADA_ID;
            $byFolder[$folderId][] = $safeName;
        }

        foreach ($data['folders'] as $folder) {
            $folderId = $folder['id'];
            $inFolder = $byFolder[$folderId] ?? [];
            $existingOrder = $data['folder_order'][$folderId] ?? [];
            $ordered = array_values(array_filter(
                $existingOrder,
                fn (string $name): bool => in_array($name, $inFolder, true)
            ));
            $orderedSet = array_flip($ordered);

            foreach ($inFolder as $name) {
                if (! isset($orderedSet[$name])) {
                    $ordered[] = $name;
                }
            }

            if ($ordered !== ($data['folder_order'][$folderId] ?? [])) {
                $data['folder_order'][$folderId] = $ordered;
                $changed = true;
            }
        }

        foreach ($data['folder_order'] as $folderId => $order) {
            $filtered = array_values(array_filter(
                $order,
                fn (string $name): bool => ($data['assignments'][$name] ?? self::SYSTEM_ENTRADA_ID) === $folderId
            ));

            if ($filtered !== $order) {
                $data['folder_order'][$folderId] = $filtered;
                $changed = true;
            }
        }

        if ($changed) {
            $this->write($userId, $data);
        }
    }

    /**
     * @param  list<string>  $filenames
     */
    public function reorderPhotosInFolder(string|int $userId, string $folderId, array $filenames): void
    {
        if (! $this->enabled()) {
            throw new \RuntimeException('Pastas da galeria desactivadas.');
        }

        $this->ensureInitialized($userId);
        $data = $this->read($userId);

        if (! $this->hasFolderId($data['folders'], $folderId)) {
            throw new \InvalidArgumentException('Pasta não encontrada.');
        }

        $filenames = array_values(array_unique(array_filter(array_map(
            fn ($name) => $this->storage->safeFilename((string) $name),
            $filenames
        ))));

        if ($filenames === []) {
            throw new \InvalidArgumentException('Nenhum ficheiro indicado para ordenar.');
        }

        $inFolder = [];

        foreach ($data['assignments'] as $filename => $assignedId) {
            if ($assignedId === $folderId) {
                $inFolder[] = $filename;
            }
        }

        $inFolderSet = array_flip($inFolder);

        foreach ($filenames as $filename) {
            if (! isset($inFolderSet[$filename])) {
                throw new \InvalidArgumentException("Ficheiro não pertence à pasta: {$filename}");
            }
        }

        if (count($filenames) !== count($inFolder)) {
            throw new \InvalidArgumentException('A lista de ordenação tem de incluir todas as imagens da pasta.');
        }

        $data['folder_order'][$folderId] = $filenames;
        $this->write($userId, $data);
    }

    /**
     * @param  list<string>  $filenames
     */
    public function movePhotos(string|int $userId, array $filenames, string $folderId): void
    {
        if (! $this->enabled()) {
            throw new \RuntimeException('Pastas da galeria desactivadas.');
        }

        $this->ensureInitialized($userId);
        $data = $this->read($userId);

        if (! $this->hasFolderId($data['folders'], $folderId)) {
            throw new \InvalidArgumentException('Pasta não encontrada.');
        }

        foreach ($filenames as $filename) {
            $safeName = $this->storage->safeFilename((string) $filename);
            if ($safeName === '') {
                continue;
            }
            if (! $this->storage->fileExists($userId, $safeName)) {
                throw new \InvalidArgumentException("Ficheiro não encontrado: {$safeName}");
            }

            $previousFolderId = $data['assignments'][$safeName] ?? self::SYSTEM_ENTRADA_ID;

            if ($previousFolderId !== $folderId) {
                $this->removeFromFolderOrder($data, $previousFolderId, $safeName);
                $data['assignments'][$safeName] = $folderId;
                $this->appendToFolderOrder($data, $folderId, $safeName);
            }
        }

        $this->write($userId, $data);
    }

    /**
     * @return array{id: string, name: string, color: string}
     */
    public function createFolder(string|int $userId, string $name, ?string $color = null): array
    {
        if (! $this->enabled()) {
            throw new \RuntimeException('Pastas da galeria desactivadas.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Nome da pasta em falta.');
        }
        if (mb_strlen($name) > 80) {
            throw new \InvalidArgumentException('Nome da pasta demasiado longo.');
        }

        $this->ensureInitialized($userId);
        $data = $this->read($userId);
        $id = 'f_'.Str::lower(Str::random(10));

        while ($this->hasFolderId($data['folders'], $id)) {
            $id = 'f_'.Str::lower(Str::random(10));
        }

        $resolvedColor = $color !== null && $color !== ''
            ? $this->normalizeColor($color)
            : $this->defaultColorForNewFolder($data['folders']);

        $folder = ['id' => $id, 'name' => $name, 'color' => $resolvedColor];
        $data['folders'][] = $folder;
        $this->write($userId, $data);

        return $folder;
    }

    public function renameFolder(string|int $userId, string $folderId, string $name, ?string $color = null): void
    {
        if (! $this->enabled()) {
            throw new \RuntimeException('Pastas da galeria desactivadas.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Nome da pasta em falta.');
        }
        if (mb_strlen($name) > 80) {
            throw new \InvalidArgumentException('Nome da pasta demasiado longo.');
        }

        $this->ensureInitialized($userId);
        $data = $this->read($userId);
        $found = false;

        foreach ($data['folders'] as &$folder) {
            if ($folder['id'] === $folderId) {
                $folder['name'] = $name;
                if ($color !== null && $color !== '') {
                    $folder['color'] = $this->normalizeColor($color);
                }
                $found = true;
                break;
            }
        }
        unset($folder);

        if (! $found) {
            throw new \InvalidArgumentException('Pasta não encontrada.');
        }

        $this->write($userId, $data);
    }

    public function deleteFolder(string|int $userId, string $folderId): void
    {
        if (! $this->enabled()) {
            throw new \RuntimeException('Pastas da galeria desactivadas.');
        }

        if ($folderId === self::SYSTEM_ENTRADA_ID) {
            throw new \InvalidArgumentException('A pasta Entrada não pode ser eliminada.');
        }

        $this->ensureInitialized($userId);
        $data = $this->read($userId);
        $data['folders'] = array_values(array_filter(
            $data['folders'],
            fn (array $folder): bool => $folder['id'] !== $folderId
        ));

        foreach ($data['assignments'] as $filename => $assignedId) {
            if ($assignedId === $folderId) {
                $data['assignments'][$filename] = self::SYSTEM_ENTRADA_ID;
                $this->removeFromFolderOrder($data, $folderId, $filename);
                $this->appendToFolderOrder($data, self::SYSTEM_ENTRADA_ID, $filename);
            }
        }

        unset($data['folder_order'][$folderId]);

        $this->write($userId, $data);
    }

    /**
     * @return array{
     *     folders: list<array{id: string, name: string, system?: bool, color?: string}>,
     *     assignments: array<string, string>,
     *     folder_order: array<string, list<string>>
     * }
     */
    private function defaultData(): array
    {
        return [
            'folders' => [$this->entradaFolder()],
            'assignments' => [],
            'folder_order' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    private function parseFolderOrder(array $data): array
    {
        $folderOrder = [];

        foreach ($data['folder_order'] ?? [] as $folderId => $filenames) {
            if (! is_array($filenames)) {
                continue;
            }

            $ordered = [];

            foreach ($filenames as $filename) {
                $safeName = $this->storage->safeFilename((string) $filename);
                if ($safeName !== '') {
                    $ordered[] = $safeName;
                }
            }

            if ($ordered !== []) {
                $folderOrder[(string) $folderId] = $ordered;
            }
        }

        return $folderOrder;
    }

    /**
     * @param  array<string, list<string>>  $folderOrder
     * @param  list<array{filename: string, url: string, path: string, timestamp: int|float, is_blank_canvas: bool, folder_id?: string}>  $photos
     * @return list<array{filename: string, url: string, path: string, timestamp: int|float, is_blank_canvas: bool, folder_id?: string}>
     */
    private function sortPhotosInFolder(string $folderId, array $photos, array $folderOrder): array
    {
        $order = $folderOrder[$folderId] ?? [];

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
     * @param  array{
     *     folders: list<array{id: string, name: string, system?: bool}>,
     *     assignments: array<string, string>,
     *     folder_order?: array<string, list<string>>
     * }  $data
     */
    private function appendToFolderOrder(array &$data, string $folderId, string $filename): void
    {
        if (! isset($data['folder_order']) || ! is_array($data['folder_order'])) {
            $data['folder_order'] = [];
        }

        $order = $data['folder_order'][$folderId] ?? [];

        if (! in_array($filename, $order, true)) {
            $order[] = $filename;
            $data['folder_order'][$folderId] = $order;
        }
    }

    /**
     * @param  array{
     *     folders: list<array{id: string, name: string, system?: bool}>,
     *     assignments: array<string, string>,
     *     folder_order?: array<string, list<string>>
     * }  $data
     */
    private function removeFromFolderOrder(array &$data, string $folderId, string $filename): void
    {
        if (! isset($data['folder_order'][$folderId])) {
            return;
        }

        $data['folder_order'][$folderId] = array_values(array_filter(
            $data['folder_order'][$folderId],
            fn (string $name): bool => $name !== $filename
        ));
    }

    /**
     * @param  array{
     *     folders: list<array{id: string, name: string, system?: bool}>,
     *     assignments: array<string, string>,
     *     folder_order?: array<string, list<string>>
     * }  $data
     */
    private function removeFromAllFolderOrders(array &$data, string $filename): void
    {
        if (! isset($data['folder_order']) || ! is_array($data['folder_order'])) {
            return;
        }

        foreach ($data['folder_order'] as $folderId => $order) {
            $this->removeFromFolderOrder($data, (string) $folderId, $filename);
        }
    }

    /**
     * @return array{id: string, name: string, system: true, color: string}
     */
    private function entradaFolder(): array
    {
        return [
            'id' => self::SYSTEM_ENTRADA_ID,
            'name' => 'Entrada',
            'system' => true,
            'color' => self::SYSTEM_FOLDER_COLOR,
        ];
    }

    /**
     * @param  list<array{id: string, name: string, system?: bool, color?: string}>  $folders
     */
    private function assignMissingFolderColors(array &$folders): void
    {
        $userIndex = 0;

        foreach ($folders as &$folder) {
            if (! empty($folder['system'])) {
                if (empty($folder['color'])) {
                    $folder['color'] = self::SYSTEM_FOLDER_COLOR;
                }
                continue;
            }

            if (empty($folder['color'])) {
                $folder['color'] = self::FOLDER_COLORS[$userIndex % count(self::FOLDER_COLORS)];
                $userIndex++;
            }
        }
        unset($folder);
    }

    private function normalizeColor(string $color): string
    {
        $color = trim($color);
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new \InvalidArgumentException('Cor inválida.');
        }

        return strtoupper($color);
    }

    private function normalizeColorOrNull(mixed $color): ?string
    {
        if (! is_string($color) || trim($color) === '') {
            return null;
        }

        try {
            return $this->normalizeColor($color);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  list<array{id: string, name: string, system?: bool, color?: string}>  $folders
     */
    private function defaultColorForNewFolder(array $folders): string
    {
        $userFolders = array_values(array_filter(
            $folders,
            fn (array $folder): bool => empty($folder['system'])
        ));
        $index = count($userFolders) % count(self::FOLDER_COLORS);

        return self::FOLDER_COLORS[$index];
    }

    /**
     * @param  list<array{id: string, name: string, system?: bool, color?: string}>  $folders
     */
    private function hasFolderId(array $folders, string $folderId): bool
    {
        foreach ($folders as $folder) {
            if (($folder['id'] ?? '') === $folderId) {
                return true;
            }
        }

        return false;
    }
}
