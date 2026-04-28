<?php
require __DIR__ . '/_bootstrap.php';
[$user, $planInfo] = require_api_user();
$createdKey = (string)($_SESSION['api_plain_key'] ?? '');
$apiUrl = app_base_url() . '/api';
$playgroundRunUrl = '/api/playground-run.php';
$snippetKey = 'YOUR_API_KEY';
$keyOptions = fetch_playground_key_options((int)$user['id'], $createdKey);
$defaultSystemPrompt = DEFAULT_API_SYSTEM_PROMPT;
api_header('API playground', $user, $planInfo, 'playground');
?>
<section class="api-card mb-3">
  <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
    <div>
      <span class="badge-soft"><i class="fa-solid fa-flask"></i> Live request builder</span>
      <h2 class="mt-3 mb-2">Build, edit, copy, and run the same request.</h2>
      <p class="muted mb-0">This is back to the richer builder style: editable JSON body, editable generated snippets, language tabs, and a live response panel.</p>
    </div>
    <div class="endpoint-line"><span class="method-pill">POST</span><span class="endpoint-url"><?= e($apiUrl) ?></span></div>
  </div>
</section>

<section class="api-card mb-3">
  <div class="docs-grid docs-grid-3">
    <div class="docs-mini-card"><span>1 - Choose key</span><strong>Member or team</strong><p>Available keys are masked in the dropdown and sent as a bearer token.</p></div>
    <div class="docs-mini-card"><span>2 - Tune request</span><strong>Prompt + options</strong><p>Edit the user prompt, optional system prompt, thinking, and sampling values.</p></div>
    <div class="docs-mini-card"><span>3 - Copy or run</span><strong>Real endpoint</strong><p>Snippets and the Run button call <code>/api</code>, not the dashboard route.</p></div>
  </div>
</section>

<div class="playground-grid">
  <section class="playground-stack">
    <div class="playground-card">
      <div class="playground-builder-grid">
        <div>
          <label for="playgroundKey">Bearer key</label>
          <select class="form-select" id="playgroundKey">
            <?php if ($keyOptions): ?>
              <?php foreach ($keyOptions as $option): $available = !empty($option['available']); ?>
                <option value="<?= e((string)$option['value']) ?>" data-kind="<?= e((string)$option['type']) ?>" data-masked="<?= e((string)$option['masked']) ?>" <?= $available ? '' : 'disabled' ?>><?= e(ucfirst((string)$option['type'])) ?> · <?= e((string)$option['label']) ?> · <?= e((string)$option['masked']) ?></option>
              <?php endforeach; ?>
            <?php else: ?>
              <option value="" disabled>No stored keys available</option>
            <?php endif; ?>
            <option value="__paste__">Paste a bearer key...</option>
          </select>
          <input class="form-control mt-2 d-none" type="password" id="playgroundCustomKey" placeholder="Paste rgpt_... key here" autocomplete="off">
          <div class="play-inline-note">Stored member and team keys are selected by server-side reference. Full keys are never stored in your browser.</div>
        </div>
        <div>
          <label>Options</label>
          <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" role="switch" id="playgroundThink">
            <label class="form-check-label" for="playgroundThink">Enable <code>think</code> in the request</label>
          </div>
        </div>
        <div class="span-2">
          <label for="playgroundSystemPrompt">System prompt</label>
          <textarea class="form-control" id="playgroundSystemPrompt" rows="6" placeholder="Optional: add tone, format, or task behaviour. Identity/name changes are ignored."></textarea>
          <div class="play-inline-note">This is sent as <code>system_prompt</code>. Empty is valid. It can tune behaviour, but it cannot rename Rook or override the hidden app identity.</div>
        </div>
        <div>
          <label for="playgroundTemperature">Temperature</label>
          <input class="form-control" id="playgroundTemperature" type="number" min="0" max="2" step="0.01" value="<?= e((string) DEFAULT_API_TEMPERATURE) ?>">
        </div>
        <div class="playground-params-row">
          <div>
            <label for="playgroundTopP">Top P</label>
            <input class="form-control" id="playgroundTopP" type="number" min="0" max="1" step="0.01" value="<?= e((string) DEFAULT_API_TOP_P) ?>">
          </div>
          <div>
            <label for="playgroundTopK">Top K</label>
            <input class="form-control" id="playgroundTopK" type="number" min="0" max="1000" step="1" value="<?= e((string) DEFAULT_API_TOP_K) ?>">
          </div>
        </div>
        <div class="span-2">
          <label for="playgroundPrompt">Prompt</label>
          <textarea class="form-control" id="playgroundPrompt" rows="5" placeholder="Ask the API something useful."></textarea>
        </div>
        <div class="span-2">
          <label for="playgroundBody">JSON body</label>
          <textarea class="form-control docs-editor" id="playgroundBody" spellcheck="false" rows="10"></textarea>
          <div class="play-inline-note">You can edit this directly. The snippets regenerate from this JSON body.</div>
        </div>
      </div>
      <div class="mini-actions mt-3">
        <button class="btn btn-rook" type="button" id="runSnippetBtn"><i class="fa-solid fa-play me-2"></i>Run request</button>
        <button class="btn btn-outline-light" type="button" id="playgroundFill"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Fill demo prompt</button>
        <button class="btn btn-outline-light" type="button" id="resetBodyBtn"><i class="fa-solid fa-rotate-left me-2"></i>Reset body</button>
      </div>
    </div>
  </section>

  <section class="playground-stack">
    <div class="playground-card">
      <div class="code-toolbar">
        <div class="lang-primary-tabs">
          <button type="button" class="lang-tab active" data-lang="shell">Shell</button>
          <button type="button" class="lang-tab" data-lang="node">Node</button>
          <button type="button" class="lang-tab" data-lang="php">PHP</button>
          <button type="button" class="lang-tab" data-lang="python">Python</button>
        </div>
        <div class="dropdown lang-dropdown">
          <button class="lang-menu-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="moreLangBtn">More languages</button>
          <ul class="dropdown-menu dropdown-menu-end" id="moreLangMenu">
            <li><button class="dropdown-item" type="button" data-lang="csharp">C#</button></li>
            <li><button class="dropdown-item" type="button" data-lang="go">Go</button></li>
            <li><button class="dropdown-item" type="button" data-lang="http">HTTP</button></li>
            <li><button class="dropdown-item" type="button" data-lang="java">Java</button></li>
            <li><button class="dropdown-item" type="button" data-lang="javascript">JavaScript</button></li>
            <li><button class="dropdown-item" type="button" data-lang="json">JSON</button></li>
            <li><button class="dropdown-item" type="button" data-lang="powershell">PowerShell</button></li>
            <li><button class="dropdown-item" type="button" data-lang="ruby">Ruby</button></li>
            <li><button class="dropdown-item" type="button" data-lang="swift">Swift</button></li>
          </ul>
        </div>
      </div>
      <div class="docs-mini-grid">
        <div class="docs-mini-card"><span>Endpoint</span><strong><?= e($apiUrl) ?></strong></div>
        <div class="docs-mini-card"><span>Auth</span><strong>Bearer token</strong></div>
        <div class="docs-mini-card"><span>Format</span><strong>JSON in / JSON out</strong></div>
      </div>
      <label for="snippetEditor">Editable example</label>
      <textarea class="form-control docs-editor" id="snippetEditor" spellcheck="false"></textarea>
      <div class="mini-actions mt-3">
        <button class="btn btn-outline-light" type="button" id="copySnippetBtn"><i class="fa-regular fa-copy me-2"></i>Copy snippet</button>
        <button class="btn btn-outline-light" type="button" id="syncSnippetBtn"><i class="fa-solid fa-arrows-rotate me-2"></i>Sync from inputs</button>
      </div>
      <div class="play-inline-note">Snippets use <code>YOUR_API_KEY</code> unless you paste a key. The Run button can use selected stored keys securely without exposing them to the browser.</div>
    </div>
    <pre class="play-output" id="playgroundOutput">No request fired yet.</pre>
  </section>
</div>

<script>
const apiBaseUrl = <?= json_encode($apiUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const playgroundRunUrl = <?= json_encode($playgroundRunUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const playgroundCsrfToken = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const defaultSnippetKey = <?= json_encode($snippetKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const keyEl = document.getElementById('playgroundKey');
const customKeyEl = document.getElementById('playgroundCustomKey');
const systemPromptEl = document.getElementById('playgroundSystemPrompt');
const temperatureEl = document.getElementById('playgroundTemperature');
const topPEl = document.getElementById('playgroundTopP');
const topKEl = document.getElementById('playgroundTopK');
const promptEl = document.getElementById('playgroundPrompt');
const bodyEl = document.getElementById('playgroundBody');
const thinkEl = document.getElementById('playgroundThink');
const snippetEl = document.getElementById('snippetEditor');
const outputEl = document.getElementById('playgroundOutput');
const primaryLangButtons = Array.from(document.querySelectorAll('.lang-tab'));
const moreLangButtons = Array.from(document.querySelectorAll('#moreLangMenu [data-lang]'));
const moreLangBtn = document.getElementById('moreLangBtn');
let activeLanguage = 'shell';

function maskBearerKey(value) {
  const key = String(value || '').trim();
  if (!key) return '';
  const prefix = key.startsWith('rgpt_team_') ? 'rgpt_team_' : (key.startsWith('rk_live_') ? 'rk_live_' : (key.startsWith('rgpt_') ? 'rgpt_' : key.slice(0, Math.min(8, key.length))));
  return prefix + '****' + key.slice(-4);
}
function selectedKeyLabel() {
  if (!keyEl) return '';
  const option = keyEl.options[keyEl.selectedIndex];
  if (!option) return '';
  if (option.value === '__paste__') return customKeyEl?.value?.trim() || 'PASTE_YOUR_API_KEY';
  return option.dataset.masked || option.textContent.trim() || 'SELECTED_SERVER_KEY';
}
function syncCustomKeyVisibility() {
  if (!customKeyEl || !keyEl) return;
  customKeyEl.classList.toggle('d-none', keyEl.value !== '__paste__');
}
function numericValue(input, fallback) {
  const value = Number(input?.value);
  return Number.isFinite(value) ? value : fallback;
}
function buildDefaultBody() {
  return {
    system_prompt: systemPromptEl?.value ?? '',
    messages: [{ role: 'user', content: promptEl?.value.trim() || '' }],
    think: !!thinkEl?.checked,
    temperature: numericValue(temperatureEl, 1.0),
    top_p: numericValue(topPEl, 0.95),
    top_k: Math.trunc(numericValue(topKEl, 64))
  };
}
function readBodyJson() { try { return JSON.parse(bodyEl?.value || '{}'); } catch (_) { return null; } }
function syncBodyFromPrompt() { if (bodyEl) bodyEl.value = JSON.stringify(buildDefaultBody(), null, 2); }
function normalizeJsonString(value) { return JSON.stringify(JSON.parse(value), null, 2); }
function selectedKeyRef() {
  return keyEl?.value?.trim() || '';
}
function selectedBearerKey() {
  const selected = selectedKeyRef();
  if (selected === '__paste__') return customKeyEl?.value?.trim() || '';
  return defaultSnippetKey || 'YOUR_API_KEY';
}
function selectedPlainKey() {
  return selectedKeyRef() === '__paste__' ? (customKeyEl?.value?.trim() || '') : '';
}
function currentRequestParts() {
  const key = selectedBearerKey();
  const rawBody = bodyEl?.value?.trim() || JSON.stringify(buildDefaultBody(), null, 2);
  return { key, rawBody, endpoint: apiBaseUrl };
}
function generateSnippet(lang) {
  const { key, rawBody, endpoint } = currentRequestParts();
  const escapedSingle = rawBody.replace(/'/g, "'\\''");
  const map = {
    shell: `curl -X POST ${endpoint} \\\n  -H "Content-Type: application/json" \\\n  -H "Authorization: Bearer ${key}" \\\n  -d '${escapedSingle}'`,
    node: `const response = await fetch('${endpoint}', {\n  method: 'POST',\n  headers: {\n    'Content-Type': 'application/json',\n    'Authorization': 'Bearer ${key}'\n  },\n  body: JSON.stringify(${rawBody})\n});\n\nconst data = await response.json();\nconsole.log(data);`,
    php: `<?php\n$payload = ${rawBody};\n\n$ch = curl_init('${endpoint}');\ncurl_setopt_array($ch, [\n    CURLOPT_POST => true,\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_HTTPHEADER => [\n        'Content-Type: application/json',\n        'Authorization: Bearer ${key}',\n    ],\n    CURLOPT_POSTFIELDS => json_encode($payload),\n]);\n\n$response = curl_exec($ch);\ncurl_close($ch);\necho $response;`,
    python: `import json\nimport requests\n\npayload = ${rawBody}\n\nresponse = requests.post(\n    '${endpoint}',\n    headers={\n        'Content-Type': 'application/json',\n        'Authorization': 'Bearer ${key}',\n    },\n    data=json.dumps(payload),\n)\n\nprint(response.json())`,
    csharp: `using System.Net.Http;\nusing System.Text;\n\nvar client = new HttpClient();\nclient.DefaultRequestHeaders.Add("Authorization", "Bearer ${key}");\nvar content = new StringContent(@"${rawBody.replace(/"/g, '""')}", Encoding.UTF8, "application/json");\nvar response = await client.PostAsync("${endpoint}", content);\nConsole.WriteLine(await response.Content.ReadAsStringAsync());`,
    go: `package main\n\nimport (\n  "bytes"\n  "fmt"\n  "io"\n  "net/http"\n)\n\nfunc main() {\n  payload := []byte(${JSON.stringify(rawBody)})\n  req, _ := http.NewRequest("POST", "${endpoint}", bytes.NewBuffer(payload))\n  req.Header.Set("Content-Type", "application/json")\n  req.Header.Set("Authorization", "Bearer ${key}")\n  res, _ := http.DefaultClient.Do(req)\n  body, _ := io.ReadAll(res.Body)\n  fmt.Println(string(body))\n}`,
    http: `POST ${endpoint} HTTP/1.1\nAuthorization: Bearer ${key}\nContent-Type: application/json\n\n${rawBody}`,
    java: `var client = java.net.http.HttpClient.newHttpClient();\nvar request = java.net.http.HttpRequest.newBuilder()\n    .uri(java.net.URI.create("${endpoint}"))\n    .header("Content-Type", "application/json")\n    .header("Authorization", "Bearer ${key}")\n    .POST(java.net.http.HttpRequest.BodyPublishers.ofString(${JSON.stringify(rawBody)}))\n    .build();\n\nvar response = client.send(request, java.net.http.HttpResponse.BodyHandlers.ofString());\nSystem.out.println(response.body());`,
    javascript: `fetch('${endpoint}', {\n  method: 'POST',\n  headers: {\n    'Content-Type': 'application/json',\n    'Authorization': 'Bearer ${key}'\n  },\n  body: JSON.stringify(${rawBody})\n})\n  .then((res) => res.json())\n  .then((data) => console.log(data));`,
    json: rawBody,
    powershell: `$headers = @{\n  Authorization = "Bearer ${key}"\n  'Content-Type' = 'application/json'\n}\n$body = @'\n${rawBody}\n'@\nInvoke-RestMethod -Method Post -Uri '${endpoint}' -Headers $headers -Body $body`,
    ruby: `require 'net/http'\n\nuri = URI('${endpoint}')\nreq = Net::HTTP::Post.new(uri)\nreq['Content-Type'] = 'application/json'\nreq['Authorization'] = 'Bearer ${key}'\nreq.body = <<~JSON\n${rawBody}\nJSON\n\nres = Net::HTTP.start(uri.hostname, uri.port, use_ssl: uri.scheme == 'https') { |http| http.request(req) }\nputs res.body`,
    swift: `let url = URL(string: "${endpoint}")!\nvar request = URLRequest(url: url)\nrequest.httpMethod = "POST"\nrequest.setValue("application/json", forHTTPHeaderField: "Content-Type")\nrequest.setValue("Bearer ${key}", forHTTPHeaderField: "Authorization")\nrequest.httpBody = ${JSON.stringify(rawBody)}.data(using: .utf8)`
  };
  return map[lang] || map.shell;
}
function setActiveLanguage(lang) {
  activeLanguage = lang;
  primaryLangButtons.forEach((button) => button.classList.toggle('active', button.dataset.lang === lang));
  moreLangButtons.forEach((button) => button.classList.toggle('active', button.dataset.lang === lang));
  const selectedMore = moreLangButtons.find((button) => button.dataset.lang === lang);
  if (moreLangBtn) {
    moreLangBtn.classList.toggle('active', !!selectedMore);
    moreLangBtn.textContent = selectedMore ? selectedMore.textContent : 'More languages';
  }
  if (snippetEl) snippetEl.value = generateSnippet(lang);
}
function syncBodyOptionsFromControls() {
  const body = readBodyJson() || buildDefaultBody();
  body.system_prompt = systemPromptEl?.value ?? '';
  body.temperature = numericValue(temperatureEl, 1.0);
  body.top_p = numericValue(topPEl, 0.95);
  body.top_k = Math.trunc(numericValue(topKEl, 64));
  body.think = !!thinkEl?.checked;
  if (bodyEl) bodyEl.value = JSON.stringify(body, null, 2);
}
function syncSnippetFromInputs() {
  if (bodyEl) {
    try { bodyEl.value = normalizeJsonString(bodyEl.value || JSON.stringify(buildDefaultBody(), null, 2)); } catch (_) {}
  }
  setActiveLanguage(activeLanguage);
}
async function runRequestFromState() {
  const body = readBodyJson();
  const keyRef = selectedKeyRef();
  const keyPlain = selectedPlainKey();
  if ((!keyRef || keyRef === '__paste__') && !keyPlain) { outputEl.textContent = 'Choose a stored key or paste a bearer key.'; return; }
  if (!body) { outputEl.textContent = 'Need a valid JSON body.'; return; }
  outputEl.textContent = 'Running request...';
  try {
    const response = await fetch(playgroundRunUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': playgroundCsrfToken
      },
      credentials: 'same-origin',
      body: JSON.stringify({ key_ref: keyRef, key_plain: keyPlain, body })
    });
    const text = await response.text();
    try { outputEl.textContent = JSON.stringify(JSON.parse(text), null, 2); } catch (_) { outputEl.textContent = text; }
  } catch (error) { outputEl.textContent = 'Request failed: ' + (error?.message || 'Unknown error'); }
}
function shellUnquoteSingleQuoted(value) {
  // curl snippets are generated as: -d 'JSON', with embedded apostrophes encoded as '\''
  // That shell-safe sequence must be turned back into a plain apostrophe before JSON.parse().
  return String(value || '').replace(/'\\''/g, "'");
}
function extractShellJsonPayload(rawSnippet) {
  const dataFlagMatch = rawSnippet.match(/(?:^|\s)(?:-d|--data|--data-raw)\s+'/m);
  if (!dataFlagMatch) return null;

  let index = dataFlagMatch.index + dataFlagMatch[0].length;
  let payload = '';
  while (index < rawSnippet.length) {
    if (rawSnippet.startsWith("'\\''", index)) {
      payload += "'";
      index += 4;
      continue;
    }
    if (rawSnippet[index] === "'") {
      return payload;
    }
    payload += rawSnippet[index];
    index += 1;
  }
  return null;
}
function parseShellSnippet(rawSnippet) {
  const authMatch = rawSnippet.match(/Authorization:\s*Bearer\s+([^"'\n\r]+)/i);
  const urlMatch = rawSnippet.match(/curl(?:\s+-X\s+\w+)?\s+([^\s\\]+)/);
  const payload = extractShellJsonPayload(rawSnippet);
  if (!authMatch || payload === null) return null;
  return { endpoint: (urlMatch?.[1] || apiBaseUrl).trim(), key: authMatch[1].trim(), body: JSON.parse(shellUnquoteSingleQuoted(payload)) };
}
async function runCurrentSnippet() {
  if (activeLanguage === 'shell' && snippetEl && selectedKeyRef() === '__paste__') {
    try {
      const parsed = parseShellSnippet(snippetEl.value);
      if (parsed && parsed.key) {
        if (customKeyEl) customKeyEl.value = parsed.key;
        if (bodyEl) bodyEl.value = JSON.stringify(parsed.body, null, 2);
      }
    } catch (_) {}
  }
  await runRequestFromState();
}
document.getElementById('playgroundFill')?.addEventListener('click', () => { if (promptEl) promptEl.value = 'Create a concise Ruby example that calls this API and prints the JSON response nicely.'; syncBodyFromPrompt(); syncSnippetFromInputs(); });
document.getElementById('resetBodyBtn')?.addEventListener('click', () => { syncBodyFromPrompt(); syncSnippetFromInputs(); });
document.getElementById('runSnippetBtn')?.addEventListener('click', runCurrentSnippet);
document.getElementById('copySnippetBtn')?.addEventListener('click', (event) => { window.copyApiText(snippetEl?.value || '', event.currentTarget); });
document.getElementById('syncSnippetBtn')?.addEventListener('click', syncSnippetFromInputs);
keyEl?.addEventListener('input', () => { syncCustomKeyVisibility(); syncSnippetFromInputs(); });
customKeyEl?.addEventListener('input', syncSnippetFromInputs);
promptEl?.addEventListener('input', () => { const body = readBodyJson(); if (body && Array.isArray(body.messages) && body.messages[0]) { body.messages[0].content = promptEl.value; bodyEl.value = JSON.stringify(body, null, 2); } syncSnippetFromInputs(); });
thinkEl?.addEventListener('change', () => { syncBodyOptionsFromControls(); syncSnippetFromInputs(); });
systemPromptEl?.addEventListener('input', () => { syncBodyOptionsFromControls(); syncSnippetFromInputs(); });
temperatureEl?.addEventListener('input', () => { syncBodyOptionsFromControls(); syncSnippetFromInputs(); });
topPEl?.addEventListener('input', () => { syncBodyOptionsFromControls(); syncSnippetFromInputs(); });
topKEl?.addEventListener('input', () => { syncBodyOptionsFromControls(); syncSnippetFromInputs(); });
bodyEl?.addEventListener('input', syncSnippetFromInputs);
primaryLangButtons.forEach((button) => button.addEventListener('click', () => setActiveLanguage(button.dataset.lang)));
moreLangButtons.forEach((button) => button.addEventListener('click', () => setActiveLanguage(button.dataset.lang)));
syncCustomKeyVisibility();
syncBodyFromPrompt();
setActiveLanguage('shell');
</script>
<?php api_footer(); ?>
