<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Image Driver
    |--------------------------------------------------------------------------
    |
    | Intervention Image 4.x expects a driver class name. For the canonical
    | configuration (and publish command), see intervention/image-laravel.
    |
    */

    'driver' => env('IMAGE_DRIVER', \Intervention\Image\Drivers\Gd\Driver::class),

    'options' => [
        'autoOrientation' => true,
        'decodeAnimation' => true,
        'backgroundColor' => 'ffffff',
        'strip' => false,
    ],
];
