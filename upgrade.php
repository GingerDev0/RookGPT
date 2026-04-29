<?php
declare(strict_types=1);

date_default_timezone_set('Europe/London');
session_start();
require_once __DIR__ . '/lib/install_guard.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/plans.php';
csrf_bootstrap_web();

defined('ANNUAL_DISCOUNT_MONTHS') || define('ANNUAL_DISCOUNT_MONTHS', 2); // Annual plans are charged as 10 months for 12 months of access.

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli
{
    static $db = null;
    if ($db instanceof mysqli) return $db;
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->set_charset('utf8mb4');
    return $db;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function db_fetch_one(string $sql, string $types = '', array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return is_array($row) ? $row : null;
}

function db_execute(string $sql, string $types = '', array $params = []): int
{
    $stmt = db()->prepare($sql);
    if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function db_column_exists(string $table, string $column): bool
{
    $row = db_fetch_one(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
        'ss',
        [$table, $column]
    );
    return (int) ($row['c'] ?? 0) > 0;
}

function db_index_exists(string $table, string $index): bool
{
    $row = db_fetch_one(
        'SELECT COUNT(*) AS c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
        'ss',
        [$table, $index]
    );
    return (int) ($row['c'] ?? 0) > 0;
}

function ensure_upgrade_schema(): void
{
    try { db()->query("ALTER TABLE users MODIFY COLUMN plan VARCHAR(64) NOT NULL DEFAULT 'free'"); } catch (Throwable $e) {}
        if (!db_column_exists('users', 'plan_expires_at')) {
        db()->query("ALTER TABLE users ADD COLUMN plan_expires_at DATETIME NULL AFTER plan");
    }
    if (!db_column_exists('users', 'plan_billing_period')) {
        db()->query("ALTER TABLE users ADD COLUMN plan_billing_period ENUM('monthly','annual','team','manual') NULL AFTER plan_expires_at");
    }

    db()->query("CREATE TABLE IF NOT EXISTS promo_codes (
"
      . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,
"
      . "code VARCHAR(64) NOT NULL,
"
      . "description VARCHAR(255) NULL,
"
      . "discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
"
      . "discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
"
      . "applies_to_plan VARCHAR(64) NOT NULL DEFAULT 'any',
"
      . "applies_to_period ENUM('any','monthly','annual') NOT NULL DEFAULT 'any',
"
      . "max_redemptions INT UNSIGNED NULL,
"
      . "redeemed_count INT UNSIGNED NOT NULL DEFAULT 0,
"
      . "starts_at DATETIME NULL,
"
      . "expires_at DATETIME NULL,
"
      . "is_active TINYINT(1) NOT NULL DEFAULT 1,
"
      . "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
"
      . "PRIMARY KEY (id),
"
      . "UNIQUE KEY uniq_promo_codes_code (code),
"
      . "KEY idx_promo_codes_active (is_active),
"
      . "KEY idx_promo_codes_dates (starts_at, expires_at)
"
      . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $promoColumns = [
        'description' => "ALTER TABLE promo_codes ADD COLUMN description VARCHAR(255) NULL AFTER code",
        'discount_type' => "ALTER TABLE promo_codes ADD COLUMN discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent' AFTER description",
        'discount_value' => "ALTER TABLE promo_codes ADD COLUMN discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_type",
        'applies_to_plan' => "ALTER TABLE promo_codes ADD COLUMN applies_to_plan VARCHAR(64) NOT NULL DEFAULT 'any' AFTER discount_value",
        'applies_to_period' => "ALTER TABLE promo_codes ADD COLUMN applies_to_period ENUM('any','monthly','annual') NOT NULL DEFAULT 'any' AFTER applies_to_plan",
        'max_redemptions' => "ALTER TABLE promo_codes ADD COLUMN max_redemptions INT UNSIGNED NULL AFTER applies_to_period",
        'redeemed_count' => "ALTER TABLE promo_codes ADD COLUMN redeemed_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER max_redemptions",
        'starts_at' => "ALTER TABLE promo_codes ADD COLUMN starts_at DATETIME NULL AFTER redeemed_count",
        'expires_at' => "ALTER TABLE promo_codes ADD COLUMN expires_at DATETIME NULL AFTER starts_at",
        'is_active' => "ALTER TABLE promo_codes ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER expires_at",
        'created_at' => "ALTER TABLE promo_codes ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active",
    ];
    foreach ($promoColumns as $column => $sql) {
        if (!db_column_exists('promo_codes', $column)) db()->query($sql);
    }
    if (!db_index_exists('promo_codes', 'uniq_promo_codes_code')) db()->query('ALTER TABLE promo_codes ADD UNIQUE KEY uniq_promo_codes_code (code)');

    db()->query("CREATE TABLE IF NOT EXISTS promo_code_redemptions (
"
      . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
"
      . "promo_code_id INT UNSIGNED NOT NULL,
"
      . "user_id INT UNSIGNED NOT NULL,
"
      . "stripe_session_id VARCHAR(255) NOT NULL,
"
      . "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
"
      . "PRIMARY KEY (id),
"
      . "UNIQUE KEY uniq_promo_redemptions_session (stripe_session_id),
"
      . "KEY idx_promo_redemptions_promo_code_id (promo_code_id),
"
      . "KEY idx_promo_redemptions_user_id (user_id),
"
      . "CONSTRAINT fk_promo_redemptions_code FOREIGN KEY (promo_code_id) REFERENCES promo_codes (id) ON DELETE CASCADE,
"
      . "CONSTRAINT fk_promo_redemptions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
"
      . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}


function db_column_exists_auth(string $table, string $column): bool
{
    try {
        $stmt = db()->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $stmt->bind_param('ss', $table, $column); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close(); return (int) ($row['total'] ?? 0) > 0;
    } catch (Throwable $e) { return false; }
}
function ensure_auth_security_schema(): void
{
    try {
        try { db()->query("ALTER TABLE users MODIFY COLUMN plan VARCHAR(64) NOT NULL DEFAULT 'free'"); } catch (Throwable $e) {}
        if (!db_column_exists_auth('users', 'current_session_token')) db()->query("ALTER TABLE users ADD COLUMN current_session_token VARCHAR(128) NULL AFTER custom_prompt");
        if (!db_column_exists_auth('users', 'session_rotated_at')) db()->query("ALTER TABLE users ADD COLUMN session_rotated_at DATETIME NULL AFTER current_session_token");
        if (!db_column_exists_auth('users', 'two_factor_secret')) db()->query("ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(64) NULL AFTER session_rotated_at");
        if (!db_column_exists_auth('users', 'two_factor_enabled_at')) db()->query("ALTER TABLE users ADD COLUMN two_factor_enabled_at DATETIME NULL AFTER two_factor_secret");
    } catch (Throwable $e) {}
}
function clear_local_session(?string $flash = null): void { $_SESSION=[]; session_destroy(); session_start(); if($flash) $_SESSION['flash']=$flash; }
function current_user(): ?array
{
    ensure_auth_security_schema();
    if (!isset($_SESSION['user_id'])) return null;
    $row = db_fetch_one('SELECT id, username, email, CASE WHEN plan_expires_at IS NOT NULL AND plan_expires_at < NOW() THEN "free" ELSE plan END AS plan, plan_expires_at, plan_billing_period, thinking_enabled, current_session_token, session_rotated_at, two_factor_secret, two_factor_enabled_at, created_at FROM users WHERE id = ? LIMIT 1', 'i', [(int) $_SESSION['user_id']]);
    if (!$row) { clear_local_session(); return null; }
    $sessionToken = (string) ($_SESSION['session_token'] ?? '');
    $currentToken = (string) ($row['current_session_token'] ?? '');
    if ($sessionToken === '' || $currentToken === '' || !hash_equals($currentToken, $sessionToken)) { clear_local_session('You were signed out because this account was used on another device.'); return null; }
    return $row;
}

function env_value(string $key, string $fallback = ''): string
{
    $value = getenv($key);
    if ($value === false || trim((string) $value) === '') return $fallback;
    return trim((string) $value);
}

function stripe_secret_key(): string
{
    return env_value('STRIPE_SECRET_KEY', defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '');
}

function app_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($scriptDir === '/' || $scriptDir === '.') $scriptDir = '';
    return $scheme . '://' . $host . $scriptDir;
}

function stripe_request(string $method, string $endpoint, array $params = []): array
{
    $secret = stripe_secret_key();
    if ($secret === '' || !str_starts_with($secret, 'sk_')) {
        throw new RuntimeException('Stripe is not configured yet. Add your Stripe secret key in /admin/ or config/app.php.');
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
    $body = $params === [] ? '' : http_build_query($params);
    $headers = [
        'Authorization: Bearer ' . $secret,
        'Content-Type: application/x-www-form-urlencoded',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Could not initialise Stripe request.');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) throw new RuntimeException('Stripe request failed: ' . $curlError);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $raw = file_get_contents($url, false, $context);
        if ($raw === false) throw new RuntimeException('Stripe request failed. Enable PHP curl or allow_url_fopen.');
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $httpCode = isset($matches[1]) ? (int) $matches[1] : 0;
    }

    $json = json_decode((string) $raw, true);
    if (!is_array($json)) throw new RuntimeException('Stripe returned an invalid response.');

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = $json['error']['message'] ?? 'Stripe request failed.';
        throw new RuntimeException((string) $message);
    }

    return $json;
}

function billing_periods(): array
{
    return [
        'monthly' => ['label' => 'Monthly', 'months' => 1, 'suffix' => '/month', 'multiplier' => 1],
        'annual' => ['label' => 'Annual', 'months' => 12, 'suffix' => '/year', 'multiplier' => 12, 'discount_months' => ANNUAL_DISCOUNT_MONTHS],
    ];
}

function normalise_period(string $period): string
{
    return array_key_exists($period, billing_periods()) ? $period : 'monthly';
}

function upgrade_plan_price(string $plan, float $fallback): float { return rook_plan_price_gbp($plan, $fallback); }

function plan_base_amount(array $plan, string $period): float
{
    $periods = billing_periods();
    $multiplier = (int) ($periods[$period]['multiplier'] ?? 1);
    return ((float) $plan['price_gbp']) * $multiplier;
}

function annual_discount_amount(array $plan, string $period): float
{
    if ($period !== 'annual') return 0;
    $periods = billing_periods();
    $discountMonths = (int) ($periods[$period]['discount_months'] ?? 0);
    return max(0.0, ((float) $plan['price_gbp']) * $discountMonths);
}

function plan_amount(array $plan, string $period): float
{
    return max(0, plan_base_amount($plan, $period) - annual_discount_amount($plan, $period));
}

function plan_credit_info(array $user, array $plans): array
{
    $currentPlan = strtolower((string) ($user['plan'] ?? 'free'));
    if ($currentPlan === 'free' || !isset($plans[$currentPlan])) {
        return ['credit_pence' => 0, 'days_left' => 0, 'label' => ''];
    }

    $expiresRaw = (string) ($user['plan_expires_at'] ?? '');
    if ($expiresRaw === '') {
        return ['credit_pence' => 0, 'days_left' => 0, 'label' => ''];
    }

    try {
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/London'));
        $expires = new DateTimeImmutable($expiresRaw, new DateTimeZone('Europe/London'));
    } catch (Throwable $e) {
        return ['credit_pence' => 0, 'days_left' => 0, 'label' => ''];
    }

    $remainingSeconds = $expires->getTimestamp() - $now->getTimestamp();
    if ($remainingSeconds <= 0) {
        return ['credit_pence' => 0, 'days_left' => 0, 'label' => ''];
    }

    $billingPeriod = normalise_period(strtolower((string) ($user['plan_billing_period'] ?? 'monthly')));
    $periodSeconds = $billingPeriod === 'annual' ? (365.25 * 86400) : (30.4375 * 86400);
    $currentPlanPricePence = (int) round(plan_amount($plans[$currentPlan], $billingPeriod) * 100);
    $creditPence = (int) floor($currentPlanPricePence * min(1, $remainingSeconds / $periodSeconds));
    $daysLeft = (int) ceil($remainingSeconds / 86400);

    return [
        'credit_pence' => max(0, $creditPence),
        'days_left' => max(1, $daysLeft),
        'label' => (string) $plans[$currentPlan]['label'],
        'billing_period' => $billingPeriod,
        'expires_at' => $expires->format('Y-m-d H:i:s'),
    ];
}

function normalise_promo_code(string $rawCode): string
{
    return strtoupper(preg_replace('/[^A-Z0-9_-]/i', '', trim($rawCode)) ?? '');
}

function validate_promo_code(string $rawCode, string $plan, string $period, int $subtotalPence): ?array
{
    $code = normalise_promo_code($rawCode);
    if ($code === '') return null;

    $promo = db_fetch_one(
        "SELECT * FROM promo_codes
         WHERE UPPER(code) = ?
           AND is_active = 1
           AND (starts_at IS NULL OR starts_at <= NOW())
           AND (expires_at IS NULL OR expires_at >= NOW())
         LIMIT 1",
        's',
        [$code]
    );

    if (!$promo) throw new RuntimeException('That promo code is not valid.');

    $appliesToPlan = (string) ($promo['applies_to_plan'] ?? 'any');
    $appliesToPeriod = (string) ($promo['applies_to_period'] ?? 'any');
    if ($appliesToPlan !== 'any' && $appliesToPlan !== $plan) throw new RuntimeException('That promo code does not apply to this plan.');
    if ($appliesToPeriod !== 'any' && $appliesToPeriod !== $period) throw new RuntimeException('That promo code does not apply to this billing option.');

    $maxRedemptions = $promo['max_redemptions'] === null ? null : (int) $promo['max_redemptions'];
    if ($maxRedemptions !== null && (int) $promo['redeemed_count'] >= $maxRedemptions) throw new RuntimeException('That promo code has already been fully redeemed.');

    $discountPence = 0;
    if (($promo['discount_type'] ?? '') === 'percent') {
        $percent = max(0, min(100, (float) $promo['discount_value']));
        $discountPence = (int) round($subtotalPence * ($percent / 100));
    } else {
        $discountPence = ((int) round((float) $promo['discount_value'] * 100));
    }
    $discountPence = max(0, min($subtotalPence, $discountPence));

    return [
        'id' => (int) $promo['id'],
        'code' => (string) $promo['code'],
        'discount_pence' => $discountPence,
        'discount_label' => ($promo['discount_type'] === 'percent') ? rtrim(rtrim((string) $promo['discount_value'], '0'), '.') . '%' : '£' . number_format($discountPence / 100, 2),
    ];
}

function create_stripe_checkout_coupon(int $discountPence, string $name): string
{
    $discountPence = max(0, $discountPence);
    if ($discountPence <= 0) return '';

    $coupon = stripe_request('POST', 'coupons', [
        'amount_off' => (string) $discountPence,
        'currency' => 'gbp',
        'duration' => 'once',
        'name' => $name,
        'metadata[source]' => 'rook_upgrade_checkout',
    ]);

    $couponId = $coupon['id'] ?? '';
    if (!is_string($couponId) || $couponId === '') throw new RuntimeException('Stripe did not return a coupon for the discount.');
    return $couponId;
}

function create_checkout_session(array $user, string $selectedPlan, string $period, array $plan, ?array $promo, array $planCredit): string
{
    $baseUrl = app_base_url();
    // Annual discount is already baked into this Stripe line item amount.
    // Current-plan credit and local promo codes are then applied inside Stripe as a one-off coupon.
    $subtotalPence = (int) round(plan_amount($plan, $period) * 100);
    $planCreditPence = max(0, min($subtotalPence, (int) ($planCredit['credit_pence'] ?? 0)));
    $promoBasePence = max(0, $subtotalPence - $planCreditPence);
    $promoDiscountPence = (int) ($promo['discount_pence'] ?? 0);
    $promoDiscountPence = max(0, min($promoBasePence, $promoDiscountPence));
    $stripeDiscountPence = max(0, min($subtotalPence, $planCreditPence + $promoDiscountPence));
    $priceInPence = max(0, $subtotalPence);
    $periods = billing_periods();

    $params = [
        'mode' => 'payment',
        'success_url' => $baseUrl . '/upgrade?stripe_success=1&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $baseUrl . '/upgrade?plan=' . rawurlencode($selectedPlan) . '&period=' . rawurlencode($period) . '&stripe_cancel=1',
        'customer_email' => (string) $user['email'],
        'client_reference_id' => (string) $user['id'],
        'metadata[user_id]' => (string) $user['id'],
        'metadata[plan]' => $selectedPlan,
        'metadata[billing_period]' => $period,
        'metadata[promo_code_id]' => $promo ? (string) $promo['id'] : '',
        'metadata[promo_code]' => $promo ? (string) $promo['code'] : '',
        'metadata[subtotal_pence]' => (string) $subtotalPence,
        'metadata[promo_discount_pence]' => (string) $promoDiscountPence,
        'metadata[plan_credit_pence]' => (string) $planCreditPence,
        'metadata[stripe_discount_pence]' => (string) $stripeDiscountPence,
        'metadata[plan_credit_days_left]' => (string) ((int) ($planCredit['days_left'] ?? 0)),
        'line_items[0][quantity]' => '1',
        'line_items[0][price_data][currency]' => 'gbp',
        'line_items[0][price_data][unit_amount]' => (string) $priceInPence,
        'line_items[0][price_data][tax_behavior]' => 'exclusive',
        'line_items[0][price_data][product_data][name]' => APP_NAME . ' ' . (string) $plan['label'] . ' — ' . (string) $periods[$period]['label'],
        'line_items[0][price_data][product_data][description]' => (string) $plan['description'],
        'automatic_tax[enabled]' => 'true',
        'billing_address_collection' => 'required',
        'tax_id_collection[enabled]' => 'true',
    ];

    if ($stripeDiscountPence > 0) {
        $couponLabel = APP_NAME . ' upgrade credit';
        if ($promo) $couponLabel .= ' + promo ' . (string) $promo['code'];
        $params['discounts[0][coupon]'] = create_stripe_checkout_coupon($stripeDiscountPence, $couponLabel);
    }

    $session = stripe_request('POST', 'checkout/sessions', $params);

    $url = $session['url'] ?? '';
    if (!is_string($url) || $url === '') throw new RuntimeException('Stripe did not return a checkout URL.');
    return $url;
}

function verify_checkout_session_and_upgrade(array $user, array $plans): string
{
    $sessionId = (string) ($_GET['session_id'] ?? '');
    if ($sessionId === '' || !str_starts_with($sessionId, 'cs_')) {
        throw new RuntimeException('Missing or invalid Stripe checkout session.');
    }

    $session = stripe_request('GET', 'checkout/sessions/' . rawurlencode($sessionId));
    $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
    $planSlug = strtolower((string) ($metadata['plan'] ?? ''));
    $period = normalise_period(strtolower((string) ($metadata['billing_period'] ?? 'monthly')));
    $promoCodeId = (int) ($metadata['promo_code_id'] ?? 0);
    $sessionUserId = (int) ($metadata['user_id'] ?? $session['client_reference_id'] ?? 0);
    $status = (string) ($session['status'] ?? '');
    $paymentStatus = (string) ($session['payment_status'] ?? '');

    if ($sessionUserId !== (int) $user['id']) {
        throw new RuntimeException('That Stripe session does not belong to this account.');
    }
    if (!isset($plans[$planSlug])) {
        throw new RuntimeException('Stripe returned an unknown plan.');
    }
    if ($status !== 'complete' || !in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
        throw new RuntimeException('Stripe checkout is not complete yet.');
    }

    $periods = billing_periods();
    $months = (int) $periods[$period]['months'];
    db_execute(
        "UPDATE users
         SET plan = ?, thinking_enabled = 1, plan_billing_period = ?, plan_expires_at = DATE_ADD(NOW(), INTERVAL ? MONTH)
         WHERE id = ?",
        'ssii',
        [$planSlug, $period, $months, (int) $user['id']]
    );

    if ($promoCodeId > 0) {
        db_execute('UPDATE promo_codes SET redeemed_count = redeemed_count + 1 WHERE id = ?', 'i', [$promoCodeId]);
        db_execute(
            'INSERT IGNORE INTO promo_code_redemptions (promo_code_id, user_id, stripe_session_id) VALUES (?, ?, ?)',
            'iis',
            [$promoCodeId, (int) $user['id'], $sessionId]
        );
    }

    return (string) $plans[$planSlug]['label'];
}

$plans = rook_enabled_paid_plans();

ensure_upgrade_schema();

$user = current_user();
if (!$user) {
    $_SESSION['flash'] = 'Sign in before upgrading your account.';
    header('Location: index.php');
    exit;
}

$selectedPlan = strtolower((string) ($_GET['plan'] ?? $_POST['plan'] ?? 'pro'));
if (!isset($plans[$selectedPlan])) { $keys = array_keys($plans); $selectedPlan = $keys[0] ?? 'free'; }
$period = normalise_period(strtolower((string) ($_GET['period'] ?? $_POST['period'] ?? 'monthly')));
$plan = $plans[$selectedPlan];
$currentPlan = (string) ($user['plan'] ?? 'free');
$isCurrentOrLower = rook_plan_rank($selectedPlan) <= rook_plan_rank($currentPlan);
$stripeNotice = '';
$stripeError = '';
$promoInput = normalise_promo_code((string) ($_GET['promo'] ?? $_POST['promo_code'] ?? ''));
$baseSubtotal = plan_base_amount($plan, $period);
$annualDiscount = annual_discount_amount($plan, $period);
$subtotal = plan_amount($plan, $period);
$planCredit = plan_credit_info($user, $plans);
$subtotalPence = (int) round($subtotal * 100);
$planCreditPence = max(0, min($subtotalPence, (int) ($planCredit['credit_pence'] ?? 0)));
$promoBasePence = max(0, $subtotalPence - $planCreditPence);
$promo = null;

if ($promoInput !== '') {
    try {
        $promo = validate_promo_code($promoInput, $selectedPlan, $period, $promoBasePence);
    } catch (Throwable $e) {
        $stripeError = $e->getMessage();
    }
}

if (isset($_GET['stripe_success'])) {
    try {
        $upgradedLabel = verify_checkout_session_and_upgrade($user, $plans);
        $_SESSION['flash'] = 'Payment complete. Account upgraded to ' . $upgradedLabel . '.';
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        $stripeError = $e->getMessage();
    }
}

if (isset($_GET['stripe_cancel'])) {
    $stripeNotice = 'Stripe checkout was cancelled. No payment was taken.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_promo'])) {
    header('Location: upgrade?plan=' . urlencode($selectedPlan) . '&period=' . urlencode($period) . ($promoInput !== '' ? '&promo=' . urlencode($promoInput) : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_upgrade'])) {
    if ($isCurrentOrLower) {
        $_SESSION['flash'] = 'You are already on this plan or better.';
        header('Location: upgrade?plan=' . urlencode($selectedPlan) . '&period=' . urlencode($period));
        exit;
    }

    try {
        if ($promoInput !== '' && !$promo) {
            throw new RuntimeException($stripeError !== '' ? $stripeError : 'That promo code could not be applied.');
        }

        $checkoutUrl = create_checkout_session($user, $selectedPlan, $period, $plan, $promo, $planCredit);
        header('Location: ' . $checkoutUrl, true, 303);
        exit;
    } catch (Throwable $e) {
        $stripeError = $e->getMessage();
    }
}

$discount = $promo ? ((int) $promo['discount_pence'] / 100) : 0.0;
$planCreditAmount = $planCreditPence / 100;
$total = max(0, $subtotal - $planCreditAmount - $discount);
$periods = billing_periods();
$periodLabel = (string) $periods[$period]['label'];
$periodSuffix = (string) $periods[$period]['suffix'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Upgrade to <?= e((string) $plan['label']) ?> — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="/rook.css">
  <style>
    :root { --bg:#070b12; --panel:#0d1524; --panel-2:#111b2d; --text:#e9f0ff; --muted:#8d9bb3; --accent:#7c9cff; --danger:#ff6b7a; --ok:#35d09f; --shadow-lg:0 24px 70px rgba(0,0,0,.45); }
    * { box-sizing:border-box; }
    html, body { min-height:100%; }
    body { margin:0; font-family:Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:radial-gradient(circle at 20% 0%, rgba(124,156,255,.2), transparent 34%), linear-gradient(135deg, #070b12, #0a111d 45%, #070b12); color:var(--text); }
    a { color:inherit; }
    .upgrade-shell { min-height:100vh; display:grid; grid-template-rows:auto 1fr; }
    .upgrade-topbar { position:sticky; top:0; z-index:5; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:16px 22px; background:rgba(9,14,24,.9); backdrop-filter:blur(18px); border-bottom:1px solid rgba(255,255,255,.06); }
    .brand { display:flex; align-items:center; gap:14px; text-decoration:none; }
    .brand-mark { width:42px; height:42px; display:grid; place-items:center; background:linear-gradient(135deg, var(--accent), #b6c8ff); color:#08111f; font-weight:900; box-shadow:0 18px 32px rgba(77,109,218,.22); }
    .brand h1 { margin:0; font-size:1.05rem; font-weight:900; letter-spacing:-.04em; }
    .brand p { margin:2px 0 0; color:var(--muted); font-size:.78rem; }
    .ghost-btn, .new-chat-btn { display:inline-flex; align-items:center; justify-content:center; gap:9px; border:0; text-decoration:none; font-weight:800; transition:.18s ease; }
    .ghost-btn { padding:11px 13px; color:var(--text); background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); }
    .ghost-btn:hover { background:rgba(255,255,255,.08); }
    .new-chat-btn { padding:14px 16px; color:#08111f; background:linear-gradient(135deg, var(--accent), #b6c8ff); box-shadow:0 14px 28px rgba(77,109,218,.26); }
    .new-chat-btn:disabled { opacity:.55; cursor:not-allowed; }
    .upgrade-main { width:min(1180px, calc(100% - 32px)); margin:0 auto; padding:34px 0; }
    .eyebrow { color:var(--muted); font-size:.76rem; font-weight:900; letter-spacing:.14em; text-transform:uppercase; margin-bottom:10px; }
    .hero { display:grid; grid-template-columns:minmax(0, 1.15fr) minmax(320px, .85fr); gap:16px; align-items:stretch; }
    .panel { border:1px solid rgba(255,255,255,.08); background:linear-gradient(180deg, rgba(17,24,39,.94), rgba(12,18,32,.92)); box-shadow:var(--shadow-lg); padding:22px; }
    .plan-hero { position:relative; overflow:hidden; }
    .plan-hero::after { content:""; position:absolute; inset:auto -70px -100px auto; width:260px; height:260px; border-radius:999px; background:rgba(124,156,255,.14); filter:blur(6px); }
    .plan-title { position:relative; z-index:1; margin:0; font-size:clamp(2rem, 5vw, 4rem); font-weight:900; letter-spacing:-.07em; line-height:.95; }
    .plan-subtitle { position:relative; z-index:1; margin:16px 0 0; color:var(--muted); line-height:1.7; max-width:680px; }
    .recommended { display:inline-flex; align-items:center; gap:8px; padding:7px 10px; color:#08111f; background:linear-gradient(135deg, var(--accent), #b8c9ff); font-size:.72rem; font-weight:900; letter-spacing:.08em; text-transform:uppercase; margin-bottom:16px; }
    .feature-grid { position:relative; z-index:1; display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; margin-top:24px; }
    .feature { padding:13px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.04); color:var(--muted); line-height:1.45; }
    .feature i { color:var(--ok); margin-right:8px; }
    .cart { display:flex; flex-direction:column; gap:14px; }
    .cart-row { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding:13px 0; border-bottom:1px solid rgba(255,255,255,.08); }
    .cart-row:last-child { border-bottom:0; }
    .cart-row span { color:var(--muted); }
    .cart-row strong { font-weight:900; text-align:right; }
    .price-big { font-size:2.6rem; font-weight:900; letter-spacing:-.06em; line-height:1; }
    .price-big small { color:var(--muted); font-size:.9rem; font-weight:700; letter-spacing:0; }
    .plan-picker { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; margin-top:16px; }
    .plan-option { position:relative; display:flex; flex-direction:column; min-height:100%; padding:16px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.035); text-decoration:none; }
    .plan-option:hover, .plan-option.active { border-color:rgba(124,156,255,.55); background:rgba(124,156,255,.1); }
    .plan-option.active::before { content:"Selected"; position:absolute; top:12px; right:12px; color:#08111f; background:linear-gradient(135deg, var(--accent), #b8c9ff); padding:4px 7px; font-size:.62rem; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
    .plan-option-title { color:var(--text); font-weight:900; padding-right:74px; }
    .plan-option-price { margin-top:8px; color:var(--text); font-size:1.35rem; font-weight:900; }
    .plan-option p { color:var(--muted); line-height:1.5; margin:8px 0 0; font-size:.88rem; }
    .billing-switch { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .billing-switch a { text-decoration:none; text-align:center; padding:11px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.04); color:var(--muted); font-weight:900; }
    .billing-switch a.active { color:#08111f; background:linear-gradient(135deg, var(--accent), #b6c8ff); }
    .promo-form { display:grid; grid-template-columns:1fr auto; gap:8px; }
    .promo-form input { min-width:0; padding:12px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:var(--text); outline:0; }
    .promo-form button { padding:12px 14px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.08); color:var(--text); font-weight:900; }
    .notice { border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.04); color:var(--muted); padding:13px; line-height:1.55; }
    @media (max-width: 900px) { .hero { grid-template-columns:1fr; } .plan-picker, .feature-grid { grid-template-columns:1fr; } .upgrade-topbar { align-items:flex-start; flex-direction:column; } }
  </style>
</head>
<body class="rook-body rook-app is-authenticated">
  <div class="app">
    <aside class="sidebar">
      <div class="sidebar-top">
        <a href="index.php" class="brand"><div class="brand-mark"><i class="fa-solid fa-chess-rook"></i></div><div><h1><?= APP_NAME ?></h1><p>Upgrade cart</p></div></a>
        <div class="workspace-label">Workspace</div>
      </div>
      <div class="sidebar-body">
        <a class="sidebar-link" href="index.php"><i class="fa-solid fa-comments"></i> Chat workspace</a>
        <a class="sidebar-link" href="/api/"><i class="fa-solid fa-key"></i> API keys</a>
        <a class="sidebar-link" href="/teams/"><i class="fa-solid fa-users"></i> Teams</a>
        <a class="sidebar-link active" href="upgrade"><i class="fa-solid fa-arrow-up-right-dots"></i> Upgrade</a>
        <div class="page-panel p-3 mt-auto"><div class="muted small">Selected plan</div><strong><?= e((string) $plan['label']) ?></strong><div class="muted small"><?= e($periodLabel) ?></div></div>
      </div>
    </aside>
    <main class="main-panel">
      <header class="topbar">
        <div class="topbar-main"><div class="topbar-icon"><i class="fa-solid fa-arrow-up-right-dots"></i></div><div class="topbar-title"><h2>Upgrade to <?= e((string) $plan['label']) ?></h2><p>Review your selected plan and complete checkout.</p></div></div>
        <div class="topbar-actions"><a class="ghost-btn" href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to chat</a></div>
      </header>
      <div class="page-content"><div class="upgrade-main">
      <div class="hero">
        <section class="panel plan-hero">
          <?php if (!empty($plan['recommended'])): ?><div class="recommended"><i class="fa-solid fa-bolt"></i> Recommended</div><?php endif; ?>
          <div class="eyebrow">Selected upgrade</div>
          <h2 class="plan-title"><?= e((string) $plan['label']) ?></h2>
          <p class="plan-subtitle"><strong style="color:var(--text)"><?= e((string) $plan['tagline']) ?>.</strong> <?= e((string) $plan['description']) ?></p>
          <div class="feature-grid">
            <?php foreach ($plan['features'] as $feature): ?>
              <div class="feature"><i class="fa-solid fa-check"></i><?= e((string) $feature) ?></div>
            <?php endforeach; ?>
          </div>
        </section>

        <aside class="panel cart">
          <div>
            <div class="eyebrow">Order summary</div>
            <div class="price-big">£<?= number_format((float) $subtotal, 0) ?><small><?= e($periodSuffix) ?></small></div>
          </div>

          <div class="billing-switch">
            <?php foreach ($periods as $periodSlug => $periodData): ?>
              <a class="<?= $periodSlug === $period ? 'active' : '' ?>" href="upgrade?plan=<?= urlencode($selectedPlan) ?>&period=<?= urlencode($periodSlug) ?><?= $promoInput !== '' ? '&promo=' . urlencode($promoInput) : '' ?>"><?= e((string) $periodData['label']) ?></a>
            <?php endforeach; ?>
          </div>

          <div class="cart-row"><span>Account</span><strong><?= e((string) $user['email']) ?></strong></div>
          <div class="cart-row"><span>Current plan</span><strong><?= e(ucfirst($currentPlan)) ?></strong></div>
          <div class="cart-row"><span>New plan</span><strong><?= e((string) $plan['label']) ?></strong></div>
          <div class="cart-row"><span>Billing</span><strong><?= e($periodLabel) ?></strong></div>
          <div class="cart-row"><span>Subtotal</span><strong>£<?= number_format((float) $subtotal, 2) ?></strong></div>
          <?php if ($planCreditPence > 0): ?>
            <div class="cart-row"><span>Current plan credit <small style="display:block;color:var(--muted);">active <?= e((string) ($planCredit['label'] ?? '')) ?>, <?= (int) ($planCredit['days_left'] ?? 0) ?> day<?= ((int) ($planCredit['days_left'] ?? 0)) === 1 ? '' : 's' ?> left</small></span><strong>-£<?= number_format($planCreditAmount, 2) ?></strong></div>
          <?php endif; ?>
          <?php if ($promo): ?>
            <div class="cart-row"><span>Promo <?= e((string) $promo['code']) ?></span><strong>-£<?= number_format($discount, 2) ?></strong></div>
          <?php endif; ?>
          <?php if ($promo || $planCreditPence > 0): ?>
            <div class="cart-row"><span>Total today</span><strong>£<?= number_format($total, 2) ?></strong></div>
          <?php endif; ?>

          <form class="promo-form" method="post">
            <input type="hidden" name="plan" value="<?= e($selectedPlan) ?>">
            <input type="hidden" name="period" value="<?= e($period) ?>">
            <input type="text" name="promo_code" value="<?= e($promoInput) ?>" placeholder="Promo code" autocomplete="off">
            <button type="submit" name="apply_promo" value="1">Apply</button>
          </form>

          <?php if ($isCurrentOrLower): ?>
            <div class="notice"><i class="fa-solid fa-circle-info me-2"></i>You are already on <?= e((string) $plan['label']) ?> or a higher plan.</div>
            <button class="new-chat-btn" type="button" disabled>Already covered</button>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="plan" value="<?= e($selectedPlan) ?>">
              <input type="hidden" name="period" value="<?= e($period) ?>">
              <input type="hidden" name="promo_code" value="<?= e($promoInput) ?>">
              <button class="new-chat-btn w-100" type="submit" name="confirm_upgrade" value="1"><i class="fa-brands fa-stripe-s"></i> Pay with Stripe</button>
            </form>
          <?php endif; ?>
          <?php if ($stripeError !== ''): ?>
            <div class="notice" style="border-color:rgba(255,107,122,.45);color:#ffc2c8;"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= e($stripeError) ?></div>
          <?php elseif ($stripeNotice !== ''): ?>
            <div class="notice"><i class="fa-solid fa-circle-info me-2"></i><?= e($stripeNotice) ?></div>
          <?php endif; ?>
          <div class="notice">Payments are one-off Stripe Checkout payments, not subscriptions. Monthly access runs for one month from payment; annual access runs for one year from payment. Active paid-plan credit is discounted at checkout instead of extending the new expiry.</div>
        </aside>
      </div>

      <section class="plan-picker">
        <?php foreach ($plans as $slug => $candidate): ?>
          <a class="plan-option <?= $slug === $selectedPlan ? 'active' : '' ?>" href="upgrade?plan=<?= urlencode($slug) ?>&period=<?= urlencode($period) ?>">
            <div class="plan-option-title"><?= e((string) $candidate['label']) ?></div>
            <div class="plan-option-price">£<?= number_format((float) plan_amount($candidate, $period), 0) ?><small><?= e($periodSuffix) ?></small></div>
            <p><?= e((string) $candidate['tagline']) ?></p>
          </a>
        <?php endforeach; ?>
      </section>
      </div></div>
    </main>
  </div>
</body>
</html>
