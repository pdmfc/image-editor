<?php

namespace PDMFC\ImageEditor\Services;

use Illuminate\Http\Request;
use Intervention\Image\Alignment;
use Intervention\Image\Direction;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Typography\FontFactory;
use PDMFC\ImageEditor\Support\GalleryFolders;

class ImageService
{
    public function __construct(
        protected UserPhotoStorage $storage,
        protected GalleryFolders $galleryFolders,
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
        $sourcePath = $this->storage->storagePath($userId, $filename);

        if (! file_exists($sourcePath)) {
            throw new \Exception('Imagem não encontrada: '.$sourcePath);
        }

        return [
            'path' => $sourcePath,
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
            $composeOverlays = $this->hasImageOverlays($data);

            if (! $composeOverlays) {
                $this->applyToneAdjustments($image, $data);
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

            $this->applyImageOverlays($image, $data);

            if ($composeOverlays) {
                $this->applyToneAdjustments($image, $data);
            }

            $this->applyBlur($image, $data);
            $this->applyPixelate($image, $data);
            $this->applySharpen($image, $data);
            (new DrawingApplicator())->apply($image, $data->input('drawings'));
            $this->applyWatermark($image, $data);
            $image = $this->applyPhotoCaptionBand($image, $data);

            if ($save) {

                if ($data->texts) {
                    foreach ($data->texts as $text) {
                        if (! is_array($text) || ! isset($text['content'])) {
                            continue;
                        }
                        $this->applyTextItem($image, $text);
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

                if ($saveMode === 'copy' && $this->galleryFolders->enabled()) {
                    $folderId = $data->input('save_copy_folder_id');
                    if (is_string($folderId) && $folderId !== '') {
                        $this->galleryFolders->assignNewPhoto($userId, $targetFilename, $folderId);
                    } else {
                        $this->galleryFolders->assignDuplicateFromSource(
                            $userId,
                            $sourceFilename,
                            $targetFilename
                        );
                    }

                    if ($this->storage->readGalleryOrder($userId) !== []) {
                        $this->storage->appendToGalleryOrder($userId, $targetFilename);
                    }
                }

                return [
                    'success' => true,
                    'image_url' => $targetFilename,
                    'url' => $this->storage->photoUrl($userId, $targetFilename),
                    'save_mode' => $saveMode,
                    'save_copy_folder_id' => $saveMode === 'copy'
                        ? (is_string($data->input('save_copy_folder_id')) ? $data->input('save_copy_folder_id') : null)
                        : null,
                ];
            }

            $overlayItems = $data->input('image_overlays');
            $previewQuality = is_array($overlayItems) && $overlayItems !== []
                ? 92
                : 85;
            $base64Image = 'data:image/jpeg;base64,' . $image->encode(new JpegEncoder(quality: $previewQuality))->toBase64();

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
     * Folha em branco com imagens coladas: brilho, contraste, saturação, filtros e gama.
     */
    private function applyToneAdjustments(ImageInterface $image, Request $data): void
    {
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
    }

    private function hasImageOverlays(Request $data): bool
    {
        $items = $data->input('image_overlays');

        return is_array($items) && $items !== [];
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
     * Aplica efeito à imagem inteira ou só numa região rectangular (coords naturais).
     *
     * @param  callable(ImageInterface): void  $applyGlobal
     * @param  callable(ImageInterface): void  $applyPatch
     */
    private function applyRegionEffect(
        ImageInterface $image,
        mixed $region,
        callable $applyGlobal,
        callable $applyPatch
    ): void {
        if (! is_array($region)) {
            $applyGlobal($image);

            return;
        }

        $x = (int) ($region['x'] ?? 0);
        $y = (int) ($region['y'] ?? 0);
        $w = (int) ($region['width'] ?? 0);
        $h = (int) ($region['height'] ?? 0);

        if ($w < 1 || $h < 1) {
            $applyGlobal($image);

            return;
        }

        $x = max(0, min($image->width() - 1, $x));
        $y = max(0, min($image->height() - 1, $y));
        $w = min($w, $image->width() - $x);
        $h = min($h, $image->height() - $y);

        if ($w < 1 || $h < 1) {
            $applyGlobal($image);

            return;
        }

        $patch = clone $image;
        $patch->crop($w, $h, $x, $y);
        $applyPatch($patch);
        $image->insert($patch, $x, $y, Alignment::TOP_LEFT);
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

        $this->applyRegionEffect(
            $image,
            $data->input('blur_region'),
            fn (ImageInterface $img) => $img->blur($blur),
            fn (ImageInterface $patch) => $patch->blur($blur)
        );
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

        $this->applyRegionEffect(
            $image,
            $data->input('pixelate_region'),
            fn (ImageInterface $img) => $img->pixelate($tile),
            fn (ImageInterface $patch) => $patch->pixelate($tile)
        );
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

    /**
     * Dimensões do bloco de texto como o driver GD do Intervention (align + valign top).
     *
     * @return array{width: int, height: int}
     */
    private function measureTextBlockBox(string $content, array $text, ImageInterface $image): array
    {
        $boxWidth = (int) ($text['box_width'] ?? 0);
        if ($boxWidth > 0) {
            $content = $this->wrapTextBlockToWidth($content, $boxWidth, $text, $image);
        }

        $fontPath = $this->resolveTextFontPath($text);
        $naturalPx = max(6.0, min(900.0, (float) ($text['size'] ?? 24)));

        if ($fontPath === null) {
            return $this->estimateTextBox($content, $naturalPx);
        }

        $nativeSize = $this->nativeFontSizeForRendering($image, $naturalPx);
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [''];
        $lineCount = max(1, count($lines));

        $blockWidth = 0;
        foreach ($lines as $line) {
            $blockWidth = max($blockWidth, $this->ttfTextWidth($fontPath, $nativeSize, $line));
        }

        $capHeight = $this->ttfTextHeight($fontPath, $nativeSize, 'T');
        $typographicalSize = $this->ttfTextHeight($fontPath, $nativeSize, 'Hy');
        $leading = (int) round($typographicalSize * 1.25 * 0.8);
        $blockHeight = $leading * ($lineCount - 1) + $capHeight;

        return [
            'width' => max(1, $blockWidth),
            'height' => max(1, $blockHeight),
        ];
    }

    private function nativeFontSizeForRendering(ImageInterface $image, float $naturalPx): float
    {
        $fontSize = $this->fontSizeForTextRendering($image, $naturalPx);

        if ($image->driver()->id() === 'GD') {
            return floatval(round($fontSize * 0.76, 6));
        }

        return $fontSize;
    }

    private function ttfTextWidth(string $fontPath, float $nativeSize, string $line): int
    {
        $box = imageftbbox($nativeSize, 0, $fontPath, $line !== '' ? $line : ' ');

        if ($box === false) {
            return 0;
        }

        return (int) abs($box[6] - $box[4]);
    }

    private function ttfTextHeight(string $fontPath, float $nativeSize, string $sample): int
    {
        $box = imageftbbox($nativeSize, 0, $fontPath, $sample);

        if ($box === false) {
            return 0;
        }

        return (int) abs($box[7] - $box[1]);
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
     * Legenda estilo Word por baixo da foto (faixa branca + texto centrado).
     */
    private function applyPhotoCaptionBand(ImageInterface $image, Request $data): ImageInterface
    {
        $cap = $data->input('photo_caption');
        if (! is_array($cap) || empty($cap['enabled'])) {
            return $image;
        }

        $config = $this->resolveCaptionConfigForCanvas($data);
        $number = max(1, (int) ($cap['number'] ?? 1));
        $description = trim((string) ($cap['description'] ?? ''));
        $captionText = $this->formatCaptionText($config, $number, $description);

        if ($captionText === '') {
            return $image;
        }

        try {
            return $this->composeImageWithCaptionBand($image, $captionText, $config);
        } catch (\Throwable) {
            return $image;
        }
    }

    /**
     * @return array{prefix: string, separator: string, font_size: float, band_padding: int, color: string, bold: bool, band_border_color: ?string, band_border_width: int}
     */
    private function resolveCaptionConfig(Request $data): array
    {
        $raw = $data->input('caption_settings');
        $settings = is_array($raw) ? $raw : [];
        $borderWidth = max(0, min(12, (int) ($settings['band_border_width'] ?? 0)));
        $borderColor = trim((string) ($settings['band_border_color'] ?? ''));

        return [
            'prefix' => trim((string) ($settings['prefix'] ?? 'Fig.')),
            'separator' => (string) ($settings['separator'] ?? ' — '),
            'font_size' => max(8.0, min(120.0, (float) ($settings['font_size'] ?? 14))),
            'band_padding' => max(4, min(80, (int) ($settings['band_padding'] ?? 10))),
            'color' => (string) ($settings['color'] ?? '#000000'),
            'bold' => ! empty($settings['bold']),
            'band_border_color' => $borderColor !== '' ? $borderColor : null,
            'band_border_width' => $borderWidth,
        ];
    }

    /**
     * @return array{prefix: string, separator: string, font_size: float, band_padding: int, color: string, bold: bool, band_border_color: ?string, band_border_width: int}
     */
    private function resolveCaptionConfigForCanvas(Request $data): array
    {
        $config = $this->resolveCaptionConfig($data);
        $settings = $data->input('caption_settings');
        if (! is_array($settings) || empty($settings['band_border_canvas'])) {
            $config['band_border_color'] = null;
            $config['band_border_width'] = 0;
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{prefix: string, separator: string, font_size: float, band_padding: int, color: string, bold: bool, band_border_color: ?string, band_border_width: int}
     */
    private function resolveCaptionConfigForOverlay(Request $data, array $item): array
    {
        $config = $this->resolveCaptionConfig($data);
        $settings = $data->input('caption_settings');
        $allOverlays = is_array($settings) && ! empty($settings['band_border_all_overlays']);
        $overlayBorder = ! empty($item['caption_band_border']);

        if (! $allOverlays && ! $overlayBorder) {
            $config['band_border_color'] = null;
            $config['band_border_width'] = 0;
        }

        return $config;
    }

    /**
     * @param  array{prefix: string, separator: string, font_size: float, band_padding: int, color: string, bold: bool, band_border_color: ?string, band_border_width: int}  $config
     */
    private function formatCaptionText(array $config, int $number, string $description): string
    {
        $prefix = trim($config['prefix']);
        $numPart = $prefix !== '' ? $prefix.' '.$number : (string) $number;
        $description = trim($description);

        if ($description === '') {
            return $numPart;
        }

        $separator = (string) ($config['separator'] ?? ' — ');

        return $numPart.$separator.$description;
    }

    /**
     * @param  array{prefix: string, separator: string, font_size: float, band_padding: int, color: string, bold: bool, band_border_color: ?string, band_border_width: int}  $config
     * @return array{size: float, bold: bool}
     */
    private function captionTextPayload(array $config): array
    {
        return [
            'size' => (float) $config['font_size'],
            'bold' => ! empty($config['bold']),
        ];
    }

    /**
     * @param  array{band_border_color: ?string, band_border_width: int}  $config
     */
    private function captionBorderInset(array $config): int
    {
        $borderWidth = (int) ($config['band_border_width'] ?? 0);
        $borderColor = $config['band_border_color'] ?? null;

        if ($borderWidth < 1 || ! is_string($borderColor) || $borderColor === '') {
            return 0;
        }

        return max(1, min(12, $borderWidth));
    }

    /**
     * @param  array{prefix: string, separator: string, font_size: float, band_padding: int, color: string, bold: bool, band_border_color: ?string, band_border_width: int}  $config
     */
    private function composeImageWithCaptionBand(
        ImageInterface $image,
        string $captionText,
        array $config
    ): ImageInterface {
        $fontSize = (float) $config['font_size'];
        $padding = (int) $config['band_padding'];
        $imgW = $image->width();
        $imgH = $image->height();
        $borderInset = $this->captionBorderInset($config);
        $innerW = max(1, $imgW - (2 * $padding) - (2 * $borderInset));
        $textPayload = $this->captionTextPayload($config);

        $wrapped = $this->wrapTextBlockToWidth($captionText, $innerW, $textPayload, $image);
        $box = $this->measureTextBlockBox($wrapped, $textPayload, $image);
        $bandH = max((int) ceil($fontSize * 2.2), $box['height'] + (2 * $padding) + (2 * $borderInset));

        $canvas = Image::createImage($imgW, $imgH + $bandH);
        $canvas->fill('#ffffff');
        $canvas->insert($image, 0, 0, Alignment::TOP_LEFT);

        $renderPayload = array_merge($textPayload, [
            'content' => $wrapped,
            'align' => 'center',
        ]);

        $canvas->text(
            $wrapped,
            (int) floor($imgW / 2),
            $imgH + $padding + $borderInset,
            function (FontFactory $font) use ($renderPayload, $canvas): void {
                $this->configureTextFont($font, $renderPayload, $canvas);
            }
        );

        $this->drawCaptionBandBorder($canvas, 0, $imgH, $imgW, $bandH, $config);

        return $canvas;
    }

    /**
     * @param  array{band_border_color: ?string, band_border_width: int}  $config
     */
    private function drawCaptionBandBorder(
        ImageInterface $image,
        int $x,
        int $y,
        int $width,
        int $height,
        array $config
    ): void {
        $borderWidth = (int) ($config['band_border_width'] ?? 0);
        $borderColor = $config['band_border_color'] ?? null;

        if ($borderWidth < 1 || ! is_string($borderColor) || $borderColor === '' || $width < 1 || $height < 1) {
            return;
        }

        $borderWidth = max(1, min(12, $borderWidth));

        $image->drawRectangle(function ($r) use ($x, $y, $width, $height, $borderColor, $borderWidth): void {
            $r->size($width, $height)->at($x, $y);
            $r->border($borderColor, $borderWidth);
        });
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

            $caption = $item['caption'] ?? null;
            if (is_array($caption) && isset($caption['number'])) {
                try {
                    $captionAngle = ((int) ($item['caption_angle'] ?? 0) % 360 + 360) % 360;
                    $this->drawOverlayCaptionBand(
                        $image,
                        $tx,
                        $ty,
                        $tw,
                        $th,
                        $caption,
                        $this->resolveCaptionConfigForOverlay($data, $item),
                        $captionAngle
                    );
                } catch (\Throwable) {
                    // Ignora legenda inválida neste overlay
                }
            }
        }
    }

    /**
     * Faixa branca com legenda por baixo de um overlay (coordenadas da imagem final).
     *
     * @param  array{number: int, description?: string}  $caption
     * @param  array{prefix: string, separator: string, font_size: float, band_padding: int, color: string, bold: bool, band_border_color: ?string, band_border_width: int}  $config
     */
    private function drawOverlayCaptionBand(
        ImageInterface $image,
        int $tx,
        int $ty,
        int $tw,
        int $th,
        array $caption,
        array $config,
        int $angle = 0
    ): void {
        $number = max(1, (int) ($caption['number'] ?? 1));
        $description = trim((string) ($caption['description'] ?? ''));
        $captionText = $this->formatCaptionText($config, $number, $description);

        if ($captionText === '') {
            return;
        }

        $angle = (($angle % 360) + 360) % 360;
        $fontSize = (float) $config['font_size'];
        $padding = (int) $config['band_padding'];
        $borderInset = $this->captionBorderInset($config);
        $edgeLength = ($angle === 90 || $angle === 270) ? $th : $tw;
        $innerW = max(1, $edgeLength - (2 * $padding) - (2 * $borderInset));
        $textPayload = $this->captionTextPayload($config);
        $wrapped = $this->wrapTextBlockToWidth($captionText, $innerW, $textPayload, $image);
        $box = $this->measureTextBlockBox($wrapped, $textPayload, $image);
        $bandThickness = max((int) ceil($fontSize * 2.2), $box['height'] + (2 * $padding) + (2 * $borderInset));

        if ($angle === 90) {
            $bandX = $tx + $tw;
            $bandY = $ty;
            $bandW = $bandThickness;
            $bandH = $th;
            $textX = $bandX + (int) floor($bandThickness / 2);
            $textY = $ty + (int) floor($th / 2);
        } elseif ($angle === 180) {
            $bandX = $tx;
            $bandY = $ty - $bandThickness;
            $bandW = $tw;
            $bandH = $bandThickness;
            $textX = $tx + (int) floor($tw / 2);
            $textY = $bandY + $padding + $borderInset;
        } elseif ($angle === 270) {
            $bandX = $tx - $bandThickness;
            $bandY = $ty;
            $bandW = $bandThickness;
            $bandH = $th;
            $textX = $bandX + (int) floor($bandThickness / 2);
            $textY = $ty + (int) floor($th / 2);
        } else {
            $bandX = $tx;
            $bandY = $ty + $th;
            $bandW = $tw;
            $bandH = $bandThickness;
            $textX = $tx + (int) floor($tw / 2);
            $textY = $bandY + $padding + $borderInset;
        }

        $image->drawRectangle(function ($r) use ($bandW, $bandH, $bandX, $bandY): void {
            $r->size($bandW, $bandH)->at($bandX, $bandY);
            $r->background('#ffffff');
        });

        $renderPayload = array_merge($textPayload, [
            'content' => $wrapped,
            'color' => $config['color'],
            'align' => 'center',
            'angle' => (float) $angle,
        ]);

        $image->text(
            $wrapped,
            $textX,
            $textY,
            function (FontFactory $font) use ($renderPayload, $image): void {
                $this->configureTextFont($font, $renderPayload, $image);
            }
        );

        $this->drawCaptionBandBorder($image, $bandX, $bandY, $bandW, $bandH, $config);
    }

    private function applyTextItem(ImageInterface $image, array $text): void
    {
        $content = $this->normalizeTextContent((string) ($text['content'] ?? ''));
        if ($content === '') {
            return;
        }

        $boxWidth = (int) ($text['box_width'] ?? 0);
        if ($boxWidth > 0) {
            $content = $this->wrapTextBlockToWidth($content, $boxWidth, $text, $image);
        }

        $x = (int) ($text['x'] ?? 0);
        $y = (int) ($text['y'] ?? 0);
        $bgColor = $text['background_color'] ?? null;

        if (is_string($bgColor) && $bgColor !== '') {
            $this->drawTextBackground($image, $text, $content, $x, $y, $bgColor);
        }

        $borderColor = $text['box_border_color'] ?? null;
        $borderWidth = (int) ($text['box_border_width'] ?? 0);
        if ($borderWidth > 0 && is_string($borderColor) && $borderColor !== '') {
            $this->drawTextBoxBorder($image, $text, $content, $x, $y, $borderColor, $borderWidth);
        }

        $lines = preg_split('/\n/', $content) ?: [''];
        $leading = $this->textLineLeading($text, $image);

        foreach ($lines as $index => $line) {
            $lineY = $y + ($index * $leading);
            $this->renderTextLine($image, $line === '' ? ' ' : $line, $text, $x, $lineY);
        }
    }

    private function renderTextLine(
        ImageInterface $image,
        string $line,
        array $text,
        int $x,
        int $y
    ): void {
        $image->text(
            $line,
            $x,
            $y,
            function (FontFactory $font) use ($text, $image): void {
                $this->configureTextFont($font, $text, $image);
            }
        );
    }

    private function normalizeTextContent(string $content): string
    {
        $content = preg_replace("/\r\n|\r/", "\n", $content) ?? $content;

        return trim($content);
    }

    private function wrapTextBlockToWidth(string $content, int $maxWidth, array $text, ImageInterface $image): string
    {
        if ($maxWidth < 1 || $content === '') {
            return $content;
        }

        $paragraphs = preg_split('/\n/', $content) ?: [''];
        $wrappedLines = [];

        foreach ($paragraphs as $paragraph) {
            if ($paragraph === '') {
                $wrappedLines[] = '';

                continue;
            }

            $wrappedLines = array_merge(
                $wrappedLines,
                $this->wrapTextLineToWidth($paragraph, $maxWidth, $text, $image)
            );
        }

        return implode("\n", $wrappedLines);
    }

    /**
     * @return list<string>
     */
    private function wrapTextLineToWidth(string $line, int $maxWidth, array $text, ImageInterface $image): array
    {
        $fontPath = $this->resolveTextFontPath($text);
        if ($fontPath === null) {
            return $this->wrapTextLineToWidthEstimated($line, $maxWidth, $text);
        }

        $naturalPx = max(6.0, min(900.0, (float) ($text['size'] ?? 24)));
        $nativeSize = $this->nativeFontSizeForRendering($image, $naturalPx);
        $words = preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return [''];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            foreach ($this->splitTtfWordToMaxWidth($word, $maxWidth, $fontPath, $nativeSize) as $segment) {
                $candidate = $current === '' ? $segment : $current.' '.$segment;

                if ($this->ttfTextWidth($fontPath, $nativeSize, $candidate) <= $maxWidth) {
                    $current = $candidate;

                    continue;
                }

                if ($current !== '') {
                    $lines[] = $current;
                }

                $current = $segment;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines !== [] ? $lines : [''];
    }

    /**
     * @return list<string>
     */
    private function splitTtfWordToMaxWidth(string $word, int $maxWidth, string $fontPath, float $nativeSize): array
    {
        if ($word === '' || $this->ttfTextWidth($fontPath, $nativeSize, $word) <= $maxWidth) {
            return [$word];
        }

        $parts = [];
        $current = '';
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($chars as $char) {
            $trial = $current.$char;

            if ($current !== '' && $this->ttfTextWidth($fontPath, $nativeSize, $trial) > $maxWidth) {
                $parts[] = $current;
                $current = $char;
            } else {
                $current = $trial;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts !== [] ? $parts : [$word];
    }

    /**
     * @return list<string>
     */
    private function wrapTextLineToWidthEstimated(string $line, int $maxWidth, array $text): array
    {
        $fontSize = max(6.0, min(900.0, (float) ($text['size'] ?? 24)));
        $charWidth = max(1.0, $fontSize * 0.55);
        $maxChars = max(1, (int) floor($maxWidth / $charWidth));
        $words = preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return [''];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines !== [] ? $lines : [''];
    }

    private function textLineLeading(array $text, ImageInterface $image): int
    {
        $naturalPx = max(6.0, min(900.0, (float) ($text['size'] ?? 24)));
        $fontPath = $this->resolveTextFontPath($text);

        if ($fontPath === null) {
            return max(1, (int) round($naturalPx * 1.3));
        }

        $nativeSize = $this->nativeFontSizeForRendering($image, $naturalPx);
        $typographicalSize = $this->ttfTextHeight($fontPath, $nativeSize, 'Hy');

        return max(1, (int) round($typographicalSize * 1.25 * 0.8));
    }

    private function drawTextBackground(
        ImageInterface $image,
        array $text,
        string $content,
        int $x,
        int $y,
        string $bgColor
    ): void {
        $rect = $this->textBlockRect($content, $text, $image, (int) ($text['background_padding'] ?? 6));
        if ($rect === null) {
            return;
        }

        $opacity = max(5, min(100, (int) ($text['background_opacity'] ?? 75)));
        $fill = $this->colorWithOpacity($bgColor, $opacity);

        $image->drawRectangle(function ($r) use ($rect, $fill): void {
            $r->size($rect['width'], $rect['height'])->at($rect['x'], $rect['y']);
            $r->background($fill);
        });
    }

    private function drawTextBoxBorder(
        ImageInterface $image,
        array $text,
        string $content,
        int $x,
        int $y,
        string $borderColor,
        int $borderWidth
    ): void {
        $padding = max(0, min(48, (int) ($text['box_border_padding'] ?? 4)));
        $rect = $this->textBlockRect($content, $text, $image, $padding);
        if ($rect === null) {
            return;
        }

        $borderWidth = max(1, min(12, $borderWidth));

        $image->drawRectangle(function ($r) use ($rect, $borderColor, $borderWidth): void {
            $r->size($rect['width'], $rect['height'])->at($rect['x'], $rect['y']);
            $r->border($borderColor, $borderWidth);
        });
    }

    /**
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    private function textBlockRect(
        string $content,
        array $text,
        ImageInterface $image,
        int $padding
    ): ?array {
        $box = $this->measureTextBlockBox($content, $text, $image);
        $padding = max(0, min(48, $padding));
        $x = (int) ($text['x'] ?? 0);
        $y = (int) ($text['y'] ?? 0);

        $align = (string) ($text['align'] ?? 'left');
        $bx = $x;
        if ($align === 'center') {
            $bx = $x - (int) ($box['width'] / 2);
        } elseif ($align === 'right') {
            $bx = $x - $box['width'];
        }

        $rectX = max(0, $bx - $padding);
        $rectY = max(0, $y - $padding);
        $rectW = min($image->width() - $rectX, $box['width'] + (2 * $padding));
        $rectH = min($image->height() - $rectY, $box['height'] + (2 * $padding));

        if ($rectW < 1 || $rectH < 1) {
            return null;
        }

        return [
            'x' => $rectX,
            'y' => $rectY,
            'width' => $rectW,
            'height' => $rectH,
        ];
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
        return $this->packagedTextFontPath(! empty($text['bold']));
    }

    private function packagedTextFontPath(bool $bold): ?string
    {
        $filename = $bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf';
        $path = dirname(__DIR__).'/Resources/fonts/'.$filename;

        if (is_file($path) && is_readable($path)) {
            return $path;
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
