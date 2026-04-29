<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/install_guard.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli
{
    static $db = null;

    if ($db instanceof mysqli) {
        return $db;
    }

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->set_charset('utf8mb4');
    return $db;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function fetch_one(string $sql, string $types = '', array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    if ($types !== '' && $params !== []) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return is_array($row) ? $row : null;
}

function fetch_all(string $sql, string $types = '', array $params = []): array
{
    $stmt = db()->prepare($sql);
    if ($types !== '' && $params !== []) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

function db_column_exists(string $table, string $column): bool
{
    $row = fetch_one(
        'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        'ss',
        [$table, $column]
    );
    return (int) ($row['total'] ?? 0) > 0;
}

function decode_message_images(?string $imagesJson): array
{
    $imagesJson = trim((string) $imagesJson);
    if ($imagesJson === '') return [];
    $decoded = json_decode($imagesJson, true);
    if (!is_array($decoded)) return [];

    $images = [];
    foreach ($decoded as $image) {
        if (!is_array($image)) continue;
        $mime = (string) ($image['mime'] ?? '');
        if (!preg_match('/^image\/(png|jpe?g|webp|gif)$/i', $mime)) continue;
        $name = mb_substr((string) ($image['name'] ?? 'Uploaded image'), 0, 120);
        $data = (string) ($image['data'] ?? '');
        $path = (string) ($image['path'] ?? '');
        if ($data === '' && $path !== '') {
            $full = __DIR__ . '/' . ltrim($path, '/');
            if (is_file($full) && filesize($full) <= 8 * 1024 * 1024) {
                $raw = file_get_contents($full);
                if ($raw !== false) $data = base64_encode($raw);
            }
        }
        if ($data === '') continue;
        $images[] = ['name' => $name, 'mime' => $mime, 'data' => $data];
    }
    return $images;
}

$shareToken = trim((string) ($_GET['s'] ?? ''));
$conversation = null;
$messages = [];
$messagesHaveImages = db_column_exists('messages', 'images_json');
$appTagline = defined('APP_TAGLINE') ? (string) APP_TAGLINE : 'A cleaner, sharper AI workspace.';

if ($shareToken !== '') {
    $conversation = fetch_one(
        'SELECT c.id, c.title, c.created_at, c.updated_at, u.username FROM conversations c JOIN users u ON u.id = c.user_id WHERE c.share_token = ? LIMIT 1',
        's',
        [$shareToken]
    );

    if ($conversation) {
        $imageColumn = $messagesHaveImages ? 'images_json,' : "NULL AS images_json,";
        $messages = fetch_all(
            "SELECT id, role, content, {$imageColumn} created_at FROM messages WHERE conversation_id = ? ORDER BY id ASC",
            'i',
            [(int) $conversation['id']]
        );
    }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> — Shared conversation</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/styles/github-dark.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
  <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/highlight.min.js" defer></script>
  <style>
    :root {
      --bg: #0b1020;
      --bg-soft: #11182b;
      --panel: rgba(17, 24, 39, 0.82);
      --panel-2: rgba(15, 23, 42, 0.92);
      --line: rgba(255,255,255,0.08);
      --line-strong: rgba(255,255,255,0.14);
      --text: #f3f7ff;
      --muted: #9fb0cf;
      --accent: #7c9cff;
      --accent-2: #8b5cf6;
      --success: #38d39f;
      --danger: #ff6b81;
      --warning: #ffb84d;
      --shadow-xl: 0 30px 80px rgba(0,0,0,0.46);
      --shadow-lg: 0 18px 40px rgba(0,0,0,0.28);
      --radius-xl: 0px;
      --radius-lg: 0px;
      --radius-md: 0px;
    }

    * { box-sizing: border-box; }

    html {
      scrollbar-width: thin;
      scrollbar-color: rgba(124, 156, 255, 0.38) rgba(255,255,255,0.03);
    }

    html, body, div, section, aside, main, nav, form, textarea, pre, code, ul, ol {
      scrollbar-width: thin;
      scrollbar-color: rgba(124, 156, 255, 0.38) rgba(255,255,255,0.03);
    }

    html::-webkit-scrollbar,
    body::-webkit-scrollbar,
    div::-webkit-scrollbar,
    section::-webkit-scrollbar,
    aside::-webkit-scrollbar,
    main::-webkit-scrollbar,
    nav::-webkit-scrollbar,
    form::-webkit-scrollbar,
    textarea::-webkit-scrollbar,
    pre::-webkit-scrollbar,
    code::-webkit-scrollbar,
    ul::-webkit-scrollbar,
    ol::-webkit-scrollbar {
      width: 10px;
      height: 10px;
    }

    html::-webkit-scrollbar-track,
    body::-webkit-scrollbar-track,
    div::-webkit-scrollbar-track,
    section::-webkit-scrollbar-track,
    aside::-webkit-scrollbar-track,
    main::-webkit-scrollbar-track,
    nav::-webkit-scrollbar-track,
    form::-webkit-scrollbar-track,
    textarea::-webkit-scrollbar-track,
    pre::-webkit-scrollbar-track,
    code::-webkit-scrollbar-track,
    ul::-webkit-scrollbar-track,
    ol::-webkit-scrollbar-track {
      background: rgba(255,255,255,0.03);
      border-radius: 999px;
    }

    html::-webkit-scrollbar-thumb,
    body::-webkit-scrollbar-thumb,
    div::-webkit-scrollbar-thumb,
    section::-webkit-scrollbar-thumb,
    aside::-webkit-scrollbar-thumb,
    main::-webkit-scrollbar-thumb,
    nav::-webkit-scrollbar-thumb,
    form::-webkit-scrollbar-thumb,
    textarea::-webkit-scrollbar-thumb,
    pre::-webkit-scrollbar-thumb,
    code::-webkit-scrollbar-thumb,
    ul::-webkit-scrollbar-thumb,
    ol::-webkit-scrollbar-thumb {
      background: linear-gradient(180deg, rgba(124, 156, 255, 0.42), rgba(139, 92, 246, 0.34));
      border-radius: 999px;
      border: 2px solid rgba(11, 16, 32, 0.55);
      background-clip: padding-box;
    }

    html::-webkit-scrollbar-thumb:hover,
    body::-webkit-scrollbar-thumb:hover,
    div::-webkit-scrollbar-thumb:hover,
    section::-webkit-scrollbar-thumb:hover,
    aside::-webkit-scrollbar-thumb:hover,
    main::-webkit-scrollbar-thumb:hover,
    nav::-webkit-scrollbar-thumb:hover,
    form::-webkit-scrollbar-thumb:hover,
    textarea::-webkit-scrollbar-thumb:hover,
    pre::-webkit-scrollbar-thumb:hover,
    code::-webkit-scrollbar-thumb:hover,
    ul::-webkit-scrollbar-thumb:hover,
    ol::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(180deg, rgba(124, 156, 255, 0.58), rgba(139, 92, 246, 0.46));
      border: 2px solid rgba(11, 16, 32, 0.55);
      background-clip: padding-box;
    }

    html::-webkit-scrollbar-corner,
    body::-webkit-scrollbar-corner,
    div::-webkit-scrollbar-corner,
    section::-webkit-scrollbar-corner,
    aside::-webkit-scrollbar-corner,
    main::-webkit-scrollbar-corner,
    nav::-webkit-scrollbar-corner,
    form::-webkit-scrollbar-corner,
    textarea::-webkit-scrollbar-corner,
    pre::-webkit-scrollbar-corner,
    code::-webkit-scrollbar-corner,
    ul::-webkit-scrollbar-corner,
    ol::-webkit-scrollbar-corner {
      background: transparent;
    }

    html, body {
      margin: 0;
      min-height: 100%;
      font-family: 'Inter', system-ui, sans-serif;
      background:
        radial-gradient(circle at top left, rgba(124, 156, 255, 0.16), transparent 30%),
        radial-gradient(circle at top right, rgba(139, 92, 246, 0.14), transparent 26%),
        linear-gradient(180deg, #0a1020 0%, #0b1324 46%, #0d1528 100%);
      color: var(--text);
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      pointer-events: none;
      background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
      background-size: 28px 28px;
      mask-image: radial-gradient(circle at center, black, transparent 88%);
      opacity: 0.38;
    }

    .shell { min-height: 100vh; padding: 18px; display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; }
    .auth-wrap { width: min(1160px, 100%); display: grid; grid-template-columns: 1.05fr 0.95fr; border-radius: 34px; overflow: hidden; border: 1px solid var(--line); background: rgba(8, 12, 22, 0.74); box-shadow: var(--shadow-xl); backdrop-filter: blur(24px); }
    .hero, .auth-panel, .sidebar, .main-panel { min-width: 0; }
    .hero { padding: 48px; background: linear-gradient(180deg, rgba(11, 17, 31, 0.96), rgba(10, 15, 27, 0.9)); border-right: 1px solid var(--line); }
    .hero-badge, .status-badge, .plan-pill, .meta-pill, .thinking-summary, .composer-toggle { display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; }
    .hero-badge { background: rgba(124, 156, 255, 0.12); border: 1px solid rgba(124, 156, 255, 0.18); color: #dce6ff; font-size: 0.84rem; font-weight: 700; padding: 9px 14px; margin-bottom: 20px; }
    .hero h1 { margin: 0; font-size: clamp(2.5rem, 5vw, 4.6rem); line-height: 0.94; letter-spacing: -0.06em; max-width: 8ch; }
    .hero p { margin: 20px 0 0; max-width: 58ch; color: var(--muted); line-height: 1.75; font-size: 1rem; }
    .feature-grid { display: grid; gap: 14px; margin-top: 28px; }
    .feature { border-radius: 20px; padding: 16px 18px; border: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.04); color: var(--muted); line-height: 1.6; }
    .feature strong { display: block; color: var(--text); margin-bottom: 6px; font-size: 0.95rem; }
    .auth-panel { padding: 44px; background: linear-gradient(180deg, rgba(12, 18, 33, 0.95), rgba(9, 14, 26, 0.94)); display: flex; flex-direction: column; justify-content: center; }
    .auth-tabs { display: inline-flex; gap: 8px; padding: 6px; border-radius: 999px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); margin-bottom: 24px; align-self: flex-start; }
    .auth-tab, .submit, .new-chat-btn, .ghost-btn, .danger-btn, .send-button, .copy-btn, .regen-btn, .suggestion-btn { border: 0; cursor: pointer; transition: 0.18s ease; }
    .auth-tab { border-radius: 999px; padding: 10px 16px; color: var(--muted); background: transparent; font-weight: 700; position: relative; z-index: 5; pointer-events: auto; }
    .auth-tab.active { color: #08111f; background: linear-gradient(135deg, var(--accent), #b8c9ff); }
    .panel-title { margin: 0 0 10px; font-size: 1.9rem; letter-spacing: -0.04em; }
    .panel-subtitle { margin: 0 0 22px; color: var(--muted); line-height: 1.65; }
    .notice { margin-bottom: 14px; padding: 14px 16px; border-radius: 16px; font-size: 0.92rem; border: 1px solid rgba(255,255,255,0.08); line-height: 1.55; }
    .notice.error { background: rgba(130, 22, 41, 0.2); color: #ffd5dc; border-color: rgba(255, 112, 145, 0.18); }
    .notice.danger { background: rgba(130, 22, 41, 0.2); color: #ffd5dc; border-color: rgba(255, 112, 145, 0.18); }
    .notice.info { background: rgba(20, 74, 58, 0.2); color: #ccfff1; border-color: rgba(82, 237, 181, 0.18); }
    .auth-form { display: none; }
    .auth-form.active { display: block; }
    [data-form="login"], [data-form="register"] { display: none; }
    [data-form="login"].active, [data-form="register"].active { display: block; }
    .field { margin-bottom: 14px; }
    .field label, .composer-label, .composer-note { display: block; color: var(--muted); font-size: 0.8rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
    .field label { margin-bottom: 8px; }
    .field input, .composer textarea { width: 100%; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04); color: var(--text); border-radius: 16px; padding: 15px 16px; outline: none; font-size: 0.96rem; }
    .field input:focus, .composer textarea:focus { border-color: rgba(124, 156, 255, 0.34); box-shadow: 0 0 0 3px rgba(124, 156, 255, 0.1); }
    .submit, .new-chat-btn, .send-button { color: #08111f; font-weight: 800; background: linear-gradient(135deg, var(--accent), #b6c8ff); box-shadow: 0 14px 28px rgba(77, 109, 218, 0.26); }
    .submit { width: 100%; padding: 15px 18px; border-radius: 18px; }

    body.is-guest { min-height: 100vh; overflow-x: hidden; overflow-y: auto; }
    body.is-guest::after {
      content: '';
      position: fixed;
      inset: -20%;
      pointer-events: none;
      background:
        radial-gradient(circle at 18% 18%, rgba(124,156,255,0.24), transparent 28%),
        radial-gradient(circle at 82% 8%, rgba(139,92,246,0.24), transparent 26%),
        radial-gradient(circle at 66% 86%, rgba(56,211,159,0.15), transparent 28%);
      filter: blur(8px);
      opacity: 0.95;
      animation: rookAura 12s ease-in-out infinite alternate;
    }
    @keyframes rookAura { from { transform: translate3d(-1.5%, -1%, 0) scale(1); } to { transform: translate3d(1.5%, 1%, 0) scale(1.04); } }
    body.is-guest .shell { min-height: 100vh; height: auto; padding: clamp(14px, 2vw, 28px); align-items: flex-start; overflow: visible; }
    body.is-guest .auth-wrap {
      width: min(1480px, 100%);
      min-height: calc(100vh - 56px);
      grid-template-columns: minmax(0, 1.22fr) minmax(420px, 0.78fr);
      background:
        linear-gradient(135deg, rgba(255,255,255,0.09), rgba(255,255,255,0.02)) padding-box,
        linear-gradient(135deg, rgba(124,156,255,0.62), rgba(139,92,246,0.34), rgba(56,211,159,0.32)) border-box;
      border: 1px solid transparent;
      position: relative;
      isolation: isolate;
    }
    body.is-guest .auth-wrap::before {
      content: '';
      position: absolute;
      inset: 0;
      pointer-events: none;
      background:
        linear-gradient(90deg, rgba(124,156,255,0.14), transparent 42%, rgba(139,92,246,0.12)),
        repeating-linear-gradient(90deg, rgba(255,255,255,0.055) 0 1px, transparent 1px 96px);
      opacity: 0.38;
      mix-blend-mode: screen;
      z-index: -1;
    }
    body.is-guest .hero {
      padding: clamp(32px, 5vw, 76px);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 34px;
      background:
        radial-gradient(circle at 18% 8%, rgba(124,156,255,0.23), transparent 30%),
        linear-gradient(180deg, rgba(7, 12, 24, 0.94), rgba(8, 13, 24, 0.76));
      position: relative;
      overflow: hidden;
    }
    body.is-guest .hero::before {
      content: '';
      position: absolute;
      width: 540px;
      height: 540px;
      right: -180px;
      bottom: -190px;
      background: conic-gradient(from 170deg, rgba(124,156,255,0.02), rgba(124,156,255,0.32), rgba(139,92,246,0.16), rgba(56,211,159,0.18), rgba(124,156,255,0.02));
      opacity: 0.68;
      filter: blur(1px);
      animation: rookSpin 24s linear infinite;
    }
    @keyframes rookSpin { to { transform: rotate(360deg); } }
    .guest-nav { display:flex; align-items:center; justify-content:space-between; gap:18px; position:relative; z-index:1; }
    .guest-brand { display:flex; align-items:center; gap:14px; }
    .guest-brand-mark { width:54px; height:54px; display:grid; place-items:center; border:1px solid rgba(255,255,255,0.14); background:linear-gradient(135deg, rgba(124,156,255,0.24), rgba(139,92,246,0.22)); box-shadow:0 18px 50px rgba(64,91,180,0.28), inset 0 1px 0 rgba(255,255,255,0.08); }
    .guest-brand-title { font-size:1.05rem; font-weight:900; letter-spacing:-0.03em; }
    .guest-brand-subtitle { color:var(--muted); font-size:0.84rem; margin-top:2px; }
    .guest-top-pills { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; }
    .guest-pill, .guest-mini-pill { display:inline-flex; align-items:center; gap:8px; border:1px solid rgba(255,255,255,0.09); background:rgba(255,255,255,0.055); color:#dce6ff; padding:8px 11px; font-weight:800; font-size:0.78rem; letter-spacing:0.02em; backdrop-filter: blur(14px); }
    .guest-copy { max-width: 780px; position:relative; z-index:1; }
    .hero-kicker { display:inline-flex; align-items:center; gap:10px; padding:10px 13px; border:1px solid rgba(124,156,255,0.24); background:rgba(124,156,255,0.12); color:#dce6ff; font-weight:900; font-size:0.78rem; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:20px; }
    body.is-guest .hero h1 { max-width: 11ch; font-size: clamp(3.2rem, 7vw, 7.4rem); line-height:0.82; letter-spacing:-0.085em; text-wrap: balance; }
    .gradient-word { background:linear-gradient(135deg, #ffffff 0%, #cfd9ff 35%, #8faaff 65%, #7ff2c8 100%); -webkit-background-clip:text; background-clip:text; color:transparent; text-shadow:none; }
    body.is-guest .hero p.hero-lede { max-width: 70ch; font-size: clamp(1.02rem, 1.3vw, 1.22rem); color:#c5d2ec; line-height:1.75; }
    .guest-actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:28px; }
    .guest-cta, .guest-secondary { border:1px solid rgba(255,255,255,0.1); padding:14px 18px; font-weight:900; color:#08111f; background:linear-gradient(135deg, var(--accent), #b8c9ff); box-shadow:0 20px 52px rgba(77,109,218,0.3); text-decoration:none; }
    .guest-secondary { color:#dce6ff; background:rgba(255,255,255,0.055); box-shadow:none; }
    .guest-dashboard { display:grid; grid-template-columns: 1fr 1fr; gap:14px; position:relative; z-index:1; }
    .guest-metric, .guest-terminal, .guest-feature-card { border:1px solid rgba(255,255,255,0.09); background:rgba(255,255,255,0.055); backdrop-filter: blur(18px); box-shadow:0 18px 50px rgba(0,0,0,0.18); }
    .guest-metric { padding:18px; min-height:112px; }
    .guest-metric span { display:block; color:var(--muted); font-size:0.78rem; font-weight:900; letter-spacing:0.09em; text-transform:uppercase; }
    .guest-metric strong { display:block; margin-top:10px; font-size:clamp(1.55rem, 2vw, 2.25rem); letter-spacing:-0.06em; }
    .guest-metric em { display:block; margin-top:4px; color:#c5d2ec; font-style:normal; font-size:0.9rem; }
    .guest-terminal { grid-column: span 2; overflow:hidden; }
    .terminal-head { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; border-bottom:1px solid rgba(255,255,255,0.08); color:var(--muted); font-size:0.78rem; font-weight:900; letter-spacing:0.08em; text-transform:uppercase; }
    .terminal-dots { display:flex; gap:6px; }
    .terminal-dots i { width:8px; height:8px; display:block; background:rgba(255,255,255,0.24); }
    .terminal-body { padding:18px; font-family:'JetBrains Mono','SFMono-Regular',Consolas,monospace; color:#dce6ff; font-size:0.88rem; line-height:1.75; }
    .terminal-body .muted { color:#7f90b3; }
    .terminal-body .accent { color:#98b0ff; }
    .terminal-body .success { color:#80f0c6; }
    body.is-guest .auth-panel {
      padding: clamp(28px, 4vw, 54px);
      background:
        linear-gradient(180deg, rgba(12, 18, 33, 0.94), rgba(7, 12, 23, 0.96)),
        radial-gradient(circle at 80% 0%, rgba(124,156,255,0.2), transparent 34%);
      border-left:1px solid rgba(255,255,255,0.08);
      position:relative;
      overflow:hidden;
    }
    body.is-guest .auth-panel::before { content:''; position:absolute; inset:0; pointer-events:none; background:linear-gradient(180deg, rgba(255,255,255,0.05), transparent 24%); }
    .auth-card-eyebrow { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:22px; color:var(--muted); font-size:0.78rem; font-weight:900; letter-spacing:0.1em; text-transform:uppercase; }
    .auth-card-eyebrow span { color:#80f0c6; }
    body.is-guest .auth-tabs { position:relative; z-index:2; width:100%; display:grid; grid-template-columns:1fr 1fr; padding:5px; background:rgba(255,255,255,0.055); }
    body.is-guest .auth-tab { padding:13px 14px; }
    body.is-guest .panel-title { position:relative; z-index:1; font-size:clamp(2rem, 3vw, 3.1rem); letter-spacing:-0.07em; }
    body.is-guest .panel-subtitle { position:relative; z-index:1; color:#b8c7e6; }
    body.is-guest .field input { background:rgba(5,10,20,0.58); border-color:rgba(255,255,255,0.11); padding:16px 16px; }
    body.is-guest .field input:hover { border-color:rgba(124,156,255,0.22); }
    body.is-guest .submit { padding:16px 18px; font-size:0.98rem; text-transform:uppercase; letter-spacing:0.05em; }
    .auth-footnote { margin-top:18px; color:var(--muted); line-height:1.6; font-size:0.86rem; }
    .auth-footnote i { color:#80f0c6; margin-right:7px; }

    .app { width: min(100vw - 24px, 1920px); height: calc(100vh - 24px); display: grid; grid-template-columns: 292px minmax(0, 1fr); background: rgba(7, 12, 22, 0.72); border: 1px solid var(--line); border-radius: 32px; overflow: hidden; box-shadow: var(--shadow-xl); backdrop-filter: blur(24px); }
    .sidebar { display: flex; flex-direction: column; min-height: 0; background: linear-gradient(180deg, rgba(10, 16, 29, 0.96), rgba(9, 15, 27, 0.92)); border-right: 1px solid var(--line); }
    .sidebar-top { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .brand { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
    .brand-mark { width: 52px; height: 52px; border-radius: 18px; display: grid; place-items: center; background: linear-gradient(135deg, rgba(124,156,255,0.24), rgba(139,92,246,0.28)); border: 1px solid rgba(255,255,255,0.1); font-size: 1.15rem; box-shadow: inset 0 1px 0 rgba(255,255,255,0.04); }
    .brand h1 { margin: 0; font-size: 1.04rem; letter-spacing: -0.02em; }
    .brand p { margin: 4px 0 0; color: var(--muted); font-size: 0.88rem; }
    .card { border-radius: 20px; border: 1px solid rgba(255,255,255,0.06); background: linear-gradient(180deg, rgba(17, 24, 39, 0.92), rgba(12, 18, 32, 0.9)); padding: 16px; box-shadow: var(--shadow-lg); }
    .status-badge { padding: 8px 12px; background: rgba(56, 211, 159, 0.12); color: #c7ffe9; border: 1px solid rgba(56, 211, 159, 0.18); font-size: 0.84rem; font-weight: 700; }
    .plan-pill { padding: 6px 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); color: var(--text); font-size: 0.8rem; font-weight: 700; margin-top: 12px; }
    .sidebar-body { flex: 1; min-height: 0; overflow: auto; padding: 18px 20px 22px; display: flex; flex-direction: column; gap: 14px; }
    .new-chat-btn { width: 100%; border-radius: 18px; padding: 14px 16px; font-size: 0.95rem; }
    .ghost-btn, .danger-btn, .copy-btn, .regen-btn, .suggestion-btn, .composer-toggle { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); color: var(--muted); }
    .ghost-btn, .danger-btn, .copy-btn, .regen-btn, .suggestion-btn { border-radius: 14px; padding: 10px 12px; font-weight: 700; font-size: 0.86rem; }
    .copy-btn, .regen-btn { min-height: 38px; padding: 8px 12px; font-size: 0.8rem; letter-spacing: 0.01em; }
    .copy-btn i, .regen-btn i { font-size: 0.85rem; }
    .danger-btn { color: #ffd4d9; background: rgba(135, 27, 45, 0.16); border-color: rgba(255, 109, 109, 0.18); }
    .conversation-list { display: grid; gap: 10px; }
    .conversation-accordion { display: grid; gap: 10px; }
    .conversation-accordion-item { border: 1px solid rgba(255,255,255,0.07); background: rgba(255,255,255,0.025); }
    .conversation-accordion-toggle { width: 100%; border: 0; color: var(--text); background: rgba(255,255,255,0.04); padding: 11px 12px; display: flex; align-items: center; justify-content: space-between; gap: 10px; font-weight: 850; letter-spacing: -0.02em; }
    .conversation-accordion-toggle span:first-child { display: inline-flex; align-items: center; gap: 9px; }
    .conversation-accordion-toggle::after { content: '\f078'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: .72rem; color: var(--muted); transition: transform .16s ease; margin-left: auto; }
    .conversation-accordion-toggle:not(.collapsed)::after { transform: rotate(180deg); }
    .conversation-count { min-width: 24px; height: 24px; padding: 0 8px; display: inline-flex; align-items: center; justify-content: center; background: rgba(124,156,255,0.14); border: 1px solid rgba(124,156,255,0.18); color: #dfe7ff; font-size: .76rem; font-weight: 900; }
    .conversation-accordion .conversation-list { padding: 10px; }
    .conversation-empty { padding: 12px; color: var(--muted); font-size: .84rem; border: 1px dashed rgba(255,255,255,0.1); background: rgba(255,255,255,0.025); }
    .conversation-item { display: block; text-decoration: none; color: inherit; padding: 14px; border-radius: 18px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); transition: 0.18s ease; }
    .conversation-item:hover, .ghost-btn:hover, .copy-btn:hover, .regen-btn:hover, .composer-toggle:hover, .suggestion-btn:hover, .danger-btn:hover { transform: translateY(-1px); border-color: rgba(255,255,255,0.14); background: rgba(255,255,255,0.07); }

    .conversation-item { position: relative; padding-right: 42px; }
    .conversation-menu-trigger {
      position: absolute;
      top: 10px;
      right: 10px;
      width: 26px;
      height: 26px;
      border: 1px solid rgba(255,255,255,0.08);
      background: rgba(255,255,255,0.04);
      color: var(--muted);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity .16s ease, background .16s ease, color .16s ease;
    }
    .conversation-item:hover .conversation-menu-trigger,
    .conversation-item.active .conversation-menu-trigger,
    .conversation-menu-trigger:focus { opacity: 1; }
    .conversation-menu-trigger:hover { background: rgba(124,156,255,0.14); color: var(--text); }
    .conversation-context-menu {
      position: fixed;
      z-index: 5000;
      min-width: 230px;
      display: none;
      padding: 8px;
      border: 1px solid rgba(255,255,255,0.1);
      background: rgba(10,16,32,0.98);
      box-shadow: var(--shadow-lg);
    }
    .conversation-context-menu.is-open { display: block; }
    .conversation-context-menu button,
    .conversation-context-menu a {
      width: 100%;
      display: flex;
      align-items: center;
      gap: 10px;
      border: 0;
      background: transparent;
      color: var(--text);
      text-align: left;
      padding: 10px 11px;
      text-decoration: none;
      font-weight: 700;
      font-size: .88rem;
    }
    .conversation-context-menu button:hover,
    .conversation-context-menu a:hover { background: rgba(255,255,255,0.07); }
    .conversation-context-menu button[disabled] { opacity: .42; cursor: not-allowed; }
    .conversation-context-menu .danger { color: var(--danger); }
    .conversation-context-menu .context-hint { padding: 7px 11px 3px; color: var(--muted); font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
    .conversation-item.active { background: rgba(124, 156, 255, 0.12); border-color: rgba(124, 156, 255, 0.18); box-shadow: inset 0 0 0 1px rgba(124, 156, 255, 0.06); }
    .conversation-item strong { display: block; font-size: 0.92rem; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .conversation-item span { display: block; color: var(--muted); font-size: 0.82rem; line-height: 1.45; max-height: 2.7em; overflow: hidden; }
    .conversation-context-menu { position: fixed; z-index: 4000; min-width: 240px; padding: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(8, 13, 24, 0.98); box-shadow: var(--shadow-xl); display: none; }
    .conversation-context-menu.open { display: grid; gap: 4px; }
    .conversation-context-menu button, .conversation-context-menu a { width: 100%; text-align: left; display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: var(--text); background: transparent; border: 0; text-decoration: none; font-weight: 750; }
    .conversation-context-menu button:hover, .conversation-context-menu a:hover { background: rgba(255,255,255,0.07); }
    .conversation-context-menu [disabled] { opacity: 0.45; cursor: not-allowed; }
    .conversation-context-menu .context-danger { color: #ffd4d9; }
    .main-panel { display: flex; flex-direction: column; min-height: 0; background: linear-gradient(180deg, rgba(10, 16, 29, 0.58), rgba(8, 14, 26, 0.82)); position: relative; }
    .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 22px; border-bottom: 1px solid var(--line); background: rgba(9, 14, 26, 0.72); backdrop-filter: blur(18px); }
    .topbar-main { display: flex; align-items: center; gap: 14px; min-width: 0; }
    .topbar-icon { width: 46px; height: 46px; border-radius: 16px; display: grid; place-items: center; background: linear-gradient(135deg, rgba(124,156,255,0.2), rgba(139,92,246,0.2)); border: 1px solid rgba(255,255,255,0.1); font-size: 1rem; }
    .topbar-title h2 { margin: 0; font-size: 1.05rem; letter-spacing: -0.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .topbar-title p { margin: 4px 0 0; color: var(--muted); font-size: 0.87rem; }
    .topbar-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
    .chat-scroll { flex: 1; min-height: 0; overflow: auto; padding: 20px 24px 112px; display: flex; flex-direction: column; gap: 16px; scroll-behavior: smooth; }
    .empty-shell { flex: 1; display: grid; place-items: center; min-height: 420px; }
    .empty-card { width: min(860px, 100%); padding: 30px; border-radius: var(--radius-xl); background: linear-gradient(180deg, rgba(14, 21, 38, 0.95), rgba(10, 16, 28, 0.94)); border: 1px solid var(--line-strong); box-shadow: var(--shadow-xl); }
    .empty-card h3 { margin: 0; font-size: clamp(2.1rem, 4vw, 3rem); line-height: 0.98; letter-spacing: -0.05em; }
    .empty-card p { margin: 16px 0 0; color: var(--muted); line-height: 1.7; max-width: 62ch; }
    .suggestions-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 24px; }
    .suggestion-btn { padding: 14px; border-radius: 18px; text-align: left; line-height: 1.55; color: var(--text); font-weight: 600; }
    .suggestion-btn span { display: block; color: var(--muted); font-size: 0.84rem; font-weight: 500; margin-top: 6px; }
    .messages-wrap { display: flex; flex-direction: column; gap: 22px; }
    .message-row { display: flex; justify-content: center; width: 100%; }
    .message-card { width: min(980px, 100%); display: grid; gap: 12px; }
    .message-row.user .message-card { justify-items: end; }
    .message-head { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 14px; }
    .message-meta { display: inline-flex; align-items: center; gap: 10px; color: var(--muted); font-size: 0.8rem; flex-wrap: wrap; }
    .meta-pill { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.045); font-weight: 800; font-size: 0.78rem; letter-spacing: 0.02em; }
    .message-tools { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .message-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.08); position: relative; z-index: 1; }
    .message-row.user .message-actions { justify-content: flex-end; }
    .message-status { color: rgba(226,232,240,0.56); font-size: 0.72rem; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 800; }
    .message-row.user .message-status { display: none; }
    .bubble { width: 100%; padding: 18px 20px 16px; border: 1px solid rgba(255,255,255,0.08); box-shadow: var(--shadow-lg); background: linear-gradient(180deg, rgba(18, 27, 46, 0.96), rgba(12, 20, 36, 0.98)); position: relative; overflow: hidden; }
    .bubble::before { content: ''; position: absolute; inset: 0; pointer-events: none; background: linear-gradient(180deg, rgba(255,255,255,0.05), transparent 30%); }
    .message-row.user .bubble { background: linear-gradient(135deg, rgba(57, 89, 180, 0.94), rgba(65, 110, 240, 0.96)); border-color: rgba(255,255,255,0.14); }
    .message-row.assistant .bubble { backdrop-filter: blur(14px); }
    .thinking-summary { display: none; margin-bottom: 10px; }
    .thinking-accordion { border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04); }
    .thinking-accordion .accordion-item,
    .thinking-accordion .accordion-button,
    .thinking-accordion .accordion-body { background: transparent; color: inherit; }
    .thinking-accordion .accordion-button { box-shadow: none; padding: 8px 12px; color: var(--muted); font-size: 0.82rem; line-height: 1.4; }
    .thinking-accordion .accordion-button:not(.collapsed) { color: var(--text); box-shadow: none; background: rgba(139, 92, 246, 0.08); }
    .thinking-accordion .accordion-button::after { filter: invert(1) grayscale(1); opacity: .72; }
    .thinking-accordion-share .accordion-button { cursor: default; pointer-events: none; }
    .thinking-accordion-share .accordion-button::after { display: none; }
    .thinking-accordion .accordion-body { padding: 0 12px 12px; }
    .thinking-inline { display: none; padding: 10px 12px; border: 1px solid rgba(177, 126, 255, 0.2); background: rgba(139, 92, 246, 0.14); color: #dfcfff; font-size: 0.84rem; line-height: 1.55; }
    .thinking-inline strong { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 6px; color: #f3eaff; font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase; }
    .message-markdown { position: relative; z-index: 1; color: var(--text); font-size: 0.97rem; line-height: 1.75; word-break: break-word; }
    .message-markdown p { margin: 0 0 0.9rem; }
    .message-markdown p:last-child { margin-bottom: 0; }
    .message-markdown ul, .message-markdown ol { margin: 0.45rem 0 0.9rem 1.25rem; }
    .message-markdown li + li { margin-top: 0.34rem; }
    .message-markdown code { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.08); padding: 0.16rem 0.42rem; border-radius: 0.45rem; font-size: 0.9em; }
    .message-markdown pre { position: relative; background: #09101d; border: 1px solid rgba(255,255,255,0.08); border-radius: 18px; padding: 1rem; overflow-x: auto; margin: 0.9rem 0; }
    .message-markdown pre code { background: transparent; border: 0; padding: 0; }
    .message-markdown a { color: #a9c4ff; text-decoration: underline; }
    .message-images { display: flex; gap: 10px; flex-wrap: wrap; margin: 0 0 12px; position: relative; z-index: 1; }
    .message-image-thumb { width: 132px; max-width: 46%; aspect-ratio: 1.25; object-fit: cover; border-radius: 14px; border: 1px solid rgba(255,255,255,0.14); background: rgba(255,255,255,0.05); cursor: zoom-in; transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease; }
    .message-image-thumb:hover { transform: translateY(-1px); border-color: rgba(124,156,255,0.48); box-shadow: 0 14px 34px rgba(0,0,0,0.25); }
    .image-gallery-modal .modal-content { background: rgba(8, 12, 22, 0.98); border: 1px solid rgba(255,255,255,0.12); border-radius: 0; color: var(--text); overflow: hidden; box-shadow: 0 28px 90px rgba(0,0,0,0.55); }
    .image-gallery-modal .modal-header, .image-gallery-modal .modal-footer { border-color: rgba(255,255,255,0.1); }
    .image-gallery-stage { min-height: min(72vh, 760px); display: grid; place-items: center; background: radial-gradient(circle at top, rgba(124,156,255,0.12), rgba(0,0,0,0.18)); padding: 18px; }
    .image-gallery-stage img { max-width: 100%; max-height: 72vh; border-radius: 18px; object-fit: contain; box-shadow: 0 20px 70px rgba(0,0,0,0.42); }
    .image-gallery-nav { border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.08); color: #fff; width: 44px; height: 44px; border-radius: 999px; display: inline-grid; place-items: center; }
    .image-gallery-nav:disabled { opacity: .35; cursor: not-allowed; }
    .image-gallery-caption { color: var(--muted); font-size: .9rem; }
    .image-upload-btn { width: 40px; height: 40px; min-width: 40px; padding: 0; border-radius: 0; display: inline-flex; align-items: center; justify-content: center; gap: 0; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.045); color: var(--text); cursor: pointer; aspect-ratio: 1 / 1; }
    .image-upload-btn:hover { border-color: rgba(124, 156, 255, 0.28); background: rgba(124, 156, 255, 0.12); }
    .image-upload-btn.is-disabled { opacity: 0.48; cursor: not-allowed; pointer-events: none; }
    .message-input-shell.is-disabled { opacity: 0.72; }
    .message-input-shell.is-disabled:focus-within { border-color: rgba(255,255,255,0.08); box-shadow: none; }
    .message-input-shell { position: relative; min-height: 40px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04); border-radius: 0; overflow: hidden; transition: border-color .16s ease, box-shadow .16s ease; display: flex; align-items: center; gap: 6px; padding: 4px 8px; }
    .message-input-shell:focus-within { border-color: rgba(124, 156, 255, 0.34); box-shadow: 0 0 0 3px rgba(124, 156, 255, 0.1); }
    .message-input-shell textarea { flex: 1 1 auto; min-width: 0; border: 0 !important; background: transparent !important; box-shadow: none !important; padding: 4px 2px !important; }
    .message-input-shell.has-images textarea { padding-left: 2px !important; }
    .image-preview-strip { display: none; position: static; transform: none; gap: 5px; flex-wrap: nowrap; margin: 0; z-index: 2; max-width: min(188px, 46%); overflow: hidden; pointer-events: auto; flex: 0 0 auto; align-items: center; }
    .image-preview-strip.active { display: flex; }
    .composer-image-preview { position: relative; width: 30px; height: 30px; flex: 0 0 auto; border-radius: 9px; overflow: hidden; border: 1px solid rgba(255,255,255,0.16); background: rgba(255,255,255,0.05); box-shadow: 0 8px 18px rgba(0,0,0,0.18); }
    .composer-image-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .composer-image-preview button { position: absolute; top: -2px; right: -2px; width: 15px; height: 15px; border-radius: 999px; border: 0; background: rgba(7,11,20,0.9); color: #fff; display: grid; place-items: center; cursor: pointer; font-size: 0.58rem; line-height: 1; padding: 0; }
    .composer-image-preview button i { pointer-events: none; }
    .message-markdown blockquote { margin: 0.9rem 0; padding-left: 1rem; border-left: 3px solid rgba(124,156,255,0.38); color: #d2dcf1; }
    .code-copy-btn { position: absolute; top: 10px; right: 10px; border: 1px solid rgba(255,255,255,0.1); background: rgba(12, 18, 32, 0.88); color: #d8e3ff; border-radius: 10px; padding: 6px 10px; font-size: 0.74rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; z-index: 2; transition: 0.18s ease; }
    .code-copy-btn:hover { background: rgba(26, 36, 60, 0.94); border-color: rgba(255,255,255,0.16); transform: translateY(-1px); }
    .composer-wrap { position: sticky; bottom: 0; left: 0; right: 0; width: 100%; margin: 0; padding: 0; border-top: 1px solid var(--line); background: rgba(10, 15, 27, 0.98); backdrop-filter: blur(20px); z-index: 8; align-self: stretch; }
    .composer { display: block; width: 100%; max-width: none; margin: 0; border-radius: 0; border: 0; background: linear-gradient(180deg, rgba(15, 22, 39, 0.98), rgba(10, 16, 28, 0.98)); padding: 6px 16px 7px; box-shadow: none; }
    .composer-top { display: flex; gap: 6px; align-items: stretch; width: 100%; }
    .textarea-wrap { flex: 1 1 auto; min-width: 0; width: 100%; }
    .composer-action { display: flex; flex-direction: column; flex-shrink: 0; }
    .composer-action-spacer { margin-bottom: 2px; min-height: calc(0.72rem * 1.2 + 0.08em + 2px); visibility: hidden; }
    .composer-label { margin-bottom: 2px; font-size: 0.68rem; }
    .composer textarea { min-height: 40px; max-height: 112px; resize: none; line-height: 1.35; font-size: 0.9rem; padding: 8px 10px; border-radius: 0; }
    .send-button { height: 40px; min-width: 88px; padding: 0 12px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 0.84rem; align-self: stretch; }
    .send-button.is-stop { background: linear-gradient(135deg, #ff8c8c, #ffb2b2); box-shadow: 0 14px 28px rgba(204, 78, 78, 0.24); }
    .send-button:disabled { opacity: 0.7; cursor: wait; transform: none; box-shadow: none; }
    .composer-footer { margin-top: 4px; display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap; width: 100%; color: var(--muted); font-size: 0.72rem; }
    .composer-left, .composer-right { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .composer-toggle { padding: 5px 8px; font-size: 0.72rem; font-weight: 700; line-height: 1; }
    .composer-toggle.active { background: rgba(124, 156, 255, 0.14); border-color: rgba(124, 156, 255, 0.24); color: #dce6ff; }
    .stop-btn {
      border-radius: 14px;
      padding: 10px 12px;
      font-weight: 700;
      font-size: 0.86rem;
      border: 1px solid rgba(255, 109, 109, 0.18);
      background: rgba(135, 27, 45, 0.16);
      color: #ffd4d9;
      display: none;
    }
    .usage-live {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      color: var(--muted);
      font-size: 0.8rem;
      line-height: 1;
    }
    .typing-wrap { display: inline-flex; align-items: center; gap: 6px; padding: 4px 0; }
    .typing-dot { width: 8px; height: 8px; border-radius: 999px; background: rgba(233, 240, 255, 0.9); animation: bounce 1.1s infinite ease-in-out; }
    .typing-dot:nth-child(2) { animation-delay: 0.14s; }
    .typing-dot:nth-child(3) { animation-delay: 0.28s; }
    @keyframes bounce { 0%, 80%, 100% { transform: translateY(0); opacity: 0.45; } 40% { transform: translateY(-5px); opacity: 1; } }
    .sidebar-top { padding: 16px 18px 14px; background: linear-gradient(180deg, rgba(11,17,29,0.98), rgba(9,14,24,0.92)); }
    .brand { display:flex; align-items:center; gap:14px; }
    .brand h1 { margin:0; font-size:1.05rem; font-weight:800; letter-spacing:-0.04em; }
    .brand p { margin:2px 0 0; color:var(--muted); font-size:0.78rem; }
    .workspace-switcher { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:14px; padding-top:14px; border-top:1px solid rgba(255,255,255,0.06); }
    .workspace-label { color:var(--muted); font-size:0.75rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; }
    .sidebar-quick-actions { display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:10px; align-items:stretch; }
    .sidebar-actions-dropdown { position:relative; }
    .sidebar-menu-button { width:48px; height:100%; min-height:48px; display:inline-flex; align-items:center; justify-content:center; padding:0; }
    .sidebar-menu-button::after { display:none; }
    .sidebar-dropdown-menu { min-width:245px; max-width:calc(100vw - 36px); }
    .dropdown-item-form { margin:0; }
    .sidebar-link-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
    .sidebar-nav-link { display:flex; align-items:center; justify-content:center; gap:8px; padding:11px 12px; text-decoration:none; color:var(--text); font-weight:700; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); }
    .sidebar-nav-link.active, .sidebar-nav-link:hover { background:rgba(124,156,255,0.12); border-color:rgba(124,156,255,0.24); }
    .sidebar-stat-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(110px, 1fr)); gap:8px; }
    .mini-stat { min-width:0; padding:10px; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.04); }
    .mini-stat span { display:block; color:var(--muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:6px; overflow-wrap:anywhere; }
    .mini-stat strong { display:block; font-size:1rem; overflow-wrap:anywhere; }
    .sidebar-section-label { color:var(--muted); font-size:0.74rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; margin:4px 0 0; }
    .conversation-item { padding: 12px 42px 12px 13px; min-width: 0; }
    .conversation-item strong { display: block; width: 100%; max-width: 100%; min-width: 0; margin-bottom: 6px; font-size: 0.92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .conversation-item span { display:block; color:var(--muted); font-size:0.83rem; line-height:1.45; }
    .conversation-meta { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:8px; color:var(--muted); font-size:0.74rem; }
    .topbar { position:sticky; top:0; z-index:5; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 22px; background:rgba(9,14,24,0.9); backdrop-filter:blur(18px); border-bottom:1px solid rgba(255,255,255,0.06); }
    .topbar-main { display:flex; align-items:center; gap:14px; min-width:0; }
    .topbar-title h2 { margin:0; font-size:1.15rem; letter-spacing:-0.04em; }
    .topbar-title p { margin:3px 0 0; color:var(--muted); font-size:0.82rem; }
    .topbar-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .user-chip { display:inline-flex; align-items:center; gap:10px; padding:10px 12px; min-height:40px; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.04); color:var(--text); font-weight:700; font-size:0.86rem; }
    .user-chip small { display:block; color:var(--muted); font-size:0.72rem; font-weight:600; line-height:1.1; }
    .topbar-menu .dropdown-menu, .sidebar-actions-dropdown .dropdown-menu { min-width:240px; background:#0d1524; border:1px solid rgba(255,255,255,0.08); border-radius:0; box-shadow:var(--shadow-lg); padding:8px; }
    .topbar-menu .dropdown-item, .topbar-menu .dropdown-header, .sidebar-actions-dropdown .dropdown-item, .sidebar-actions-dropdown .dropdown-header { color:var(--text); }
    .topbar-menu .dropdown-header, .sidebar-actions-dropdown .dropdown-header { color:var(--muted); font-size:0.7rem; font-weight:900; letter-spacing:0.1em; text-transform:uppercase; padding:7px 10px; }
    .topbar-menu .dropdown-item, .sidebar-actions-dropdown .dropdown-item { border-radius:0; padding:9px 10px; font-weight:700; }
    .topbar-menu .dropdown-item:hover, .sidebar-actions-dropdown .dropdown-item:hover, .sidebar-actions-dropdown .dropdown-item.active { background:rgba(255,255,255,0.06); color:var(--text); }
    .topbar-menu .dropdown-divider, .sidebar-actions-dropdown .dropdown-divider { border-color:rgba(255,255,255,0.08); margin:7px 0; }
    .notifications-menu { position:relative; }
    .notification-bell { position:relative; width:42px; height:42px; min-width:42px; padding:0; display:inline-grid; place-items:center; line-height:1; flex:0 0 auto; }
    .notification-bell.dropdown-toggle::after { display:none !important; content:none !important; margin:0 !important; border:0 !important; }
    .notification-bell::after { display:none !important; content:none !important; }
    .notification-count { position:absolute; top:-6px; right:-7px; min-width:20px; height:20px; padding:0 5px; border-radius:999px; display:grid; place-items:center; background:#ff5f7a; color:#fff; font-size:0.68rem; font-weight:900; border:2px solid #0d1524; }
    .notification-dropdown { width:min(420px, calc(100vw - 24px)); max-height:70vh; overflow:auto; background:#0d1524; border:1px solid rgba(255,255,255,0.08); box-shadow:var(--shadow-lg); padding:0; }
    .notification-dropdown-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 14px; border-bottom:1px solid rgba(255,255,255,0.08); }
    .notification-dropdown-head strong { font-weight:900; }
    .notification-dropdown-head button, .notification-actions button { border:0; background:transparent; color:#9fb3ff; font-weight:800; font-size:0.78rem; }
    .notification-empty { padding:22px 14px; color:var(--muted); }
    .notification-item { display:grid; grid-template-columns:1fr auto; gap:10px; padding:12px 14px; border-bottom:1px solid rgba(255,255,255,0.07); background:rgba(255,255,255,0.02); }
    .notification-item.unread { background:rgba(124,156,255,0.09); }
    .notification-open { text-align:left; border:0; background:transparent; color:var(--text); padding:0; min-width:0; }
    .notification-title, .notification-preview, .notification-date { display:block; }
    .notification-title { font-weight:900; letter-spacing:-0.02em; }
    .notification-preview { color:var(--muted); font-size:0.84rem; margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .notification-date { color:#7f90b3; font-size:0.75rem; margin-top:6px; }
    .notification-actions { display:flex; flex-direction:column; align-items:flex-end; justify-content:center; gap:6px; }
    .notification-modal .modal-content { background:#0d1524; border:1px solid rgba(255,255,255,0.08); color:var(--text); box-shadow:var(--shadow-lg); }
    .notification-modal .modal-header, .notification-modal .modal-footer { border-color:rgba(255,255,255,0.08); }
    .notification-modal .btn-close { filter:invert(1) grayscale(1) brightness(1.8); opacity:0.8; }
    .upgrade-cards { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; margin: 0 0 16px; align-items:stretch; }
    .upgrade-card { position:relative; min-height:100%; border:1px solid rgba(255,255,255,0.08); background: linear-gradient(180deg, rgba(17, 24, 39, 0.92), rgba(12, 18, 32, 0.9)); padding:16px; box-shadow: var(--shadow-lg); display:flex; flex-direction:column; height:100%; }
    .upgrade-card-body { flex:1 1 auto; }
    .upgrade-card-title { font-size:1.15rem; font-weight:800; margin-top:8px; padding-right:82px; }
    .upgrade-card-price { margin-top:10px; font-size:1.8rem; font-weight:800; letter-spacing:-0.04em; color:var(--text); }
    .upgrade-card-price small { font-size:0.84rem; color:var(--muted); font-weight:600; margin-left:4px; }
    .upgrade-card p { margin:10px 0 14px; color:var(--muted); line-height:1.6; }
    .upgrade-card .new-chat-btn { width:100%; margin-top:auto; justify-content:center; }
    .upgrade-card.is-recommended { border-color:rgba(124,156,255,0.65); background:linear-gradient(180deg, rgba(41, 58, 108, 0.94), rgba(12, 18, 32, 0.94)); box-shadow:0 20px 60px rgba(7,13,28,0.5), 0 0 0 1px rgba(124,156,255,0.16) inset; }
    .upgrade-card-badge { position:absolute; top:14px; right:14px; padding:5px 8px; font-size:0.68rem; font-weight:900; letter-spacing:0.08em; text-transform:uppercase; color:#08111f; background:linear-gradient(135deg, var(--accent), #b8c9ff); }
    .plan-compare-table-wrap { margin-top: 4px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); overflow: hidden; }
    .plan-compare-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .plan-compare-table th, .plan-compare-table td { padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; vertical-align: top; }
    .plan-compare-table thead th { background: rgba(124, 156, 255, 0.08); color: var(--text); font-size: 0.82rem; letter-spacing: 0.08em; text-transform: uppercase; }
    .plan-compare-table tbody tr:last-child td { border-bottom: 0; }
    .plan-compare-table tbody td:first-child { color: var(--text); font-weight: 700; width: 28%; }
    .plan-compare-table .is-current { color: #08111f; background: linear-gradient(135deg, var(--accent), #b8c9ff); font-weight: 800; }
    .plan-compare-table .muted-cell { color: var(--muted); }
    .topbar-status { display:flex; align-items:center; gap:8px; color:var(--muted); font-size:0.8rem; }

    .personality-modal .modal-content { background:#0d1524; border:1px solid rgba(255,255,255,0.08); color:var(--text); box-shadow:var(--shadow-lg); }
    .personality-modal .modal-header, .personality-modal .modal-footer { border-color:rgba(255,255,255,0.08); }
    .personality-modal .modal-title { font-weight:800; letter-spacing:-0.03em; }
    .personality-modal .btn-close { filter:invert(1) grayscale(1) brightness(1.8); opacity:0.8; }
    .personality-modal .btn-close:hover { opacity:1; }
    .personality-modal .form-control { background:#0a111d; border:1px solid rgba(255,255,255,0.1); color:var(--text); }
    .personality-modal .form-control:focus { background:#0a111d; color:var(--text); border-color:rgba(124,156,255,0.4); box-shadow:none; }
    .personality-modal .form-text { color:var(--muted); }
    .personality-presets { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
    .personality-preset-btn { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.04); color:var(--text); font-weight:700; font-size:0.8rem; }
    .personality-preset-btn:hover { background:rgba(124,156,255,0.12); border-color:rgba(124,156,255,0.24); }
    .mobile-sidebar-toggle, .sidebar-mobile-backdrop { display:none; }
    .mobile-sidebar-toggle { width:42px; height:42px; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.04); color:var(--text); }
    .main-panel { overflow: hidden; }
    .chat-scroll { overscroll-behavior: contain; }
    .sidebar-body, .chat-scroll { scrollbar-gutter: stable; }
    @media (min-width: 921px) {
      body.is-authenticated { height:100%; overflow:hidden; }
      body.is-authenticated .shell { height:100vh; overflow:hidden; }
      body.is-authenticated .app { height:100vh; overflow:hidden; }
      body.is-authenticated .sidebar { position:relative; overflow:hidden; }
      body.is-authenticated .main-panel { height:100%; }
      body.is-authenticated .composer-wrap { flex-shrink:0; }
      body.is-guest { min-height:100vh; overflow-x:hidden; overflow-y:auto; }
      body.is-guest .shell { height:auto; min-height:100vh; padding:clamp(14px, 2vw, 28px); overflow:visible; }
    }
    @media (max-width: 1180px) { .app { grid-template-columns: 280px minmax(0, 1fr); } .suggestions-grid { grid-template-columns: 1fr; } }

    body.is-authenticated .shell { padding: 0; }
    body.is-guest .shell { padding: clamp(14px, 2vw, 28px); }
    .app { width: 100vw; height: 100vh; border-radius: 0; border-left: 0; border-right: 0; }
    .sidebar, .topbar, .main-panel, .auth-wrap, .hero, .auth-panel, .card, .conversation-item, .empty-card, .bubble, .composer, .field input, .composer textarea, .new-chat-btn, .ghost-btn, .danger-btn, .copy-btn, .regen-btn, .suggestion-btn, .composer-toggle, .send-button, .submit, .notice, .status-badge, .plan-pill, .meta-pill, .thinking-summary, .thinking-inline, .code-copy-btn, .brand-mark, .topbar-icon, .stop-btn, .mobile-sidebar-toggle { border-radius: 0 !important; }
    .hero-badge, .status-badge, .plan-pill, .meta-pill, .thinking-summary, .composer-toggle, .auth-tabs, .auth-tab, .typing-dot, html::-webkit-scrollbar-track, html::-webkit-scrollbar-thumb, body::-webkit-scrollbar-track, body::-webkit-scrollbar-thumb, div::-webkit-scrollbar-track, div::-webkit-scrollbar-thumb, section::-webkit-scrollbar-track, section::-webkit-scrollbar-thumb, aside::-webkit-scrollbar-track, aside::-webkit-scrollbar-thumb, main::-webkit-scrollbar-track, main::-webkit-scrollbar-thumb, nav::-webkit-scrollbar-track, nav::-webkit-scrollbar-thumb, form::-webkit-scrollbar-track, form::-webkit-scrollbar-thumb, textarea::-webkit-scrollbar-track, textarea::-webkit-scrollbar-thumb, pre::-webkit-scrollbar-track, pre::-webkit-scrollbar-thumb, code::-webkit-scrollbar-track, code::-webkit-scrollbar-thumb, ul::-webkit-scrollbar-track, ul::-webkit-scrollbar-thumb, ol::-webkit-scrollbar-track, ol::-webkit-scrollbar-thumb { border-radius: 0 !important; }
    .sidebar { background: #0b111a; }
    .topbar { min-height: 56px; padding-top: 12px; padding-bottom: 12px; }
    .conversation-item.active, .auth-tab.active, .composer-toggle.active { box-shadow: inset 2px 0 0 var(--accent); }
    .message-markdown code, .message-markdown pre, .usage-live { border-radius: 0 !important; }
    .message-markdown ol, .message-markdown ul { padding-left: 1.35rem; }
    .message-markdown ol li::marker, .message-markdown ul li::marker { color: #c9d6ee; font-weight: 700; }
    .message-markdown .katex-display { margin: 0.9rem 0; padding: 0.85rem 1rem; background: #0a0f18; border: 1px solid rgba(255,255,255,0.08); overflow-x: auto; }
    .message-markdown .katex { color: #eef3ff; font-size: 1.02em; }
    @media (max-width: 920px) {
      html, body { height:auto; overflow-x:hidden; }
      body.sidebar-open { overflow:hidden; }
      .shell { padding: 0; }
      .auth-wrap, .app { grid-template-columns: 1fr; }
      .app { min-height: 100vh; height: 100vh; position: relative; }
      .hero { display:none; }
      .auth-wrap { width:100%; min-height:100vh; border:0; background:#0b111a; }
      .auth-panel { width:100%; min-height:100vh; padding-top:24px; padding-bottom:24px; position:relative; z-index:2; }
      .auth-tabs { position:relative; z-index:3; isolation:isolate; }
      .auth-tab { pointer-events:auto; touch-action:manipulation; -webkit-tap-highlight-color: transparent; }
      .auth-form { position:relative; z-index:2; }
      .mobile-sidebar-toggle { display:none; }
      .sidebar, .sidebar-mobile-backdrop { display:none !important; }
      .main-panel { min-width:0; width:100%; }
      .topbar, .chat-scroll, .auth-panel { padding-left: 16px; padding-right: 16px; }
      .topbar { gap:12px; align-items:center; padding-top:12px; padding-bottom:12px; }
      .topbar-main { width:100%; min-width:0; }
      .topbar-icon { width:40px; height:40px; flex-shrink:0; }
      .topbar-title { min-width:0; }
      .topbar-title h2 { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:1rem; }
      .topbar-title p { display:none; }
      .topbar-actions { width:auto; margin-left:auto; flex:0 0 auto; }
      .topbar-status,
      .topbar-actions > form,
      .topbar-actions > a:not(.user-chip),
      .topbar-actions .ghost-btn,
      .topbar-actions .danger-btn { display:none !important; }
      .topbar-actions .upgrade-topbar-btn { display:inline-flex !important; align-items:center; justify-content:center; }
      .topbar-actions .notifications-menu { display:block !important; }
      .topbar-actions .notification-bell { display:inline-grid !important; }
      .notification-bell .notification-count { display:grid !important; }
      .user-chip { min-height:40px; padding:8px 12px; }
      .user-chip small { display:none; }
      .chat-scroll { padding-top:16px; padding-bottom:108px; }
      .empty-shell { min-height: 280px; }
      .empty-card { padding: 22px 18px; }
      .empty-card h3 { font-size: clamp(1.7rem, 9vw, 2.35rem); }
      .suggestions-grid { grid-template-columns: 1fr; }
      .message-card { width:100%; }
      .bubble { padding: 16px 14px 14px; }
      .message-actions { flex-direction:column; align-items:flex-start; }
      .message-tools { width:100%; }
      .message-tools > * { flex:1 1 auto; justify-content:center; }
      .composer-wrap { padding: 0; }
      .composer-top { flex-direction: column; align-items: stretch; }
      .composer-action { width: 100%; }
      .composer-action-spacer { display: none; }
      .send-button { width: 100%; height: 46px; min-width: 0; }
      .send-button span, .danger-btn span { display: inline; }
      .ghost-btn span { display: none; }
      .composer-footer { align-items:flex-start; }
      .message-input-shell { align-items: center; }
      .message-input-shell.has-images textarea { padding-left: 2px !important; }
      .image-preview-strip { max-width: min(150px, 42%); }
      .composer-image-preview { width: 28px; height: 28px; }
      .composer-left, .composer-right { width:100%; }
      .composer-right { justify-content:space-between; }
    }
    @media (max-width: 720px) {
      .app { min-height: 100vh; }
      .topbar-actions form, .topbar-actions a, .topbar-actions .dropdown { width: auto; max-width: 100%; }
      .ghost-btn, .danger-btn, .copy-btn, .regen-btn { min-height: 40px; }
      .topbar, .message-head { flex-direction: column; align-items: flex-start; }
      .topbar-actions { gap:8px; }
      .usage-live { width:100%; justify-content:center; }
      .composer-note { letter-spacing:0.03em; }
    }

    .app-modal-backdrop { position:fixed; inset:0; background:rgba(3,7,16,.72); backdrop-filter:blur(6px); z-index:1200; display:none; align-items:center; justify-content:center; padding:18px; }
    .app-modal-backdrop.is-open { display:flex; }
    body.modal-open { overflow:hidden; }
    .app-modal { width:min(520px,100%); background:#0b1220; border:1px solid rgba(255,255,255,.08); box-shadow:0 30px 80px rgba(0,0,0,.45); }
    .app-modal-head, .app-modal-body, .app-modal-actions { padding:18px 20px; }
    .app-modal-head { border-bottom:1px solid rgba(255,255,255,.08); display:flex; justify-content:space-between; gap:16px; align-items:center; }
    .app-modal-head h3 { margin:0; font-size:1.05rem; }
    .app-modal-close { background:transparent; border:0; color:var(--muted); font-size:1.2rem; }
    .app-modal-body { color:var(--muted); line-height:1.65; }
    .app-modal-input { display:none; width:100%; margin-top:14px; }
    .app-modal-actions { border-top:1px solid rgba(255,255,255,.08); display:flex; justify-content:flex-end; gap:10px; }


    @media (max-width: 920px) {
      body.is-guest { overflow:auto; }
      body.is-guest .shell { min-height:100vh; padding:0; overflow:auto; }
      body.is-guest .auth-wrap { min-height:100vh; display:grid; grid-template-columns:1fr; }
      body.is-guest .hero { display:flex; min-height:auto; padding:28px 18px 24px; border-right:0; border-bottom:1px solid rgba(255,255,255,0.08); }
      body.is-guest .guest-nav { align-items:flex-start; }
      body.is-guest .guest-top-pills { display:none; }
      body.is-guest .hero h1 { font-size:clamp(3rem, 16vw, 4.6rem); max-width:9ch; }
      body.is-guest .hero p.hero-lede { font-size:1rem; margin-top:16px; }
      body.is-guest .guest-actions { margin-top:20px; }
      body.is-guest .guest-dashboard { grid-template-columns:1fr; }
      body.is-guest .guest-terminal { grid-column:span 1; }
      body.is-guest .guest-metric { min-height:auto; padding:14px; }
      body.is-guest .auth-panel { min-height:auto; padding:28px 18px 34px; }
      body.is-guest .auth-card-eyebrow { margin-bottom:16px; }
    }

    /* Composer alignment override: input, upload and send controls share one height and gap. */
    .composer { padding: 8px 16px !important; }
    .composer-top { display: flex !important; flex-direction: row !important; align-items: center !important; gap: 8px !important; width: 100% !important; }
    .textarea-wrap { flex: 1 1 auto !important; min-width: 0 !important; }
    .composer-action { display: flex !important; flex-direction: row !important; align-items: center !important; width: auto !important; flex: 0 0 auto !important; }
    .composer-action-spacer, .composer-label { position: absolute !important; width: 1px !important; height: 1px !important; overflow: hidden !important; clip: rect(0, 0, 0, 0) !important; white-space: nowrap !important; margin: 0 !important; padding: 0 !important; border: 0 !important; }
    .message-input-shell { height: 44px !important; min-height: 44px !important; display: flex !important; align-items: center !important; gap: 8px !important; padding: 5px 10px !important; }
    .message-input-shell textarea, .composer textarea { height: 32px !important; min-height: 32px !important; max-height: 96px !important; padding: 5px 0 !important; line-height: 1.35 !important; }
    .message-input-shell.has-images textarea { padding-left: 0 !important; }
    .image-preview-strip { gap: 6px !important; }
    .composer-image-preview { width: 32px !important; height: 32px !important; border-radius: 0 !important; }
    .image-upload-btn, .send-button { width: 44px !important; height: 44px !important; min-width: 44px !important; max-width: 44px !important; padding: 0 !important; border-radius: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 0 !important; align-self: center !important; line-height: 1 !important; }
    .send-button span { display: none !important; }
    @media (max-width: 720px) {
      .composer { padding: 8px !important; }
      .composer-top { flex-direction: row !important; align-items: center !important; gap: 8px !important; }
      .composer-action { width: auto !important; }
      .image-upload-btn, .send-button { width: 44px !important; height: 44px !important; min-width: 44px !important; }
      .send-button span { display: none !important; }
    }
  </style>

  <link rel="stylesheet" href="/rook.css?v=mobile-typography-2">
  <style>
    body.rook-share .share-readonly-item { cursor: default; text-decoration: none; }
    body.rook-share .share-readonly-item:hover { transform: none; color: var(--text); }
    body.rook-share .new-chat-btn[disabled],
    body.rook-share .ghost-btn[disabled],
    body.rook-share .send-button[disabled],
    body.rook-share .image-upload-btn.is-disabled { opacity: .62; cursor: not-allowed; pointer-events: none; }
    body.rook-share .composer textarea[disabled] { opacity: 1; color: var(--muted); cursor: not-allowed; }
    body.rook-share .conversation-item { pointer-events: none; }
    body.rook-share .topbar-actions .ghost-btn { pointer-events: none; }
    body.rook-share .message-image-thumb { cursor: zoom-in; }
  </style>
</head>
<body class="rook-body rook-app rook-share is-authenticated">
<div class="shell container-fluid">
  <div class="app">
  <aside class="sidebar" id="chatSidebar">
    <div class="sidebar-top">
      <div class="brand">
        <div class="brand-mark"><i class="fa-solid fa-chess-rook"></i></div>
        <div><h1><?= e(APP_NAME) ?></h1><p><?= e($appTagline) ?></p></div>
      </div>
      <div class="workspace-switcher">
        <div>
          <div class="workspace-label">Workspace</div>
          <div style="font-weight:800; letter-spacing:-0.03em;">Shared snapshot</div>
        </div>
        <span class="status-badge"><i class="fa-solid fa-lock"></i> Read-only</span>
      </div>
    </div>
    <div class="sidebar-body">

      <div class="conversation-list">
        <?php if ($conversation): ?>
          <div class="conversation-item active share-readonly-item" aria-current="page">
            <strong><?= e((string) $conversation['title']) ?></strong>
            <span><i class="fa-solid fa-share-nodes"></i> Shared by <?= e((string) $conversation['username']) ?></span>
            <div class="conversation-meta">
              <span><i class="fa-regular fa-clock"></i> <?= e(date('d M · H:i', strtotime((string) $conversation['updated_at']))) ?></span>
              <span>Open</span>
            </div>
          </div>
        <?php else: ?>
          <div class="conversation-empty">Shared conversation unavailable.</div>
        <?php endif; ?>
      </div>

      <div class="sidebar-section-label mt-auto">Shared mode</div>
      <div class="notice info" style="margin:0;">
        <strong>Read-only snapshot</strong><br>
        This page uses the chat layout without sidebar links or editing controls.
      </div>
    </div>
  </aside>
  <button type="button" class="sidebar-mobile-backdrop" id="sidebarMobileBackdrop" aria-label="Close conversations"></button>

  <main class="main-panel">
    <header class="topbar">
      <div class="topbar-main">
        <button type="button" class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Open conversations" aria-controls="chatSidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-icon"><i class="fa-solid fa-comments"></i></div>
        <div class="topbar-title">
          <h2><?= $conversation ? e((string) $conversation['title']) : 'Shared conversation' ?></h2>
          <p><?= $conversation ? 'Read-only snapshot from ' . e((string) $conversation['username']) . '.' : 'This shared conversation is unavailable.' ?></p>
        </div>
      </div>
      <div class="topbar-actions">
        <span class="ghost-btn" aria-label="Read-only shared conversation"><i class="fa-solid fa-lock"></i> <span>Read-only</span></span>
      </div>
    </header>

    <section id="chatLog" class="chat-scroll">
      <?php if (!$conversation): ?>
        <div class="empty-shell">
          <div class="empty-card">
            <h3>Shared conversation unavailable</h3>
            <p>This snapshot may have been revoked, or the share link may be incorrect.</p>
          </div>
        </div>
      <?php elseif ($messages === []): ?>
        <div class="empty-shell">
          <div class="empty-card">
            <h3>No messages in this snapshot</h3>
            <p>The conversation exists, but there are no visible messages to show.</p>
          </div>
        </div>
      <?php endif; ?>

      <div id="messagesWrap" class="messages-wrap">
        <?php foreach ($messages as $index => $msg): ?>
          <?php
            $role = (string) ($msg['role'] ?? 'assistant');
            if ($role === 'thinking') { continue; }
            $isUserMessage = $role === 'user';
            $previousThinking = '';
            if (!$isUserMessage && $index > 0) {
                $prev = $messages[$index - 1] ?? null;
                if (is_array($prev) && (($prev['role'] ?? '') === 'thinking')) {
                    $previousThinking = trim((string) ($prev['content'] ?? ''));
                }
            }
            $messageId = (int) ($msg['id'] ?? $index);
            $authorName = $isUserMessage ? (string) (($msg['author_username'] ?? '') ?: ($conversation['username'] ?? 'User')) : 'Rook';
            $messageTimeRaw = (string) ($msg['created_at'] ?? '');
            $messageTimestamp = $messageTimeRaw !== '' ? strtotime($messageTimeRaw) : false;
            $messageTime = $messageTimestamp ? date('H:i', $messageTimestamp) : '';
          ?>
          <div class="message-row <?= $isUserMessage ? 'user' : 'assistant' ?>" data-message-role="<?= e($role) ?>" data-message-id="<?= $messageId ?>">
            <div class="message-card">
              <div class="message-head">
                <div class="message-meta">
                  <span class="meta-pill"><i class="fa-solid <?= $isUserMessage ? 'fa-user' : 'fa-chess-rook' ?>"></i> <?= e($authorName) ?></span>
                  <?php if ($messageTime !== ''): ?><span><?= e($messageTime) ?></span><?php endif; ?>
                </div>

              </div>
              <div class="bubble">
                <?php if ($previousThinking !== ''): ?>
                  <div class="thinking-summary" style="display:block;">
                    <div class="accordion thinking-accordion thinking-accordion-share" aria-label="Thinking summary hidden in shared view">
                      <div class="accordion-item">
                        <h2 class="accordion-header">
                          <button class="accordion-button collapsed" type="button" aria-expanded="false">
                            <i class="fa-solid fa-brain"></i>&nbsp;<span>Thought for a moment</span>
                          </button>
                        </h2>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
                <?php $messageImages = $isUserMessage ? decode_message_images((string) ($msg['images_json'] ?? '')) : []; ?>
                <?php if ($messageImages !== []): ?>
                  <div class="message-images">
                    <?php foreach ($messageImages as $image): ?>
                      <img class="message-image-thumb js-chat-image" src="data:<?= e((string) $image['mime']) ?>;base64,<?= e((string) $image['data']) ?>" alt="<?= e((string) ($image['name'] ?? 'Uploaded image')) ?>" loading="lazy" role="button" tabindex="0">
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <div class="message-markdown js-render-markdown"><?= e((string) ($msg['content'] ?? '')) ?></div>
                <div class="message-actions">
                  <span class="message-status"><?= $isUserMessage ? '' : 'Ready' ?></span>
                  <div class="message-tools">
                    <button type="button" class="copy-btn js-copy-message"><i class="fa-regular fa-copy"></i> Copy</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($conversation): ?>
    <?php endif; ?>
  </main>
  </div>
</div>

<div class="modal fade" id="shareImageModal" tabindex="-1" aria-labelledby="shareImageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="background:#090f1d;border:1px solid rgba(255,255,255,.12);">
      <div class="modal-header" style="border-color:rgba(255,255,255,.1);">
        <h5 class="modal-title" id="shareImageModalLabel">Image preview</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="display:grid;place-items:center;">
        <img id="shareImageModalImg" src="" alt="Image preview" style="max-width:100%;max-height:75vh;border-radius:0;">
      </div>
    </div>
  </div>
</div>

<script>
  window.addEventListener('DOMContentLoaded', () => {
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebarMobileBackdrop = document.getElementById('sidebarMobileBackdrop');
    const setMobileSidebar = (open) => {
      document.body.classList.toggle('sidebar-open', Boolean(open));
      if (mobileSidebarToggle) mobileSidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    if (mobileSidebarToggle) mobileSidebarToggle.addEventListener('click', () => setMobileSidebar(!document.body.classList.contains('sidebar-open')));
    if (sidebarMobileBackdrop) sidebarMobileBackdrop.addEventListener('click', () => setMobileSidebar(false));

    if (window.marked) {
      marked.setOptions({ breaks: true, gfm: true });
    }

    document.querySelectorAll('.js-render-markdown').forEach((node) => {
      const raw = node.textContent || '';
      node.innerHTML = window.marked ? marked.parse(raw) : raw.replace(/\n/g, '<br>');
      if (window.hljs) {
        node.querySelectorAll('pre code').forEach((block) => window.hljs.highlightElement(block));
      }
      if (window.renderMathInElement) {
        renderMathInElement(node, {
          delimiters: [
            {left: '$$', right: '$$', display: true},
            {left: '\\[', right: '\\]', display: true},
            {left: '$', right: '$', display: false},
            {left: '\\(', right: '\\)', display: false}
          ],
          throwOnError: false
        });
      }
    });

    document.querySelectorAll('.js-copy-message').forEach((button) => {
      button.addEventListener('click', async () => {
        const card = button.closest('.message-card');
        const markdown = card ? card.querySelector('.message-markdown') : null;
        const text = markdown ? markdown.innerText.trim() : '';
        if (!text) return;
        try {
          await navigator.clipboard.writeText(text);
          const original = button.innerHTML;
          button.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
          setTimeout(() => { button.innerHTML = original; }, 1400);
        } catch (error) {
          button.innerHTML = '<i class="fa-solid fa-check"></i> Select text to copy';
        }
      });
    });

    const imageModalElement = document.getElementById('shareImageModal');
    const imageModalImg = document.getElementById('shareImageModalImg');
    const imageModal = imageModalElement && window.bootstrap ? new bootstrap.Modal(imageModalElement) : null;
    document.querySelectorAll('.js-chat-image').forEach((image) => {
      const openImage = () => {
        if (!imageModal || !imageModalImg) return;
        imageModalImg.src = image.src;
        imageModalImg.alt = image.alt || 'Image preview';
        imageModal.show();
      };
      image.addEventListener('click', openImage);
      image.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openImage();
        }
      });
    });
  });
</script>
</body>
</html>
