<?php
/** Shared web security helpers for RookGPT. */
if (!function_exists('csrf_token')) {
    function rook_is_https_request(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    }

    function rook_hardened_session_start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        if (!headers_sent()) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', rook_is_https_request() ? '1' : '0');
            ini_set('session.cookie_samesite', 'Lax');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => rook_is_https_request(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_start();
    }

    function rook_safe_restart_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => (bool) ($params['secure'] ?? rook_is_https_request()),
                    'httponly' => true,
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]);
            }
            session_destroy();
        }
        rook_hardened_session_start();
    }

    function rook_ip_is_private_or_reserved(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return true;
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    function rook_ip_is_loopback(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        return $ip === '127.0.0.1' || str_starts_with($ip, '127.') || $ip === '::1';
    }

    function rook_hostname_is_local(string $host): bool
    {
        $host = strtolower(trim($host, '[] '));
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost');
    }

    function rook_validate_outbound_http_url(string $url, bool $allowLocal = false): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Endpoint must be a valid HTTP or HTTPS URL.');
        }
        if (rook_hostname_is_local($host)) {
            if ($allowLocal) return;
            throw new RuntimeException('Endpoint cannot target localhost or loopback addresses.');
        }
        $cleanHost = trim($host, '[]');
        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            if (rook_ip_is_private_or_reserved($cleanHost) && !($allowLocal && rook_ip_is_loopback($cleanHost))) {
                throw new RuntimeException('Endpoint must resolve to a public IP address.');
            }
            return;
        }
        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        if ($records === []) throw new RuntimeException('Endpoint host could not be resolved.');
        foreach ($records as $record) {
            $ip = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
            if ($ip !== '' && rook_ip_is_private_or_reserved($ip)) {
                throw new RuntimeException('Endpoint must resolve only to public IP addresses.');
            }
        }
    }

    function csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            rook_hardened_session_start();
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
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
        $sent = (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
            http_response_code(403);
            exit('Invalid CSRF token. Refresh the page and try again.');
        }
    }

    function csrf_inject_html(string $html): string
    {
        if (stripos($html, '<form') === false || stripos($html, 'method="post"') === false && stripos($html, "method='post'") === false && stripos($html, 'method=post') === false) return $html;
        $field = csrf_field();
        return preg_replace_callback('/<form\b([^>]*)>/i', static function (array $m) use ($field): string {
            $tag = $m[0];
            $attrs = $m[1] ?? '';
            if (!preg_match('/\bmethod\s*=\s*(["\']?)post\1/i', $attrs)) return $tag;
            if (stripos($tag, 'data-no-csrf') !== false) return $tag;
            return $tag . $field;
        }, $html) ?? $html;
    }

    function csrf_bootstrap_web(bool $verifyPost = true): void
    {
        csrf_token();
        if ($verifyPost) verify_csrf_request();
        if (!headers_sent()) ob_start('csrf_inject_html');
    }
}
