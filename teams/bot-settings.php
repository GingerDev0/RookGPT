<?php
require_once __DIR__ . '/_bootstrap.php';
render_team_header('bot-settings', 'Bot Settings', 'Customise the team bot name, prompt, mention trigger, and response behaviour.');

$botSettings = team_bot_settings($activeTeam);
?>

<?php if (!$activeTeam): ?>
  <div class="notice">Create or join a team before changing bot settings.</div>
<?php else: ?>
  <div class="grid" style="grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);align-items:start;">
    <div class="panel" style="max-width:920px;">
      <div class="eyebrow">Bot customisation</div>
      <h2 style="font-weight:900;margin:8px 0 10px;">Customise <?= e((string) $botSettings['name']) ?></h2>
      <p class="muted">These settings apply across the team chat. Members mention the bot with the trigger below. The bot uses the same AI provider and model configured for the main chat, so no team API key is required.</p>

      <?php if ($isTeamOwner): ?>
        <form method="post" action="/teams/bot-settings" style="margin-top:18px;">
          <?= team_return_input('bot-settings') ?>
          <div class="checks" style="margin-top:0;">
            <label><input type="checkbox" name="bot_enabled" value="1" <?= $botSettings['enabled'] ? 'checked' : '' ?>> Enable team bot</label>
          </div>

          <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;">
            <div class="field">
              <label for="bot_name">Name</label>
              <input id="bot_name" name="bot_name" maxlength="80" value="<?= e((string) $botSettings['name']) ?>" placeholder="AI">
            </div>
            <div class="field">
              <label for="bot_mention">Mention trigger</label>
              <input id="bot_mention" name="bot_mention" maxlength="40" value="<?= e((string) $botSettings['mention']) ?>" placeholder="@AI">
            </div>
          </div>

          <div class="field" style="margin-top:14px;">
            <label for="bot_custom_prompt">Custom prompt</label>
            <textarea id="bot_custom_prompt" name="bot_custom_prompt" rows="8" maxlength="4000" style="width:100%;background:#0a111d;color:var(--text);border:1px solid var(--line);padding:12px;resize:vertical;line-height:1.55;" placeholder="Example: You are the internal support bot for our team. Answer in UK English. Prefer concise bullet points. Ask one clarifying question only when needed."><?= e((string) $botSettings['custom_prompt']) ?></textarea>
            <p class="muted" style="margin:8px 0 0;font-size:.9rem;">This shapes the bot's role, tone, formatting, and domain knowledge. The configured bot name is treated as its identity.</p>
          </div>

          <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:14px;">
            <div class="field">
              <label for="bot_response_style">Response style</label>
              <select id="bot_response_style" name="bot_response_style">
                <option value="concise" <?= $botSettings['response_style'] === 'concise' ? 'selected' : '' ?>>Concise</option>
                <option value="balanced" <?= $botSettings['response_style'] === 'balanced' ? 'selected' : '' ?>>Balanced</option>
                <option value="detailed" <?= $botSettings['response_style'] === 'detailed' ? 'selected' : '' ?>>Detailed</option>
              </select>
            </div>
            <div class="field">
              <label for="bot_temperature">Creativity / temperature</label>
              <input id="bot_temperature" name="bot_temperature" type="number" min="0" max="2" step="0.05" value="<?= e((string) $botSettings['temperature']) ?>">
            </div>
            <div class="field">
              <label for="bot_top_p">Top P</label>
              <input id="bot_top_p" name="bot_top_p" type="number" min="0" max="1" step="0.05" value="<?= e((string) $botSettings['top_p']) ?>">
            </div>
            <div class="field">
              <label for="bot_top_k">Top K</label>
              <input id="bot_top_k" name="bot_top_k" type="number" min="1" max="200" step="1" value="<?= (int) $botSettings['top_k'] ?>">
            </div>
            <div class="field">
              <label for="bot_context_messages">Context messages</label>
              <input id="bot_context_messages" name="bot_context_messages" type="number" min="10" max="200" step="1" value="<?= (int) $botSettings['context_messages'] ?>">
            </div>
            <div class="field">
              <label for="bot_max_reply_chars">Max reply characters</label>
              <input id="bot_max_reply_chars" name="bot_max_reply_chars" type="number" min="500" max="12000" step="100" value="<?= (int) $botSettings['max_reply_chars'] ?>">
            </div>
          </div>

          <button class="btn-rook" type="submit" name="update_team_bot_settings" value="1" style="margin-top:8px;"><i class="fa-solid fa-floppy-disk me-2"></i>Save bot settings</button>
        </form>
      <?php else: ?>
        <div class="notice" style="margin-top:16px;">Only the team owner can edit bot settings.</div>
        <div class="key-row">
          <span class="key-preview">Name: <?= e((string) $botSettings['name']) ?></span>
          <span class="key-preview">Mention: <?= e((string) $botSettings['mention']) ?></span>
          <span class="key-preview">Status: <?= $botSettings['enabled'] ? 'Enabled' : 'Disabled' ?></span>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel" style="max-width:520px;">
      <div class="eyebrow">How it works</div>
      <h2 style="font-weight:900;margin:8px 0 10px;">Team bot usage</h2>
      <p class="muted">In <strong>/teams/chat</strong>, start a message with:</p>
      <div class="key-preview" style="display:block;margin:12px 0;white-space:normal;"><?= e((string) $botSettings['mention']) ?> summarise the last few messages</div>
      <p class="muted">The bot appears in chat using the configured name, uses recent team messages as context, follows the custom prompt saved here, and runs through the same AI setup as the main chat.</p>
    </div>
  </div>
<?php endif; ?>
<?php render_team_footer(); ?>
