<?php

declare(strict_types=1);

namespace PawBerry\Services;

use PawBerry\Core\Logger;
use RuntimeException;

final class MemberPhotoService
{
    public const MAX_BYTES = 5_242_880; // 5 MB
    public const MAX_DIMENSION = 4500;
    public const CROP_SIZE = 800;
    public const JPEG_QUALITY = 85;

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function uploadsRoot(): string
    {
        return PAWBERRY_STORAGE . '/uploads';
    }

    public function absolutePath(string $relative): string
    {
        $relative = str_replace(['\\', "\0"], ['/', ''], $relative);
        $relative = ltrim($relative, '/');
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException('Invalid photo path.');
        }

        $root = realpath($this->uploadsRoot());
        if ($root === false) {
            throw new RuntimeException('Uploads directory missing.');
        }

        $full = $this->uploadsRoot() . '/' . $relative;
        $real = realpath($full);
        if ($real === false) {
            // File may not exist yet during writes; still constrain parent.
            $parent = realpath(dirname($full));
            if ($parent === false || !str_starts_with($parent, $root)) {
                throw new RuntimeException('Invalid photo path.');
            }
            return $full;
        }

        if (!str_starts_with($real, $root . DIRECTORY_SEPARATOR) && $real !== $root) {
            throw new RuntimeException('Invalid photo path.');
        }

        return $real;
    }

    /**
     * Validate an uploaded file and return its detected mime + extension.
     *
     * @param array{tmp_name?: string, error?: int, size?: int, name?: string} $file
     * @return array{mime: string, extension: string, tmp: string, size: int}
     */
    public function validateUpload(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('No photograph was uploaded.');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('That photograph is too large. Please use a file under 5 MB.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The photograph could not be uploaded. Please try again.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('That photograph is too large. Please use a file under 5 MB.');
        }
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid upload.');
        }

        $mime = $this->detectMime($tmp);
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Please upload a JPEG, PNG, or WebP photograph.');
        }

        $info = @getimagesize($tmp);
        if ($info === false) {
            throw new RuntimeException('That file does not look like a valid photograph.');
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        if ($width < 32 || $height < 32) {
            throw new RuntimeException('That photograph is too small. Please use a clearer photo.');
        }
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new RuntimeException('That photograph is too large in resolution. Please use a smaller image.');
        }

        return [
            'mime' => $mime,
            'extension' => self::ALLOWED[$mime],
            'tmp' => $tmp,
            'size' => $size,
        ];
    }

    /**
     * @param array{x?: float|int|string, y?: float|int|string, width?: float|int|string, height?: float|int|string, rotate?: float|int|string} $crop
     * @return array{original: string, cropped: string}
     */
    public function storeNewPhoto(int $memberId, array $upload, array $crop): array
    {
        $dir = $this->memberDir($memberId);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not prepare photo storage.');
        }

        $token = bin2hex(random_bytes(16));
        $originalRel = "members/{$memberId}/original-{$token}.jpg";
        $croppedRel = "members/{$memberId}/cropped-{$token}.jpg";

        $originalAbs = $this->uploadsRoot() . '/' . $originalRel;
        $croppedAbs = $this->uploadsRoot() . '/' . $croppedRel;

        $source = $this->loadImage($upload['tmp'], $upload['mime']);
        try {
            $this->writeJpeg($source, $originalAbs, 92);
            $cropped = $this->cropToSquare($source, $crop);
            try {
                $this->writeJpeg($cropped, $croppedAbs, self::JPEG_QUALITY);
            } finally {
                imagedestroy($cropped);
            }
        } finally {
            imagedestroy($source);
        }

        return [
            'original' => $originalRel,
            'cropped' => $croppedRel,
        ];
    }

    /**
     * Recrop from an existing original without a new upload.
     *
     * @param array{x?: float|int|string, y?: float|int|string, width?: float|int|string, height?: float|int|string, rotate?: float|int|string} $crop
     * @return array{original: string, cropped: string}
     */
    public function recropExisting(int $memberId, string $existingOriginalRel, array $crop): array
    {
        $originalAbs = $this->absolutePath($existingOriginalRel);
        if (!is_file($originalAbs)) {
            throw new RuntimeException('The original photograph could not be found.');
        }

        $mime = $this->detectMime($originalAbs);
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('The saved original photograph is not a supported format.');
        }

        $dir = $this->memberDir($memberId);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not prepare photo storage.');
        }

        $token = bin2hex(random_bytes(16));
        $croppedRel = "members/{$memberId}/cropped-{$token}.jpg";
        $croppedAbs = $this->uploadsRoot() . '/' . $croppedRel;

        $source = $this->loadImage($originalAbs, $mime);
        try {
            $cropped = $this->cropToSquare($source, $crop);
            try {
                $this->writeJpeg($cropped, $croppedAbs, self::JPEG_QUALITY);
            } finally {
                imagedestroy($cropped);
            }
        } finally {
            imagedestroy($source);
        }

        return [
            'original' => $existingOriginalRel,
            'cropped' => $croppedRel,
        ];
    }

    public function deleteRelative(?string $relative): void
    {
        if ($relative === null || $relative === '') {
            return;
        }

        try {
            $abs = $this->absolutePath($relative);
            if (is_file($abs)) {
                @unlink($abs);
            }
        } catch (\Throwable $e) {
            Logger::warning('Failed to delete photo file', ['path' => $relative, 'error' => $e->getMessage()]);
        }
    }

    public function mimeForPath(string $absolute): string
    {
        $mime = $this->detectMime($absolute);
        return isset(self::ALLOWED[$mime]) ? $mime : 'application/octet-stream';
    }

    private function memberDir(int $memberId): string
    {
        return $this->uploadsRoot() . '/members/' . $memberId;
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        return is_string($mime) ? strtolower($mime) : '';
    }

    /** @return \GdImage */
    private function loadImage(string $path, string $mime): \GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException('Could not read that photograph.');
        }

        // Normalize palette / alpha onto truecolor for consistent JPEG output.
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, true);
        imagesavealpha($image, false);

        return $image;
    }

    /**
     * @param array{x?: float|int|string, y?: float|int|string, width?: float|int|string, height?: float|int|string, rotate?: float|int|string} $crop
     * @return \GdImage
     */
    private function cropToSquare(\GdImage $source, array $crop): \GdImage
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);

        $rotate = (float) ($crop['rotate'] ?? 0);
        $working = $source;
        $createdRotated = false;

        if (abs($rotate) > 0.01) {
            $bg = imagecolorallocate($source, 52, 39, 32);
            $rotated = imagerotate($source, -$rotate, $bg === false ? 0 : $bg);
            if ($rotated === false) {
                throw new RuntimeException('Could not process photograph rotation.');
            }
            $working = $rotated;
            $createdRotated = true;
            $srcW = imagesx($working);
            $srcH = imagesy($working);
        }

        try {
            $x = (float) ($crop['x'] ?? 0);
            $y = (float) ($crop['y'] ?? 0);
            $w = (float) ($crop['width'] ?? 0);
            $h = (float) ($crop['height'] ?? 0);

            $this->assertValidCropBox($srcW, $srcH, $x, $y, $w, $h);

            // Normalize to a square using the smaller side without leaving the image.
            $side = min($w, $h);
            if ($x + $side > $srcW + 0.5 || $y + $side > $srcH + 0.5) {
                throw new RuntimeException('That crop area is invalid. Please crop the photograph again.');
            }
            $w = $side;
            $h = $side;

            $dest = imagecreatetruecolor(self::CROP_SIZE, self::CROP_SIZE);
            if ($dest === false) {
                throw new RuntimeException('Could not create cropped photograph.');
            }

            $fill = imagecolorallocate($dest, 52, 39, 32);
            if ($fill !== false) {
                imagefill($dest, 0, 0, $fill);
            }

            $ok = imagecopyresampled(
                $dest,
                $working,
                0,
                0,
                (int) round($x),
                (int) round($y),
                self::CROP_SIZE,
                self::CROP_SIZE,
                (int) round($w),
                (int) round($h)
            );

            if (!$ok) {
                imagedestroy($dest);
                throw new RuntimeException('Could not crop that photograph.');
            }

            return $dest;
        } finally {
            if ($createdRotated) {
                imagedestroy($working);
            }
        }
    }

    private function writeJpeg(\GdImage $image, string $path, int $quality): void
    {
        // Strip metadata by re-encoding through GD (EXIF is not copied).
        if (!imagejpeg($image, $path, $quality)) {
            throw new RuntimeException('Could not save the photograph.');
        }
        @chmod($path, 0640);
    }

    /**
     * Reject crop boxes that fall outside the source image instead of silently clamping.
     */
    private function assertValidCropBox(int $srcW, int $srcH, float $x, float $y, float $w, float $h): void
    {
        $epsilon = 1.0;

        if (
            !is_finite($x)
            || !is_finite($y)
            || !is_finite($w)
            || !is_finite($h)
            || $w < 1
            || $h < 1
            || $x < -$epsilon
            || $y < -$epsilon
            || $x + $w > $srcW + $epsilon
            || $y + $h > $srcH + $epsilon
            || $w > $srcW + $epsilon
            || $h > $srcH + $epsilon
        ) {
            throw new RuntimeException('That crop area is invalid. Please crop the photograph again.');
        }
    }
}
