<?php

namespace PDMFC\ImageEditor\Services;

use Illuminate\Support\Facades\Http;
use PDMFC\ImageEditor\Support\CallbackRoute;
use PDMFC\ImageEditor\Support\GalleryFolders;
use PDMFC\ImageEditor\Support\GalleryUploadLimits;
use PDMFC\ImageEditor\Support\QrUploadFolder;

class QrCodeService
{
    public function __construct(
        protected UserPhotoStorage $storage,
        protected CameraService $cameraService,
        protected GalleryFolders $galleryFolders,
    ) {
    }

    public function fetchQrCode(string|int|null $userId, ?string $folderId = null): array
    {
        if ($userId === null || $userId === '') {
            return [
                'error' => 'ID do utilizador em falta.',
                'status' => 422,
            ];
        }

        $apiUrl = config('image-editor.qr_code.api_url');
        $bearer = config('image-editor.qr_code.api_bearer_token');
        $deliveryMode = (string) config('image-editor.qr_code.delivery_mode', 'callback_base64');

        if (! $apiUrl || ! $bearer) {
            return [
                'error' => 'Serviço de QR Code não configurado (QRCODE_URL, QRCODE_API_TOKEN).',
                'status' => 500,
            ];
        }

        if (! CallbackRoute::isConfigured()) {
            return [
                'error' => 'Callback QR não configurado (QRCODE_CALLBACK_URL).',
                'status' => 500,
            ];
        }

        $remainingSlots = $this->cameraService->galleryRemainingSlots($userId);

        if ($remainingSlots !== null && $remainingSlots <= 0) {
            $max = $this->cameraService->galleryMaxImages();

            return [
                'error' => "Limite da galeria atingido ({$max} imagens). Elimine imagens para adicionar novas.",
                'status' => 422,
            ];
        }

        $sanitizedUserId = $this->storage->sanitizeUserId($userId);
        $this->rememberQrUploadFolder($userId, $sanitizedUserId, $folderId);

        $payload = [
            'user_token' => $sanitizedUserId,
            'endpoint' => $this->storage->callbackUrl($userId),
            'delivery_mode' => $deliveryMode,
        ];

        if ($remainingSlots !== null && $remainingSlots > 0) {
            $payload['max_files'] = $remainingSlots;
        }

        $payload['max_upload_mb'] = GalleryUploadLimits::maxMegabytes();

        $response = Http::withToken($bearer)->post($apiUrl, $payload);

        if (! $response->successful()) {
            return [
                'error' => 'Falha ao obter QR Code do serviço externo.',
                'status' => $response->status(),
            ];
        }

        return $this->normalizeQrResponse($response->body());
    }

    private function rememberQrUploadFolder(string|int $userId, string $sanitizedUserId, ?string $folderId): void
    {
        if (! $this->galleryFolders->enabled()) {
            return;
        }

        $this->galleryFolders->ensureInitialized($userId);
        $folderId = is_string($folderId) && $folderId !== '' ? $folderId : GalleryFolders::SYSTEM_ENTRADA_ID;

        $valid = false;
        foreach ($this->galleryFolders->listFolders($userId) as $folder) {
            if (($folder['id'] ?? '') === $folderId) {
                $valid = true;
                break;
            }
        }

        if (! $valid) {
            $folderId = GalleryFolders::SYSTEM_ENTRADA_ID;
        }

        QrUploadFolder::remember($sanitizedUserId, $folderId);
    }

    private function normalizeQrResponse(string $body): array
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            return [
                'error' => 'Resposta vazia do serviço QR.',
                'status' => 502,
            ];
        }

        $json = json_decode($trimmed, true);
        if (is_array($json)) {
            foreach (['image', 'qr', 'data', 'content', 'base64', 'qr_image'] as $key) {
                if (! empty($json[$key]) && is_string($json[$key])) {
                    $trimmed = trim($json[$key]);
                    break;
                }
            }
        }

        if (str_contains($trimmed, '<svg')) {
            return ['svg' => $trimmed, 'status' => 200];
        }

        if (preg_match('#^data:image/[\w+.-]+;base64,#i', $trimmed)) {
            return ['qr_image' => $trimmed, 'status' => 200];
        }

        $b64 = preg_replace('#\s#', '', $trimmed);

        return [
            'qr_image' => 'data:image/png;base64,'.$b64,
            'status' => 200,
        ];
    }
}
