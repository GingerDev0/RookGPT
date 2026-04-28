<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Privacy Policy | RookGPT</title>
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
      <a class="sidebar-link " href="/terms"><i class="fa-solid fa-scale-balanced"></i> Terms</a>
      <a class="sidebar-link active" href="/privacy"><i class="fa-solid fa-lock"></i> Privacy</a>
    </div>
  </aside>
  <main class="main-panel">
    <header class="topbar"><div class="topbar-main"><div class="topbar-icon"><i class="fa-solid fa-lock"></i></div><div class="topbar-title"><h2>Privacy Policy</h2><p>Your workspace should feel private.</p></div></div><div class="topbar-actions"><a class="ghost-btn" href="/"><i class="fa-solid fa-arrow-left"></i> Back to chat</a></div></header>
    <div class="page-content"><main class="container-xl py-5">
    <div class="legal-card p-4 p-lg-5">
      <p class="text-uppercase fw-bold" style="color:var(--accent);letter-spacing:.12em;">Privacy Policy</p>
      <h1 class="display-4 mb-3">Your workspace should feel private.</h1>
      <p>This policy explains the kind of information RookGPT may use to run your account and keep your conversations available.</p>
      <hr class="border-secondary my-4">
      <h2>Information you provide</h2>
      <p>RookGPT may store account details, conversations, shared snapshots, team membership, plan details, and app connection activity needed to provide the service.</p>
      <h2>How it is used</h2>
      <p>Information is used to sign you in, save your work, apply plan limits, power team features, and show account or usage information.</p>
      <h2>Shared conversations</h2>
      <p>If you turn on sharing or team sharing, people with access may be able to view the shared conversation. Only share content you are comfortable making visible.</p>
      <h2>App connections</h2>
      <p>API keys and usage records help you connect RookGPT to your own tools and understand how those connections are being used.</p>
      <h2>Keeping data safe</h2>
      <p>Use strong passwords, rotate app keys when needed, and remove access for people who no longer need it.</p>
      <h2>Questions</h2>
      <p>For privacy questions, contact the site owner or support channel listed by your RookGPT deployment.</p>
      <p class="mb-0"><small>Last updated: <?= date('j F Y') ?></small></p>
    </div>
    </main></div>
  </main>
</div>
</body>
</html>
