<?php
/** Shared web security helpers for RookGPT. */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    function verify_csrf_request(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        $sent = (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
            http_response_code(403);
            exit('Invalid CSRF token. Refresh the page and try again.');
        }
    }

    function csrf_inject_html(string $html): string
    {
        if (stripos($html, '<form') === false || stripos($html, 'method="post"') === false && stripos($html, "method='post'") === false && stripos($html, 'method=post') === false) {
            return $html;
        }
        $field = csrf_field();
        return preg_replace_callback('/<form\b([^>]*)>/i', static function (array $m) use ($field): string {
            $tag = $m[0];
            $attrs = $m[1] ?? '';
            if (!preg_match('/\bmethod\s*=\s*(["\']?)post\1/i', $attrs)) {
                return $tag;
            }
            if (stripos($tag, 'data-no-csrf') !== false) {
                return $tag;
            }
            return $tag . $field;
        }, $html) ?? $html;
    }

    function csrf_bootstrap_web(bool $verifyPost = true): void
    {
        csrf_token();
        if ($verifyPost) {
            verify_csrf_request();
        }
        if (!headers_sent()) {
            ob_start('csrf_inject_html');
        }
    }
}
