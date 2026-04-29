<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/security.php';
rook_hardened_session_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/lib/ai_providers.php';
try {
    if (is_file(dirname(__DIR__) . '/config/app.php')) { http_response_code(404); echo json_encode(['ok'=>false, 'error'=>'Installer is disabled after installation.']); exit; }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') throw new RuntimeException('POST required.');
    $provider = strtolower(trim((string)($_POST['provider'] ?? '')));
    $endpoint = trim((string)($_POST['endpoint'] ?? ''));
    $apiKey = trim((string)($_POST['api_key'] ?? ''));
    $host = parse_url($endpoint, PHP_URL_HOST);
    $scheme = strtolower((string)(parse_url($endpoint, PHP_URL_SCHEME) ?: ''));
    if ($endpoint !== '' && !in_array($scheme, ['http','https'], true)) throw new RuntimeException('Invalid endpoint scheme.');
    if ($provider !== 'ollama' && $host) {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        foreach ($records as $record) {
            $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('Endpoint must resolve to a public IP address.');
            }
        }
    }
    $models = rook_ai_fetch_models($provider, $endpoint, $apiKey);
    echo json_encode(['ok'=>true, 'models'=>$models], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    error_log($e);
    echo json_encode(['ok'=>false, 'error'=>'Could not fetch models. Check the endpoint and key.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
