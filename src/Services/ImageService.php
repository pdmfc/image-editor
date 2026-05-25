<?php

namespace PDMFC\ImageEditor\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Alignment;
use Intervention\Image\Direction;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Typography\FontFactory;

class ImageService
{
    public function __construct(
        protected UserPhotoStorage $storage,
    ) {
    }

    /** Máscara borracha: branco sólido (evita halo JPEG / antialiasing alargar a zona). */
    private const MASK_LUMINANCE_THRESHOLD = 200;

    private function resolveSourceFile(Request $data): array
    {
        $userId = $data->input('user_id');
        if ($userId === null || $userId === '') {
            throw new \Exception('user_id em falta.');
        }

        $filename = basename((string) $data->input('image_url'));
        $relative = $this->storage->filePath($userId, $filename);
        $sourcePath = $this->storage->storagePath($userId, $filename);

        if (! file_exists($sourcePath)) {
            throw new \Exception('Imagem não encontrada: '.$sourcePath);
        }

        return [
            'path' => $sourcePath,
            'relative' => $relative,
            'filename' => $filename,
            'user_id' => $userId,
        ];
    }

    private function maskPixelIsActive(int $rgb): bool
    {
        // GD: alpha 0 = opaco, 127 = transparente (truecolor PNG)
        $alpha = ($rgb & 0x7F000000) >> 24;
        if ($alpha >= 100) {
            return false;
        }

        return (($rgb >> 16) & 0xFF) >= self::MASK_LUMINANCE_THRESHOLD;
    }

    public function preview($data, $save = false)
    {

        try {

            $source = $this->resolveSourceFile($data);
            $sourcePath = $source['path'];
            $sourceFilename = $source['filename'];
            $userId = $source['user_id'];

            $image = Image::decodePath($sourcePath);

            try {
                $image->orient();
            } catch (\Throwable) {
                // Sem EXIF / orientação ou formato sem suporte — continua sem correcção
            }

            $rotation = (int) round((float) $data->input('rotation', 0));
            $image->brightness($data->input('brightness', 0))
                ->contrast($data->input('contrast', 0));
            $this->applySaturation($image, (float) $data->input('saturation', 0));
            $this->applyFilterPresetTone($image, $data->input('filter_preset'));

            $gammaAdj = min(100, max(-100, (int) round((float) $data->input('gamma', 0))));
            $gammaFactor = 1.0 + ($gammaAdj / 100.0) * 0.42;
            if (abs($gammaFactor - 1.0) > 0.012) {
                try {
                    $image->gamma(max(0.55, min(2.2, $gammaFactor)));
                } catch (\Throwable) {
                    //
                }
            }

            $gammaFine = min(50, max(-50, (int) round((float) $data->input('gamma_fine', 0))));
            $fineFactor = 1.0 + ($gammaFine / 50.0) * 0.14;
            if (abs($fineFactor - 1.0) > 0.01) {
                try {
                    $image->gamma(max(0.88, min(1.15, $fineFactor)));
                } catch (\Throwable) {
                    //
                }
            }

            if ($rotation !== 0) {
                $image->rotate($rotation);
            }

            $crop = $data->input('crop');
            if (is_array($crop)
                && ($crop['width'] ?? 0) >= 1
                && ($crop['height'] ?? 0) >= 1
            ) {
                $image->crop(
                    (int) $crop['width'],
                    (int) $crop['height'],
                    (int) $crop['x'],
                    (int) $crop['y']
                );
            }

            if ($data->boolean('flip_horizontal')) {
                $image->flip(Direction::HORIZONTAL);
            }

            if ($data->boolean('flip_vertical')) {
                $image->flip(Direction::VERTICAL);
            }

            $this->applyBlur($image, $data);
            $this->applyPixelate($image, $data);
            $this->applySharpen($image, $data);
            $this->applyImageOverlays($image, $data);
            (new DrawingApplicator())->apply($image, $data->input('drawings'));
            $this->applyWatermark($image, $data);
            $image = $this->composeZoomLayoutIfPresent($image, $data);

            if ($save) {

                if ($data->texts) {
                    foreach ($data->texts as $text) {
                        if (! is_array($text) || ! isset($text['content'])) {
                            continue;
                        }
                        $content = trim((string) $text['content']);
                        if ($content === '') {
                            continue;
                        }
                        $image->text(
                            $content,
                            (int) ($text['x'] ?? 0),
                            (int) ($text['y'] ?? 0),
                            function (FontFactory $font) use ($text, $image): void {
                                $this->configureTextFont($font, $text, $image);
                            }
                        );
                    }
                }

                $saveMode = $data->input('save_mode', 'overwrite') === 'copy' ? 'copy' : 'overwrite';
                $targetFilename = $sourceFilename;
                $writePath = $sourcePath;

                $outputFormat = $data->input('output_format', 'jpeg') === 'png' ? 'png' : 'jpeg';
                $outputQuality = min(100, max(1, (int) $data->input('output_quality', 85)));

                $this->storage->ensureDirectory($userId);

                if ($saveMode === 'copy') {
                    $extension = $outputFormat === 'png' ? 'png' : 'jpg';
                    $targetFilename = 'photo_' . time() . '_' . uniqid() . '.' . $extension;
                    $writePath = $this->storage->storagePath($userId, $targetFilename);
                } elseif ($outputFormat === 'png' && ! str_ends_with(strtolower($targetFilename), '.png')) {
                    $targetFilename = pathinfo($targetFilename, PATHINFO_FILENAME) . '.png';
                    $writePath = $this->storage->storagePath($userId, $targetFilename);
                }

                $encoder = $outputFormat === 'png'
                    ? new PngEncoder()
                    : new JpegEncoder(quality: $outputQuality);

                $image->encode($encoder)->save($writePath);

                $relativePath = $this->storage->filePath($userId, $targetFilename);

                return [
                    'success' => true,
                    'image_url' => $targetFilename,
                    'url' => $this->storage->photoUrl($userId, $targetFilename),
                    'save_mode' => $saveMode,
                ];
            }

            $base64Image = 'data:image/jpeg;base64,' . $image->encode(new JpegEncoder())->toBase64();

            return [
                'success' => true,
                'image_data' => $base64Image
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function save($data)
    {
        return $this->preview($data, true);
    }

    /**
     * Saturação: -1.0 (P&B) a +1.0 (mais vívido); 0 = sem alteração.
     * O slider do editor envia saturation/100.
     */
    private function applySaturation(ImageInterface $image, float $saturation): void
    {
        if (abs($saturation) < 0.005) {
            return;
        }

        if ($image->driver()->id() === 'Imagick') {
            $this->applySaturationImagick($image, $saturation);

            return;
        }

        $this->applySaturationGd($image, $saturation);
    }

    private function applySaturationImagick(ImageInterface $image, float $saturation): void
    {
        $mod = max(0, min(200, (int) round(100 + $saturation * 100)));

        foreach ($image as $frame) {
            try {
                $frame->native()->modulateImage(100, $mod, 100);
            } catch (\Throwable) {
                //
            }
        }
    }

    private function applySaturationGd(ImageInterface $image, float $saturation): void
    {
        if ($saturation <= -0.995) {
            $image->grayscale();

            return;
        }

        if ($saturation < 0) {
            $pct = (int) round(abs($saturation) * 100);
            if ($pct < 1) {
                return;
            }

            $gray = clone $image;
            $gray->grayscale();

            $frames = [];
            foreach ($image as $frame) {
                $frames[] = $frame;
            }
            $grayFrames = [];
            foreach ($gray as $frame) {
                $grayFrames[] = $frame;
            }

            foreach ($frames as $i => $frame) {
                $dst = $frame->native();
                $src = ($grayFrames[$i] ?? $grayFrames[0])->native();
                if ($dst instanceof \GdImage && $src instanceof \GdImage) {
                    imagecopymerge($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $pct);
                }
            }

            return;
        }

        $mult = 1.0 + $saturation;

        foreach ($image as $frame) {
            $gd = $frame->native();
            if (! $gd instanceof \GdImage) {
                continue;
            }

            $w = imagesx($gd);
            $h = imagesy($gd);

            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $rgb = imagecolorat($gd, $x, $y);
                    $a = ($rgb >> 24) & 0x7F;
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    $lum = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                    $r = max(0, min(255, (int) round($lum + ($r - $lum) * $mult)));
                    $g = max(0, min(255, (int) round($lum + ($g - $lum) * $mult)));
                    $b = max(0, min(255, (int) round($lum + ($b - $lum) * $mult)));
                    imagesetpixel($gd, $x, $y, imagecolorallocatealpha($gd, $r, $g, $b, $a));
                }
            }
        }
    }

    /**
     * Tons extra por preset (ex.: sépia) depois da saturação.
     */
    private function applyFilterPresetTone(ImageInterface $image, mixed $preset): void
    {
        if ($preset !== 'sepia') {
            return;
        }

        try {
            $image->colorize(32, 18, -28);
        } catch (\Throwable) {
            //
        }
    }

    /**
     * Nitidez: valores positivos aumentam nitidez; negativos suavizam (blur leve).
     */
    private function applySharpen(ImageInterface $image, Request $data): void
    {
        $level = min(100, max(-100, (int) $data->input('sharpen', 0)));
        if ($level === 0) {
            return;
        }

        try {
            if ($level > 0) {
                $image->sharpen(max(1, (int) round($level * 0.2)));
            } else {
                $image->blur(max(1, (int) round(abs($level) * 0.06)));
            }
        } catch (\Throwable) {
            //
        }
    }

    /**
     * Desfoque global, numa região (retângulo) ou só onde a máscara (borracha) indica.
     */
    private function applyBlur($image, Request $data): void
    {
        $blur = min(100, max(0, (int) $data->input('blur', 0)));
        $maskSrc = $data->input('blur_mask');
        $isBrush = $data->boolean('blur_brush');
        $hasMask = is_string($maskSrc) && strlen($maskSrc) > 80;

        if ($hasMask) {
            $strength = $blur > 0 ? min(100, $blur) : 12;
            $this->applyBlurWithMask($image, $maskSrc, $strength);

            return;
        }

        if ($blur <= 0) {
            return;
        }

        if ($isBrush) {
            return;
        }

        $region = $data->input('blur_region');
        if (! is_array($region)) {
            $image->blur($blur);

            return;
        }

        $x = (int) ($region['x'] ?? 0);
        $y = (int) ($region['y'] ?? 0);
        $w = (int) ($region['width'] ?? 0);
        $h = (int) ($region['height'] ?? 0);

        if ($w < 1 || $h < 1) {
            $image->blur($blur);

            return;
        }

        $x = max(0, min($image->width() - 1, $x));
        $y = max(0, min($image->height() - 1, $y));
        $w = min($w, $image->width() - $x);
        $h = min($h, $image->height() - $y);

        if ($w < 1 || $h < 1) {
            $image->blur($blur);

            return;
        }

        $patch = clone $image;
        $patch->crop($w, $h, $x, $y);
        $patch->blur($blur);
        $image->insert($patch, $x, $y, Alignment::TOP_LEFT);
    }

    private function applyBlurWithMask(ImageInterface $image, string $maskSrc, int $strength): void
    {
        $this->applyEffectWithMask(
            $image,
            $maskSrc,
            max(16, (int) ceil($strength * 0.9)),
            fn (ImageInterface $patch) => $patch->blur($strength)
        );
    }

    /**
     * Pixelização (tamanho do bloco em px, 1–100) global ou só numa região.
     *
     * @see https://image.intervention.io/v4/modifying-images/effects#pixelation-effect
     */
    private function applyPixelate($image, Request $data): void
    {
        $level = min(100, max(0, (int) $data->input('pixelate', 0)));
        $maskSrc = $data->input('pixelate_mask');
        $isBrush = $data->boolean('pixelate_brush');
        $hasMask = is_string($maskSrc) && strlen($maskSrc) > 80;

        if ($hasMask) {
            $tile = $level > 0 ? max(1, min(100, $level)) : 1;
            $this->applyPixelateWithMask($image, $maskSrc, $tile);

            return;
        }

        if ($level <= 0) {
            return;
        }

        $tile = max(1, min(100, $level));

        if ($isBrush) {
            return;
        }

        $region = $data->input('pixelate_region');
        if (! is_array($region)) {
            $image->pixelate($tile);

            return;
        }

        $x = (int) ($region['x'] ?? 0);
        $y = (int) ($region['y'] ?? 0);
        $w = (int) ($region['width'] ?? 0);
        $h = (int) ($region['height'] ?? 0);

        if ($w < 1 || $h < 1) {
            $image->pixelate($tile);

            return;
        }

        $x = max(0, min($image->width() - 1, $x));
        $y = max(0, min($image->height() - 1, $y));
        $w = min($w, $image->width() - $x);
        $h = min($h, $image->height() - $y);

        if ($w < 1 || $h < 1) {
            $image->pixelate($tile);

            return;
        }

        $patch = clone $image;
        $patch->crop($w, $h, $x, $y);
        $patch->pixelate($tile);
        $image->insert($patch, $x, $y, Alignment::TOP_LEFT);
    }

    private function applyPixelateWithMask(ImageInterface $image, string $maskSrc, int $tile): void
    {
        $this->applyEffectWithMask(
            $image,
            $maskSrc,
            max(6, (int) ceil($tile * 2)),
            fn (ImageInterface $patch) => $patch->pixelate($tile)
        );
    }

    /**
     * Efeito (desfoque, pixelização, …) só onde a máscara indica — traço tipo borracha no cliente.
     *
     * @param  callable(ImageInterface): void  $applyEffectToPatch
     */
    private function applyEffectWithMask(
        ImageInterface $image,
        string $maskSrc,
        int $pad,
        callable $applyEffectToPatch
    ): void {
        try {
            $maskImage = str_starts_with($maskSrc, 'data:')
                ? Image::decodeDataUri($maskSrc)
                : Image::decodeBase64($maskSrc);
        } catch (\Throwable) {
            return;
        }

        $iw = $image->width();
        $ih = $image->height();
        if ($iw < 1 || $ih < 1) {
            return;
        }

        try {
            $maskImage->resize($iw, $ih);
        } catch (\Throwable) {
            return;
        }

        $maskGd = $maskImage->core()->native();
        if (! $maskGd instanceof \GdImage) {
            return;
        }

        $minX = $iw;
        $minY = $ih;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $ih; $y += 3) {
            for ($x = 0; $x < $iw; $x += 3) {
                $rgb = @imagecolorat($maskGd, $x, $y);
                if ($rgb === false) {
                    continue;
                }
                if ($this->maskPixelIsActive($rgb)) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        if ($maxX < 0) {
            return;
        }

        $bx = max(0, $minX - $pad);
        $by = max(0, $minY - $pad);
        $bw = min($iw - $bx, $maxX - $minX + 2 * $pad + 4);
        $bh = min($ih - $by, $maxY - $minY + 2 * $pad + 4);

        if ($bw < 1 || $bh < 1) {
            return;
        }

        try {
            $patchOrig = clone $image;
            $patchOrig->crop($bw, $bh, $bx, $by);
            $patchEffect = clone $patchOrig;
            $applyEffectToPatch($patchEffect);
        } catch (\Throwable) {
            return;
        }

        $gdP = $patchEffect->core()->native();
        $baseGd = $image->core()->native();

        if (! $gdP instanceof \GdImage || ! $baseGd instanceof \GdImage) {
            return;
        }

        for ($yy = 0; $yy < $bh; $yy++) {
            for ($xx = 0; $xx < $bw; $xx++) {
                $ix = $bx + $xx;
                $iy = $by + $yy;
                $rgbM = @imagecolorat($maskGd, $ix, $iy);
                if ($rgbM === false) {
                    continue;
                }
                if ($this->maskPixelIsActive($rgbM)) {
                    $c = imagecolorat($gdP, $xx, $yy);
                    imagesetpixel($baseGd, $ix, $iy, $c);
                }
            }
        }
    }

    /**
     * Composição "detalhe ampliado": fundo branco, imagem principal reduzida e recortes posicionados.
     */
    private function composeZoomLayoutIfPresent(ImageInterface $image, Request $data): ImageInterface
    {
        $layout = $data->input('zoom_layout');
        if (! is_array($layout)) {
            return $image;
        }

        $callouts = $layout['callouts'] ?? [];
        if (! is_array($callouts) || $callouts === []) {
            return $image;
        }

        $base = $layout['base'] ?? null;
        if (! is_array($base)) {
            return $image;
        }

        $canvasW = max(1, (int) ($layout['canvas_width'] ?? $image->width()));
        $canvasH = max(1, (int) ($layout['canvas_height'] ?? $image->height()));
        $bx = max(0, (int) ($base['x'] ?? 0));
        $by = max(0, (int) ($base['y'] ?? 0));
        $bw = max(2, (int) ($base['width'] ?? $image->width()));
        $bh = max(2, (int) ($base['height'] ?? $image->height()));

        $background = is_string($layout['background'] ?? null) ? (string) $layout['background'] : '#ffffff';

        try {
            $canvas = Image::createImage($canvasW, $canvasH);
            $canvas->fill($background);

            $baseLayer = Image::decode($image->encode(new JpegEncoder())->toString());
            $baseLayer->resize($bw, $bh);
            $canvas->insert($baseLayer, $bx, $by, Alignment::TOP_LEFT);

            foreach (array_slice($callouts, 0, 12) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $src = $item['src'] ?? null;
                if (! is_string($src) || strlen($src) > 6_500_000) {
                    continue;
                }

                try {
                    $piece = str_starts_with($src, 'data:')
                        ? Image::decodeDataUri($src)
                        : Image::decodeBase64($src);
                } catch (\Throwable) {
                    continue;
                }

                $tx = (int) ($item['x'] ?? 0);
                $ty = (int) ($item['y'] ?? 0);
                $tw = (int) ($item['width'] ?? 0);
                $th = (int) ($item['height'] ?? 0);

                if ($tw < 2 || $th < 2) {
                    continue;
                }

                $tx = max(0, min($canvasW - 1, $tx));
                $ty = max(0, min($canvasH - 1, $ty));
                $tw = min($tw, $canvasW - $tx);
                $th = min($th, $canvasH - $ty);

                if ($tw < 2 || $th < 2) {
                    continue;
                }

                try {
                    $piece->resize($tw, $th);
                    $canvas->insert($piece, $tx, $ty, Alignment::TOP_LEFT);
                } catch (\Throwable) {
                    continue;
                }
            }

            return $canvas;
        } catch (\Throwable) {
            return $image;
        }
    }

    /**
     * Marca de água (texto ou imagem) num canto da foto.
     */
    private function applyWatermark(ImageInterface $image, Request $data): void
    {
        $wm = $data->input('watermark');
        if (! is_array($wm) || empty($wm['enabled'])) {
            return;
        }

        $position = (string) ($wm['position'] ?? 'bottom-right');
        if (! in_array($position, ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'], true)) {
            $position = 'bottom-right';
        }

        $margin = max(0, min(200, (int) ($wm['margin'] ?? 16)));
        $opacityPct = max(5, min(100, (int) ($wm['opacity'] ?? 45)));
        $alpha = $opacityPct / 100.0;

        $type = (string) ($wm['type'] ?? 'text');
        if ($type === 'image') {
            $this->applyImageWatermark($image, $wm, $position, $margin, $alpha);

            return;
        }

        $this->applyTextWatermark($image, $wm, $position, $margin, $opacityPct);
    }

    /**
     * @param  array<string, mixed>  $wm
     */
    private function applyTextWatermark(
        ImageInterface $image,
        array $wm,
        string $position,
        int $margin,
        int $opacityPct
    ): void {
        $content = trim((string) ($wm['text'] ?? ''));
        if ($content === '') {
            return;
        }

        $sizePct = max(2, min(25, (int) ($wm['size'] ?? 4)));
        $minDim = min($image->width(), $image->height());
        $fontPx = max(10.0, $minDim * ($sizePct / 100.0));
        $box = $this->estimateTextBox($content, $fontPx);
        [$x, $y] = $this->watermarkBoxOrigin(
            $image->width(),
            $image->height(),
            $box['width'],
            $box['height'],
            $position,
            $margin
        );

        $textPayload = [
            'content' => $content,
            'size' => $fontPx,
            'color' => $this->colorWithOpacity((string) ($wm['color'] ?? '#ffffff'), $opacityPct),
            'align' => 'left',
        ];

        $image->text(
            $content,
            $x,
            $y,
            function (FontFactory $font) use ($textPayload, $image): void {
                $this->configureTextFont($font, $textPayload, $image);
            }
        );
    }

    /**
     * @param  array<string, mixed>  $wm
     */
    private function applyImageWatermark(
        ImageInterface $image,
        array $wm,
        string $position,
        int $margin,
        float $alpha
    ): void {
        $src = $wm['src'] ?? null;
        if (! is_string($src) || strlen($src) > 6_500_000) {
            return;
        }

        try {
            $overlay = str_starts_with($src, 'data:')
                ? Image::decodeDataUri($src)
                : Image::decodeBase64($src);
        } catch (\Throwable) {
            return;
        }

        $scalePct = max(5, min(60, (int) ($wm['image_scale'] ?? 20)));
        $tw = max(8, (int) round($image->width() * ($scalePct / 100.0)));
        $th = max(8, (int) round($tw * ($overlay->height() / max(1, $overlay->width()))));
        $tw = min($tw, $image->width());
        $th = min($th, $image->height());

        try {
            $overlay->resize($tw, $th);
        } catch (\Throwable) {
            return;
        }

        [$x, $y] = $this->watermarkBoxOrigin(
            $image->width(),
            $image->height(),
            $tw,
            $th,
            $position,
            $margin
        );

        try {
            $image->insert($overlay, $x, $y, Alignment::TOP_LEFT, $alpha);
        } catch (\Throwable) {
            //
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function watermarkBoxOrigin(
        int $imgW,
        int $imgH,
        int $boxW,
        int $boxH,
        string $position,
        int $margin
    ): array {
        $boxW = max(1, $boxW);
        $boxH = max(1, $boxH);
        $m = max(0, $margin);

        return match ($position) {
            'top-left' => [$m, $m],
            'top-right' => [max(0, $imgW - $boxW - $m), $m],
            'bottom-left' => [$m, max(0, $imgH - $boxH - $m)],
            'center' => [
                max(0, (int) floor(($imgW - $boxW) / 2)),
                max(0, (int) floor(($imgH - $boxH) / 2)),
            ],
            default => [max(0, $imgW - $boxW - $m), max(0, $imgH - $boxH - $m)],
        };
    }

    /**
     * @return array{width: int, height: int}
     */
    private function estimateTextBox(string $content, float $fontSize): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [''];
        $lineHeight = $fontSize * 1.3;
        $maxW = 0.0;

        foreach ($lines as $line) {
            $len = mb_strlen($line);
            $maxW = max($maxW, $len * $fontSize * 0.55);
        }

        return [
            'width' => (int) ceil($maxW),
            'height' => (int) ceil(count($lines) * $lineHeight),
        ];
    }

    private function colorWithOpacity(string $color, int $opacityPercent): string
    {
        $opacityPercent = max(5, min(100, $opacityPercent));

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $color, $matches)) {
            $alpha = (int) round(255 * $opacityPercent / 100);

            return '#'.$matches[1].sprintf('%02x', $alpha);
        }

        return $color;
    }

    /**
     * Incorpora imagens extra (data URI / base64) na foto, com posição e tamanho em px da imagem final.
     *
     * @see https://image.intervention.io/v4/modifying-images/inserting#insert-images
     */
    private function applyImageOverlays(ImageInterface $image, Request $data): void
    {
        $items = $data->input('image_overlays');
        if (! is_array($items)) {
            return;
        }

        $items = array_slice($items, 0, 20);

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $src = $item['src'] ?? null;
            if (! is_string($src) || strlen($src) > 6_500_000) {
                continue;
            }

            try {
                $overlay = str_starts_with($src, 'data:')
                    ? Image::decodeDataUri($src)
                    : Image::decodeBase64($src);
            } catch (\Throwable) {
                continue;
            }

            $tx = (int) ($item['x'] ?? 0);
            $ty = (int) ($item['y'] ?? 0);
            $tw = (int) ($item['width'] ?? 0);
            $th = (int) ($item['height'] ?? 0);

            if ($tw < 2 || $th < 2) {
                continue;
            }

            $tx = max(0, min($image->width() - 1, $tx));
            $ty = max(0, min($image->height() - 1, $ty));
            $tw = min($tw, $image->width() - $tx);
            $th = min($th, $image->height() - $ty);

            if ($tw < 2 || $th < 2) {
                continue;
            }

            try {
                $overlay->resize($tw, $th);
                $image->insert($overlay, $tx, $ty, Alignment::TOP_LEFT);
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function configureTextFont(FontFactory $font, array $text, ImageInterface $image): void
    {
        $fontPath = $this->resolveTextFontPath($text);
        if ($fontPath !== null) {
            $font->filename($fontPath);
        }

        $font->size($this->fontSizeForTextRendering($image, (float) ($text['size'] ?? 24)));
        $font->color((string) ($text['color'] ?? '#000000'));

        $angle = (float) ($text['angle'] ?? 0);
        if (abs($angle) > 0.01) {
            $font->angle($angle);
        }

        $align = (string) ($text['align'] ?? 'left');
        if (in_array($align, ['left', 'center', 'right'], true)) {
            $font->align($align, 'top');
        }

        $strokeWidth = (int) ($text['stroke_width'] ?? 0);
        $strokeColor = $text['stroke_color'] ?? null;
        if ($strokeWidth > 0 && is_string($strokeColor) && $strokeColor !== '') {
            $font->stroke($strokeColor, min(12, max(1, $strokeWidth)));
        }
    }

    /**
     * Caminho absoluto para um TTF usado em $image->text(). Sem isto, o driver GD
     * do Intervention usa fontes internas 1–5 e qualquer tamanho > 5 vira fonte 1 (minúsculo).
     */
    private function resolveTextFontPath(array $text): ?string
    {
        $bold = ! empty($text['bold']);

        $configured = $bold
            ? config('image-editor.text_font_bold')
            : config('image-editor.text_font');

        if (is_string($configured) && $configured !== '' && is_file($configured) && is_readable($configured)) {
            return $configured;
        }

        $candidates = $bold
            ? [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
                '/Library/Fonts/Arial Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            ]
            : [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/usr/share/fonts/TTF/DejaVuSans.ttf',
                '/Library/Fonts/Arial.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
            ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Tamanho em "px" da imagem natural alinhado ao overlay do editor.
     * O FontProcessor GD do Intervention multiplica por 0,76 antes do imagettftext;
     * compensamos para o valor efectivo bater com o pedido.
     */
    private function fontSizeForTextRendering(ImageInterface $image, float $naturalPx): float
    {
        $px = max(6.0, min(900.0, $naturalPx));

        if ($image->driver()->id() === 'GD') {
            return round($px / 0.76, 3);
        }

        return round($px, 3);
    }
}
