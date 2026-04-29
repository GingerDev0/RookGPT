<div align="center">

# ♜ RookGPT

### Self-hosted AI chat workspace for users, teams, APIs, plans, admin control, 2FA, and multiple AI providers.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-mod__rewrite-D22128?style=for-the-badge&logo=apache&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-UI-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![Ollama](https://img.shields.io/badge/Ollama-Local%20AI-000000?style=for-the-badge&logo=ollama&logoColor=white)

![Status](https://img.shields.io/badge/status-active-brightgreen?style=flat-square)
![Self Hosted](https://img.shields.io/badge/self--hosted-yes-blue?style=flat-square)
![API](https://img.shields.io/badge/API-ready-purple?style=flat-square)
![2FA](https://img.shields.io/badge/2FA-supported-orange?style=flat-square)
![License: MIT](https://img.shields.io/badge/License-MIT-green?style=flat-square)

</div>

---

## Overview

**RookGPT** is a self-hosted AI chat workspace built with PHP and MySQL. It provides a ChatGPT-style interface, user accounts, configurable plans, team chat, team bot customisation, API keys, two-factor authentication, admin controls, promo codes, and an authenticated chat API.

RookGPT can use a local Ollama model or hosted providers including OpenAI, Anthropic Claude, Google Gemini, Mistral, Cohere, Groq, Perplexity, xAI, and OpenRouter.

> [!NOTE]
> RookGPT is designed for private self-hosted and team deployments. Review HTTPS, file permissions, upload access, installer access, and provider-key storage before exposing it publicly.

## Features

- AI chat interface with conversation history
- Automatic conversation titles and previews
- Image upload and pasted-image support for vision-capable models
- User registration, login, and single-session enforcement
- Markdown sanitisation for chat, share pages, and team messages
- Authenticated private image routes for uploaded chat images
- Rate limiting for login, 2FA, recovery, and API flows
- TOTP two-factor authentication with recovery codes
- Teams area with chat, members, settings, activity, bot settings, and API keys
- Configurable team bot with custom prompt, style, temperature, Top P, Top K, context size, and reply length
- Per-member permission for bot interaction
- User and team API key management
- Public JSON chat endpoint at `/api`
- API dashboard, documentation, playground, usage charts, and key management
- Admin dashboard for users, plans, promo codes, API keys, notifications, activity logs, and app settings
- Configurable plan and promo-code management
- Stripe checkout support, including zero-total upgrades
- Web installer for configuration, schema import, owner admin creation, and first login
- Extensionless routes through `.htaccess`

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.1+ |
| Database | MySQL / MariaDB |
| Server | Apache with `mod_rewrite` |
| UI | Bootstrap, Font Awesome |
| Charts | Chart.js |
| AI | Ollama or hosted AI provider |
| Payments | Stripe checkout support |

## Requirements

- PHP 8.1 or newer
- MySQL or MariaDB database
- Apache with `.htaccess` and `mod_rewrite` enabled
- PHP extensions: `mysqli`, `curl`, `json`, `mbstring`, `fileinfo`, `openssl`
- Writable `config/` directory during installation
- Writable upload directory if chat image uploads are enabled

For local AI, install and run Ollama, then pull a model, for example:

```bash
ollama pull gemma4:e4b
```

## Quick Start

1. Upload the project files to your web server.
2. Make sure Apache can read the project and write to `config/` during installation.
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
> After installation, protect or remove the installer route in production. Keep `config/app.php`, uploads, logs, backups, and database dumps outside public access.

## Manual Configuration

Copy:

```text
config/app.example.php
```

to:

```text
config/app.php
```

Then update the database, AI provider, Stripe, and app settings.

Example values:

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

Plan definitions are stored in `config/app.php` using `ROOK_PLAN_DEFINITIONS`.

Import the schema:

```bash
mysql -u your_user -p rook_chat < rook_chat.sql
```

## Supported AI Providers

| Provider | Default model | Notes |
|---|---|---|
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

The installer and admin settings page can fetch available models when the selected provider endpoint and API key are valid.

## Main Routes

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

## API Usage

The public API endpoint is:

```text
POST /api
```

Use a bearer token generated from the API dashboard.

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

Example response:

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

## Plans and Promo Codes

Plans are configurable from:

```text
/admin/prices
```

Admin users can create, edit, disable, delete, order, and feature-gate plans. Supported gates include API access, daily API calls, teams access, team sharing, thinking/reasoning, personality controls, sharing, rename support, message limits, and conversation limits.

Promo codes are managed from:

```text
/admin/promo
```

Promo codes can support percentage discounts, fixed-value discounts, plan-specific targeting, usage tracking, and enable/disable state. If the payable upgrade subtotal is `£0.00`, the account is upgraded immediately without Stripe checkout.

## Teams

The teams area includes team chat, members, conversations, activity, settings, bot settings, and team API keys.

The team bot uses the same configured AI provider and model as the main chat and does not require a team API key. Team owners can customise the bot and control which members can interact with it.

Team features can require 2FA when enabled from admin settings.

## Two-Factor Authentication

RookGPT supports TOTP-based 2FA using Google Authenticator, Microsoft Authenticator, Authy, 1Password, or any compatible authenticator app.

Users can enable 2FA from account settings by scanning a QR code and saving recovery codes.

## Project Structure

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

## Security

RookGPT includes safeguards for common self-hosted web app risks:

- Prepared `mysqli` statements across normal data paths
- Sanitised rendered markdown for chat, share pages, and team content
- Authenticated image routes for private uploads
- Server-derived team bot prompts
- Hardened cookie settings and single-session enforcement
- TOTP setup with recovery codes
- Optional 2FA requirement for team access
- CSRF protection for browser POST actions
- cURL hardening and URL validation for outbound provider requests
- Generic user-facing errors
- Rate limits for login, 2FA, recovery-code, API, and sensitive request flows

Recommended production checks:

- Use HTTPS only.
- Block public access to `config/`, `uploads/`, `storage/`, `logs/`, backups, SQL dumps, and local environment files.
- Keep `/install/` locked or removed after setup.
- Use strong owner/admin passwords and enable 2FA.
- Rotate provider/API keys if they have ever been exposed.
- Review custom AI provider endpoints before enabling them.

## Development Notes

- `.htaccess` preserves the `Authorization` header so bearer tokens work with Apache/PHP.
- `/api` is the JSON endpoint; `/api/` is the web dashboard.
- Explicit `.php` browser requests redirect to extensionless URLs.
- Provider model fetching is handled in `lib/ai_providers.php`.
- Plan logic is centralised through shared plan helpers.
- Team bot replies use the app’s configured AI provider directly.
- Internal API keys named `ChatBot` can be excluded from API usage stats.

## Recommended Repository Files

```text
README.md
LICENSE
SECURITY.md
CHANGELOG.md
.gitignore
docs/SECURITY_MODEL.md
docs/DEPLOYMENT.md
```

Suggested `.gitignore` entries:

```gitignore
config/app.php
.env
uploads/
storage/
logs/
backups/
*.sql
```

## Contributing

Contributions, issues, and feature suggestions are welcome.

Before opening a pull request, test the installer, authentication, chat, upgrades, promo codes, teams, API access, image permissions, and make sure no secrets, logs, uploads, backups, or production configs are committed.

## License

Released under the MIT License. Add a `LICENSE` file containing the full MIT License text before publishing a formal release.

---

<div align="center">

Made for builders who want a sharp, self-hosted AI workspace.

</div>
