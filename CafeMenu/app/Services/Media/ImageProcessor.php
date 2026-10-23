<?php

namespace App\Services\Media;

use GdImage;

/**
 * Resizes oversized images down to a sane maximum dimension and re-encodes
 * them via GD, which also strips EXIF/metadata (e.g. GPS tags) as a side
 * effect. No image-processing package is added: GD ships with PHP and
 * covers everything this product needs (jpeg/png/webp resize + re-encode).
 */
class ImageProcessor
{
    public function __construct(
        private readonly int $maxDimension = 2000,
        private readonly int $jpegQuality = 82,
        private readonly int $webpQuality = 82,
    ) {}

    /**
     * Optimizes the image in place. Returns the final [width, height], or
     * null if the format could not be processed (the original file is left
     * untouched in that case).
     *
     * @return array{0: int, 1: int}|null
     */
    public function optimize(string $absolutePath, string $mimeType): ?array
    {
        $image = $this->read($absolutePath, $mimeType);

        if ($image === null) {
            return null;
        }

        $image = $this->autoOrient($absolutePath, $image);

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $this->maxDimension || $height > $this->maxDimension) {
            $image = $this->resize($image, $width, $height);
        }

        $this->write($absolutePath, $mimeType, $image);

        $finalWidth = imagesx($image);
        $finalHeight = imagesy($image);

        imagedestroy($image);

        return [$finalWidth, $finalHeight];
    }

    private function read(string $path, string $mimeType): ?GdImage
    {
        $image = match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        return $image ?: null;
    }

    private function write(string $path, string $mimeType, GdImage $image): void
    {
        match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $path, $this->jpegQuality),
            'image/png' => (function () use ($image, $path) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, $path);
            })(),
            'image/webp' => imagewebp($image, $path, $this->webpQuality),
            default => null,
        };
    }

    private function resize(GdImage $image, int $width, int $height): GdImage
    {
        $ratio = min($this->maxDimension / $width, $this->maxDimension / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    /**
     * Corrects orientation for JPEGs captured on devices that store rotation
     * in EXIF rather than rotating the pixel data itself.
     */
    private function autoOrient(string $path, GdImage $image): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? 1;

        $image = match ($orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };

        return $image;
    }
}
