<?php

declare(strict_types=1);

namespace PawBerry\Services;

use RuntimeException;

final class EventAttachmentService
{
    public const MAX_BYTES = 10_485_760; // 10 MB
    public const MAX_PER_EVENT = 12;

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
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
            throw new RuntimeException('Invalid attachment path.');
        }

        $root = realpath($this->uploadsRoot());
        if ($root === false) {
            throw new RuntimeException('Uploads directory missing.');
        }

        $full = $this->uploadsRoot() . '/' . $relative;
        $real = realpath($full);
        if ($real === false) {
            $parent = realpath(dirname($full));
            if ($parent === false || !str_starts_with($parent, $root)) {
                throw new RuntimeException('Invalid attachment path.');
            }
            return $full;
        }

        if (!str_starts_with($real, $root . DIRECTORY_SEPARATOR) && $real !== $root) {
            throw new RuntimeException('Invalid attachment path.');
        }

        return $real;
    }

    /**
     * @param array{tmp_name?: string, error?: int, size?: int, name?: string, type?: string} $file
     * @return array{mime: string, extension: string, tmp: string, size: int, original_filename: string}
     */
    public function validateUpload(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('No file was uploaded.');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('That file is too large. Please use a file under 10 MB.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The file could not be uploaded. Please try again.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('That file is too large. Please use a file under 10 MB.');
        }
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid upload.');
        }

        $mime = $this->detectMime($tmp);
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Please upload a JPEG, PNG, WebP, or PDF file.');
        }

        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($tmp);
            if ($info === false) {
                throw new RuntimeException('That file does not look like a valid image.');
            }
        } elseif ($mime === 'application/pdf') {
            $head = (string) @file_get_contents($tmp, false, null, 0, 5);
            if ($head !== '%PDF-') {
                throw new RuntimeException('That file does not look like a valid PDF.');
            }
        }

        $original = $this->sanitizeFilename((string) ($file['name'] ?? 'attachment'));

        return [
            'mime' => $mime,
            'extension' => self::ALLOWED[$mime],
            'tmp' => $tmp,
            'size' => $size,
            'original_filename' => $original,
        ];
    }

    /**
     * @param array{mime: string, extension: string, tmp: string, size: int, original_filename: string} $upload
     * @return array{original_filename: string, stored_path: string, mime_type: string, file_size: int}
     */
    public function store(int $eventId, array $upload): array
    {
        $dirRel = 'events/' . $eventId;
        $dirAbs = $this->uploadsRoot() . '/' . $dirRel;
        if (!is_dir($dirAbs) && !mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            throw new RuntimeException('Could not prepare attachment storage.');
        }

        $token = bin2hex(random_bytes(16));
        $filename = $token . '.' . $upload['extension'];
        $relative = $dirRel . '/' . $filename;
        $absolute = $dirAbs . '/' . $filename;

        if (!move_uploaded_file($upload['tmp'], $absolute)) {
            throw new RuntimeException('Could not save the attachment.');
        }
        @chmod($absolute, 0644);

        return [
            'original_filename' => $upload['original_filename'],
            'stored_path' => $relative,
            'mime_type' => $upload['mime'],
            'file_size' => $upload['size'],
        ];
    }

    public function deleteRelative(string $relative): void
    {
        try {
            $absolute = $this->absolutePath($relative);
        } catch (RuntimeException) {
            return;
        }
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    public function mimeForPath(string $absolute): string
    {
        $mime = $this->detectMime($absolute);
        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        return is_string($mime) ? $mime : '';
    }

    private function sanitizeFilename(string $name): string
    {
        $name = basename(str_replace(["\0", '\\'], '', $name));
        $name = trim($name);
        if ($name === '') {
            return 'attachment';
        }
        if (mb_strlen($name) > 180) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $base = mb_substr($base, 0, 160);
            $name = $ext !== '' ? $base . '.' . $ext : $base;
        }
        return $name;
    }
}
