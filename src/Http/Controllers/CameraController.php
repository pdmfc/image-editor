<?php

namespace PDMFC\ImageEditor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use PDMFC\ImageEditor\Services\CameraService;
use PDMFC\ImageEditor\Services\QrCodeService;
use PDMFC\ImageEditor\Services\UserPhotoStorage;

class CameraController extends Controller
{
    public function __construct(
        protected CameraService $cameraService,
        protected QrCodeService $qrCodeService,
        protected UserPhotoStorage $storage,
    ) {
    }

    public function getQrCode(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required',
        ]);

        $result = $this->qrCodeService->fetchQrCode($request->input('user_id'));

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], $result['status'] ?? 422);
        }

        if (isset($result['svg'])) {
            return response()->json(['svg' => $result['svg']]);
        }

        return response()->json(['qr_image' => $result['qr_image']]);
    }

    public function showPhoto(string $userId, string $filename): BinaryFileResponse
    {
        try {
            $userId = $this->storage->sanitizeUserId($userId);
            $filename = $this->storage->safeFilename($filename);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        if (! $this->storage->fileExists($userId, $filename)) {
            abort(404);
        }

        return response()->file($this->storage->storagePath($userId, $filename));
    }

    public function callbackFiles(Request $request, string $userId): JsonResponse
    {
        try {
            $userId = $this->storage->sanitizeUserId($userId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $result = $this->cameraService->storeCallbackFiles($userId, $request->all());

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json($result);
    }

    public function capture(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required',
            'photo' => 'required|string',
        ]);

        $result = $this->cameraService->capturePhoto($request, $request->input('user_id'));

        return response()->json($result, isset($result['error']) ? 422 : 200);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required',
            'photo' => 'required|image|max:25600',
            'folder_id' => 'nullable|string|max:64',
        ]);

        $result = $this->cameraService->uploadPhoto(
            $request->file('photo'),
            $request->input('user_id'),
            $request->input('folder_id')
        );

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function getPhotos(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required',
        ]);

        try {
            $result = $this->cameraService->getPhotos($request->input('user_id'));
        } catch (\Throwable $e) {
            Log::error('Erro ao listar fotos', ['message' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 500);
        }

        if (isset($result['error'])) {
            return response()->json($result, 500);
        }

        return response()->json($result);
    }

    public function createBlank(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required',
            'width' => 'sometimes|integer|min:400|max:8000',
            'height' => 'sometimes|integer|min:300|max:8000',
            'background' => 'sometimes|string|max:32',
            'folder_id' => 'nullable|string|max:64',
        ]);

        $result = $this->cameraService->createBlankCanvas(
            (int) $request->input('width', 1600),
            (int) $request->input('height', 1200),
            (string) $request->input('background', '#ffffff'),
            $request->input('user_id'),
            $request->input('folder_id')
        );

        if (isset($result['error'])) {
            return response()->json($result, 500);
        }

        return response()->json($result);
    }

    public function reorderPhotos(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required',
                'filenames' => 'required|array|min:1',
                'filenames.*' => 'string',
                'folder_id' => 'nullable|string',
            ]);

            $result = $this->cameraService->reorderPhotos(
                $request->input('user_id'),
                $request->input('filenames', []),
                $request->input('folder_id')
            );

            if (isset($result['error'])) {
                return response()->json($result, 422);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Erro ao ordenar fotos:', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function duplicatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required',
            'filename' => 'required|string',
        ]);

        $result = $this->cameraService->duplicatePhoto(
            $request->input('filename'),
            $request->input('user_id')
        );

        if (isset($result['error'])) {
            $status = $result['error'] === 'Arquivo não encontrado' ? 404 : 500;

            return response()->json($result, $status);
        }

        return response()->json($result);
    }

    public function deletePhoto(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required',
                'filename' => 'sometimes|required_without:filenames|string',
                'filenames' => 'sometimes|required_without:filename|array|min:1',
                'filenames.*' => 'string',
            ]);

            $userId = $request->input('user_id');

            if ($request->has('filenames')) {
                $result = $this->cameraService->deletePhotos(
                    $request->input('filenames', []),
                    $userId
                );

                if (isset($result['error']) && empty($result['deleted'])) {
                    return response()->json($result, 422);
                }

                if (! empty($result['errors'])) {
                    return response()->json($result, 207);
                }

                return response()->json($result);
            }

            $result = $this->cameraService->deletePhoto(
                (string) $request->input('filename'),
                $userId
            );

            if (isset($result['error'])) {
                $status = $result['error'] === 'Arquivo não encontrado' ? 404 : 500;

                return response()->json($result, $status);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir foto:', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createGalleryFolder(Request $request): JsonResponse
    {
        if (! config('image-editor.gallery.folders_enabled', false)) {
            return response()->json(['error' => 'Pastas da galeria desactivadas.'], 404);
        }

        $request->validate([
            'user_id' => 'required',
            'name' => 'required|string|max:80',
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $result = $this->cameraService->createGalleryFolder(
            $request->input('user_id'),
            (string) $request->input('name'),
            $request->input('color')
        );

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function renameGalleryFolder(Request $request, string $folderId): JsonResponse
    {
        if (! config('image-editor.gallery.folders_enabled', false)) {
            return response()->json(['error' => 'Pastas da galeria desactivadas.'], 404);
        }

        $request->validate([
            'user_id' => 'required',
            'name' => 'required|string|max:80',
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $result = $this->cameraService->renameGalleryFolder(
            $request->input('user_id'),
            $folderId,
            (string) $request->input('name'),
            $request->input('color')
        );

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function deleteGalleryFolder(Request $request, string $folderId): JsonResponse
    {
        if (! config('image-editor.gallery.folders_enabled', false)) {
            return response()->json(['error' => 'Pastas da galeria desactivadas.'], 404);
        }

        $request->validate([
            'user_id' => 'required',
        ]);

        $result = $this->cameraService->deleteGalleryFolder(
            $request->input('user_id'),
            $folderId
        );

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function movePhotosToFolder(Request $request): JsonResponse
    {
        if (! config('image-editor.gallery.folders_enabled', false)) {
            return response()->json(['error' => 'Pastas da galeria desactivadas.'], 404);
        }

        $request->validate([
            'user_id' => 'required',
            'folder_id' => 'required|string|max:64',
            'filenames' => 'required|array|min:1',
            'filenames.*' => 'string',
        ]);

        $result = $this->cameraService->movePhotosToFolder(
            $request->input('user_id'),
            $request->input('filenames', []),
            (string) $request->input('folder_id')
        );

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }
}
