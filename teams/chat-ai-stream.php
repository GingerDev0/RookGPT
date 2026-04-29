<?php
require_once __DIR__ . '/_bootstrap.php';

if (!$activeTeam || !can_read_team_chat($activeTeam, $activeMembership, $user) || !can_send_team_chat($activeTeam, $activeMembership, $user) || !can_interact_with_team_bot($activeTeam, $activeMembership, $user)) {
    http_response_code(403);
    header('Content-Type: application/x-ndjson; charset=utf-8');
    echo json_encode(['type' => 'error', 'error' => 'You do not have permission to interact with the team bot.']) . "\n";
    exit;
}

$teamId = (int) $activeTeam['id'];
$messageId = max(0, (int) ($_POST['message_id'] ?? 0));
$prompt = trim((string) ($_POST['prompt'] ?? ''));
if ($messageId <= 0 || $prompt === '') {
    http_response_code(422);
    header('Content-Type: application/x-ndjson; charset=utf-8');
    echo json_encode(['type' => 'error', 'error' => 'Missing AI stream details.']) . "\n";
    exit;
}

$existing = fetch_team_chat_message_by_id($teamId, $messageId);
if (!$existing || (int) ($existing['is_ai'] ?? 0) !== 1) {
    http_response_code(404);
    header('Content-Type: application/x-ndjson; charset=utf-8');
    echo json_encode(['type' => 'error', 'error' => 'AI message was not found.']) . "\n";
    exit;
}

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@set_time_limit(0);
ignore_user_abort(true);
header('Content-Type: application/x-ndjson; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');
while (ob_get_level() > 0) { @ob_end_flush(); }
session_write_close();

$send = static function (array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    @ob_flush();
    flush();
};

try {
    if (!function_exists('curl_init')) throw new RuntimeException('cURL is not enabled on this server.');

    $settings = team_bot_settings($activeTeam);
    $send(['type' => 'start', 'message_id' => $messageId, 'content' => '']);
    update_team_chat_message_content($teamId, $messageId, '…');

    $payload = team_chat_ai_provider_payload($teamId, $user, $prompt, $messageId, $activeTeam, false);
    $data = rook_ai_post_json($payload, 600);
    $full = team_chat_clean_ai_reply(trim(rook_ai_response_text($data)));
    if ($full === '') throw new RuntimeException(rook_ai_label() . ' returned an empty response.');

    $maxChars = (int) $settings['max_reply_chars'];
    if (mb_strlen($full, 'UTF-8') > $maxChars) $full = mb_substr($full, 0, $maxChars, 'UTF-8');
    update_team_chat_message_content($teamId, $messageId, $full);
    $send(['type' => 'replace', 'message_id' => $messageId, 'content' => $full, 'done' => true]);
} catch (Throwable $e) {
    $msg = 'AI response failed: ' . $e->getMessage();
    try { update_team_chat_message_content($teamId, $messageId, $msg); } catch (Throwable $ignored) {}
    $send(['type' => 'error', 'message_id' => $messageId, 'error' => $msg, 'content' => $msg]);
}
