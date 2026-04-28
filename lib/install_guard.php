<?php
declare(strict_types=1);

function rook_base_path_for_install(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    foreach (['/admin/', '/api/', '/teams/', '/install/'] as $dir) {
        $pos = strpos($script, $dir);
        if ($pos !== false) {
            $base = substr($script, 0, $pos);
            return $base === '' ? '' : rtrim($base, '/');
        }
    }
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $base === '/' ? '' : $base;
}

function rook_require_installed(): void
{
    $root = dirname(__DIR__);
    $installed = is_file($root . '/config/app.php');
    $installerExists = is_dir($root . '/install');
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if (!$installed && $installerExists && strpos($uri, '/install') === false) {
        header('Location: ' . rook_base_path_for_install() . '/install/', true, 302);
        exit;
    }
    if (!$installed) {
        http_response_code(503);
        echo 'RookGPT is not installed yet. Upload the /install/ directory and open /install/.';
        exit;
    }
}

rook_require_installed();
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/lib/ai_providers.php';
