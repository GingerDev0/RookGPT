<?php
require __DIR__ . '/_bootstrap.php';
[$user, $planInfo] = require_api_user();
$apiUrl = app_base_url() . '/api';
$model = rook_ai_label() . ' · ' . rook_ai_model();
$defaultSystemPrompt = DEFAULT_API_SYSTEM_PROMPT;
api_header('API documentation', $user, $planInfo, 'docs');
?>
<div class="docs-layout">
  <aside class="docs-card docs-toc">
    <div class="badge-soft mb-3"><i class="fa-solid fa-book-open"></i> API reference</div>
    <a href="#quickstart"><i class="fa-solid fa-bolt"></i> Quickstart</a>
    <a href="#auth"><i class="fa-solid fa-lock"></i> Authentication</a>
    <a href="#request"><i class="fa-solid fa-paper-plane"></i> Request body</a>
    <a href="#system-prompt"><i class="fa-solid fa-sliders"></i> System prompt</a>
    <a href="#response"><i class="fa-solid fa-reply"></i> Response body</a>
    <a href="#errors"><i class="fa-solid fa-triangle-exclamation"></i> Errors</a>
    <a href="#limits"><i class="fa-solid fa-gauge-high"></i> Limits</a>
    <a href="#examples"><i class="fa-solid fa-code"></i> Examples</a>
    <a href="#notes"><i class="fa-solid fa-circle-info"></i> Behaviour notes</a>
  </aside>

  <main class="docs-section">
    <section class="docs-card docs-hero" id="quickstart">
      <span class="badge-soft"><i class="fa-solid fa-plug"></i> RookGPT Chat API</span>
      <h1>Ship Rook responses from your own app.</h1>
      <p class="docs-lead">The API is a single authenticated chat endpoint. Send a JSON conversation to <strong><?= e($apiUrl) ?></strong>, get a JSON assistant response back, then log usage against the API key that made the request.</p>
      <div class="endpoint-line mt-4">
        <span class="method-pill">POST</span>
        <span class="endpoint-url" id="endpointText"><?= e($apiUrl) ?></span>
        <button class="copy-doc-btn" type="button" data-copy-target="endpointText"><i class="fa-regular fa-copy me-1"></i>Copy endpoint</button>
      </div>
    </section>

    <section class="docs-grid">
      <div class="docs-card"><h3>Model</h3><p>The endpoint currently sends requests to <code><?= e($model) ?></code>.</p></div>
      <div class="docs-card"><h3>Auth</h3><p>Every request needs <code>Authorization: Bearer rgpt_...</code> or a valid <code>rgpt_team_...</code> team key.</p></div>
      <div class="docs-card"><h3>Format</h3><p>Requests and responses are JSON. Set <code>Content-Type: application/json</code>.</p></div>
      <div class="docs-card"><h3>Defaults</h3><p><code>temperature: 1.0</code>, <code>top_p: 0.95</code>, <code>top_k: 64</code>.</p></div>
    </section>

    <section class="docs-card" id="auth">
      <h2>Authentication</h2>
      <p class="muted">Create a member key in the Keys tab, or use a team key if your team permissions allow key access. The playground now loads available member and team keys into a masked dropdown, so you do not need to paste keys manually. Disabled, deleted, revoked, or invalid keys return a 401.</p>
      <pre class="docs-code" id="authExample">Authorization: Bearer rgpt_YOUR_KEY
Content-Type: application/json</pre>
      <button class="copy-doc-btn mt-2" type="button" data-copy-target="authExample"><i class="fa-regular fa-copy me-1"></i>Copy headers</button>
    </section>

    <section class="docs-card" id="request">
      <h2>Request body</h2>
      <p class="muted">The endpoint requires <code>messages</code>. The user prompt starts empty by default. Add messages when you want the model to respond; optional request settings can be overridden per call.</p>
      <div class="table-responsive">
        <table class="docs-table">
          <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
          <tbody>
            <tr class="schema-row"><td><code>messages</code></td><td>array</td><td>Yes</td><td>Conversation messages. Each item should include <code>role</code> and <code>content</code>.</td></tr>
            <tr class="schema-row"><td><code>messages[].role</code></td><td>string</td><td>Yes</td><td>Use <code>user</code> or <code>assistant</code>. Any non-assistant role is treated as <code>user</code>.</td></tr>
            <tr class="schema-row"><td><code>messages[].content</code></td><td>string</td><td>Yes</td><td>The text content for that message. Empty content is ignored.</td></tr>
            <tr class="schema-row"><td><code>think</code></td><td>boolean</td><td>No</td><td>Turns model thinking on when true. Leave false for clean app responses.</td></tr>
            <tr class="schema-row"><td><code>system_prompt</code></td><td>string</td><td>No</td><td>Optional extra system instructions for tone, format, or task behaviour. Empty or omitted means no extra visible system prompt is added. Identity/name overrides are ignored.</td></tr>
            <tr class="schema-row"><td><code>temperature</code></td><td>number</td><td>No</td><td>Sampling temperature from <code>0</code> to <code>2</code>. Default: <code><?= e((string) DEFAULT_API_TEMPERATURE) ?></code>.</td></tr>
            <tr class="schema-row"><td><code>top_p</code></td><td>number</td><td>No</td><td>Nucleus sampling value from <code>0</code> to <code>1</code>. Default: <code><?= e((string) DEFAULT_API_TOP_P) ?></code>.</td></tr>
            <tr class="schema-row"><td><code>top_k</code></td><td>integer</td><td>No</td><td>Top-k sampling value from <code>0</code> to <code>1000</code>. Default: <code><?= e((string) DEFAULT_API_TOP_K) ?></code>.</td></tr>
          </tbody>
        </table>
      </div>
      <pre class="docs-code mt-3" id="requestExample">{
  "messages": [
    {
      "role": "user",
      "content": "Write a short launch email for a new dashboard."
    }
  ],
  "think": false,
  "temperature": 1.0,
  "top_p": 0.95,
  "top_k": 64
}</pre>
      <button class="copy-doc-btn mt-2" type="button" data-copy-target="requestExample"><i class="fa-regular fa-copy me-1"></i>Copy request JSON</button>
    </section>

    <section class="docs-card" id="system-prompt">
      <h2>System prompt behaviour</h2>
      <p class="muted">The API lets each request add optional extra system instructions. The core Rook identity instruction is applied server-side, is not exposed, and cannot be overridden by request input.</p>
      <div class="table-responsive">
        <table class="docs-table">
          <thead><tr><th>How you send it</th><th>Result</th></tr></thead>
          <tbody>
            <tr><td>Omit <code>system_prompt</code></td><td>No extra visible system prompt is added.</td></tr>
            <tr><td><code>"system_prompt": "Be concise."</code></td><td>Adds your extra instruction to the request.</td></tr>
            <tr><td><code>"system_prompt": "You are Peter."</code></td><td>The identity override is ignored; the assistant still responds as Rook.</td></tr>
            <tr><td><code>"system_prompt": ""</code></td><td>Same as omitting it: no extra visible system prompt is added.</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="docs-card" id="response">
      <h2>Successful response</h2>
      <p class="muted">A successful request returns <code>ok: true</code>, the model name, the assistant message, optional thinking text, and usage metadata.</p>
      <div class="table-responsive">
        <table class="docs-table">
          <thead><tr><th>Field</th><th>Type</th><th>Description</th></tr></thead>
          <tbody>
            <tr class="schema-row"><td><code>ok</code></td><td>boolean</td><td>True when the request completed successfully.</td></tr>
            <tr class="schema-row"><td><code>model</code></td><td>string</td><td>The model that responded.</td></tr>
            <tr class="schema-row"><td><code>message</code></td><td>string</td><td>The assistant response you normally display in your app.</td></tr>
            <tr class="schema-row"><td><code>thinking</code></td><td>string</td><td>Thinking text returned by the active provider when available and enabled.</td></tr>
            <tr class="schema-row"><td><code>usage.prompt_eval_count</code></td><td>integer</td><td>Prompt token/evaluation count reported by the active provider.</td></tr>
            <tr class="schema-row"><td><code>usage.eval_count</code></td><td>integer</td><td>Generation token/evaluation count reported by the active provider.</td></tr>
            <tr class="schema-row"><td><code>usage.total_duration</code></td><td>integer</td><td>Total duration reported by the active provider when available.</td></tr>
          </tbody>
        </table>
      </div>
      <pre class="docs-code mt-3" id="responseExample">{
  "ok": true,
  "model": "<?= e($model) ?>",
  "message": "Here is the finished response...",
  "thinking": "",
  "usage": {
    "prompt_eval_count": 42,
    "eval_count": 180,
    "total_duration": 123456789
  }
}</pre>
      <button class="copy-doc-btn mt-2" type="button" data-copy-target="responseExample"><i class="fa-regular fa-copy me-1"></i>Copy response shape</button>
    </section>

    <section class="docs-card" id="errors">
      <h2>Error responses</h2>
      <p class="muted">Errors return JSON with <code>ok: false</code> and a short <code>error</code> string.</p>
      <div class="table-responsive">
        <table class="docs-table">
          <thead><tr><th>Status</th><th>Error</th><th>Meaning</th></tr></thead>
          <tbody>
            <tr><td>400</td><td><code>Invalid JSON body</code></td><td>The request body was not valid JSON.</td></tr>
            <tr><td>400</td><td><code>messages array is required</code></td><td>The body is missing a non-empty <code>messages</code> array.</td></tr>
            <tr><td>401</td><td><code>Missing bearer token</code></td><td>No bearer token was sent.</td></tr>
            <tr><td>401</td><td><code>Invalid API key</code></td><td>The key is wrong, disabled, revoked, or deleted.</td></tr>
            <tr><td>403</td><td><code>API access is not available on this plan</code></td><td>The account plan does not include API access.</td></tr>
            <tr><td>405</td><td><code>POST only</code></td><td>The endpoint only accepts POST requests.</td></tr>
            <tr><td>429</td><td><code>Daily API call limit reached for this plan</code></td><td>The current plan daily request limit has been reached.</td></tr>
            <tr><td>502</td><td><code>AI provider request failed</code></td><td>The local AI provider request failed or returned an invalid response.</td></tr>
          </tbody>
        </table>
      </div>
      <pre class="docs-code mt-3">{
  "ok": false,
  "error": "Missing bearer token"
}</pre>
    </section>

    <section class="docs-card" id="limits">
      <h2>Plans and limits</h2>
      <div class="table-responsive">
        <table class="docs-table">
          <thead><tr><th>Plan</th><th>API access</th><th>Daily API calls</th></tr></thead>
          <tbody>
            <tr><td>Free</td><td>No</td><td>0</td></tr>
            <tr><td>Plus</td><td>No</td><td>0</td></tr>
            <tr><td>Pro</td><td>Yes</td><td>1,000</td></tr>
            <tr><td>Business</td><td>Yes</td><td>Unlimited</td></tr>
          </tbody>
        </table>
      </div>
      <p class="muted mt-3 mb-0">Usage is logged against the user and API key. Last-used time, request counts, failures, and evaluation counts are shown in the API workspace.</p>
    </section>

    <section class="docs-card" id="examples">
      <h2>Examples</h2>
      <p class="muted">Use the Playground tab for a full language switcher. These are the core examples most people need first.</p>
      <h3 class="h5 mt-4">curl</h3>
      <pre class="docs-code" id="curlExample">curl -X POST <?= e($apiUrl) ?> \
  -H "Authorization: Bearer rgpt_YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "system_prompt": "Be concise.",
    "messages": [
      {"role": "user", "content": "Write a short launch email."}
    ],
    "think": false,
    "temperature": 1.0,
    "top_p": 0.95,
    "top_k": 64
  }'</pre>
      <button class="copy-doc-btn mt-2" type="button" data-copy-target="curlExample"><i class="fa-regular fa-copy me-1"></i>Copy curl</button>

      <h3 class="h5 mt-4">JavaScript</h3>
      <pre class="docs-code" id="jsExample">const response = await fetch('<?= e($apiUrl) ?>', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer rgpt_YOUR_KEY',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    system_prompt: 'Be concise.',
    messages: [{ role: 'user', content: 'Summarise this idea in one paragraph.' }],
    think: false,
    temperature: 1.0,
    top_p: 0.95,
    top_k: 64
  })
});

const data = await response.json();
console.log(data.message);</pre>
      <button class="copy-doc-btn mt-2" type="button" data-copy-target="jsExample"><i class="fa-regular fa-copy me-1"></i>Copy JavaScript</button>

      <h3 class="h5 mt-4">PHP</h3>
      <pre class="docs-code" id="phpExample">&lt;?php
$payload = [
    'system_prompt' => 'Be concise.',
    'messages' => [
        ['role' => 'user', 'content' => 'Write a polite support reply.'],
    ],
    'think' => false,
    'temperature' => 1.0,
    'top_p' => 0.95,
    'top_k' => 64,
];

$ch = curl_init('<?= e($apiUrl) ?>');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer rgpt_YOUR_KEY',
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$response = curl_exec($ch);
curl_close($ch);

echo $response;</pre>
      <button class="copy-doc-btn mt-2" type="button" data-copy-target="phpExample"><i class="fa-regular fa-copy me-1"></i>Copy PHP</button>
    </section>

    <section class="docs-card" id="notes">
      <h2>Behaviour notes</h2>
      <ul>
        <li>The server applies the core Rook identity instruction internally. Use <code>system_prompt</code> only for extra per-request behaviour. Attempts to rename or re-identify the assistant are ignored.</li>
        <li>The API currently sends <code>stream: false</code>, so responses come back as one JSON object.</li>
        <li>Default generation settings are <code>temperature: 1.0</code>, <code>top_p: 0.95</code>, and <code>top_k: 64</code>. Each can be overridden per request.</li>
        <li>The endpoint logs status code and usage counts after each successful model call.</li>
        <li>Use <a href="/api/playground">the playground</a> to test a real request before wiring it into production. It auto-fills available member and team keys while keeping the visible labels masked.</li>
      </ul>
    </section>
  </main>
</div>

<?php api_footer(); ?>
