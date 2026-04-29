<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    require_admin();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') throw new RuntimeException('POST required.');
    $provider = strtolower(trim((string)($_POST['provider'] ?? '')));
    $endpoint = trim((string)($_POST['endpoint'] ?? ''));
    $apiKey = trim((string)($_POST['api_key'] ?? ''));
    $models = rook_ai_fetch_models($provider, $endpoint, $apiKey);
    echo json_encode(['ok'=>true, 'models'=>$models], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    error_log($e);
    echo json_encode(['ok'=>false, 'error'=>'Could not fetch models. Check the provider endpoint and key.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
