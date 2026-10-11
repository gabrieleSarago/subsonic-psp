<?php

namespace App\Support;

/**
 * Re-encodes an image into the thumbnail the PSP shows next to a feed item.
 *
 * The device draws an item image only from a <media:thumbnail> element, and
 * only when the file is a JPEG no larger than 160 by 120 pixels. The artwork
 * this application proxies is usually a PNG, a GIF or an ICO, so it has to be
 * decoded and re-encoded into the one format the device reads.
 */
final class Thumbnailer
{
    /**
     * The largest width and height the PSP accepts for an item image.
     */
    private const int MAX_WIDTH = 160;

    private const int MAX_HEIGHT = 120;

    /**
     * The JPEG quality, high enough to stay clean at this size.
     */
    private const int JPEG_QUALITY = 85;

    /**
     * Re-encode the given image data as a JPEG that fits the PSP limits.
     *
     * Small images are scaled up and large ones down, the aspect ratio is kept,
     * and any transparency is flattened onto a white background, because JPEG
     * has no alpha channel.
     *
     * Returns null when the source cannot be decoded, so the caller can answer
     * with a neutral image instead.
     */
    public function jpegFromBytes(string $bytes): ?string
    {
        $source = @imagecreatefromstring($bytes);

        if (! $source instanceof \GdImage) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        [$width, $height] = $this->fittedSize($sourceWidth, $sourceHeight);

        $canvas = imagecreatetruecolor($width, $height);

        if (! $canvas instanceof \GdImage) {
            return null;
        }

        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width - 1, $height - 1, $background);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight,
        );

        ob_start();
        imagejpeg($canvas, null, self::JPEG_QUALITY);
        $jpeg = ob_get_clean();

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }

    /**
     * The size of the thumbnail, scaled to fit the PSP limits.
     *
     * @return array{0: int, 1: int} width and height
     */
    private function fittedSize(int $width, int $height): array
    {
        $scale = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }
}
