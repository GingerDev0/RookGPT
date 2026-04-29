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
$message = trim((string) ($_POST['message'] ?? ''));
if ($message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Type a message first.']);
    exit;
}

$teamId = (int) $activeTeam['id'];
$userId = (int) $user['id'];
$aiPrompt = team_chat_ai_trigger($message);
$aiMessage = null;
$aiError = '';

try {
    $created = create_team_chat_message($teamId, $userId, $message);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not encrypt and save that message.']);
    exit;
}
if (!$created) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save that message.']);
    exit;
}

$aiStream = null;
if ($aiPrompt !== null) {
    try {
        $keyState = team_chat_ai_key_plaintext($teamId);
        if (empty($keyState['ok'])) {
            $aiMessage = create_team_chat_message($teamId, $userId, (string) $keyState['message'], true, 'AI');
        } else {
            $aiMessage = create_team_chat_message($teamId, $userId, 'Thinking…', true, 'AI');
            if ($aiMessage) {
                $aiStream = [
                    'message_id' => (int) $aiMessage['id'],
                    'prompt' => $aiPrompt,
                ];
            } else {
                $aiError = 'AI response placeholder could not be saved.';
            }
        }
    } catch (Throwable $e) {
        $aiError = 'AI response failed: ' . $e->getMessage();
        try {
            $aiMessage = create_team_chat_message($teamId, $userId, $aiError, true, 'AI');
        } catch (Throwable $ignored) {}
    }
}

$response = ['ok' => true, 'message' => $created];
if ($aiMessage) $response['ai_message'] = $aiMessage;
if ($aiStream) $response['ai_stream'] = $aiStream;
if ($aiError !== '') $response['ai_error'] = $aiError;
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
