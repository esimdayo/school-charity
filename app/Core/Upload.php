<?php
declare(strict_types=1);

namespace App\Core;

// Saves an uploaded photo (JPG, PNG or WEBP) under a random name.
final class Upload
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_BYTES = 8 * 1024 * 1024;

    // Returns the new file name, or throws with a Lang key as the message.
    // Returns null when no file was sent and $required is false.
    public static function image(?array $file, string $dir, bool $required = true): ?string
    {
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            if ($required) throw new \RuntimeException('photo_missing');
            return null;
        }
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('photo_too_big');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) throw new \RuntimeException('photo_failed');
        // Check the real file type, not the name the browser sent.
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$type])) throw new \RuntimeException('photo_missing');

        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $name = bin2hex(random_bytes(12)) . '.' . self::TYPES[$type];
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) throw new \RuntimeException('photo_failed');
        return $name;
    }
}
