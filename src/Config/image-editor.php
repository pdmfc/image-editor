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
    ],

    'broadcasting' => [
        'enabled' => env('IMAGE_EDITOR_BROADCASTING', true),
        'session_user_id_key' => 'image_editor_broadcast_user_id',
        'authorize' => null,
    ],

    'gallery' => [
        'folders_enabled' => filter_var(
            env('IMAGE_EDITOR_GALLERY_FOLDERS', false),
            FILTER_VALIDATE_BOOL
        ),
    ],
];
