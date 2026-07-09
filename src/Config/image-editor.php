<?php

return [
    'storage' => [
        'disk' => 'public',
        'temp_path' => 'photos/tmp',
    ],

    'routes' => [
        'prefix' => env('IMAGE_EDITOR_ROUTES_PREFIX', 'api'),
        'callback_path' => env('IMAGE_EDITOR_CALLBACK_PATH'),
        'browser_middleware' => ['web'],
        'callback_middleware' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('IMAGE_EDITOR_CALLBACK_MIDDLEWARE', 'api'))
        ))),
    ],

    'name' => 'Image Editor',

    'demo_routes' => env('IMAGE_EDITOR_DEMO_ROUTES', false),

    'action_buttons' => env('IMAGE_EDITOR_ACTION_BUTTONS', env('ACTION_BUTTONS', 'upload,qrcode,camera,canvas')),

    'qr_code' => [
        'api_url' => env('QRCODE_URL'),
        'api_bearer_token' => env('QRCODE_API_TOKEN'),
        'delivery_mode' => env('QRCODE_DELIVERY_MODE', 'callback_base64'),
        // URL base do callback (obrigatória para QR). O {userId} é acrescentado em código.
        'callback_url' => env('QRCODE_CALLBACK_URL'),
        // Minutos em que a pasta escolhida ao abrir o QR se mantém para uploads do telemóvel.
        'upload_folder_ttl_minutes' => max(5, (int) env('IMAGE_EDITOR_QR_UPLOAD_FOLDER_TTL', 1440)),
    ],

    'broadcasting' => [
        'enabled' => env('IMAGE_EDITOR_BROADCASTING', true),
        'session_user_id_key' => 'image_editor_broadcast_user_id',
        'authorize' => null,
    ],

    'authorization' => [
        'enforce_user_ownership' => env('IMAGE_EDITOR_ENFORCE_USER_OWNERSHIP', true),
        'authorize' => null,
        'denied_message' => 'Não autorizado a aceder a esta galeria.',
        'denied_status' => 403,
    ],

    'gallery' => [
        'folders_enabled' => filter_var(
            env('IMAGE_EDITOR_GALLERY_FOLDERS', false),
            FILTER_VALIDATE_BOOL
        ),
        'max_images' => max(0, (int) env(
            'IMAGE_EDITOR_GALLERY_MAX_IMAGES',
            env('IMAGE_EDITOR_GALLERY_TOTAL', 50)
        )),
        'max_upload_mb' => max(1, (int) env(
            'IMAGE_EDITOR_GALLERY_MAX_UPLOAD_MB',
            env('IMAGE_EDITOR_MAX_UPLOAD_MB', 10)
        )),
    ],
];
