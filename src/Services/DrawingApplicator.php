<?php

declare(strict_types=1);

namespace PDMFC\ImageEditor\Services;

use Intervention\Image\Interfaces\ImageInterface;

/**
 * Aplica desenhos enviados pelo cliente (primitivas Intervention Image v4).
 *
 * @see https://image.intervention.io/v4/modifying-images/drawing
 */
class DrawingApplicator
{
    public function apply(ImageInterface $image, mixed $drawings): void
    {
        if (! is_array($drawings)) {
            return;
        }

        foreach ($drawings as $drawing) {
            if (! is_array($drawing)) {
                continue;
            }

            $type = (string) ($drawing['type'] ?? '');

            try {
                match ($type) {
                    'line' => $this->applyLine($image, $drawing),
                    'arrow' => $this->applyArrow($image, $drawing),
                    'rectangle' => $this->applyRectangle($image, $drawing),
                    'ellipse' => $this->applyEllipse($image, $drawing),
                    'circle' => $this->applyCircle($image, $drawing),
                    'polygon' => $this->applyPolygon($image, $drawing),
                    'pen' => $this->applyPen($image, $drawing),
                    'bezier' => $this->applyBezier($image, $drawing),
                    'pixel' => $this->applyPixel($image, $drawing),
                    'fill' => $this->applyFill($image, $drawing),
                    default => null,
                };
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function strokeColor(array $d): string
    {
        $c = $d['strokeColor'] ?? '#000000';

        return is_string($c) ? $c : '#000000';
    }

    private function strokeWidth(array $d): int
    {
        return max(1, min(64, (int) ($d['strokeWidth'] ?? 2)));
    }

    /**
     * @return array{0: bool, 1: string, 2: int} desenhar traço, cor, espessura (>=1)
     */
    private function strokeSpec(array $d): array
    {
        $color = $this->strokeColor($d);
        if (array_key_exists('strokeWidth', $d) && (int) $d['strokeWidth'] <= 0) {
            return [false, $color, 0];
        }

        $sw = max(1, min(64, (int) ($d['strokeWidth'] ?? 2)));

        return [true, $color, $sw];
    }

    private function fillColor(array $d): ?string
    {
        $c = $d['fillColor'] ?? null;
        if ($c === null || $c === '' || $c === 'transparent') {
            return null;
        }

        return is_string($c) ? $c : null;
    }

    private function applyLine(ImageInterface $image, array $d): void
    {
        $x1 = (int) ($d['x1'] ?? 0);
        $y1 = (int) ($d['y1'] ?? 0);
        $x2 = (int) ($d['x2'] ?? 0);
        $y2 = (int) ($d['y2'] ?? 0);
        $color = $this->strokeColor($d);
        $w = $this->strokeWidth($d);

        $image->drawLine(function ($line) use ($x1, $y1, $x2, $y2, $color, $w): void {
            $line->from($x1, $y1)->to($x2, $y2)->width($w)->color($color);
        });
    }

    /** Linha com ponta de seta no extremo (x2, y2). */
    private function applyArrow(ImageInterface $image, array $d): void
    {
        $x1 = (int) ($d['x1'] ?? 0);
        $y1 = (int) ($d['y1'] ?? 0);
        $x2 = (int) ($d['x2'] ?? 0);
        $y2 = (int) ($d['y2'] ?? 0);
        $color = $this->strokeColor($d);
        $w = $this->strokeWidth($d);

        $head = $this->arrowHeadPoints($x1, $y1, $x2, $y2, $w);
        if ($head === null) {
            $this->applyLine($image, $d);

            return;
        }

        [$tipX, $tipY, $leftX, $leftY, $rightX, $rightY, $baseX, $baseY] = $head;

        $image->drawLine(function ($line) use ($x1, $y1, $baseX, $baseY, $color, $w): void {
            $line->from($x1, $y1)->to($baseX, $baseY)->width($w)->color($color);
        });

        $image->drawPolygon(function ($p) use ($tipX, $tipY, $leftX, $leftY, $rightX, $rightY, $color): void {
            $p->point($tipX, $tipY)
                ->point($leftX, $leftY)
                ->point($rightX, $rightY)
                ->background($color);
        });
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int}|null
     */
    private function arrowHeadPoints(int $x1, int $y1, int $x2, int $y2, int $strokeWidth): ?array
    {
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $len = hypot($dx, $dy);
        if ($len < 1.0) {
            return null;
        }

        $ux = $dx / $len;
        $uy = $dy / $len;
        $headLen = max(8.0, min(48.0, $strokeWidth * 4.0));
        $halfWidth = $headLen * 0.45;

        $baseX = (int) round($x2 - $ux * $headLen);
        $baseY = (int) round($y2 - $uy * $headLen);
        $px = -$uy;
        $py = $ux;

        return [
            $x2,
            $y2,
            (int) round($baseX + $px * $halfWidth),
            (int) round($baseY + $py * $halfWidth),
            (int) round($baseX - $px * $halfWidth),
            (int) round($baseY - $py * $halfWidth),
            $baseX,
            $baseY,
        ];
    }

    private function applyRectangle(ImageInterface $image, array $d): void
    {
        $x = (int) ($d['x'] ?? 0);
        $y = (int) ($d['y'] ?? 0);
        $width = max(1, (int) ($d['width'] ?? 1));
        $height = max(1, (int) ($d['height'] ?? 1));
        $fill = $this->fillColor($d);
        [$doStroke, $stroke, $sw] = $this->strokeSpec($d);

        if ($fill === null && ! $doStroke) {
            return;
        }

        $image->drawRectangle(function ($r) use ($x, $y, $width, $height, $fill, $doStroke, $stroke, $sw): void {
            $r->size($width, $height)->at($x, $y);
            if ($fill !== null) {
                $r->background($fill);
            }
            if ($doStroke) {
                $r->border($stroke, $sw);
            }
        });
    }

    private function applyEllipse(ImageInterface $image, array $d): void
    {
        $cx = (int) ($d['cx'] ?? 0);
        $cy = (int) ($d['cy'] ?? 0);
        $width = max(2, (int) ($d['width'] ?? 2));
        $height = max(2, (int) ($d['height'] ?? 2));
        $fill = $this->fillColor($d);
        [$doStroke, $stroke, $sw] = $this->strokeSpec($d);

        if ($fill === null && ! $doStroke) {
            return;
        }

        $image->drawEllipse(function ($e) use ($cx, $cy, $width, $height, $fill, $doStroke, $stroke, $sw): void {
            $e->size($width, $height)->at($cx, $cy);
            if ($fill !== null) {
                $e->background($fill);
            }
            if ($doStroke) {
                $e->border($stroke, $sw);
            }
        });
    }

    private function applyCircle(ImageInterface $image, array $d): void
    {
        $cx = (int) ($d['cx'] ?? 0);
        $cy = (int) ($d['cy'] ?? 0);
        $diameter = max(2, (int) ($d['diameter'] ?? 2));
        $fill = $this->fillColor($d);
        [$doStroke, $stroke, $sw] = $this->strokeSpec($d);

        if ($fill === null && ! $doStroke) {
            return;
        }

        $image->drawCircle(function ($c) use ($cx, $cy, $diameter, $fill, $doStroke, $stroke, $sw): void {
            $c->diameter($diameter)->at($cx, $cy);
            if ($fill !== null) {
                $c->background($fill);
            }
            if ($doStroke) {
                $c->border($stroke, $sw);
            }
        });
    }

    /**
     * Traço livre (caneta): segmentos entre pontos consecutivos.
     *
     * @param  array<int, array{x?:int, y?:int}>  $points
     */
    private function applyPen(ImageInterface $image, array $d): void
    {
        $points = $d['points'] ?? [];
        if (! is_array($points) || count($points) < 2) {
            return;
        }

        [$doStroke, $stroke, $sw] = $this->strokeSpec($d);
        if (! $doStroke) {
            return;
        }

        $prev = null;
        foreach ($points as $pt) {
            if (! is_array($pt)) {
                continue;
            }
            $x = (int) ($pt['x'] ?? 0);
            $y = (int) ($pt['y'] ?? 0);
            if ($prev !== null) {
                $image->drawLine(function ($line) use ($prev, $x, $y, $stroke, $sw): void {
                    $line->from($prev[0], $prev[1])->to($x, $y)->width($sw)->color($stroke);
                });
            }
            $prev = [$x, $y];
        }
    }

    /**
     * @param  array<int, array{x?:int, y?:int}>  $points
     */
    private function applyPolygon(ImageInterface $image, array $d): void
    {
        $points = $d['points'] ?? [];
        if (! is_array($points) || count($points) < 3) {
            return;
        }

        $fill = $this->fillColor($d);
        [$doStroke, $stroke, $sw] = $this->strokeSpec($d);

        if ($fill === null && ! $doStroke) {
            return;
        }

        $image->drawPolygon(function ($p) use ($points, $fill, $doStroke, $stroke, $sw): void {
            foreach ($points as $pt) {
                if (! is_array($pt)) {
                    continue;
                }
                $p->point((int) ($pt['x'] ?? 0), (int) ($pt['y'] ?? 0));
            }
            if ($fill !== null) {
                $p->background($fill);
            }
            if ($doStroke) {
                $p->border($stroke, $sw);
            }
        });
    }

    /**
     * @param  array<int, array{x?:int, y?:int}>  $points
     */
    private function applyBezier(ImageInterface $image, array $d): void
    {
        $points = $d['points'] ?? [];
        if (! is_array($points)) {
            return;
        }

        $n = count($points);
        if ($n !== 3 && $n !== 4) {
            return;
        }

        $fill = $this->fillColor($d);
        [$doStroke, $stroke, $sw] = $this->strokeSpec($d);

        if ($fill === null && ! $doStroke) {
            return;
        }

        $image->drawBezier(function ($b) use ($points, $fill, $doStroke, $stroke, $sw): void {
            foreach ($points as $pt) {
                if (! is_array($pt)) {
                    continue;
                }
                $b->point((int) ($pt['x'] ?? 0), (int) ($pt['y'] ?? 0));
            }
            if ($fill !== null) {
                $b->background($fill);
            }
            if ($doStroke) {
                $b->border($stroke, $sw);
            }
        });
    }

    private function applyPixel(ImageInterface $image, array $d): void
    {
        $x = (int) ($d['x'] ?? 0);
        $y = (int) ($d['y'] ?? 0);
        $color = is_string($d['color'] ?? null) ? (string) $d['color'] : '#000000';

        $image->drawPixel($x, $y, $color);
    }

    private function applyFill(ImageInterface $image, array $d): void
    {
        $x = (int) ($d['x'] ?? 0);
        $y = (int) ($d['y'] ?? 0);
        $color = is_string($d['color'] ?? null) ? (string) $d['color'] : '#000000';

        $image->fill($color, $x, $y);
    }
}
