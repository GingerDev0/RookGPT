<?php
/** Shared image storage helpers for chat uploads. */
if (!function_exists('chat_upload_root')) {
    function chat_upload_root(): string
    {
        return __DIR__ . '/../uploads/chat-images';
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
