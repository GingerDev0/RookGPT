<div align="center">

# ♜ RookGPT

### A self-hosted AI chat workspace with teams chat, configurable plans, APIs, admin control, 2FA, and multiple AI providers.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-mod__rewrite-D22128?style=for-the-badge&logo=apache&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-UI-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![Ollama](https://img.shields.io/badge/Ollama-Local%20AI-000000?style=for-the-badge&logo=ollama&logoColor=white)

![Status](https://img.shields.io/badge/status-active-brightgreen?style=flat-square)
![Self Hosted](https://img.shields.io/badge/self--hosted-yes-blue?style=flat-square)
![API](https://img.shields.io/badge/API-ready-purple?style=flat-square)
![2FA](https://img.shields.io/badge/2FA-supported-orange?style=flat-square)
![Teams](https://img.shields.io/badge/teams-supported-success?style=flat-square)
![Plans](https://img.shields.io/badge/plans-configurable-indigo?style=flat-square)
![Promo Codes](https://img.shields.io/badge/promo--codes-supported-pink?style=flat-square)

</div>

---

## ✨ Overview

**RookGPT** is a self-hosted AI chat workspace built with PHP and MySQL. It provides a polished ChatGPT-style interface, user accounts, configurable subscriptions, team chat, team bot customisation, API keys, 2FA-protected team access, admin controls, promo codes, and a simple authenticated chat API.

The app can run against a local Ollama model or a hosted AI provider such as **OpenAI**, **Anthropic Claude**, **Google Gemini**, **Mistral**, **Cohere**, **Groq**, **Perplexity**, **xAI**, or **OpenRouter**.

## 🚀 Highlights

- 💬 Clean AI chat interface with conversation history
- 🧠 Automatic conversation title and preview generation
- 🖼️ Image upload and pasted-image support for vision-capable models
- 🔍 Bootstrap image gallery for viewing uploaded chat images
- 👤 User registration and login
- 🔐 Single-session enforcement to prevent the same user account being active across multiple devices at once
- 🛡️ Two-factor authentication using authenticator apps and recovery codes
- 👥 Teams area with members, conversations, activity, settings, API keys, and bot settings
- 🤖 Team chat bot powered by the same AI provider/model as the main chat
- 🧩 Team bot customisation with bot name, mention trigger, prompt, style, temperature, Top P, Top K, context size, and reply length controls
- ⌨️ Live “user is typing” indicators in team chat
- ✅ Per-member permission to allow or block bot interaction
- 🔑 Team API key management with copy support
- 🧾 User API key management with masked key previews
- 📊 API usage tracking with support for excluding internal ChatBot keys from stats
- 🌐 Public JSON chat endpoint at `/api`
- 📚 API dashboard, docs, playground, usage charts, and key management pages
- 🧰 Admin dashboard for users, plans, promo codes, API keys, notifications, activity logs, and app settings
- 💼 Fully configurable plan management from `/admin/prices`
- 🏷️ Promo-code management from `/admin/promo`
- 💳 Stripe checkout support with automatic zero-total upgrades when the payable subtotal is £0.00
- 🪄 Installer wizard that writes `config/app.php`, imports the schema, creates the owner admin, and signs the admin in
- 🔗 Extensionless routes through `.htaccess`

## 🧱 Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.1+ |
| Database | MySQL / MariaDB |
| Server | Apache with `mod_rewrite` |
| UI | Bootstrap, Font Awesome |
| Charts | Chart.js |
| AI | Ollama or hosted AI provider |
| Payments | Stripe checkout support |

## ✅ Requirements

Make sure your server has:

- PHP 8.1 or newer recommended
- MySQL/MariaDB database access
- Apache with `.htaccess` and `mod_rewrite` enabled
- PHP extensions:
  - `mysqli`
  - `curl`
  - `json`
  - `mbstring`
  - `fileinfo`
  - `openssl`
- Writable `config/` directory during installation
- Writable upload directory if you use chat image uploads

For local AI, install and run Ollama, then pull a model such as:

```bash
ollama pull gemma4:e4b
```

## ⚡ Quick Start

1. Upload the project files to your web server.
2. Make sure Apache can read the project and write to `config/` while installing.
3. Open the site in your browser.
4. If `config/app.php` does not exist, the app redirects to:

```text
/install/
```

5. Complete the installer:

- Enter database details
- Choose an AI provider
- Select or enter a model
- Create the owner admin account
- Optionally enter a Stripe secret key

The installer imports `rook_chat.sql`, creates the database tables, writes `config/app.php`, creates the owner admin account, and signs the admin in automatically.

> [!IMPORTANT]
> After installation, protect or remove the installer route in production if your deployment process allows it.

## ⚙️ Manual Configuration

You can configure the app manually by copying:

```text
config/app.example.php
```

to:

```text
config/app.php
```

Then edit the values:

```php
defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_NAME') || define('DB_NAME', 'rook_chat');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

defined('AI_PROVIDER') || define('AI_PROVIDER', 'ollama');
defined('AI_BASE_URL') || define('AI_BASE_URL', 'http://127.0.0.1:11434/api/chat');
defined('AI_MODEL') || define('AI_MODEL', 'gemma4:e4b');
defined('AI_API_KEY') || define('AI_API_KEY', '');

defined('STRIPE_SECRET_KEY') || define('STRIPE_SECRET_KEY', '');
defined('APP_NAME') || define('APP_NAME', 'RookGPT');
defined('APP_TAGLINE') || define('APP_TAGLINE', 'Professional AI assistant');
```

Plan definitions are also stored in `config/app.php` using `ROOK_PLAN_DEFINITIONS`, allowing custom plan names, prices, limits, and feature gates.

Then import the schema:

```bash
mysql -u your_user -p rook_chat < rook_chat.sql
```

## 🤖 Supported AI Providers

RookGPT includes provider presets for:

| Provider | Default model | Notes |
|---|---:|---|
| Ollama | `gemma4:e4b` | Local/self-hosted default |
| OpenAI | `gpt-4.1-mini` | Chat Completions API |
| Anthropic Claude | `claude-3-5-sonnet-latest` | Messages API |
| Google Gemini | `gemini-1.5-flash` | OpenAI-compatible Gemini endpoint |
| Mistral AI | `mistral-small-latest` | Chat completions |
| Cohere | `command-r` | OpenAI-compatible endpoint |
| Groq | `llama-3.1-8b-instant` | OpenAI-compatible endpoint |
| Perplexity | `sonar` | Sonar chat completions |
| xAI | `grok-2-latest` | OpenAI-compatible endpoint |
| OpenRouter | `openai/gpt-4o-mini` | Multi-model gateway |

The installer and admin settings page can fetch available models from the selected provider when the endpoint and API key are valid.

## 🧭 Routes

| Route | Purpose |
|---|---|
| `/` | Main chat app |
| `/install/` | Installation wizard |
| `/admin/` | Admin dashboard |
| `/admin/prices` | Plan and pricing management |
| `/admin/promo` | Promo-code management |
| `/api/` | API dashboard |
| `/api` | JSON chat API endpoint |
| `/api/docs` | API documentation |
| `/api/playground` | API playground |
| `/api/keys` | API key management |
| `/api/usage` | API usage analytics |
| `/teams/` | Teams dashboard |
| `/teams/chat` | Team chat |
| `/teams/members` | Team member management |
| `/teams/bot-settings` | Team bot customisation |
| `/teams/api-keys` | Team API key management |
| `/upgrade` | Upgrade/cart page |
| `/share` | Shared conversation route |
| `/terms` | Terms of Service |
| `/privacy` | Privacy Policy |

## 🔌 API Usage

The public API endpoint is:

```text
POST /api
```

Use a bearer token generated from the API dashboard.

### Example Request

```bash
curl -X POST https://your-domain.com/api \
  -H "Authorization: Bearer rgpt_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "messages": [
      {"role": "user", "content": "Write a short welcome message for my app."}
    ],
    "temperature": 0.8,
    "top_p": 0.95,
    "think": false
  }'
```

### Example Response

```json
{
  "ok": true,
  "provider": "Ollama",
  "model": "gemma4:e4b",
  "message": "Welcome to your new app — fast, focused, and ready to help.",
  "thinking": "",
  "usage": {
    "prompt_eval_count": 42,
    "eval_count": 18
  }
}
```

### Request Body

| Field | Type | Required | Description |
|---|---:|---:|---|
| `messages` | array | Yes | Conversation messages with `role` and `content` |
| `system_prompt` | string | No | Extra lower-priority instructions for the assistant |
| `temperature` | number | No | Defaults to `1.0`; must be between `0` and `2` |
| `top_p` | number | No | Defaults to `0.95`; must be between `0` and `1` |
| `top_k` | integer | No | Ollama option; defaults to `64` |
| `think` | boolean | No | Enables model thinking output when supported |

## 💼 Plans, Pricing, and Feature Gates

Plans are configurable from:

```text
/admin/prices
```

Admin users can:

- Add plans
- Edit plans
- Disable plans
- Delete plans
- Set plan labels, slugs, prices, descriptions, and display order
- Configure usage limits
- Control feature gates per plan

Supported feature gates include:

| Feature | Configurable |
|---|---:|
| Thinking/reasoning | Yes |
| API access | Yes |
| API daily calls | Yes |
| AI personality controls | Yes |
| Conversation rename | Yes |
| Share snapshots | Yes |
| Teams access | Yes |
| Team sharing | Yes |
| Message limits | Yes |
| Conversation limits | Yes |

Disabled plans are hidden from the upgrade modal and upgrade comparison table.

Deleting a plan can move affected users back to Free and disable promo codes targeting that plan.

## 🏷️ Promo Codes

Promo codes can be managed from:

```text
/admin/promo
```

Promo codes can be used during upgrade checkout and may support:

- Percentage discounts
- Fixed-value discounts
- Plan-specific targeting
- Enable/disable state
- Usage tracking

If the final payable upgrade subtotal is `£0.00`, the account is upgraded immediately without sending the user to Stripe.

## 👥 Teams

The teams area includes:

- Team dashboard
- Team chat
- Members
- Conversations
- Activity
- Settings
- Bot Settings
- Team API keys

Team features can require 2FA when enabled from `/admin/settings`.

## 🤖 Team Bot Settings

Team bot customisation is available from:

```text
/teams/bot-settings
```

The team bot uses the same configured AI provider and model as the main chat. It does not require a team API key.

Configurable options include:

- Bot enabled/disabled state
- Bot name
- Mention trigger
- Custom prompt
- Response style
- Temperature
- Top P
- Top K
- Context message count
- Max reply characters

Team owners can also control whether each member can interact with the bot from:

```text
/teams/members
```

Members without bot permission can still send normal team messages, but bot mentions will not trigger a response.

## 🔐 Two-Factor Authentication

RookGPT supports TOTP-based 2FA using apps such as Google Authenticator, Microsoft Authenticator, Authy, 1Password, or any compatible authenticator app.

Team features can require 2FA when enabled in `/admin/settings`. Users can enable 2FA from account settings by scanning the QR code and saving recovery codes.

## 📁 Project Structure

```text
admin/                 Admin dashboard pages, plan management, promo codes
api/                   API dashboard, docs, keys, playground, usage
config/                App configuration
install/               Web installer
lib/                   Shared provider, plan, security, image, and install helpers
teams/                 Team workspace, chat, bot settings, members, API keys
api.php                Public JSON API endpoint
index.php              Main chat application
rook.css               Shared app styling
rook_chat.sql          Database schema
upgrade.php            Upgrade/cart page
terms.php              Terms page
privacy.php            Privacy page
.htaccess              Apache routing rules
```

## 🛡️ Security Notes

- Keep `config/app.php` private.
- Use HTTPS in production.
- Use strong admin passwords.
- Enable 2FA before using team features when the admin setting requires it.
- Keep PHP, MySQL/MariaDB, Apache, and dependencies updated.
- Ensure uploads are served safely and only expected image MIME types are accepted.
- Restrict access to `/install/` after setup.
- Rotate exposed API keys or secrets immediately.
- Do not commit production `config/app.php` files.

## 🧪 Development Notes

- The app uses `mysqli` prepared statements for database access.
- `.htaccess` preserves the `Authorization` header so bearer tokens work with Apache/PHP.
- `/api` is intentionally the JSON endpoint, while `/api/` is the web dashboard.
- Explicit `.php` browser requests are redirected to extensionless URLs for cleaner routes.
- Provider model fetching is handled in `lib/ai_providers.php`.
- Plan logic is centralised through shared plan helpers.
- Team bot replies use the app’s configured AI provider directly.
- Internal API keys named `ChatBot` can be excluded from API usage stats.

## 🗺️ Coming Soon

Planned ideas for future RookGPT releases include:

- 🧠 **Per-user AI profiles** for saved tones, prompts, temperatures, and preferred providers
- 📎 **File-aware chat** with document upload, summarisation, and retrieval-assisted answers
- 🔎 **Workspace search** across conversations, team messages, shared snapshots, and uploaded files
- 🧑‍💼 **Role-based admin permissions** so owners can delegate support, billing, and moderation tasks
- 🏢 **Multi-team organisations** with organisation-level billing, settings, and audit views
- 📡 **Webhooks and event logs** for API usage, billing events, team activity, and security alerts
- 🧾 **Advanced billing options** including metered API usage, invoices, trials, and coupons
- 🛠️ **Plugin/tool calling support** for custom actions, internal tools, and provider-native function calling
- 🌍 **Internationalisation** for multiple languages, regional date formats, and custom currency display
- 📱 **Progressive Web App improvements** for installable mobile/desktop usage and richer notifications
- 🧪 **Model comparison playground** to test prompts across multiple providers side by side
- 🛡️ **Expanded security controls** such as IP allowlists, device management, and admin audit exports

> [!NOTE]
> These items are roadmap ideas, not guaranteed release commitments. Feature priority may change based on feedback, security requirements, and project direction.

## 🤝 Contributing

Contributions, issues, and feature suggestions are welcome.

Before opening a pull request:

1. Test the installer.
2. Test login, registration, and chat.
3. Test plan upgrades, disabled plans, and promo codes.
4. Test team chat, typing indicators, bot settings, and member bot permissions.
5. Check that `/api` still requires a valid bearer token.
6. Make sure no secrets are committed.

## 📜 License

![License: MIT](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

---

<div align="center">

Made for builders who want a sharp, self-hosted AI workspace.

</div>
