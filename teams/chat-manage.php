<?php
require_once __DIR__ . '/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!$activeTeam || !can_read_team_chat($activeTeam, $activeMembership, $user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'You do not have access to this team chat.']);
    exit;
}
if (!$isTeamOwner) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Only the team owner can delete team chat messages.']);
    exit;
}

$teamId = (int) $activeTeam['id'];
$actorId = (int) $user['id'];
$action = strtolower(trim((string) ($_POST['action'] ?? '')));

try {
    if ($action === 'delete') {
        $messageId = max(0, (int) ($_POST['message_id'] ?? 0));
        if ($messageId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Missing message id.']);
            exit;
        }
        $deleted = delete_team_chat_message($teamId, $messageId, $actorId);
        echo json_encode(['ok' => true, 'deleted_ids' => $deleted ? [$messageId] : []]);
        exit;
    }

    if ($action === 'clear') {
        $count = clear_team_chat_messages($teamId, $actorId);
        echo json_encode(['ok' => true, 'cleared' => true, 'deleted_count' => $count]);
        exit;
    }

    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Unknown team chat action.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not update team chat.']);
}
