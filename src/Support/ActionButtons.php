<?php

namespace PDMFC\ImageEditor\Support;

class ActionButtons
{
    public const ALL = ['upload', 'qrcode', 'camera', 'canvas'];

    private const ALIASES = [
        'upload' => 'upload',
        'qrcode' => 'qrcode',
        'qr' => 'qrcode',
        'qr-code' => 'qrcode',
        'camera' => 'camera',
        'canvas' => 'canvas',
        'blank' => 'canvas',
        'blank_canvas' => 'canvas',
    ];

    public static function parse(?string $value = null): array
    {
        if ($value === null || trim($value) === '') {
            return self::ALL;
        }

        $parsed = [];

        foreach (explode(',', $value) as $part) {
            $key = strtolower(trim($part));

            if ($key === '') {
                continue;
            }

            $normalized = self::ALIASES[$key] ?? null;

            if ($normalized !== null && ! in_array($normalized, $parsed, true)) {
                $parsed[] = $normalized;
            }
        }

        return $parsed !== [] ? $parsed : self::ALL;
    }
}
