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

$shareToken = trim((string) ($_GET['s'] ?? ''));
$conversation = null;
$messages = [];

if ($shareToken !== '') {
    $conversation = fetch_one(
        'SELECT c.id, c.title, c.created_at, c.updated_at, u.username FROM conversations c JOIN users u ON u.id = c.user_id WHERE c.share_token = ? LIMIT 1',
        's',
        [$shareToken]
    );

    if ($conversation) {
        $messages = fetch_all(
            'SELECT role, content, created_at FROM messages WHERE conversation_id = ? ORDER BY id ASC',
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/highlight.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/styles/github-dark.min.css">
  <style>
    body {
      margin: 0;
      font-family: 'Inter', system-ui, sans-serif;
      background: linear-gradient(180deg, #0a1020 0%, #0d1528 100%);
      color: #f3f7ff;
    }
    .wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: 32px 20px 48px;
    }
    .hero {
      border: 1px solid rgba(255,255,255,0.08);
      background: rgba(14, 21, 38, 0.88);
      border-radius: 24px;
      padding: 24px;
      margin-bottom: 22px;
    }
    .hero h1 {
      margin: 0;
      font-size: 1.8rem;
      letter-spacing: -0.04em;
    }
    .hero p {
      margin: 10px 0 0;
      color: #9fb0cf;
      line-height: 1.65;
    }
    .message-list {
      display: grid;
      gap: 16px;
    }
    .message {
      border: 1px solid rgba(255,255,255,0.08);
      background: rgba(18, 27, 46, 0.94);
      border-radius: 22px;
      padding: 18px 20px;
    }
    .message.user {
      background: linear-gradient(135deg, rgba(57, 89, 180, 0.94), rgba(65, 110, 240, 0.96));
    }
    .meta {
      color: #9fb0cf;
      font-size: 0.8rem;
      margin-bottom: 10px;
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }
    .pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 5px 10px;
      border-radius: 999px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.08);
      color: #f3f7ff;
      font-weight: 700;
      font-size: 0.78rem;
    }
    .content { line-height: 1.75; }
    .content pre {
      background: #09101d;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 18px;
      padding: 1rem;
      overflow-x: auto;
      margin: 0.9rem 0;
    }
    .content code {
      background: rgba(255,255,255,0.08);
      border-radius: 0.45rem;
      padding: 0.16rem 0.42rem;
    }
    .content pre code { background: transparent; padding: 0; }
    .thinking-summary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 10px;
      padding: 8px 12px;
      border-radius: 999px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.08);
      color: #9fb0cf;
      font-size: 0.82rem;
    }
    .empty {
      border: 1px solid rgba(255,255,255,0.08);
      background: rgba(14, 21, 38, 0.88);
      border-radius: 24px;
      padding: 24px;
      color: #9fb0cf;
    }
  </style>
  <link rel="stylesheet" href="/rook.css">
</head>
<body class="rook-body rook-app rook-share is-authenticated">
<div class="app">
  <aside class="sidebar">
    <div class="sidebar-top"><div class="brand"><span class="brand-mark"><i class="fa-solid fa-chess-rook"></i></span><span><h1>RookGPT</h1><p>Shared snapshot</p></span></div><div class="workspace-label">Workspace</div></div>
    <div class="sidebar-body">
      <div class="page-panel p-3 mt-auto"><div class="muted small">Mode</div><strong>Read-only</strong></div>
    </div>
  </aside>
  <main class="main-panel">
    <header class="topbar"><div class="topbar-main"><div class="topbar-icon"><i class="fa-solid fa-share-nodes"></i></div><div class="topbar-title"><h2>Shared conversation</h2><p>Read-only snapshot from RookGPT.</p></div></div><div class="topbar-actions"><a class="ghost-btn" href="/"><i class="fa-solid fa-arrow-left"></i> Back to chat</a></div></header>
    <div class="page-content"><div class="wrap container-fluid">
    <?php if (!$conversation): ?>
      <div class="empty">This shared conversation is unavailable.</div>
    <?php else: ?>
      <div class="hero">
        <h1><?= e((string) $conversation['title']) ?></h1>
        <p>Shared read-only snapshot from <?= e((string) $conversation['username']) ?>. Input is disabled on purpose.</p>
      </div>

      <div class="message-list">
        <?php foreach ($messages as $index => $msg): ?>
          <?php
            $role = (string) ($msg['role'] ?? 'assistant');
            if ($role === 'thinking') {
                continue;
            }
            $isUser = $role === 'user';
            $previousThinking = '';
            if (!$isUser && $index > 0) {
                $prev = $messages[$index - 1] ?? null;
                if (is_array($prev) && (($prev['role'] ?? '') === 'thinking')) {
                    $previousThinking = trim((string) ($prev['content'] ?? ''));
                }
            }
          ?>
          <div class="message <?= $isUser ? 'user' : 'assistant' ?>">
            <div class="meta">
              <span class="pill"><?= $isUser ? 'User' : 'RookGPT' ?></span>
              <span><?= e(date('H:i', strtotime((string) $msg['created_at']))) ?></span>
            </div>
            <?php if ($previousThinking !== ''): ?>
              <div class="thinking-summary">Thought for a moment</div>
            <?php endif; ?>
            <div class="content js-render-markdown"><?= e((string) ($msg['content'] ?? '')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    </div></div>
  </main>
</div>

  <script>
    if (window.marked) {
      marked.setOptions({ breaks: true, gfm: true });
    }

    document.querySelectorAll('.js-render-markdown').forEach((node) => {
      const raw = node.textContent || '';
      node.innerHTML = window.marked ? marked.parse(raw) : raw.replace(/\n/g, '<br>');
      if (window.hljs) {
        node.querySelectorAll('pre code').forEach((block) => window.hljs.highlightElement(block));
      }
    });
  </script>
</body>
</html>
