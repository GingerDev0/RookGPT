<?php
require_once __DIR__ . '/_bootstrap.php';

render_team_header('chat', 'Team chat', 'Real-time team messages with usernames, dates, and timestamps.');

$canReadChat = false;
$canSendChat = false;
$canManageChat = false;
$teamChatMessages = [];
$lastTeamChatId = 0;
$teamMentionUsers = [];

if ($activeTeam) {
    $canReadChat = can_read_team_chat($activeTeam, $activeMembership, $user);
    $canSendChat = can_send_team_chat($activeTeam, $activeMembership, $user);
    $canManageChat = $isTeamOwner;
    $teamChatMessages = $canReadChat ? fetch_recent_team_chat_messages((int) $activeTeam['id'], 50) : [];
    if ($canReadChat) {
        $teamMentionUsers = array_values(array_filter(fetch_team_members((int) $activeTeam['id']), static function (array $member) use ($user): bool {
            return (int) ($member['user_id'] ?? 0) !== (int) ($user['id'] ?? 0);
        }));
    }
    $lastTeamChatId = $teamChatMessages ? (int) end($teamChatMessages)['id'] : 0;
    reset($teamChatMessages);
}
?>
<style id="team-chat-index-exact-style">
  body.rook-app { overflow: hidden !important; }
  body.rook-app .app { height: 100vh !important; overflow: hidden !important; }
  body.rook-app .main-panel { height: 100vh !important; min-height: 0 !important; overflow: hidden !important; display: flex !important; flex-direction: column !important; }
  body.rook-app .page-content { flex: 1 1 auto !important; min-height: 0 !important; overflow: hidden !important; display: flex !important; flex-direction: column !important; padding-bottom: 0 !important; }
  body.rook-app .team-subnav { flex: 0 0 auto !important; margin: 0 0 16px !important; }

  html, body, div, section, aside, main, nav, form, textarea, pre, code, ul, ol {
    scrollbar-width: thin;
    scrollbar-color: rgba(124, 156, 255, 0.38) rgba(255,255,255,0.03);
  }
  html::-webkit-scrollbar, body::-webkit-scrollbar, div::-webkit-scrollbar, section::-webkit-scrollbar, aside::-webkit-scrollbar, main::-webkit-scrollbar, nav::-webkit-scrollbar, form::-webkit-scrollbar, textarea::-webkit-scrollbar, pre::-webkit-scrollbar, code::-webkit-scrollbar, ul::-webkit-scrollbar, ol::-webkit-scrollbar { width: 10px; height: 10px; }
  html::-webkit-scrollbar-track, body::-webkit-scrollbar-track, div::-webkit-scrollbar-track, section::-webkit-scrollbar-track, aside::-webkit-scrollbar-track, main::-webkit-scrollbar-track, nav::-webkit-scrollbar-track, form::-webkit-scrollbar-track, textarea::-webkit-scrollbar-track, pre::-webkit-scrollbar-track, code::-webkit-scrollbar-track, ul::-webkit-scrollbar-track, ol::-webkit-scrollbar-track { background: rgba(255,255,255,0.03); border-radius: 999px; }
  html::-webkit-scrollbar-thumb, body::-webkit-scrollbar-thumb, div::-webkit-scrollbar-thumb, section::-webkit-scrollbar-thumb, aside::-webkit-scrollbar-thumb, main::-webkit-scrollbar-thumb, nav::-webkit-scrollbar-thumb, form::-webkit-scrollbar-thumb, textarea::-webkit-scrollbar-thumb, pre::-webkit-scrollbar-thumb, code::-webkit-scrollbar-thumb, ul::-webkit-scrollbar-thumb, ol::-webkit-scrollbar-thumb { background: linear-gradient(180deg, rgba(124, 156, 255, 0.42), rgba(139, 92, 246, 0.34)); border-radius: 999px; border: 2px solid rgba(11, 16, 32, 0.55); background-clip: padding-box; }
  html::-webkit-scrollbar-thumb:hover, body::-webkit-scrollbar-thumb:hover, div::-webkit-scrollbar-thumb:hover, section::-webkit-scrollbar-thumb:hover, aside::-webkit-scrollbar-thumb:hover, main::-webkit-scrollbar-thumb:hover, nav::-webkit-scrollbar-thumb:hover, form::-webkit-scrollbar-thumb:hover, textarea::-webkit-scrollbar-thumb:hover, pre::-webkit-scrollbar-thumb:hover, code::-webkit-scrollbar-thumb:hover, ul::-webkit-scrollbar-thumb:hover, ol::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, rgba(124, 156, 255, 0.58), rgba(139, 92, 246, 0.46)); border: 2px solid rgba(11, 16, 32, 0.55); background-clip: padding-box; }
  html::-webkit-scrollbar-corner, body::-webkit-scrollbar-corner, div::-webkit-scrollbar-corner, section::-webkit-scrollbar-corner, aside::-webkit-scrollbar-corner, main::-webkit-scrollbar-corner, nav::-webkit-scrollbar-corner, form::-webkit-scrollbar-corner, textarea::-webkit-scrollbar-corner, pre::-webkit-scrollbar-corner, code::-webkit-scrollbar-corner, ul::-webkit-scrollbar-corner, ol::-webkit-scrollbar-corner { background: transparent; }

  .team-chat-index { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; margin: 0 -18px -18px; }
  .team-chat-index .chat-scroll { flex: 1; min-height: 0; overflow: auto; padding: 20px 24px 112px; display: flex; flex-direction: column; gap: 16px; scroll-behavior: smooth; }
  .team-chat-index .empty-shell { flex: 1; display: grid; place-items: center; min-height: 420px; }
  .team-chat-index .empty-card { width: min(860px, 100%); padding: 30px; border-radius: var(--radius-xl, 0); background: linear-gradient(180deg, rgba(14, 21, 38, 0.95), rgba(10, 16, 28, 0.94)); border: 1px solid var(--line-strong, rgba(255,255,255,0.14)); box-shadow: var(--shadow-xl, 0 30px 80px rgba(0,0,0,0.46)); }
  .team-chat-index .empty-card h3 { margin: 0; font-size: clamp(2.1rem, 4vw, 3rem); line-height: 0.98; letter-spacing: -0.05em; }
  .team-chat-index .empty-card p { margin: 16px 0 0; color: var(--muted); line-height: 1.7; max-width: 62ch; }
  .team-chat-index .messages-wrap { display: flex; flex-direction: column; gap: 22px; }
  .team-chat-index .message-row { display: flex; justify-content: center; width: 100%; }
  .team-chat-index .message-card { width: min(980px, 100%); display: grid; gap: 12px; }
  .team-chat-index .message-row.user .message-card { justify-items: end; }
  .team-chat-index .message-row.assistant .message-card { justify-items: start; }
  .team-chat-index .message-head { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 14px; }
  .team-chat-index .message-row.user .message-head { justify-content: flex-end; }
  .team-chat-index .message-row.assistant .message-head { justify-content: flex-start; }
  .team-chat-index .message-meta { width: 100%; display: inline-flex; align-items: center; gap: 10px; color: var(--muted); font-size: 0.8rem; flex-wrap: wrap; }
  .team-chat-index .message-row.user .message-meta { justify-content: flex-end; text-align: right; }
  .team-chat-index .message-row.assistant .message-meta { justify-content: flex-start; text-align: left; }
  .team-chat-index .meta-pill { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.045); font-weight: 800; font-size: 0.78rem; letter-spacing: 0.02em; }
  .team-chat-index .message-tools { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .team-chat-index .message-actions { display: flex; align-items: center; justify-content: flex-end; gap: 12px; margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.08); position: relative; z-index: 1; }
  .team-chat-index .message-row.user .message-actions { justify-content: flex-end; }
  .team-chat-index .bubble { width: 100%; padding: 18px 20px 16px; border: 1px solid rgba(255,255,255,0.08); box-shadow: var(--shadow-lg, 0 18px 40px rgba(0,0,0,0.28)); background: linear-gradient(180deg, rgba(18, 27, 46, 0.96), rgba(12, 20, 36, 0.98)); position: relative; overflow: hidden; }
  .team-chat-index .bubble::before { content: ''; position: absolute; inset: 0; pointer-events: none; background: linear-gradient(180deg, rgba(255,255,255,0.05), transparent 30%); }
  body.rook-app .team-chat-index .message-row.user .bubble { background: linear-gradient(135deg, rgba(124, 92, 255, .20), rgba(32, 217, 255, .10)); border-color: rgba(124, 92, 255, .18); }
  .team-chat-index .message-row.user .bubble { background: linear-gradient(135deg, rgba(57, 89, 180, 0.94), rgba(65, 110, 240, 0.96)); border-color: rgba(255,255,255,0.14); }
  .team-chat-index .message-row.assistant .bubble { backdrop-filter: blur(14px); }
  .team-chat-index .message-markdown { position: relative; z-index: 1; color: var(--text); font-size: 0.97rem; line-height: 1.75; word-break: break-word; }
  .team-chat-index .message-markdown p { margin: 0 0 0.9rem; }
  .team-chat-index .message-markdown p:last-child { margin-bottom: 0; }
  .team-chat-index .message-markdown ul, .team-chat-index .message-markdown ol { margin: 0.45rem 0 0.9rem 1.25rem; }
  .team-chat-index .message-markdown li + li { margin-top: 0.34rem; }
  .team-chat-index .message-markdown code { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.08); padding: 0.16rem 0.42rem; border-radius: 0.45rem; font-size: 0.9em; }
  .team-chat-index .message-markdown pre { position: relative; background: #09101d; border: 1px solid rgba(255,255,255,0.08); border-radius: 18px; padding: 1rem; overflow-x: auto; margin: 0.9rem 0; }
  .team-chat-index .message-markdown pre code { background: transparent; border: 0; padding: 0; }
  .team-chat-index .message-markdown a { color: #a9c4ff; text-decoration: underline; }
  .team-chat-index .copy-btn, .team-chat-index .code-copy-btn { border: 1px solid rgba(255,255,255,0.1); background: rgba(10,16,32,0.7); color: #d8e3ff; padding: 6px 10px; font-size: 0.74rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.18s ease; }
  .team-chat-index .copy-btn:hover, .team-chat-index .code-copy-btn:hover { background: rgba(26, 36, 60, 0.94); border-color: rgba(255,255,255,0.16); transform: translateY(-1px); }
  .team-chat-index .delete-message-btn { border-color: rgba(255, 94, 94, 0.22); color: #ffc6c6; background: rgba(255, 70, 70, 0.08); }
  .team-chat-index .delete-message-btn:hover { border-color: rgba(255, 94, 94, 0.42); background: rgba(255, 70, 70, 0.16); color: #fff; }
  .team-chat-index .reply-message-btn { border-color: rgba(124, 156, 255, 0.20); color: #dbe7ff; background: rgba(124, 156, 255, 0.08); }
  .team-chat-index .reply-message-btn:hover { border-color: rgba(124, 156, 255, 0.42); background: rgba(124, 156, 255, 0.16); color: #fff; }
  .team-chat-index .reply-quote { position: relative; z-index: 1; margin: 0 0 14px; padding: 12px 14px 12px 16px; border-left: 3px solid rgba(32, 217, 255, 0.58); background: linear-gradient(135deg, rgba(124, 92, 255, 0.13), rgba(32, 217, 255, 0.07)); border-top: 1px solid rgba(255,255,255,0.08); border-right: 1px solid rgba(255,255,255,0.06); border-bottom: 1px solid rgba(255,255,255,0.06); box-shadow: inset 0 1px 0 rgba(255,255,255,0.05); cursor: pointer; transition: transform .16s ease, border-color .16s ease, background .16s ease; }
  .team-chat-index .reply-quote:hover { transform: translateY(-1px); border-left-color: rgba(124, 156, 255, 0.86); background: linear-gradient(135deg, rgba(124, 92, 255, 0.20), rgba(32, 217, 255, 0.11)); }
  .team-chat-index .message-row.is-jump-target .bubble { border-color: rgba(32, 217, 255, 0.68) !important; box-shadow: 0 0 0 3px rgba(32, 217, 255, 0.14), var(--shadow-lg, 0 18px 40px rgba(0,0,0,0.28)); }
  .team-chat-index .reply-quote-label { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; color: #c9d8ff; font-size: 0.77rem; font-weight: 900; letter-spacing: 0.02em; text-transform: uppercase; }
  .team-chat-index .reply-quote-text { color: rgba(234, 242, 255, 0.82); font-size: 0.88rem; line-height: 1.45; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .team-chat-index .team-chat-owner-tools { display: flex; justify-content: flex-end; padding: 0 24px 12px; }
  .team-chat-index .clear-chat-btn { border: 1px solid rgba(255, 94, 94, 0.24); background: rgba(255, 70, 70, 0.09); color: #ffd0d0; padding: 8px 12px; font-size: 0.75rem; font-weight: 900; letter-spacing: 0.04em; text-transform: uppercase; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
  .team-chat-index .clear-chat-btn:hover { background: rgba(255, 70, 70, 0.18); border-color: rgba(255, 94, 94, 0.42); color: #fff; }
  .team-chat-index .code-copy-btn { position: absolute; top: 10px; right: 10px; z-index: 2; }
  .team-chat-index .composer-wrap { position: sticky; bottom: 0; left: 0; right: 0; width: 100%; margin: 0; padding: 0; border-top: 1px solid var(--line); background: rgba(10, 15, 27, 0.98); backdrop-filter: blur(20px); z-index: 8; align-self: stretch; }
  .team-chat-index .composer { display: block; width: 100%; max-width: none; margin: 0; border-radius: 0; border: 0; background: linear-gradient(180deg, rgba(15, 22, 39, 0.98), rgba(10, 16, 28, 0.98)); padding: 6px 16px 7px; box-shadow: none; }
  .team-chat-index .composer-top { display: flex; gap: 6px; align-items: stretch; width: 100%; }
  .team-chat-index .textarea-wrap { flex: 1 1 auto; min-width: 0; width: 100%; }
  .team-chat-index .composer-action { display: flex; flex-direction: column; flex-shrink: 0; }
  .team-chat-index .composer-action-spacer { margin-bottom: 2px; min-height: calc(0.72rem * 1.2 + 0.08em + 2px); visibility: hidden; }
  .team-chat-index .composer-label { margin-bottom: 2px; font-size: 0.68rem; display: block; color: var(--muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
  .team-chat-index .textarea-wrap { position: relative; overflow: visible; }
  .team-chat-index .message-input-shell { min-height: 40px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04); display: flex; align-items: center; padding: 4px 8px; transition: border-color .16s ease, box-shadow .16s ease; position: relative; overflow: visible !important; }
  .team-chat-index .message-input-shell:focus-within { border-color: rgba(124,156,255,.34); box-shadow: 0 0 0 3px rgba(124,156,255,.1); }
  .team-chat-index .composer textarea { width: 100%; min-height: 40px; max-height: 112px; resize: none; line-height: 1.35; font-size: 0.9rem; padding: 8px 2px; border: 0 !important; background: transparent !important; color: var(--text); box-shadow: none !important; outline: 0; }
  .team-chat-index .send-button { height: 40px; min-width: 88px; padding: 0 12px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 0.84rem; align-self: stretch; border: 0; background: linear-gradient(135deg,var(--accent),#b8c9ff); color: #08111f; font-weight: 900; }
  .team-chat-index .send-button:disabled { opacity: 0.7; cursor: not-allowed; transform: none; box-shadow: none; }
  .team-chat-index .composer-footer { margin-top: 4px; display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap; width: 100%; color: var(--muted); font-size: 0.72rem; }
  .team-chat-index .team-chat-status { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); font-size: 0.82rem; }
  .team-chat-index .team-chat-status-dot { width: 8px; height: 8px; border-radius: 50%; background: #38d39f; box-shadow: 0 0 0 4px rgba(56,211,159,.12); }
  .team-chat-index .team-chat-status.is-offline .team-chat-status-dot { background: #ffb86b; box-shadow: 0 0 0 4px rgba(255,184,107,.12); }
  .team-chat-index .mention-picker { position: absolute !important; left: 0 !important; right: 0 !important; bottom: calc(100% + 8px) !important; top: auto !important; z-index: 99999 !important; display: none; width: auto !important; min-width: 220px; max-height: 260px; overflow: auto; border: 1px solid rgba(124, 156, 255, 0.24); background: linear-gradient(180deg, rgba(16, 24, 43, 0.98), rgba(10, 16, 28, 0.98)); box-shadow: 0 22px 60px rgba(0,0,0,0.50); backdrop-filter: blur(18px); padding: 6px; }
  .team-chat-index .mention-picker.is-open { display: grid !important; gap: 4px; }
  .team-chat-index .mention-option { width: 100%; border: 0; background: transparent; color: var(--text); padding: 9px 10px; text-align: left; cursor: pointer; display: flex; align-items: center; gap: 9px; font-weight: 800; font-size: 0.86rem; }
  .team-chat-index .mention-option small { color: var(--muted); font-weight: 700; margin-left: auto; }
  .team-chat-index .mention-option:hover, .team-chat-index .mention-option.is-active { background: linear-gradient(135deg, rgba(124, 92, 255, 0.20), rgba(32, 217, 255, 0.10)); color: #fff; }
  .team-chat-modal-backdrop { position: fixed; inset: 0; z-index: 140000; display: none; align-items: center; justify-content: center; padding: 18px; background: rgba(3, 7, 16, 0.74); backdrop-filter: blur(10px); }
  .team-chat-modal-backdrop.is-open { display: flex; }
  body.team-chat-modal-open { overflow: hidden !important; }
  .team-chat-modal { width: min(460px, 100%); overflow: hidden; border: 1px solid rgba(255,255,255,0.12); background: linear-gradient(180deg, rgba(15, 23, 42, 0.98), rgba(8, 13, 24, 0.98)); box-shadow: 0 28px 90px rgba(0,0,0,0.58); color: var(--text); }
  .team-chat-modal-head { padding: 18px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); }
  .team-chat-modal-title { margin: 0; font-size: 1.02rem; font-weight: 900; letter-spacing: -0.025em; display: inline-flex; align-items: center; gap: 10px; }
  .team-chat-modal-close { width: 34px; height: 34px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04); color: var(--muted); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
  .team-chat-modal-close:hover { color: #fff; background: rgba(255,255,255,0.08); }
  .team-chat-modal-body { padding: 20px; color: var(--muted); line-height: 1.65; }
  .team-chat-modal-message { margin: 0; }
  .team-chat-modal-actions { padding: 16px 20px 18px; display: flex; align-items: center; justify-content: flex-end; gap: 10px; border-top: 1px solid rgba(255,255,255,0.08); }
  .team-chat-modal-cancel, .team-chat-modal-confirm { min-height: 40px; padding: 0 14px; border: 1px solid rgba(255,255,255,0.10); font-weight: 900; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
  .team-chat-modal-cancel { background: rgba(255,255,255,0.045); color: var(--text); }
  .team-chat-modal-cancel:hover { background: rgba(255,255,255,0.08); }
  .team-chat-modal-confirm { background: linear-gradient(135deg, rgba(255, 94, 94, 0.92), rgba(255, 154, 96, 0.88)); color: #140809; border-color: rgba(255,255,255,0.12); }
  .team-chat-modal-confirm:hover { transform: translateY(-1px); filter: brightness(1.04); }
  @media (max-width: 900px) {
    .team-chat-index { margin-left: -12px; margin-right: -12px; }
    .team-chat-index .chat-scroll { padding: 16px 14px 108px; }
    .team-chat-index .composer { padding: 6px 10px 7px; }
    .team-chat-index .send-button { min-width: 48px; }
    .team-chat-index .send-button span { display: none; }
    .team-chat-index .message-card { width: 100%; }
  }
</style>

<?php if (!$activeTeam): ?>
  <div class="panel"><p class="muted mb-0">Create or join a team first.</p></div>
<?php else: ?>
  <div class="team-chat-index" data-team-chat-root data-team-token="<?= e((string) $activeTeam['token']) ?>" data-current-user-id="<?= (int) $user['id'] ?>" data-last-id="<?= $lastTeamChatId ?>" data-can-manage-chat="<?= $canManageChat ? '1' : '0' ?>">
    <section id="chatLog" class="chat-scroll">
      <?php if (!$canReadChat): ?>
        <div class="empty-shell"><div class="empty-card"><h3>Team chat is unavailable.</h3><p>Your role does not currently include permission to read this team chat.</p></div></div>
      <?php else: ?>
        <div class="empty-shell" id="teamChatEmpty" style="<?= $teamChatMessages ? 'display:none;' : '' ?>"><div class="empty-card"><h3>Start the team chat.</h3><p>Send a message to everyone on <?= e((string) $activeTeam['name']) ?>. Messages update live with Server-Sent Events.</p></div></div>
        <div id="messagesWrap" class="messages-wrap">
          <?php foreach ($teamChatMessages as $chatMessage): ?>
            <?php
              $isMine = empty($chatMessage['is_ai']) && (int) $chatMessage['user_id'] === (int) $user['id'];
              $createdAt = strtotime((string) $chatMessage['created_at']);
              if ($createdAt === false) { $createdAt = time(); }
            ?>
            <div id="team-message-<?= (int) $chatMessage['id'] ?>" class="message-row <?= $isMine ? 'user' : 'assistant' ?>" data-message-role="<?= $isMine ? 'user' : 'assistant' ?>" data-message-id="<?= (int) $chatMessage['id'] ?>" data-message-user="<?= e((string) ($chatMessage['username'] ?? 'Team member')) ?>">
              <div class="message-card">
                <div class="message-head">
                  <div class="message-meta">
                    <?php if ($isMine): ?>
                      <span><i class="fa-regular fa-calendar"></i> <?= e(date('j M Y', $createdAt)) ?></span>
                      <span><i class="fa-regular fa-clock"></i> <?= e(date('H:i', $createdAt)) ?></span>
                      <span class="meta-pill"><i class="fa-solid fa-user"></i> <?= e((string) ($chatMessage['username'] ?? 'Team member')) ?></span>
                    <?php else: ?>
                      <span class="meta-pill"><i class="fa-solid fa-user"></i> <?= e((string) ($chatMessage['username'] ?? 'Team member')) ?></span>
                      <span><i class="fa-regular fa-calendar"></i> <?= e(date('j M Y', $createdAt)) ?></span>
                      <span><i class="fa-regular fa-clock"></i> <?= e(date('H:i', $createdAt)) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="bubble">
                  <div class="message-markdown js-render-markdown" data-raw-message="<?= e((string) ($chatMessage['content'] ?? '')) ?>"><?= e((string) ($chatMessage['content'] ?? '')) ?></div>
                  <div class="message-actions">
                    <div class="message-tools"><?php if (!$isMine): ?><button type="button" class="copy-btn reply-message-btn js-reply-message" data-message-id="<?= (int) $chatMessage['id'] ?>" data-message-user="<?= e((string) ($chatMessage['username'] ?? 'Team member')) ?>"><i class="fa-solid fa-reply"></i> Reply</button><?php endif; ?><button type="button" class="copy-btn js-copy-message" data-message-text="<?= e((string) ($chatMessage['content'] ?? '')) ?>"><i class="fa-regular fa-copy"></i> Copy</button><?php if ($canManageChat): ?><button type="button" class="copy-btn delete-message-btn js-delete-message" data-message-id="<?= (int) $chatMessage['id'] ?>"><i class="fa-regular fa-trash-can"></i> Delete</button><?php endif; ?></div>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($canReadChat): ?>
      <?php if ($canManageChat): ?>
        <div class="team-chat-owner-tools"><button type="button" class="clear-chat-btn" id="teamChatClearButton"><i class="fa-regular fa-trash-can"></i> Clear entire chat</button></div>
      <?php endif; ?>
      <footer class="composer-wrap">
        <form class="composer" id="teamChatForm" autocomplete="off">
          <div class="composer-top">
            <div class="textarea-wrap">
              <label class="composer-label" for="teamChatMessage">Message <?= e((string) $activeTeam['name']) ?></label>
              <div class="message-input-shell" id="teamChatInputShell">
                <textarea id="teamChatMessage" name="message" rows="1" placeholder="Send a message to your team..." <?= $canSendChat ? '' : 'disabled' ?>></textarea>
                <div class="mention-picker" id="teamMentionPicker" role="listbox" aria-label="Mention suggestions"></div>
              </div>
            </div>
            <div class="composer-action">
              <span class="composer-label composer-action-spacer" aria-hidden="true">Action</span>
              <button type="submit" class="send-button" id="teamChatSendButton" <?= $canSendChat ? '' : 'disabled' ?>><i class="fa-solid fa-paper-plane"></i><span>Send</span></button>
            </div>
          </div>
          <div class="composer-footer">
            <div class="team-chat-status" id="teamChatStatus"><span class="team-chat-status-dot"></span><span>Live</span></div>
            <div><?= $canSendChat ? 'Press Enter to send · Shift + Enter for a new line' : 'Your role can read team chat but cannot send messages.' ?></div>
          </div>
        </form>
      </footer>
    <?php endif; ?>
  </div>


      <div class="team-chat-modal-backdrop" id="teamChatConfirmModal" aria-hidden="true">
        <div class="team-chat-modal" role="dialog" aria-modal="true" aria-labelledby="teamChatConfirmTitle" aria-describedby="teamChatConfirmMessage">
          <div class="team-chat-modal-head">
            <h3 class="team-chat-modal-title" id="teamChatConfirmTitle"><i class="fa-solid fa-triangle-exclamation"></i> Confirm action</h3>
            <button type="button" class="team-chat-modal-close" data-team-modal-cancel aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
          </div>
          <div class="team-chat-modal-body">
            <p class="team-chat-modal-message" id="teamChatConfirmMessage">Are you sure?</p>
          </div>
          <div class="team-chat-modal-actions">
            <button type="button" class="team-chat-modal-cancel" data-team-modal-cancel>Cancel</button>
            <button type="button" class="team-chat-modal-confirm" id="teamChatConfirmButton">Confirm</button>
          </div>
        </div>
      </div>

  <?php if ($canReadChat): ?>
<script type="application/json" id="teamMentionUsersJson"><?= json_encode(array_merge([
    ['username' => 'AI', 'mention' => '@AI', 'kind' => 'ai'],
], array_map(static function (array $member): array {
    return [
        'username' => (string) ($member['username'] ?? 'Team member'),
        'mention' => '@' . (string) ($member['username'] ?? 'Team member'),
        'kind' => 'user',
    ];
}, $teamMentionUsers)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script>
const bootTeamChat = () => {
  const root = document.querySelector('[data-team-chat-root]');
  if (!root) return;
  const token = root.dataset.teamToken || '';
  const currentUserId = Number(root.dataset.currentUserId || 0);
  const canManageChat = root.dataset.canManageChat === '1';
  const clearButton = document.getElementById('teamChatClearButton');
  const log = document.getElementById('chatLog');
  const messages = document.getElementById('messagesWrap');
  const empty = document.getElementById('teamChatEmpty');
  const form = document.getElementById('teamChatForm');
  const input = document.getElementById('teamChatMessage');
  const confirmModal = document.getElementById('teamChatConfirmModal');
  const confirmTitle = document.getElementById('teamChatConfirmTitle');
  const confirmMessage = document.getElementById('teamChatConfirmMessage');
  const confirmButton = document.getElementById('teamChatConfirmButton');
  let activeConfirmResolve = null;
  const closeTeamConfirm = (result = false) => {
    if (!confirmModal) return;
    confirmModal.classList.remove('is-open');
    confirmModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('team-chat-modal-open');
    if (activeConfirmResolve) {
      const resolve = activeConfirmResolve;
      activeConfirmResolve = null;
      resolve(Boolean(result));
    }
  };
  const showTeamConfirm = ({ title = 'Confirm action', message = 'Are you sure?', confirmText = 'Confirm', icon = 'fa-triangle-exclamation' } = {}) => new Promise((resolve) => {
    if (!confirmModal || !confirmButton || !confirmMessage || !confirmTitle) {
      resolve(false);
      return;
    }
    if (activeConfirmResolve) closeTeamConfirm(false);
    activeConfirmResolve = resolve;
    confirmTitle.innerHTML = `<i class="fa-solid ${icon}"></i> ${escapeHtml(title)}`;
    confirmMessage.textContent = message;
    confirmButton.innerHTML = `<i class="fa-regular fa-trash-can"></i> ${escapeHtml(confirmText)}`;
    confirmModal.classList.add('is-open');
    confirmModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('team-chat-modal-open');
    window.setTimeout(() => confirmButton.focus(), 30);
  });
  if (confirmModal) {
    confirmModal.addEventListener('click', (event) => {
      if (event.target === confirmModal || event.target.closest('[data-team-modal-cancel]')) closeTeamConfirm(false);
    });
  }
  if (confirmButton) confirmButton.addEventListener('click', () => closeTeamConfirm(true));
  document.addEventListener('keydown', (event) => {
    if (!confirmModal || !confirmModal.classList.contains('is-open')) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeTeamConfirm(false);
    }
  });
  const status = document.getElementById('teamChatStatus');
  const mentionPicker = document.getElementById('teamMentionPicker');
  const mentionDataNode = document.getElementById('teamMentionUsersJson');
  const mentionUsers = (() => {
    try {
      const parsed = JSON.parse(mentionDataNode ? mentionDataNode.textContent || '[]' : '[]');
      return Array.isArray(parsed) ? parsed.filter((item) => item && item.mention) : [];
    } catch (_) {
      return [];
    }
  })();
  let mentionState = { open: false, start: -1, query: '', active: 0, matches: [] };
  let lastId = Number(root.dataset.lastId || 0);
  let source = null;

  if (window.marked) marked.setOptions({ breaks: true, gfm: true });

  const escapeText = (value) => String(value ?? '');
  const scrollBottom = () => { if (log) log.scrollTop = log.scrollHeight; };
  const setStatus = (online, label) => {
    if (!status) return;
    status.classList.toggle('is-offline', !online);
    const text = status.querySelector('span:last-child');
    if (text) text.textContent = label;
  };
  const enhanceCodeBlocks = (scope) => {
    if (!scope) return;
    scope.querySelectorAll('pre').forEach((pre) => {
      if (pre.querySelector('.code-copy-btn')) return;
      const code = pre.querySelector('code');
      if (!code) return;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'code-copy-btn';
      button.innerHTML = '<i class="fa-regular fa-copy"></i> Copy code';
      pre.appendChild(button);
    });
  };
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  const stripReplyToken = (text) => String(text || '').replace(/^<replyto:([^:>]+):(\d+)>\s*/u, '');
  const parseReplyToken = (text) => {
    const match = String(text || '').match(/^<replyto:([^:>]+):(\d+)>\s*/u);
    if (!match) return null;
    return { username: match[1], messageId: match[2], body: String(text || '').slice(match[0].length) };
  };
  const getPlainReplyPreview = (messageId) => {
    const target = document.querySelector(`[data-message-id="${messageId}"] .message-markdown`);
    const raw = target ? stripReplyToken(target.dataset.rawMessage || target.textContent || '') : '';
    return raw.replace(/\s+/g, ' ').trim().slice(0, 180);
  };
  const boldMentions = (text) => String(text || '').replace(/(^|\s)(@[\p{L}\p{N}_.-]+)/gu, '$1**$2**');
  const renderMarkdown = (text) => {
    const reply = parseReplyToken(text);
    const body = reply ? reply.body : String(text || '');
    const raw = boldMentions(body);
    const wrapper = document.createElement('div');
    if (reply) {
      const preview = getPlainReplyPreview(reply.messageId) || `message #${reply.messageId}`;
      const quote = document.createElement('div');
      quote.className = 'reply-quote js-jump-reply';
      quote.dataset.replyMessageId = String(reply.messageId || '');
      quote.setAttribute('role', 'button');
      quote.setAttribute('tabindex', '0');
      quote.setAttribute('title', 'Jump to replied message');
      quote.innerHTML = `<div class="reply-quote-label"><i class="fa-solid fa-reply"></i> Replying to ${escapeHtml(reply.username)}</div><div class="reply-quote-text">${escapeHtml(preview)}</div>`;
      wrapper.append(quote);
    }
    const bodyWrapper = document.createElement('div');
    bodyWrapper.innerHTML = window.marked ? marked.parse(raw) : raw.replace(/\n/g, '<br>');
    wrapper.append(...Array.from(bodyWrapper.childNodes));
    const renderMath = () => {
      if (!window.renderMathInElement) return;
      try {
        window.renderMathInElement(wrapper, {
          delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '$', right: '$', display: false }
          ],
          throwOnError: false
        });
      } catch (_) {}
    };
    renderMath();
    if (window.hljs) wrapper.querySelectorAll('pre code').forEach((block) => window.hljs.highlightElement(block));
    enhanceCodeBlocks(wrapper);
    return wrapper.innerHTML;
  };
  const hydrateMarkdown = (scope = document) => {
    scope.querySelectorAll('.message-markdown[data-raw-message]').forEach((node) => {
      if (node.dataset.rendered === '1') return;
      node.innerHTML = renderMarkdown(node.dataset.rawMessage || '');
      node.dataset.rendered = '1';
    });
  };
  const updateMessageContent = (messageId, content) => {
    const row = document.querySelector(`[data-message-id="${messageId}"]`);
    if (!row) return false;
    const markdown = row.querySelector('.message-markdown');
    const copy = row.querySelector('.js-copy-message');
    const text = escapeText(content || '');
    if (markdown) {
      markdown.dataset.rawMessage = text;
      markdown.innerHTML = renderMarkdown(text);
      markdown.dataset.rendered = '1';
    }
    if (copy) copy.dataset.messageText = text;
    scrollBottom();
    return true;
  };
  const removeMessage = (messageId) => {
    const row = document.querySelector(`[data-message-id="${messageId}"]`);
    if (row) row.remove();
    if (messages && !messages.querySelector('.message-row') && empty) empty.style.display = '';
  };
  const removeMessages = (ids) => {
    (ids || []).forEach((id) => removeMessage(id));
  };
  const postChatManagement = async (action, messageId = null) => {
    const body = new URLSearchParams();
    body.set('action', action);
    body.set('t', token);
    if (messageId) body.set('message_id', String(messageId));
    const response = await fetch('/teams/chat-manage', {
      method: 'POST',
      body,
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': '<?= e(csrf_token()) ?>' }
    });
    const data = await response.json().catch(() => null);
    if (!response.ok || !data || data.ok === false) throw new Error((data && data.error) || 'Could not update chat.');
    if (data.cleared) {
      if (messages) messages.innerHTML = '';
      if (empty) empty.style.display = '';
    }
    if (data.deleted_ids) removeMessages(data.deleted_ids);
    return data;
  };
  const renderMessage = (msg) => {
    if (!messages || !msg || !msg.id) return;
    const existing = document.querySelector(`[data-message-id="${msg.id}"]`);
    if (existing) {
      updateMessageContent(msg.id, msg.content || '');
      return;
    }
    if (empty) empty.style.display = 'none';
    const isMine = Number(msg.is_ai || 0) !== 1 && Number(msg.user_id) === currentUserId;
    const row = document.createElement('div');
    row.className = `message-row ${isMine ? 'user' : 'assistant'}`;
    row.id = `team-message-${msg.id}`;
    row.dataset.messageRole = isMine ? 'user' : 'assistant';
    row.dataset.messageId = String(msg.id);
    row.dataset.messageUser = escapeText(msg.username || 'Team member');

    const card = document.createElement('div');
    card.className = 'message-card';
    const head = document.createElement('div');
    head.className = 'message-head';
    const meta = document.createElement('div');
    meta.className = 'message-meta';

    const user = document.createElement('span');
    user.className = 'meta-pill';
    user.innerHTML = '<i class="fa-solid fa-user"></i> ';
    user.append(document.createTextNode(escapeText(msg.username || 'Team member')));

    const date = document.createElement('span');
    date.innerHTML = '<i class="fa-regular fa-calendar"></i> ';
    date.append(document.createTextNode(escapeText(msg.date_label || '')));

    const time = document.createElement('span');
    time.innerHTML = '<i class="fa-regular fa-clock"></i> ';
    time.append(document.createTextNode(escapeText(msg.time_label || '')));

    if (isMine) {
      meta.append(date, time, user);
    } else {
      meta.append(user, date, time);
    }
    head.append(meta);

    const bubble = document.createElement('div');
    bubble.className = 'bubble';
    const markdown = document.createElement('div');
    markdown.className = 'message-markdown js-render-markdown';
    markdown.dataset.rawMessage = escapeText(msg.content || '');
    markdown.innerHTML = renderMarkdown(msg.content || '');
    markdown.dataset.rendered = '1';

    const actions = document.createElement('div');
    actions.className = 'message-actions';
    const tools = document.createElement('div');
    tools.className = 'message-tools';
    if (!isMine) {
      const reply = document.createElement('button');
      reply.type = 'button';
      reply.className = 'copy-btn reply-message-btn js-reply-message';
      reply.dataset.messageId = String(msg.id);
      reply.dataset.messageUser = escapeText(msg.username || 'Team member');
      reply.innerHTML = '<i class="fa-solid fa-reply"></i> Reply';
      tools.append(reply);
    }
    const copy = document.createElement('button');
    copy.type = 'button';
    copy.className = 'copy-btn js-copy-message';
    copy.dataset.messageText = escapeText(msg.content || '');
    copy.innerHTML = '<i class="fa-regular fa-copy"></i> Copy';
    tools.append(copy);
    if (canManageChat) {
      const del = document.createElement('button');
      del.type = 'button';
      del.className = 'copy-btn delete-message-btn js-delete-message';
      del.dataset.messageId = String(msg.id);
      del.innerHTML = '<i class="fa-regular fa-trash-can"></i> Delete';
      tools.append(del);
    }
    actions.append(tools);
    bubble.append(markdown, actions);
    card.append(head, bubble);
    row.append(card);
    messages.append(row);
    lastId = Math.max(lastId, Number(msg.id));
    root.dataset.lastId = String(lastId);
    scrollBottom();
  };
  const connect = () => {
    if (!window.EventSource || !token) return;
    if (source) source.close();
    source = new EventSource(`/teams/chat-events?t=${encodeURIComponent(token)}&after=${encodeURIComponent(lastId)}`);
    source.addEventListener('open', () => setStatus(true, 'Live'));
    source.addEventListener('chat', (event) => {
      try {
        const payload = JSON.parse(event.data || '{}');
        if (payload.cleared) {
          if (messages) messages.innerHTML = '';
          if (empty) empty.style.display = '';
        }
        (payload.messages || []).forEach(renderMessage);
        if (payload.deleted_ids) removeMessages(payload.deleted_ids);
      } catch (_) {}
    });
    source.addEventListener('error', () => {
      setStatus(false, 'Reconnecting');
      if (source) source.close();
      window.setTimeout(connect, 1600);
    });
  };

  const streamAiReply = async (stream) => {
    if (!stream || !stream.message_id || !stream.prompt) return;
    setStatus(true, 'AI typing');
    const body = new URLSearchParams();
    body.set('message_id', String(stream.message_id));
    body.set('prompt', String(stream.prompt));
    body.set('t', token);
    try {
      const response = await fetch(`/teams/chat-ai-stream?t=${encodeURIComponent(token)}`, {
        method: 'POST',
        body,
        headers: { 'Accept': 'application/x-ndjson', 'X-CSRF-Token': '<?= e(csrf_token()) ?>' }
      });
      if (!response.ok || !response.body) throw new Error('AI stream failed.');
      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let pending = '';
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        pending += decoder.decode(value, { stream: true });
        const lines = pending.split(/\r?\n/);
        pending = lines.pop() || '';
        for (const line of lines) {
          if (!line.trim()) continue;
          let payload = null;
          try { payload = JSON.parse(line); } catch (_) { continue; }
          if (payload.type === 'replace' || payload.type === 'error' || payload.content) {
            updateMessageContent(payload.message_id || stream.message_id, payload.content || payload.error || '');
          }
          if (payload.type === 'error') setStatus(false, payload.error || 'AI failed');
        }
      }
      if (pending.trim()) {
        try {
          const payload = JSON.parse(pending);
          if (payload.content || payload.error) updateMessageContent(payload.message_id || stream.message_id, payload.content || payload.error || '');
        } catch (_) {}
      }
      setStatus(true, 'Live');
    } catch (error) {
      setStatus(false, error.message || 'AI stream failed');
      updateMessageContent(stream.message_id, error.message || 'AI stream failed');
    }
  };

  const getMentionTrigger = () => {
    if (!input) return null;
    const cursor = Number(input.selectionStart || 0);
    const value = String(input.value || '');

    // Only trigger when @ is the very first character in #teamChatMessage.
    // Anything before @, even whitespace, must keep the picker closed.
    if (cursor < 1 || value[0] !== '@') return null;

    const beforeCursor = value.slice(0, cursor);
    const match = beforeCursor.match(/^@([A-Za-z0-9_.-]*)$/u);
    if (!match) return null;

    return { start: 0, query: match[1] || '' };
  };
  const positionMentionPicker = () => {
    // The picker is positioned with CSS relative to the textarea wrapper.
    // Keeping this function as a no-op avoids stale inline fixed-position
    // values preventing the dropdown from appearing above the composer.
  };
  const closeMentionPicker = () => {
    mentionState = { open: false, start: -1, query: '', active: 0, matches: [] };
    if (mentionPicker) {
      mentionPicker.classList.remove('is-open');
      mentionPicker.innerHTML = '';
    }
  };
  const renderMentionPicker = () => {
    if (!mentionPicker || !input) return;
    mentionPicker.innerHTML = '';
    mentionState.matches.forEach((item, index) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = `mention-option${index === mentionState.active ? ' is-active' : ''}`;
      option.dataset.index = String(index);
      option.setAttribute('role', 'option');
      option.setAttribute('aria-selected', index === mentionState.active ? 'true' : 'false');
      option.innerHTML = item.kind === 'ai' ? '<i class="fa-solid fa-wand-magic-sparkles"></i>' : '<i class="fa-solid fa-user"></i>';
      option.append(document.createTextNode(item.mention || `@${item.username || ''}`));
      const label = document.createElement('small');
      label.textContent = item.kind === 'ai' ? 'assistant' : 'member';
      option.append(label);
      mentionPicker.append(option);
    });
    mentionPicker.classList.toggle('is-open', mentionState.matches.length > 0);
    positionMentionPicker();
  };
  const updateMentionPicker = () => {
    if (!input || !mentionPicker || !mentionUsers.length) return;
    const trigger = getMentionTrigger();
    if (!trigger) {
      closeMentionPicker();
      return;
    }
    const query = String(trigger.query || '').toLowerCase();
    const matches = mentionUsers
      .filter((item) => String(item.mention || '').replace(/^@/, '').toLowerCase().startsWith(query))
      .slice(0, 8);
    if (!matches.length) {
      closeMentionPicker();
      return;
    }
    mentionState = { open: true, start: trigger.start, query: trigger.query, active: Math.min(mentionState.active || 0, matches.length - 1), matches };
    renderMentionPicker();
  };
  const insertMention = (item) => {
    if (!input || !item) return;
    const cursor = input.selectionStart || 0;
    const before = input.value.slice(0, mentionState.start >= 0 ? mentionState.start : cursor);
    const after = input.value.slice(cursor);
    const mention = String(item.mention || '').trim();
    const insert = `${mention} `;
    input.value = before + insert + after;
    const nextCursor = before.length + insert.length;
    input.setSelectionRange(nextCursor, nextCursor);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    closeMentionPicker();
    input.focus();
  };
  if (input) {
    input.addEventListener('input', () => {
      input.style.height = 'auto';
      input.style.height = `${Math.min(input.scrollHeight, 112)}px`;
      updateMentionPicker();
    });
    input.addEventListener('click', updateMentionPicker);
    input.addEventListener('focus', updateMentionPicker);
    input.addEventListener('keyup', (event) => {
      if (!['ArrowUp', 'ArrowDown', 'Enter', 'Tab', 'Escape'].includes(event.key)) updateMentionPicker();
    });
    input.addEventListener('keydown', (event) => {
      if (mentionState.open && mentionState.matches.length) {
        if (event.key === 'ArrowDown') {
          event.preventDefault();
          mentionState.active = (mentionState.active + 1) % mentionState.matches.length;
          renderMentionPicker();
          return;
        }
        if (event.key === 'ArrowUp') {
          event.preventDefault();
          mentionState.active = (mentionState.active - 1 + mentionState.matches.length) % mentionState.matches.length;
          renderMentionPicker();
          return;
        }
        if (event.key === 'Enter' || event.key === 'Tab') {
          event.preventDefault();
          insertMention(mentionState.matches[mentionState.active]);
          return;
        }
        if (event.key === 'Escape') {
          event.preventDefault();
          closeMentionPicker();
          return;
        }
      }
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        if (form) form.requestSubmit();
      }
    });
  }
  if (form && input) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const message = input.value.trim();
      if (!message) return;
      const button = form.querySelector('button[type="submit"]');
      if (button) button.disabled = true;
      try {
        const body = new URLSearchParams();
        body.set('message', message);
        body.set('t', token);
        const response = await fetch('/teams/chat-send', {
          method: 'POST',
          body,
          headers: { 'Accept': 'application/json', 'X-CSRF-Token': '<?= e(csrf_token()) ?>' }
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || !data || data.ok === false) throw new Error((data && data.error) || 'Could not send message.');
        if (data.message) renderMessage(data.message);
        if (data.ai_message) renderMessage(data.ai_message);
        if (data.ai_stream) streamAiReply(data.ai_stream);
        if (data.ai_error) setStatus(false, data.ai_error);
        input.value = '';
        input.style.height = 'auto';
      } catch (error) {
        setStatus(false, error.message || 'Send failed');
      } finally {
        if (button) button.disabled = false;
        input.focus();
      }
    });
  }
  const jumpToMessage = (messageId) => {
    const safeMessageId = String(messageId || '').replace(/\D/g, '');
    if (!safeMessageId) return false;
    const target = document.querySelector(`[data-message-id="${safeMessageId}"]`);
    if (!target) {
      setStatus(false, 'Original message is not currently loaded');
      return false;
    }
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target.classList.add('is-jump-target');
    window.setTimeout(() => target.classList.remove('is-jump-target'), 1800);
    return true;
  };
  const insertReplyToken = (username, messageId) => {
    if (!input || !username || !messageId) return;
    const safeUsername = String(username).replace(/[<>:\r\n]/g, '').trim() || 'Team member';
    const safeMessageId = String(messageId).replace(/\D/g, '');
    if (!safeMessageId) return;
    const tokenText = `<replyto:${safeUsername}:${safeMessageId}> `;
    input.value = tokenText;
    input.setSelectionRange(input.value.length, input.value.length);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.focus();
  };
  document.addEventListener('click', async (event) => {
    const replyButton = event.target.closest('.js-reply-message');
    if (replyButton) {
      insertReplyToken(replyButton.dataset.messageUser || 'Team member', replyButton.dataset.messageId || '');
      return;
    }
    const replyQuote = event.target.closest('.js-jump-reply');
    if (replyQuote) {
      jumpToMessage(replyQuote.dataset.replyMessageId || '');
      return;
    }
    const mentionOption = event.target.closest('.mention-option');
    if (mentionOption && mentionPicker && mentionPicker.contains(mentionOption)) {
      const index = Number(mentionOption.dataset.index || 0);
      insertMention(mentionState.matches[index]);
      return;
    }
    if (mentionPicker && input && !mentionPicker.contains(event.target) && event.target !== input) {
      closeMentionPicker();
    }
    const codeButton = event.target.closest('.code-copy-btn');
    if (codeButton) {
      const pre = codeButton.closest('pre');
      const code = pre ? pre.querySelector('code') : null;
      const text = code ? code.innerText : '';
      if (!text) return;
      const original = codeButton.innerHTML;
      const copied = () => {
        codeButton.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
        window.setTimeout(() => { codeButton.innerHTML = original; }, 1300);
      };
      try { await navigator.clipboard.writeText(text); copied(); } catch (_) {}
      return;
    }
    const deleteButton = event.target.closest('.js-delete-message');
    if (deleteButton) {
      if (!canManageChat) return;
      const messageId = Number(deleteButton.dataset.messageId || 0);
      if (!messageId) return;
      if (!await showTeamConfirm({ title: 'Delete message?', message: 'This message will be permanently removed from the database.', confirmText: 'Delete message' })) return;
      const original = deleteButton.innerHTML;
      deleteButton.disabled = true;
      deleteButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting';
      try {
        await postChatManagement('delete', messageId);
      } catch (error) {
        setStatus(false, error.message || 'Delete failed');
        deleteButton.disabled = false;
        deleteButton.innerHTML = original;
      }
      return;
    }
    const button = event.target.closest('.js-copy-message');
    if (!button) return;
    const text = button.dataset.messageText || '';
    if (!text) return;
    const original = button.innerHTML;
    const copied = () => {
      button.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
      window.setTimeout(() => { button.innerHTML = original; }, 1300);
    };
    try {
      await navigator.clipboard.writeText(text);
      copied();
    } catch (_) {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'fixed';
      textarea.style.left = '-9999px';
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      textarea.remove();
      copied();
    }
  });

  document.addEventListener('keydown', (event) => {
    const replyQuote = event.target.closest ? event.target.closest('.js-jump-reply') : null;
    if (!replyQuote) return;
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    jumpToMessage(replyQuote.dataset.replyMessageId || '');
  });

  if (clearButton) {
    clearButton.addEventListener('click', async () => {
      if (!canManageChat) return;
      if (!await showTeamConfirm({ title: 'Clear entire chat?', message: 'This permanently removes every team chat message from the database.', confirmText: 'Clear chat' })) return;
      const original = clearButton.innerHTML;
      clearButton.disabled = true;
      clearButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Clearing';
      try {
        await postChatManagement('clear');
        setStatus(true, 'Live');
      } catch (error) {
        setStatus(false, error.message || 'Clear failed');
      } finally {
        clearButton.disabled = false;
        clearButton.innerHTML = original;
      }
    });
  }

  window.addEventListener('resize', () => { if (mentionState.open) positionMentionPicker(); });
  window.addEventListener('scroll', () => { if (mentionState.open) positionMentionPicker(); }, true);
  hydrateMarkdown(document);
  scrollBottom();
  const hydrateAgain = () => hydrateMarkdown(document);
  window.setTimeout(hydrateAgain, 150);
  window.setTimeout(hydrateAgain, 600);
  connect();
  window.addEventListener('beforeunload', () => { if (source) source.close(); });
};
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootTeamChat);
} else {
  bootTeamChat();
}
</script>
  <?php endif; ?>
<?php endif; ?>
<?php render_team_footer(); ?>
