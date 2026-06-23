<?php

namespace PDMFC\ImageEditor\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GalleryAccess
{
    public static function enforcementEnabled(): bool
    {
        return (bool) config('image-editor.authorization.enforce_user_ownership', true);
    }

    public static function canAccessGallery(Request $request, string|int $requestedUserId): bool
    {
        if (! static::enforcementEnabled()) {
            return true;
        }

        $requestedUserId = (string) $requestedUserId;

        $authorize = config('image-editor.authorization.authorize')
            ?? config('image-editor.broadcasting.authorize');

        if (is_callable($authorize)) {
            return (bool) $authorize($request->user(), $requestedUserId);
        }

        $sessionKey = (string) config(
            'image-editor.broadcasting.session_user_id_key',
            'image_editor_broadcast_user_id'
        );

        if ($request->hasSession() && $request->session()->has($sessionKey)) {
            return (string) $request->session()->get($sessionKey) === $requestedUserId;
        }

        $user = $request->user();

        if ($user !== null) {
            $authId = $user->getAuthIdentifier();

            return $authId !== null && (string) $authId === $requestedUserId;
        }

        return false;
    }

    public static function denyGalleryAccessResponse(): Response
    {
        return response()->json([
            'error' => (string) config(
                'image-editor.authorization.denied_message',
                'Não autorizado a aceder a esta galeria.'
            ),
        ], (int) config('image-editor.authorization.denied_status', 403));
    }
}
