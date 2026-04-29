<?php
/** Shared image storage helpers for chat uploads. */
if (!function_exists('chat_upload_root')) {
    function chat_upload_root(): string
    {
        return __DIR__ . '/../storage/chat-images';
    }

    function chat_upload_public_path(string $relativePath): string
    {
        return '/' . ltrim($relativePath, '/');
    }

    function ensure_chat_upload_dir(string $relativeDir = ''): string
    {
        $root = rtrim(chat_upload_root(), '/');
        $dir = $relativeDir === '' ? $root : $root . '/' . trim($relativeDir, '/');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }


    function chat_image_storage_file(string $relativeFile): string
    {
        $relativeFile = trim(str_replace('\\', '/', $relativeFile), '/');
        $root = realpath(chat_upload_root());
        if ($root === false) {
            $root = chat_upload_root();
        }
        $full = rtrim((string)$root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativeFile;
        $dir = dirname($full);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $full;
    }

    function chat_image_readable_path(array $image): string
    {
        $file = (string)($image['file'] ?? '');
        if ($file !== '') {
            $full = chat_image_storage_file($file);
            if (is_file($full)) return $full;
        }

        // Backward compatibility for images saved before private storage existed.
        $path = (string)($image['path'] ?? '');
        if ($path !== '') {
            $legacy = dirname(__DIR__) . '/' . ltrim($path, '/');
            if (is_file($legacy)) return $legacy;
        }

        return '';
    }

    function chat_image_url(int $messageId, int $index): string
    {
        return 'chat-image.php?m=' . $messageId . '&i=' . $index;
    }

    function safe_image_extension(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'bin',
        };
    }
}
