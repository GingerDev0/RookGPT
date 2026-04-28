<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/lib/ai_providers.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') throw new RuntimeException('POST required.');
    $provider = strtolower(trim((string)($_POST['provider'] ?? '')));
    $endpoint = trim((string)($_POST['endpoint'] ?? ''));
    $apiKey = trim((string)($_POST['api_key'] ?? ''));
    $models = rook_ai_fetch_models($provider, $endpoint, $apiKey);
    echo json_encode(['ok'=>true, 'models'=>$models], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
