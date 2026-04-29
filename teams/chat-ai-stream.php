<?php
require_once __DIR__ . '/_bootstrap.php';

if (!$activeTeam || !can_read_team_chat($activeTeam, $activeMembership, $user) || !can_send_team_chat($activeTeam, $activeMembership, $user)) {
    http_response_code(403);
    header('Content-Type: application/x-ndjson; charset=utf-8');
    echo json_encode(['type' => 'error', 'error' => 'You do not have access to use team chat AI.']) . "\n";
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
    $keyState = team_chat_ai_key_plaintext($teamId);
    if (empty($keyState['ok'])) {
        $msg = (string) $keyState['message'];
        update_team_chat_message_content($teamId, $messageId, $msg);
        $send(['type' => 'replace', 'message_id' => $messageId, 'content' => $msg, 'done' => true]);
        exit;
    }
    if (!function_exists('curl_init')) throw new RuntimeException('cURL is not enabled on this server.');

    $payload = [
        'system_prompt' => team_chat_ai_system_prompt($user),
        'messages' => team_chat_ai_context_messages($teamId, $user, $prompt, $messageId),
        'think' => false,
        'temperature' => 1,
        'top_p' => 0.95,
        'top_k' => 64,
        'streaming' => true,
    ];

    $send(['type' => 'start', 'message_id' => $messageId, 'content' => '']);
    update_team_chat_message_content($teamId, $messageId, '…');

    $plainKey = (string) $keyState['key'];
    $full = '';
    $buffer = '';
    $lastSavedAt = microtime(true);

    $extract = static function (string $line): string {
        $line = trim($line);
        if ($line === '' || $line === '[DONE]') return '';
        if (stripos($line, 'data:') === 0) $line = trim(substr($line, 5));
        if ($line === '' || $line === '[DONE]') return '';
        $json = json_decode($line, true);
        if (is_array($json)) {
            $paths = [
                ['choices', 0, 'delta', 'content'],
                ['choices', 0, 'message', 'content'],
                ['choices', 0, 'text'],
                ['delta'],
                ['content'],
                ['token'],
                ['response'],
                ['message'],
            ];
            foreach ($paths as $path) {
                $value = $json;
                foreach ($path as $key) {
                    if (!is_array($value) || !array_key_exists($key, $value)) { $value = null; break; }
                    $value = $value[$key];
                }
                if (is_string($value) && $value !== '') return $value;
            }
            return '';
        }
        if (str_starts_with($line, '{') || str_starts_with($line, '[')) return '';
        return $line;
    };

    $flushContent = static function () use (&$full, $teamId, $messageId, $send): void {
        $safe = trim($full) !== '' ? team_chat_clean_ai_reply($full) : '…';
        update_team_chat_message_content($teamId, $messageId, $safe);
        $send(['type' => 'replace', 'message_id' => $messageId, 'content' => $safe]);
    };

    $ch = curl_init('https://pc.streamhive.uk/api');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: text/event-stream, application/x-ndjson, application/json, text/plain',
            'Authorization: Bearer ' . $plainKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$buffer, &$full, &$lastSavedAt, $extract, $flushContent): int {
            $buffer .= $chunk;
            $handledAny = false;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $text = $extract($line);
                if ($text !== '') {
                    $full .= $text;
                    $handledAny = true;
                }
            }
            if (!$handledAny && strlen($buffer) > 0 && !str_starts_with(ltrim($buffer), 'data:') && !str_starts_with(ltrim($buffer), '{')) {
                $full .= $buffer;
                $buffer = '';
                $handledAny = true;
            }
            if ($handledAny && microtime(true) - $lastSavedAt > 0.18) {
                $flushContent();
                $lastSavedAt = microtime(true);
            }
            return strlen($chunk);
        },
    ]);

    $ok = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (trim($buffer) !== '') {
        $text = $extract($buffer);
        if ($text !== '') $full .= $text;
    }
    if ($ok === false || $errno !== 0) throw new RuntimeException($error !== '' ? $error : 'Could not reach the AI API.');
    if ($status >= 400) throw new RuntimeException('AI API request failed with HTTP ' . $status . '.');
    $full = team_chat_clean_ai_reply($full);
    if ($full === '') throw new RuntimeException('The AI API returned an empty response.');
    if (mb_strlen($full, 'UTF-8') > 5000) $full = mb_substr($full, 0, 5000, 'UTF-8');
    update_team_chat_message_content($teamId, $messageId, $full);
    $send(['type' => 'replace', 'message_id' => $messageId, 'content' => $full, 'done' => true]);
} catch (Throwable $e) {
    $msg = 'AI response failed: ' . $e->getMessage();
    try { update_team_chat_message_content($teamId, $messageId, $msg); } catch (Throwable $ignored) {}
    $send(['type' => 'error', 'message_id' => $messageId, 'error' => $msg, 'content' => $msg]);
}
