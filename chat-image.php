<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/security.php';
rook_hardened_session_start();
require_once __DIR__ . '/lib/install_guard.php';
require_once __DIR__ . '/lib/image_storage.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli
{
    static $db = null;
    if ($db instanceof mysqli) return $db;
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->set_charset('utf8mb4');
    return $db;
}

function fetch_one(string $sql, string $types = '', array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return is_array($row) ? $row : null;
}

function deny_image(int $status = 404): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Image not found.');
}

$messageId = max(0, (int)($_GET['m'] ?? 0));
$index = max(0, (int)($_GET['i'] ?? 0));
if ($messageId < 1 || $index > 20) deny_image();

$userId = (int)($_SESSION['user_id'] ?? 0);
$sessionToken = (string)($_SESSION['session_token'] ?? '');
if ($userId < 1 || $sessionToken === '') deny_image(403);

$user = fetch_one('SELECT id, current_session_token FROM users WHERE id = ? LIMIT 1', 'i', [$userId]);
if (!$user || !hash_equals((string)($user['current_session_token'] ?? ''), $sessionToken)) deny_image(403);

$row = fetch_one(
    'SELECT m.id, m.images_json, c.user_id, c.team_id
     FROM messages m
     INNER JOIN conversations c ON c.id = m.conversation_id
     WHERE m.id = ? LIMIT 1',
    'i',
    [$messageId]
);
if (!$row) deny_image();

$ownerId = (int)($row['user_id'] ?? 0);
$teamId = (int)($row['team_id'] ?? 0);
$allowed = $ownerId === $userId;
if (!$allowed && $teamId > 0) {
    $member = fetch_one(
        'SELECT id FROM team_members WHERE team_id = ? AND user_id = ? AND can_read = 1 LIMIT 1',
        'ii',
        [$teamId, $userId]
    );
    $allowed = (bool)$member;
}
if (!$allowed) deny_image(403);

$images = json_decode((string)($row['images_json'] ?? ''), true);
if (!is_array($images) || !isset($images[$index]) || !is_array($images[$index])) deny_image();

$image = $images[$index];
$mime = (string)($image['mime'] ?? '');
if (!preg_match('/^image\/(png|jpe?g|webp|gif)$/i', $mime)) deny_image();

$full = chat_image_readable_path($image);
if ($full === '' || !is_file($full)) deny_image();

$size = filesize($full);
if ($size === false || $size < 1 || $size > 8 * 1024 * 1024) deny_image();

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$size);
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($full);
exit;
