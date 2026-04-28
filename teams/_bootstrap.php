<?php
declare(strict_types=1);

date_default_timezone_set('Europe/London');
session_start();
require_once __DIR__ . '/../lib/install_guard.php';
require_once __DIR__ . '/../lib/security.php';
csrf_bootstrap_web();


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli
{
    static $db = null;
    if ($db instanceof mysqli) return $db;
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->set_charset('utf8mb4');
    return $db;
}

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function mask_api_key(?string $plain): string
{
    $plain = trim((string) $plain);
    if ($plain === '') return 'Unavailable';
    $tail = strlen($plain) >= 4 ? substr($plain, -4) : $plain;
    $prefix = str_starts_with($plain, 'rgpt_team_') ? 'rgpt_team_' : (str_starts_with($plain, 'rk_live_') ? 'rk_live_' : (str_starts_with($plain, 'rgpt_') ? 'rgpt_' : substr($plain, 0, min(8, strlen($plain)))));
    return $prefix . '****' . $tail;
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

function db_fetch_all(string $sql, string $types = '', array $params = []): array
{
    $stmt = db()->prepare($sql);
    if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
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

function db_insert(string $sql, string $types = '', array $params = []): int
{
    $stmt = db()->prepare($sql);
    if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $id = (int) db()->insert_id;
    $stmt->close();
    return $id;
}


function db_column_exists(string $table, string $column): bool
{
    try {
        $row = db_fetch_one('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1', 'ss', [$table, $column]);
        return (int) ($row['total'] ?? 0) > 0;
    } catch (Throwable $e) { return false; }
}

function api_key_prefix(string $plain): string { if (str_starts_with($plain, 'rgpt_team_')) return 'rgpt_team_'; if (str_starts_with($plain, 'rgpt_')) return 'rgpt_'; if (str_starts_with($plain, 'rk_live_')) return 'rk_live_'; return substr($plain, 0, min(8, strlen($plain))); }
function api_key_suffix(string $plain): string { return substr($plain, -4); }
function masked_api_key_from_parts(?string $prefix, ?string $suffix, ?int $id = null): string { $prefix=trim((string)$prefix); $suffix=trim((string)$suffix); return ($prefix!=='' && $suffix!=='') ? $prefix.'****'.$suffix : 'Key #'.(int)$id; }
function ensure_api_key_preview_schema(): void { try { if (!db_column_exists('api_keys','key_prefix')) db()->query("ALTER TABLE api_keys ADD COLUMN key_prefix VARCHAR(32) NULL AFTER key_hash"); if (!db_column_exists('api_keys','key_suffix')) db()->query("ALTER TABLE api_keys ADD COLUMN key_suffix VARCHAR(16) NULL AFTER key_prefix"); if (db_column_exists('api_keys','plain_key')) { db()->query("UPDATE api_keys SET key_prefix = CASE WHEN plain_key LIKE 'rgpt_team_%' THEN 'rgpt_team_' WHEN plain_key LIKE 'rgpt_%' THEN 'rgpt_' WHEN plain_key LIKE 'rk_live_%' THEN 'rk_live_' ELSE LEFT(plain_key, 8) END, key_suffix = RIGHT(plain_key, 4) WHERE plain_key IS NOT NULL AND plain_key != '' AND (key_prefix IS NULL OR key_suffix IS NULL)"); db()->query("UPDATE api_keys SET plain_key = NULL WHERE plain_key IS NOT NULL AND plain_key != ''"); } } catch (Throwable $e) {} }
function ensure_auth_security_schema(): void
{
    try {
        if (!db_column_exists('users', 'current_session_token')) db()->query("ALTER TABLE users ADD COLUMN current_session_token VARCHAR(128) NULL AFTER custom_prompt");
        if (!db_column_exists('users', 'session_rotated_at')) db()->query("ALTER TABLE users ADD COLUMN session_rotated_at DATETIME NULL AFTER current_session_token");
        if (!db_column_exists('users', 'two_factor_secret')) db()->query("ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(64) NULL AFTER session_rotated_at");
        if (!db_column_exists('users', 'two_factor_enabled_at')) db()->query("ALTER TABLE users ADD COLUMN two_factor_enabled_at DATETIME NULL AFTER two_factor_secret");
    } catch (Throwable $e) {}
}
function clear_local_session(?string $flash = null): void { $_SESSION=[]; session_destroy(); session_start(); if($flash) $_SESSION['flash']=$flash; }
function two_factor_enabled_for_user(array $user): bool { return !empty($user['two_factor_enabled_at']) && !empty($user['two_factor_secret']); }
function teams_require_2fa(): bool { return defined('TEAMS_REQUIRE_2FA') ? (bool) TEAMS_REQUIRE_2FA : true; }
function ensure_team_schema(): void
{
    try {
        db_execute('ALTER TABLE team_members ADD COLUMN can_view_api_keys TINYINT(1) NOT NULL DEFAULT 0 AFTER can_create_conversations');
    } catch (Throwable $e) {
        // Column already exists or table has not been created yet.
    }
    try {
        db_execute('ALTER TABLE team_members ADD COLUMN can_manage_api_keys TINYINT(1) NOT NULL DEFAULT 0 AFTER can_view_api_keys');
    } catch (Throwable $e) {
        // Column already exists or table has not been created yet.
    }
    try {
        db_execute('UPDATE team_members SET can_view_api_keys = 1, can_manage_api_keys = 1 WHERE role = "owner"');
    } catch (Throwable $e) {
        // Keep page usable even if the migration is not available.
    }
    try {
        db_execute('ALTER TABLE team_members ADD UNIQUE KEY uniq_team_members_user_id (user_id)');
    } catch (Throwable $e) {
        // Already exists, unsupported DDL, or duplicate legacy memberships need manual cleanup.
    }
    try {
        db_execute('ALTER TABLE team_members ADD COLUMN pre_team_plan ENUM("free","plus","pro","business") NULL AFTER can_manage_api_keys');
    } catch (Throwable $e) {
        // Column already exists or table has not been created yet.
    }
    try {
        db_execute('ALTER TABLE team_members ADD COLUMN pre_team_thinking_enabled TINYINT(1) NULL AFTER pre_team_plan');
    } catch (Throwable $e) {
        // Column already exists or table has not been created yet.
    }
    try {
        db_execute('UPDATE team_members SET pre_team_plan = COALESCE(pre_team_plan, "free"), pre_team_thinking_enabled = COALESCE(pre_team_thinking_enabled, 0) WHERE pre_team_plan IS NULL OR pre_team_thinking_enabled IS NULL');
    } catch (Throwable $e) {
        // Best-effort backfill for legacy rows.
    }
    try {
        db_execute('CREATE TABLE IF NOT EXISTS team_changes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            team_id INT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED NULL,
            action VARCHAR(80) NOT NULL,
            target_type VARCHAR(40) NOT NULL DEFAULT "team",
            target_id BIGINT UNSIGNED NULL,
            target_label VARCHAR(255) NULL,
            details TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_team_changes_team_id (team_id),
            KEY idx_team_changes_actor_user_id (actor_user_id),
            KEY idx_team_changes_created_at (created_at),
            CONSTRAINT fk_team_changes_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE,
            CONSTRAINT fk_team_changes_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    } catch (Throwable $e) {
        // The activity table is optional for older installs until the SQL migration is applied.
    }
}



function ensure_notifications_schema(): void
{
    try {
        db_execute('CREATE TABLE IF NOT EXISTS notifications (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          created_by_user_id INT UNSIGNED NULL,
          type VARCHAR(40) NOT NULL DEFAULT "system",
          title VARCHAR(180) NOT NULL,
          body MEDIUMTEXT NOT NULL,
          action_url VARCHAR(255) NULL,
          related_team_invite_id BIGINT UNSIGNED NULL,
          read_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_notifications_user_read (user_id, read_at),
          KEY idx_notifications_created_at (created_at),
          KEY idx_notifications_invite (related_team_invite_id),
          CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
          CONSTRAINT fk_notifications_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        db_execute('CREATE TABLE IF NOT EXISTS team_invites (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          team_id INT UNSIGNED NOT NULL,
          invited_user_id INT UNSIGNED NOT NULL,
          invited_by_user_id INT UNSIGNED NOT NULL,
          role ENUM("admin","member") NOT NULL DEFAULT "member",
          can_read TINYINT(1) NOT NULL DEFAULT 1,
          can_send_messages TINYINT(1) NOT NULL DEFAULT 1,
          can_create_conversations TINYINT(1) NOT NULL DEFAULT 0,
          status ENUM("pending","accepted","declined","cancelled") NOT NULL DEFAULT "pending",
          responded_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_team_invites_user_status (invited_user_id, status),
          KEY idx_team_invites_team_user_status (team_id, invited_user_id, status),
          CONSTRAINT fk_team_invites_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE,
          CONSTRAINT fk_team_invites_user FOREIGN KEY (invited_user_id) REFERENCES users (id) ON DELETE CASCADE,
          CONSTRAINT fk_team_invites_inviter FOREIGN KEY (invited_by_user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    } catch (Throwable $e) {}
}

function create_notification(int $userId, string $title, string $body, string $type = 'system', ?int $creatorUserId = null, ?int $inviteId = null): int
{
    ensure_notifications_schema();
    return db_insert(
        'INSERT INTO notifications (user_id, created_by_user_id, type, title, body, related_team_invite_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
        'iisssi',
        [$userId, $creatorUserId, $type, $title, $body, $inviteId]
    );
}

function invite_user_to_team(int $teamId, int $invitedUserId, int $invitedByUserId, string $role): int
{
    ensure_notifications_schema();
    $inviteId = db_insert(
        'INSERT INTO team_invites (team_id, invited_user_id, invited_by_user_id, role, can_read, can_send_messages, can_create_conversations, status, created_at) VALUES (?, ?, ?, ?, 1, 1, 0, "pending", NOW())',
        'iiis',
        [$teamId, $invitedUserId, $invitedByUserId, $role]
    );
    $team = db_fetch_one('SELECT name FROM teams WHERE id = ? LIMIT 1', 'i', [$teamId]);
    $inviter = db_fetch_one('SELECT username FROM users WHERE id = ? LIMIT 1', 'i', [$invitedByUserId]);
    create_notification(
        $invitedUserId,
        'Team invite: ' . (string) ($team['name'] ?? 'RookGPT team'),
        (string) ($inviter['username'] ?? 'A team owner') . ' invited you to join ' . (string) ($team['name'] ?? 'their team') . ' as ' . $role . ".\n\nOpen the notification bell in RookGPT to accept or decline.",
        'team_invite',
        $invitedByUserId,
        $inviteId
    );
    return $inviteId;
}

function redirect_to(string $path): never { header('Location: ' . $path); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function generate_team_token(): string { return bin2hex(random_bytes(16)); }

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

function plan_label(string $plan): string
{
    return ['free' => 'Free', 'plus' => 'Plus', 'pro' => 'Pro', 'business' => 'Business'][$plan] ?? 'Free';
}

function fetch_owned_team(int $ownerUserId): ?array
{
    return db_fetch_one('SELECT id, name, owner_user_id, token, created_at FROM teams WHERE owner_user_id = ? LIMIT 1', 'i', [$ownerUserId]);
}

function fetch_team_by_token(string $token, int $ownerUserId): ?array
{
    return db_fetch_one('SELECT id, name, owner_user_id, token, created_at FROM teams WHERE token = ? AND owner_user_id = ? LIMIT 1', 'si', [$token, $ownerUserId]);
}

function fetch_team_members(int $teamId): array
{
    return db_fetch_all(
        'SELECT tm.id, tm.team_id, tm.user_id, tm.role, tm.can_read, tm.can_send_messages, tm.can_create_conversations, COALESCE(tm.can_view_api_keys, 0) AS can_view_api_keys, COALESCE(tm.can_manage_api_keys, 0) AS can_manage_api_keys, tm.pre_team_plan, tm.pre_team_thinking_enabled, tm.created_at, u.username, u.email, u.plan
         FROM team_members tm
         INNER JOIN users u ON u.id = tm.user_id
         WHERE tm.team_id = ?
         ORDER BY FIELD(tm.role, "owner", "admin", "member"), tm.id ASC',
        'i',
        [$teamId]
    );
}

function fetch_user_team_membership(int $userId): ?array
{
    return db_fetch_one(
        'SELECT tm.id, tm.team_id, tm.user_id, tm.role, tm.can_read, tm.can_send_messages, tm.can_create_conversations, COALESCE(tm.can_view_api_keys, 0) AS can_view_api_keys, COALESCE(tm.can_manage_api_keys, 0) AS can_manage_api_keys, tm.pre_team_plan, tm.pre_team_thinking_enabled, t.name, t.token, t.owner_user_id, t.created_at
         FROM team_members tm
         INNER JOIN teams t ON t.id = tm.team_id
         WHERE tm.user_id = ?
         ORDER BY FIELD(tm.role, "owner", "admin", "member"), tm.id ASC
         LIMIT 1',
        'i',
        [$userId]
    );
}

function log_team_change(int $teamId, ?int $actorUserId, string $action, string $targetType = 'team', ?int $targetId = null, string $targetLabel = '', string $details = ''): void
{
    try {
        db_execute(
            'INSERT INTO team_changes (team_id, actor_user_id, action, target_type, target_id, target_label, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            'iississ',
            [$teamId, $actorUserId, $action, $targetType, $targetId, $targetLabel, $details]
        );
    } catch (Throwable $e) {
        // Do not block team actions if the activity table has not been migrated yet.
    }
}

function fetch_team_change_count(int $teamId): int
{
    try {
        $row = db_fetch_one(
            'SELECT COUNT(*) AS total FROM team_changes WHERE team_id = ?',
            'i',
            [$teamId]
        );
        return (int) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function fetch_team_changes(int $teamId, int $limit = 10, int $offset = 0): array
{
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    try {
        return db_fetch_all(
            "SELECT tc.id, tc.action, tc.target_type, tc.target_id, tc.target_label, tc.details, tc.created_at, u.username AS actor_username
             FROM team_changes tc
             LEFT JOIN users u ON u.id = tc.actor_user_id
             WHERE tc.team_id = ?
             ORDER BY tc.created_at DESC, tc.id DESC
             LIMIT $limit OFFSET $offset",
            'i',
            [$teamId]
        );
    } catch (Throwable $e) {
        return [];
    }
}

function bool_word(int $value): string { return $value === 1 ? 'on' : 'off'; }

function user_has_any_team(int $userId): bool
{
    $membership = db_fetch_one('SELECT id FROM team_members WHERE user_id = ? LIMIT 1', 'i', [$userId]);
    $ownedTeam = db_fetch_one('SELECT id FROM teams WHERE owner_user_id = ? LIMIT 1', 'i', [$userId]);
    return (bool) ($membership || $ownedTeam);
}

function fetch_team_conversations(int $teamId, int $userId): array
{
    return db_fetch_all(
        'SELECT c.id, c.title, c.token, c.updated_at, c.created_at, u.username AS owner_username,
                (
                    SELECT m.content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1
                ) AS last_message
         FROM conversations c
         INNER JOIN users u ON u.id = c.user_id
         INNER JOIN team_members tm ON tm.team_id = c.team_id AND tm.user_id = ?
         WHERE c.team_id = ? AND tm.can_read = 1
         ORDER BY c.updated_at DESC, c.id DESC',
        'ii',
        [$userId, $teamId]
    );
}


function disable_shared_team_conversation(int $teamId, int $conversationId): bool
{
    return db_execute(
        'UPDATE conversations SET team_id = NULL WHERE id = ? AND team_id = ?',
        'ii',
        [$conversationId, $teamId]
    ) > 0;
}

function delete_shared_team_conversation(int $teamId, int $conversationId): bool
{
    return db_execute(
        'DELETE FROM conversations WHERE id = ? AND team_id = ?',
        'ii',
        [$conversationId, $teamId]
    ) > 0;
}

function generate_api_key_plaintext(): string { return 'rgpt_team_' . bin2hex(random_bytes(24)); }

function create_team_api_key(int $ownerUserId, int $teamId, string $name): array
{
    $plain = generate_api_key_plaintext();
    $hash = hash('sha256', $plain);
    $prefix = api_key_prefix($plain); $suffix = api_key_suffix($plain); ensure_api_key_preview_schema(); $id = db_insert('INSERT INTO api_keys (user_id, team_id, name, key_hash, key_prefix, key_suffix, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())', 'iissss', [$ownerUserId, $teamId, $name, $hash, $prefix, $suffix]);
    return ['id' => $id, 'plain' => $plain];
}

function fetch_team_api_keys(int $teamId): array
{
    return db_fetch_all('SELECT ak.id, ak.name, ak.key_prefix, ak.key_suffix, ak.last_used_at, ak.revoked_at, ak.created_at, u.username AS owner_username, COUNT(al.id) AS request_count, COALESCE(SUM(al.prompt_eval_count + al.eval_count), 0) AS token_count FROM api_keys ak INNER JOIN users u ON u.id = ak.user_id LEFT JOIN api_logs al ON al.api_key_id = ak.id WHERE ak.team_id = ? AND ak.revoked_at IS NULL GROUP BY ak.id, ak.name, ak.key_prefix, ak.key_suffix, ak.last_used_at, ak.revoked_at, ak.created_at, u.username ORDER BY ak.id DESC', 'i', [$teamId]);
}

function delete_team_api_key(int $teamId, int $keyId): bool
{
    return db_execute('UPDATE api_keys SET revoked_at = NOW() WHERE id = ? AND team_id = ? AND revoked_at IS NULL', 'ii', [$keyId, $teamId]) > 0;
}


function rotate_team_api_key(int $ownerUserId, int $teamId, int $keyId): array
{
    db_execute('UPDATE api_keys SET revoked_at = NOW() WHERE id = ? AND team_id = ? AND revoked_at IS NULL', 'ii', [$keyId, $teamId]);
    return create_team_api_key($ownerUserId, $teamId, 'Rotated team key');
}

function find_user_by_identifier(string $identifier): ?array
{
    return db_fetch_one('SELECT id, username, email, plan, thinking_enabled FROM users WHERE email = ? OR username = ? LIMIT 1', 'ss', [$identifier, $identifier]);
}

function create_team(int $ownerUserId, string $name): int
{
    return db_insert('INSERT INTO teams (name, owner_user_id, token, created_at) VALUES (?, ?, ?, NOW())', 'sis', [$name, $ownerUserId, generate_team_token()]);
}

function add_owner_membership(int $teamId, int $ownerUserId): void
{
    $owner = db_fetch_one('SELECT plan, thinking_enabled FROM users WHERE id = ? LIMIT 1', 'i', [$ownerUserId]);
    $prePlan = (string) ($owner['plan'] ?? 'free');
    $preThinking = (int) ($owner['thinking_enabled'] ?? 0);
    db_execute('INSERT IGNORE INTO team_members (team_id, user_id, role, can_read, can_send_messages, can_create_conversations, can_view_api_keys, can_manage_api_keys, pre_team_plan, pre_team_thinking_enabled, created_at) VALUES (?, ?, "owner", 1, 1, 1, 1, 1, ?, ?, NOW())', 'iisi', [$teamId, $ownerUserId, $prePlan, $preThinking]);
    db_execute('UPDATE users SET plan = "business", thinking_enabled = 1 WHERE id = ?', 'i', [$ownerUserId]);
}

function restore_user_pre_team_plan(int $userId, ?string $preTeamPlan = null, ?int $preTeamThinkingEnabled = null): void
{
    $stillInTeam = db_fetch_one('SELECT id FROM team_members WHERE user_id = ? LIMIT 1', 'i', [$userId]);
    $ownsTeam = db_fetch_one('SELECT id FROM teams WHERE owner_user_id = ? LIMIT 1', 'i', [$userId]);
    if ($stillInTeam || $ownsTeam) return;

    $allowedPlans = ['free', 'plus', 'pro', 'business'];
    $plan = in_array((string) $preTeamPlan, $allowedPlans, true) ? (string) $preTeamPlan : 'free';
    $thinking = $preTeamThinkingEnabled === null ? ($plan === 'free' ? 0 : 1) : (int) $preTeamThinkingEnabled;
    db_execute('UPDATE users SET plan = ?, thinking_enabled = ? WHERE id = ?', 'sii', [$plan, $thinking, $userId]);
}


function team_page_url(string $page = 'index', ?array $team = null, array $params = []): string
{
    $path = $page === 'index' ? '/teams/' : '/teams/' . $page;
    if ($team && !isset($params['t'])) $params['t'] = (string) ($team['token'] ?? '');
    $params = array_filter($params, static fn($value) => $value !== null && $value !== '');
    return $path . ($params ? ('?' . http_build_query($params)) : '');
}

function team_return_url(?array $team = null, string $fallbackPage = 'index'): string
{
    $allowed = ['index', 'members', 'conversations', 'api-keys', 'activity', 'settings'];
    $page = trim((string) ($_POST['_return_to'] ?? $fallbackPage));
    if (!in_array($page, $allowed, true)) $page = $fallbackPage;
    return team_page_url($page, $team);
}

function team_return_input(string $page): string
{
    return '<input type="hidden" name="_return_to" value="' . e($page) . '">';
}

ensure_notifications_schema();
$user = current_user();
if (!$user) redirect_to('index.php');

if ((string) ($user['plan'] ?? 'free') !== 'business') {
    $_SESSION['flash'] = 'Teams are only available on the Business plan.';
    redirect_to('index.php');
}
if (teams_require_2fa() && !two_factor_enabled_for_user($user)) {
    $_SESSION['flash'] = 'Enable 2FA in Account settings before using Teams.';
    redirect_to('/');
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$appError = '';
try {
    if (is_post()) {
        if (isset($_POST['create_team'])) {
            $teamName = trim((string) ($_POST['team_name'] ?? ''));
            if ($teamName === '') {
                $_SESSION['flash'] = 'Team name is required.';
                redirect_to('/teams/');
            }
            $existingTeam = fetch_owned_team((int) $user['id']);
            $existingMembership = fetch_user_team_membership((int) $user['id']);
            if ($existingTeam) {
                $_SESSION['flash'] = 'You already own a team.';
                redirect_to('/teams/?t=' . urlencode((string) $existingTeam['token']));
            }
            if ($existingMembership) {
                $_SESSION['flash'] = 'You are already a member of a team. Leave it before creating another.';
                redirect_to('/teams/?t=' . urlencode((string) $existingMembership['token']));
            }
            $teamId = create_team((int) $user['id'], $teamName);
            add_owner_membership($teamId, (int) $user['id']);
            log_team_change($teamId, (int) $user['id'], 'Team created', 'team', $teamId, $teamName, 'Created the Business team and added the owner membership.');
            $team = fetch_owned_team((int) $user['id']);
            $_SESSION['flash'] = 'Team created and your account is Business.';
            redirect_to('/teams/?t=' . urlencode((string) ($team['token'] ?? '')));
        }

        $ownedTeamForPost = fetch_owned_team((int) $user['id']);
        $membershipForPost = fetch_user_team_membership((int) $user['id']);
        $team = $ownedTeamForPost ?: ($membershipForPost ? [
            'id' => $membershipForPost['team_id'],
            'name' => $membershipForPost['name'],
            'owner_user_id' => $membershipForPost['owner_user_id'],
            'token' => $membershipForPost['token'],
            'created_at' => $membershipForPost['created_at'],
        ] : null);
        if (!$team) {
            $_SESSION['flash'] = 'Create or join a team first.';
            redirect_to('/teams/');
        }
        $isOwnerForPost = $ownedTeamForPost && (int) $ownedTeamForPost['id'] === (int) $team['id'];

        $canManageTeamKeys = ((int) $team['owner_user_id'] === (int) $user['id']) || ($membershipForPost && (int) $membershipForPost['team_id'] === (int) $team['id'] && (int) ($membershipForPost['can_manage_api_keys'] ?? 0) === 1);

        if (isset($_POST['create_team_api_key'])) {
            if (!$canManageTeamKeys) { $_SESSION['flash'] = 'You do not have permission to manage team API keys.'; redirect_to(team_return_url($team)); }
            $name = trim((string) ($_POST['team_api_key_name'] ?? 'Team key'));
            if ($name === '') $name = 'Team key';
            $created = create_team_api_key((int) $user['id'], (int) $team['id'], $name);
            log_team_change((int) $team['id'], (int) $user['id'], 'API key created', 'api_key', (int) $created['id'], $name, 'Created a new global team API key.');
            $_SESSION['flash'] = 'Team API key created. Copy it now: ' . $created['plain'];
            redirect_to(team_return_url($team));
        }

        if (isset($_POST['rotate_team_api_key'])) {
            if (!$canManageTeamKeys) { $_SESSION['flash'] = 'You do not have permission to manage team API keys.'; redirect_to(team_return_url($team)); }
            $keyId = (int) ($_POST['team_api_key_id'] ?? 0);
            if ($keyId > 0) {
                $oldKey = db_fetch_one('SELECT name FROM api_keys WHERE id = ? AND team_id = ? LIMIT 1', 'ii', [$keyId, (int) $team['id']]);
                $created = rotate_team_api_key((int) $user['id'], (int) $team['id'], $keyId);
                log_team_change((int) $team['id'], (int) $user['id'], 'API key rotated', 'api_key', $keyId, (string) ($oldKey['name'] ?? 'Team key'), 'Revoked the old team key and created a replacement.');
                $_SESSION['flash'] = 'Team API key rotated. Copy the new key now: ' . $created['plain'];
            }
            redirect_to(team_return_url($team));
        }

        if (isset($_POST['delete_team_api_key'])) {
            if (!$canManageTeamKeys) { $_SESSION['flash'] = 'You do not have permission to manage team API keys.'; redirect_to(team_return_url($team)); }
            $keyId = (int) ($_POST['team_api_key_id'] ?? 0);
            if ($keyId > 0) {
                $oldKey = db_fetch_one('SELECT name FROM api_keys WHERE id = ? AND team_id = ? LIMIT 1', 'ii', [$keyId, (int) $team['id']]);
                if (delete_team_api_key((int) $team['id'], $keyId)) {
                    log_team_change((int) $team['id'], (int) $user['id'], 'API key deleted', 'api_key', $keyId, (string) ($oldKey['name'] ?? 'Team key'), 'Revoked a global team API key.');
                    $_SESSION['flash'] = 'Team API key deleted.';
                }
            }
            redirect_to(team_return_url($team));
        }

        if (isset($_POST['disable_shared_team_conversation'])) {
            if (!$isOwnerForPost) { $_SESSION['flash'] = 'Only the team owner can manage shared team conversations.'; redirect_to(team_return_url($team)); }
            $conversationId = (int) ($_POST['conversation_id'] ?? 0);
            $conversation = $conversationId > 0 ? db_fetch_one('SELECT title FROM conversations WHERE id = ? AND team_id = ? LIMIT 1', 'ii', [$conversationId, (int) $team['id']]) : null;
            if ($conversationId > 0 && disable_shared_team_conversation((int) $team['id'], $conversationId)) {
                log_team_change((int) $team['id'], (int) $user['id'], 'Conversation sharing disabled', 'conversation', $conversationId, (string) ($conversation['title'] ?? 'Conversation'), 'Moved this conversation back to the owner\'s private conversations.');
                $_SESSION['flash'] = 'Team sharing disabled for that conversation.';
            } else {
                $_SESSION['flash'] = 'Could not disable team sharing for that conversation.';
            }
            redirect_to(team_return_url($team));
        }

        if (isset($_POST['delete_shared_team_conversation'])) {
            if (!$isOwnerForPost) { $_SESSION['flash'] = 'Only the team owner can manage shared team conversations.'; redirect_to(team_return_url($team)); }
            $conversationId = (int) ($_POST['conversation_id'] ?? 0);
            $conversation = $conversationId > 0 ? db_fetch_one('SELECT title FROM conversations WHERE id = ? AND team_id = ? LIMIT 1', 'ii', [$conversationId, (int) $team['id']]) : null;
            if ($conversationId > 0 && delete_shared_team_conversation((int) $team['id'], $conversationId)) {
                log_team_change((int) $team['id'], (int) $user['id'], 'Conversation deleted', 'conversation', $conversationId, (string) ($conversation['title'] ?? 'Conversation'), 'Permanently deleted a shared team conversation and its messages.');
                $_SESSION['flash'] = 'Shared team conversation deleted.';
            } else {
                $_SESSION['flash'] = 'Could not delete that shared team conversation.';
            }
            redirect_to(team_return_url($team));
        }

        if (isset($_POST['add_team_member'])) {
            if (!$isOwnerForPost) { $_SESSION['flash'] = 'Only the team owner can manage members or delete the team.'; redirect_to(team_return_url($team)); }
            $identifier = trim((string) ($_POST['member_identifier'] ?? ''));
            $role = (string) ($_POST['member_role'] ?? 'member');
            $role = in_array($role, ['admin', 'member'], true) ? $role : 'member';
            if ($identifier === '') {
                $_SESSION['flash'] = 'Enter a username or email.';
            } else {
                $memberUser = find_user_by_identifier($identifier);
                if (!$memberUser) {
                    $_SESSION['flash'] = 'No user found for that username/email.';
                } elseif ((int) $memberUser['id'] === (int) $user['id']) {
                    $_SESSION['flash'] = 'You are already the owner of this team.';
                } else {
                    $existingMember = db_fetch_one('SELECT tm.id, tm.team_id, t.name FROM team_members tm INNER JOIN teams t ON t.id = tm.team_id WHERE tm.user_id = ? LIMIT 1', 'i', [(int) $memberUser['id']]);
                    $ownedByInvitee = fetch_owned_team((int) $memberUser['id']);
                    if ($existingMember) {
                        $_SESSION['flash'] = ((int) $existingMember['team_id'] === (int) $team['id']) ? 'That user is already on this team.' : 'That user is already on another team.';
                    } elseif ($ownedByInvitee) {
                        $_SESSION['flash'] = 'That user already owns a team and cannot join another one.';
                    } else {
                        $pendingInvite = db_fetch_one('SELECT id FROM team_invites WHERE team_id = ? AND invited_user_id = ? AND status = "pending" LIMIT 1', 'ii', [(int) $team['id'], (int) $memberUser['id']]);
                        if ($pendingInvite) {
                            $_SESSION['flash'] = 'That user already has a pending invite.';
                        } else {
                            invite_user_to_team((int) $team['id'], (int) $memberUser['id'], (int) $user['id'], $role);
                            log_team_change((int) $team['id'], (int) $user['id'], 'Member invited', 'member', (int) $memberUser['id'], (string) $memberUser['username'], 'Invited as ' . $role . '.');
                            $_SESSION['flash'] = 'Team invite sent. They must accept or decline it from their notification bell.';
                        }
                    }
                }
            }
            redirect_to(team_return_url($team));
        }

        if (isset($_POST['update_team_member'])) {
            if (!$isOwnerForPost) { $_SESSION['flash'] = 'Only the team owner can manage members or delete the team.'; redirect_to(team_return_url($team)); }
            $memberId = (int) ($_POST['team_member_id'] ?? 0);
            $role = (string) ($_POST['member_role'] ?? 'member');
            $role = in_array($role, ['admin', 'member'], true) ? $role : 'member';
            $canRead = isset($_POST['can_read']) ? 1 : 0;
            $canSend = isset($_POST['can_send_messages']) ? 1 : 0;
            $canCreate = isset($_POST['can_create_conversations']) ? 1 : 0;
            $canViewKeys = isset($_POST['can_view_api_keys']) ? 1 : 0;
            $canManageKeys = isset($_POST['can_manage_api_keys']) ? 1 : 0;
            if ($canManageKeys) $canViewKeys = 1;
            if ($memberId > 0) {
                $beforeMember = db_fetch_one('SELECT tm.role, tm.can_read, tm.can_send_messages, tm.can_create_conversations, tm.can_view_api_keys, tm.can_manage_api_keys, u.username FROM team_members tm INNER JOIN users u ON u.id = tm.user_id WHERE tm.id = ? AND tm.team_id = ? AND tm.role != "owner" LIMIT 1', 'ii', [$memberId, (int) $team['id']]);
                db_execute('UPDATE team_members SET role = ?, can_read = ?, can_send_messages = ?, can_create_conversations = ?, can_view_api_keys = ?, can_manage_api_keys = ? WHERE id = ? AND team_id = ? AND role != "owner"', 'siiiiiii', [$role, $canRead, $canSend, $canCreate, $canViewKeys, $canManageKeys, $memberId, (int) $team['id']]);
                if ($beforeMember) {
                    $details = 'Role ' . (string) $beforeMember['role'] . ' → ' . $role
                        . '; read ' . bool_word((int) $beforeMember['can_read']) . ' → ' . bool_word($canRead)
                        . '; send ' . bool_word((int) $beforeMember['can_send_messages']) . ' → ' . bool_word($canSend)
                        . '; create ' . bool_word((int) $beforeMember['can_create_conversations']) . ' → ' . bool_word($canCreate)
                        . '; view keys ' . bool_word((int) $beforeMember['can_view_api_keys']) . ' → ' . bool_word($canViewKeys)
                        . '; manage keys ' . bool_word((int) $beforeMember['can_manage_api_keys']) . ' → ' . bool_word($canManageKeys) . '.';
                    log_team_change((int) $team['id'], (int) $user['id'], 'Member permissions updated', 'member', $memberId, (string) $beforeMember['username'], $details);
                }
                $_SESSION['flash'] = 'Team member updated.';
            }
            redirect_to(team_return_url($team));
        }

        if (isset($_POST['remove_team_member'])) {
            if (!$isOwnerForPost) { $_SESSION['flash'] = 'Only the team owner can manage members or delete the team.'; redirect_to(team_return_url($team)); }
            $memberId = (int) ($_POST['team_member_id'] ?? 0);
            $removedMember = $memberId > 0 ? db_fetch_one('SELECT tm.user_id, tm.pre_team_plan, tm.pre_team_thinking_enabled, u.username FROM team_members tm INNER JOIN users u ON u.id = tm.user_id WHERE tm.id = ? AND tm.team_id = ? AND tm.role != "owner" LIMIT 1', 'ii', [$memberId, (int) $team['id']]) : null;
            if ($removedMember) {
                db_execute('DELETE FROM team_members WHERE id = ? AND team_id = ? AND role != "owner"', 'ii', [$memberId, (int) $team['id']]);
                log_team_change((int) $team['id'], (int) $user['id'], 'Member removed', 'member', (int) $removedMember['user_id'], (string) $removedMember['username'], 'Removed from the team and restored to their pre-team plan.');
                restore_user_pre_team_plan((int) $removedMember['user_id'], $removedMember['pre_team_plan'] ?? null, isset($removedMember['pre_team_thinking_enabled']) ? (int) $removedMember['pre_team_thinking_enabled'] : null);
                $_SESSION['flash'] = 'Team member removed and restored to their pre-team plan.';
            }
            redirect_to(team_return_url($team));
        }

        if (isset($_POST['leave_team'])) {
            if ($isOwnerForPost) {
                $_SESSION['flash'] = 'Team owners cannot leave their own team. Delete the team or transfer ownership first.';
                redirect_to(team_return_url($team));
            }
            $leavingMember = db_fetch_one('SELECT pre_team_plan, pre_team_thinking_enabled FROM team_members WHERE team_id = ? AND user_id = ? AND role != "owner" LIMIT 1', 'ii', [(int) $team['id'], (int) $user['id']]);
            $left = db_execute('DELETE FROM team_members WHERE team_id = ? AND user_id = ? AND role != "owner"', 'ii', [(int) $team['id'], (int) $user['id']]);
            if ($left > 0) {
                log_team_change((int) $team['id'], (int) $user['id'], 'Member left', 'member', (int) $user['id'], (string) $user['username'], 'Left the team and restored their pre-team plan.');
                restore_user_pre_team_plan((int) $user['id'], $leavingMember['pre_team_plan'] ?? null, isset($leavingMember['pre_team_thinking_enabled']) ? (int) $leavingMember['pre_team_thinking_enabled'] : null);
                $_SESSION['flash'] = 'You left the team and your pre-team plan was restored.';
            } else {
                $_SESSION['flash'] = 'Could not leave this team.';
            }
            redirect_to('/teams/');
        }

        if (isset($_POST['delete_team'])) {
            if (!$isOwnerForPost) { $_SESSION['flash'] = 'Only the team owner can manage members or delete the team.'; redirect_to(team_return_url($team)); }
            $formerMembers = fetch_team_members((int) $team['id']);
            log_team_change((int) $team['id'], (int) $user['id'], 'Team deleted', 'team', (int) $team['id'], (string) $team['name'], 'Deleted the team and restored former members to their pre-team plans.');
            db_execute('DELETE FROM teams WHERE id = ? AND owner_user_id = ?', 'ii', [(int) $team['id'], (int) $user['id']]);
            foreach ($formerMembers as $formerMember) {
                restore_user_pre_team_plan((int) $formerMember['user_id'], $formerMember['pre_team_plan'] ?? null, isset($formerMember['pre_team_thinking_enabled']) ? (int) $formerMember['pre_team_thinking_enabled'] : null);
            }
            $_SESSION['flash'] = 'Team deleted. Former members were restored to their pre-team plans.';
            redirect_to('/teams/');
        }
    }
} catch (Throwable $e) {
    $appError = 'Action failed: ' . $e->getMessage();
}

$ownedTeam = fetch_owned_team((int) $user['id']);
$userTeamMembership = fetch_user_team_membership((int) $user['id']);

// Resolve the active team before any page metrics/layout code touches it.
// The teams refactor accidentally left $activeTeam undefined on normal GET requests.
$activeTeam = null;
$activeMembership = null;
$isTeamOwner = false;
$requestedTeamToken = trim((string) ($_GET['t'] ?? ''));

if ($requestedTeamToken !== '') {
    if ($ownedTeam && hash_equals((string) $ownedTeam['token'], $requestedTeamToken)) {
        $activeTeam = $ownedTeam;
        $isTeamOwner = true;
    } elseif ($userTeamMembership && hash_equals((string) $userTeamMembership['token'], $requestedTeamToken)) {
        $activeTeam = [
            'id' => $userTeamMembership['team_id'],
            'name' => $userTeamMembership['name'],
            'owner_user_id' => $userTeamMembership['owner_user_id'],
            'token' => $userTeamMembership['token'],
            'created_at' => $userTeamMembership['created_at'],
        ];
        $activeMembership = $userTeamMembership;
        $isTeamOwner = ((int) $userTeamMembership['owner_user_id'] === (int) $user['id']) || ((string) ($userTeamMembership['role'] ?? '') === 'owner');
    }
}

if (!$activeTeam && $ownedTeam) {
    $activeTeam = $ownedTeam;
    $isTeamOwner = true;
}

if (!$activeTeam && $userTeamMembership) {
    $activeTeam = [
        'id' => $userTeamMembership['team_id'],
        'name' => $userTeamMembership['name'],
        'owner_user_id' => $userTeamMembership['owner_user_id'],
        'token' => $userTeamMembership['token'],
        'created_at' => $userTeamMembership['created_at'],
    ];
    $activeMembership = $userTeamMembership;
    $isTeamOwner = ((int) $userTeamMembership['owner_user_id'] === (int) $user['id']) || ((string) ($userTeamMembership['role'] ?? '') === 'owner');
}

if ($activeTeam && !$activeMembership && $userTeamMembership && (int) $userTeamMembership['team_id'] === (int) $activeTeam['id']) {
    $activeMembership = $userTeamMembership;
}

$canViewTeamApiKeys = $isTeamOwner || ($activeMembership && (int) ($activeMembership['can_view_api_keys'] ?? 0) === 1);
$canManageTeamApiKeys = $isTeamOwner || ($activeMembership && (int) ($activeMembership['can_manage_api_keys'] ?? 0) === 1);

$teamMembers = $activeTeam ? fetch_team_members((int) $activeTeam['id']) : [];
$memberUserIds = array_map(static fn($m) => (int) $m['user_id'], $teamMembers);
$teamConversations = 0;
$teamMessages = 0;
$teamApiCalls = 0;
$teamSharedConversations = $activeTeam ? fetch_team_conversations((int) $activeTeam['id'], (int) $user['id']) : [];
$teamApiKeys = ($activeTeam && $canViewTeamApiKeys) ? fetch_team_api_keys((int) $activeTeam['id']) : [];
$changesPerPage = 10;
$changesPage = max(1, (int) ($_GET['changes_page'] ?? 1));
$teamChangesTotal = $activeTeam ? fetch_team_change_count((int) $activeTeam['id']) : 0;
$teamChangesTotalPages = max(1, (int) ceil($teamChangesTotal / $changesPerPage));
if ($changesPage > $teamChangesTotalPages) $changesPage = $teamChangesTotalPages;
$teamChangesOffset = ($changesPage - 1) * $changesPerPage;
$teamChanges = $activeTeam ? fetch_team_changes((int) $activeTeam['id'], $changesPerPage, $teamChangesOffset) : [];
$changesPageUrl = static function (int $page) use ($activeTeam): string {
    return team_page_url('activity', $activeTeam, ['changes_page' => max(1, $page)]) . '#recent-changes';
};
if ($memberUserIds && $activeTeam) {
    $ids = implode(',', array_map('intval', $memberUserIds));
    $activeTeamId = (int) $activeTeam['id'];
    $teamConversations = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM conversations WHERE user_id IN ($ids) OR team_id = $activeTeamId")['total'] ?? 0);
    $teamMessages = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM messages m INNER JOIN conversations c ON c.id = m.conversation_id WHERE c.user_id IN ($ids) OR c.team_id = $activeTeamId")['total'] ?? 0);
    $teamApiCalls = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM api_logs WHERE user_id IN ($ids)")['total'] ?? 0);
}

function render_team_header(string $activePage, string $title, string $subtitle): void
{
    global $user, $activeTeam, $flash, $appError;
    $nav = [
        'index' => ['Overview', 'fa-gauge-high'],
        'members' => ['Members', 'fa-user-group'],
        'conversations' => ['Conversations', 'fa-comments'],
        'api-keys' => ['API keys', 'fa-key'],
        'activity' => ['Activity', 'fa-clock-rotate-left'],
        'settings' => ['Settings', 'fa-gear'],
    ];
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> — <?= e($title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="/rook.css">
  <style>
    :root{--bg:#0b1020;--panel:#111827;--panel2:#0f172a;--line:rgba(255,255,255,.09);--text:#f3f7ff;--muted:#9fb0cf;--accent:#7c9cff;--danger:#ff6b81;--success:#38d39f}*{box-sizing:border-box}.eyebrow{color:var(--accent);text-transform:uppercase;letter-spacing:.12em;font-size:.76rem;font-weight:900}.muted{color:var(--muted)}.panel,.metric,.member{background:linear-gradient(180deg,rgba(17,24,39,.95),rgba(15,23,42,.92));border:1px solid var(--line);box-shadow:0 18px 44px rgba(0,0,0,.28)}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0}.metric{padding:18px}.metric span{display:block;color:var(--muted);font-size:.82rem}.metric strong{display:block;font-size:1.7rem;margin-top:6px}.grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:16px}.panel{padding:18px}.field{margin-bottom:12px}.field label{display:block;font-size:.78rem;color:var(--muted);font-weight:800;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}.field input,.field select{width:100%;background:#0a111d;color:var(--text);border:1px solid var(--line);padding:12px}.btn-rook{border:0;background:linear-gradient(135deg,var(--accent),#b8c9ff);color:#08111f;font-weight:900;padding:12px 14px}.btn-ghost{background:rgba(255,255,255,.05);border:1px solid var(--line);color:var(--text);padding:10px 12px}.btn-danger-soft{background:rgba(255,107,129,.1);border:1px solid rgba(255,107,129,.32);color:#ffd1d8;padding:10px 12px}.member{padding:14px;margin-top:10px}.member-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.checks{display:flex;flex-wrap:wrap;gap:12px;margin:12px 0;color:var(--muted)}.checks label{display:flex;gap:8px;align-items:center}.notice{border:1px solid var(--line);background:rgba(124,156,255,.08);padding:12px;margin-bottom:14px}.danger-zone{border-color:rgba(255,107,129,.28);background:rgba(255,107,129,.06)}.key-row,.key-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px}.key-preview{display:inline-block;background:#090f1d;border:1px solid var(--line);padding:9px 10px;color:#dbe7ff;max-width:100%;overflow:hidden;text-overflow:ellipsis}.copy-team-key.copied{border-color:rgba(56,211,159,.45);color:#b8ffe8}.changes-wrap{overflow-x:auto;margin-top:10px}.changes-table{width:100%;border-collapse:collapse;min-width:760px}.changes-table th,.changes-table td{border-bottom:1px solid var(--line);padding:10px 8px;text-align:left;vertical-align:top}.changes-table th{color:var(--muted);font-size:.74rem;text-transform:uppercase;letter-spacing:.08em}.changes-table td{font-size:.92rem}.change-action{font-weight:900;color:var(--text)}.change-target{color:#dbe7ff}.change-details{color:var(--muted);line-height:1.45}.pagination-row{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px}.pagination-meta{color:var(--muted);font-size:.9rem}.pagination-links{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.pagination-link{display:inline-flex;align-items:center;justify-content:center;min-width:38px;height:38px;padding:0 12px;border:1px solid var(--line);background:rgba(255,255,255,.04);color:var(--text);text-decoration:none;font-weight:800}.pagination-link:hover,.pagination-link.active{border-color:rgba(124,156,255,.55);background:rgba(124,156,255,.16);color:#fff}.pagination-link.disabled{opacity:.45;pointer-events:none}.team-subnav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}.team-subnav a{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:var(--muted);border:1px solid var(--line);background:rgba(255,255,255,.035);padding:10px 12px;font-weight:800}.team-subnav a.active,.team-subnav a:hover{color:#fff;border-color:rgba(124,156,255,.55);background:rgba(124,156,255,.14)}.confirm-modal-backdrop{position:fixed;inset:0;z-index:9990;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(3,7,18,.72);backdrop-filter:blur(10px)}.confirm-modal-backdrop.is-open{display:flex}.confirm-modal{width:min(460px,100%);background:linear-gradient(180deg,rgba(17,24,39,.98),rgba(15,23,42,.98));border:1px solid var(--line);box-shadow:0 28px 80px rgba(0,0,0,.52);padding:20px;color:var(--text)}.confirm-modal-icon{width:44px;height:44px;display:grid;place-items:center;background:rgba(255,107,129,.11);border:1px solid rgba(255,107,129,.28);color:#ffd1d8;margin-bottom:14px}.confirm-modal h2{font-size:1.22rem;font-weight:900;letter-spacing:-.03em;margin:0 0 8px}.confirm-modal p{margin:0;color:var(--muted);line-height:1.55}.confirm-modal-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:18px}.confirm-modal-actions button{min-width:116px}@media(max-width:900px){.metrics,.grid{grid-template-columns:1fr}}
  </style>
</head>
<body class="rook-body rook-app is-authenticated">
<div class="app">
  <aside class="sidebar">
    <div class="sidebar-top"><a href="/" class="brand"><span class="brand-mark"><i class="fa-solid fa-chess-rook"></i></span><span><h1>RookGPT</h1><p>Team workspace</p></span></a><div class="workspace-label">Workspace</div></div>
    <div class="sidebar-body">
      <a class="sidebar-link" href="/"><i class="fa-solid fa-message"></i> Chat workspace</a>
      <a class="sidebar-link" href="/api/"><i class="fa-solid fa-key"></i> API keys</a>
      <a class="sidebar-link active" href="<?= e(team_page_url('index', $activeTeam)) ?>"><i class="fa-solid fa-users"></i> Teams</a>
      <a class="sidebar-link" href="/upgrade"><i class="fa-solid fa-arrow-up-right-dots"></i> Upgrade</a>
      <div class="page-panel p-3 mt-auto"><div class="muted small">Signed in as</div><strong><?= e((string) $user['username']) ?></strong><div class="muted small"><?= e(plan_label((string) $user['plan'])) ?> plan</div></div>
    </div>
  </aside>
  <main class="main-panel">
    <header class="topbar"><div class="topbar-main"><div class="topbar-icon"><i class="fa-solid fa-users"></i></div><div class="topbar-title"><h2><?= e($title) ?></h2><p><?= e($subtitle) ?></p></div></div><div class="topbar-actions"><a class="ghost-btn" href="/"><i class="fa-solid fa-arrow-left"></i> Back to chat</a></div></header>
    <div class="page-content">
      <?php if ($flash): ?><div class="notice"><?= e((string) $flash) ?></div><?php endif; ?>
      <?php if ($appError): ?><div class="notice danger-zone"><?= e((string) $appError) ?></div><?php endif; ?>
      <?php if ($activeTeam): ?><nav class="team-subnav" aria-label="Team sections"><?php foreach ($nav as $key => [$label, $icon]): ?><a class="<?= $activePage === $key ? 'active' : '' ?>" href="<?= e(team_page_url($key, $activeTeam)) ?>"><i class="fa-solid <?= e($icon) ?>"></i><?= e($label) ?></a><?php endforeach; ?></nav><?php endif; ?>
    <?php
}

function render_team_footer(): void
{
    ?>
    </div>
  </main>
</div>
<div class="confirm-modal-backdrop" id="confirmModal" aria-hidden="true"><div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle" aria-describedby="confirmModalMessage"><div class="confirm-modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><h2 id="confirmModalTitle">Confirm action</h2><p id="confirmModalMessage">Are you sure?</p><div class="confirm-modal-actions"><button type="button" class="btn-ghost" id="confirmModalCancel">Cancel</button><button type="button" class="btn-danger-soft" id="confirmModalConfirm">Confirm</button></div></div></div>
<script>
const confirmModal=document.getElementById('confirmModal'),confirmModalTitle=document.getElementById('confirmModalTitle'),confirmModalMessage=document.getElementById('confirmModalMessage'),confirmModalConfirm=document.getElementById('confirmModalConfirm'),confirmModalCancel=document.getElementById('confirmModalCancel');let pendingConfirmForm=null,pendingConfirmSubmitter=null;const closeConfirmModal=()=>{if(!confirmModal)return;confirmModal.classList.remove('is-open');confirmModal.setAttribute('aria-hidden','true');pendingConfirmForm=null;pendingConfirmSubmitter=null};const openConfirmModal=(form,submitter=null)=>{if(!confirmModal||!confirmModalTitle||!confirmModalMessage||!confirmModalConfirm)return;pendingConfirmForm=form;pendingConfirmSubmitter=submitter;confirmModalTitle.textContent=form.getAttribute('data-confirm-title')||'Confirm action';confirmModalMessage.textContent=form.getAttribute('data-confirm-message')||'Are you sure you want to continue?';confirmModalConfirm.textContent=form.getAttribute('data-confirm-action')||'Confirm';confirmModal.classList.add('is-open');confirmModal.setAttribute('aria-hidden','false');confirmModalConfirm.focus()};document.querySelectorAll('form[data-confirm-message]').forEach(form=>form.addEventListener('submit',event=>{if(form.dataset.confirmed==='1')return;event.preventDefault();openConfirmModal(form,event.submitter||null)}));if(confirmModalCancel)confirmModalCancel.addEventListener('click',closeConfirmModal);if(confirmModal)confirmModal.addEventListener('click',event=>{if(event.target===confirmModal)closeConfirmModal()});if(confirmModalConfirm)confirmModalConfirm.addEventListener('click',()=>{if(!pendingConfirmForm)return;const form=pendingConfirmForm,submitter=pendingConfirmSubmitter;form.dataset.confirmed='1';closeConfirmModal();if(typeof form.requestSubmit==='function'){form.requestSubmit(submitter||undefined);return}if(submitter&&submitter.name){const hiddenSubmitter=document.createElement('input');hiddenSubmitter.type='hidden';hiddenSubmitter.name=submitter.name;hiddenSubmitter.value=submitter.value||'1';form.appendChild(hiddenSubmitter)}form.submit()});document.addEventListener('keydown',event=>{if(event.key==='Escape'&&confirmModal&&confirmModal.classList.contains('is-open'))closeConfirmModal()});document.querySelectorAll('.copy-team-key').forEach(button=>button.addEventListener('click',async()=>{const key=button.getAttribute('data-key')||'';if(!key)return;const original=button.innerHTML;const showCopied=()=>{button.classList.add('copied');button.innerHTML='<i class="fa-solid fa-check me-2"></i>Copied';window.setTimeout(()=>{button.classList.remove('copied');button.innerHTML=original},1400)};try{await navigator.clipboard.writeText(key);showCopied()}catch(error){const textarea=document.createElement('textarea');textarea.value=key;textarea.setAttribute('readonly','');textarea.style.position='fixed';textarea.style.left='-9999px';document.body.appendChild(textarea);textarea.select();document.execCommand('copy');textarea.remove();showCopied()}}));
</script>
</body></html>
<?php
}
