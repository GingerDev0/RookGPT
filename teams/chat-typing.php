<?php
require_once __DIR__ . '/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!$activeTeam || !can_read_team_chat($activeTeam, $activeMembership, $user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'You do not have access to this team chat.']);
    exit;
}
if (!can_send_team_chat($activeTeam, $activeMembership, $user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Your team role cannot send messages.']);
    exit;
}

$isTyping = (int) ($_POST['typing'] ?? 0) === 1;
try {
    set_team_chat_typing((int) $activeTeam['id'], (int) $user['id'], $isTyping);
    echo json_encode(['ok' => true, 'typing' => $isTyping], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not update typing status.']);
}
