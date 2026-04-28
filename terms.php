<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Terms of Service | RookGPT</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    :root { --bg:#070b14; --panel:rgba(255,255,255,.055); --line:rgba(255,255,255,.1); --text:#f7f9ff; --muted:#aab8d5; --accent:#7c9cff; }
    body { min-height:100vh; background:radial-gradient(circle at 12% 0%, rgba(124,156,255,.2), transparent 30%), linear-gradient(180deg,#08101f,#070b14); color:var(--text); font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif; }
    .legal-nav { border-bottom:1px solid var(--line); background:rgba(6,11,22,.78); backdrop-filter:blur(18px); }
    .brand-mark { width:42px; height:42px; display:grid; place-items:center; background:linear-gradient(135deg,rgba(124,156,255,.25),rgba(56,211,159,.14)); border:1px solid var(--line); color:#fff; }
    .legal-card { border:1px solid var(--line); background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.035)); box-shadow:0 24px 70px rgba(0,0,0,.24); }
    h1,h2 { font-weight:950; letter-spacing:-.05em; }
    p, li { color:var(--muted); line-height:1.75; }
    a { color:#c8d4ff; }
    .btn { border-radius:0; font-weight:900; }
  </style>
  <link rel="stylesheet" href="/rook.css">
</head>
<body class="rook-body rook-app is-authenticated">
<div class="app">
  <aside class="sidebar">
    <div class="sidebar-top"><a class="brand" href="/"><span class="brand-mark"><i class="fa-solid fa-chess-rook"></i></span><span><h1>RookGPT</h1><p>Legal</p></span></a><div class="workspace-label">Workspace</div></div>
    <div class="sidebar-body">
      <a class="sidebar-link" href="/"><i class="fa-solid fa-comments"></i> Chat workspace</a>
      <a class="sidebar-link" href="/api/"><i class="fa-solid fa-key"></i> API keys</a>
      <a class="sidebar-link" href="/teams/"><i class="fa-solid fa-users"></i> Teams</a>
      <a class="sidebar-link" href="/upgrade"><i class="fa-solid fa-arrow-up-right-dots"></i> Upgrade</a>
      <a class="sidebar-link active" href="/terms"><i class="fa-solid fa-scale-balanced"></i> Terms</a>
      <a class="sidebar-link " href="/privacy"><i class="fa-solid fa-lock"></i> Privacy</a>
    </div>
  </aside>
  <main class="main-panel">
    <header class="topbar"><div class="topbar-main"><div class="topbar-icon"><i class="fa-solid fa-scale-balanced"></i></div><div class="topbar-title"><h2>Terms of Service</h2><p>Fair use, clear accounts, no nonsense.</p></div></div><div class="topbar-actions"><a class="ghost-btn" href="/"><i class="fa-solid fa-arrow-left"></i> Back to chat</a></div></header>
    <div class="page-content"><main class="container-xl py-5">
    <div class="legal-card p-4 p-lg-5">
      <p class="text-uppercase fw-bold" style="color:var(--accent);letter-spacing:.12em;">Terms of Service</p>
      <h1 class="display-4 mb-3">Fair use, clear accounts, no nonsense.</h1>
      <p>These terms explain the basic rules for using RookGPT. They are written to be readable, but they are not a substitute for formal legal advice.</p>
      <hr class="border-secondary my-4">
      <h2>Using RookGPT</h2>
      <p>Use the service responsibly. Do not use it to harm others, break the law, attack the platform, or abuse shared features.</p>
      <h2>Your account</h2>
      <p>You are responsible for keeping your login details safe and for activity on your account. Plan limits and features may change as the product improves.</p>
      <h2>Paid plans</h2>
      <p>Paid plans unlock more space, sharing, team features, thinking controls, and app connections. Billing terms should be shown clearly before purchase.</p>
      <h2>Content</h2>
      <p>Your prompts, replies, and shared conversations remain your responsibility. Check important answers before relying on them.</p>
      <h2>Availability</h2>
      <p>RookGPT is built to be useful, but no service is perfect. Access may be interrupted for maintenance, upgrades, or unexpected issues.</p>
      <h2>Contact</h2>
      <p>For questions about these terms, contact the site owner or support channel listed by your RookGPT deployment.</p>
      <p class="mb-0"><small>Last updated: <?= date('j F Y') ?></small></p>
    </div>
    </main></div>
  </main>
</div>
</body>
</html>
