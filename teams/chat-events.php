<?php
require_once __DIR__ . '/_bootstrap.php';

if (!$activeTeam || !can_read_team_chat($activeTeam, $activeMembership, $user)) {
    http_response_code(403);
    header('Content-Type: text/event-stream; charset=utf-8');
    echo "event: error\n";
    echo 'data: ' . json_encode(['error' => 'No team chat access.']) . "\n\n";
    exit;
}

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');

while (ob_get_level() > 0) { @ob_end_flush(); }
session_write_close();

$afterId = max(0, (int) ($_GET['after'] ?? 0));
$teamId = (int) $activeTeam['id'];
$startedAt = time();

$send = static function (string $event, array $payload): void {
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush();
    flush();
};

$send('ready', ['ok' => true, 'time' => date('c')]);

while (!connection_aborted() && (time() - $startedAt) < 55) {
    $rows = fetch_team_chat_messages($teamId, $afterId, 50);
    if ($rows) {
        $messages = array_map('format_team_chat_message', $rows);
        foreach ($messages as $message) $afterId = max($afterId, (int) $message['id']);
        $send('chat', ['messages' => $messages, 'last_id' => $afterId]);
    }

    $aiUpdates = fetch_recent_team_chat_ai_updates($teamId, 6);
    $typingUsers = fetch_team_chat_typing_users($teamId, (int) ($user['id'] ?? 0), 5);
    $deletions = fetch_recent_team_chat_deletions($teamId, 6);
    $deletedIds = [];
    $chatCleared = false;
    foreach ($deletions as $deletion) {
        if (($deletion['event_type'] ?? '') === 'clear') {
            $chatCleared = true;
        } elseif (!empty($deletion['message_id'])) {
            $deletedIds[] = (int) $deletion['message_id'];
        }
    }
    $send('typing', ['users' => array_map(static function (array $typingUser): array {
        return ['user_id' => (int) ($typingUser['user_id'] ?? 0), 'username' => (string) ($typingUser['username'] ?? 'Team member')];
    }, $typingUsers)]);

    if ($aiUpdates || $deletedIds || $chatCleared) {
        $send('chat', [
            'messages' => $aiUpdates,
            'last_id' => $afterId,
            'updates' => true,
            'deleted_ids' => $deletedIds,
            'cleared' => $chatCleared,
        ]);
    }

    if (!$rows && !$aiUpdates && !$deletedIds && !$chatCleared) {
        echo ": ping " . date('c') . "\n\n";
        @ob_flush();
        flush();
    }
    sleep(1);
}
