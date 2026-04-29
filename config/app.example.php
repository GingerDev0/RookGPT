<?php
// Copy to config/app.php or run /install/.
defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_NAME') || define('DB_NAME', 'rook_chat');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('AI_PROVIDER') || define('AI_PROVIDER', 'ollama');
defined('AI_BASE_URL') || define('AI_BASE_URL', 'http://127.0.0.1:11434/api/chat');
defined('AI_MODEL') || define('AI_MODEL', 'gemma4:e4b');
defined('AI_API_KEY') || define('AI_API_KEY', '');
defined('OLLAMA_URL') || define('OLLAMA_URL', AI_BASE_URL);
defined('OLLAMA_MODEL') || define('OLLAMA_MODEL', AI_MODEL);
defined('STRIPE_SECRET_KEY') || define('STRIPE_SECRET_KEY', '');
// Set this once and keep it stable. Changing it will make encrypted team chat history unreadable.
defined('TEAM_CHAT_ENCRYPTION_KEY') || define('TEAM_CHAT_ENCRYPTION_KEY', 'change-me-to-a-64-character-random-secret');
defined('TEAMS_REQUIRE_2FA') || define('TEAMS_REQUIRE_2FA', true);
defined('APP_NAME') || define('APP_NAME', 'RookGPT');
defined('APP_TAGLINE') || define('APP_TAGLINE', 'Professional AI assistant');
defined('PLAN_PLUS_PRICE_GBP') || define('PLAN_PLUS_PRICE_GBP', 9.00);
defined('PLAN_PRO_PRICE_GBP') || define('PLAN_PRO_PRICE_GBP', 19.00);
defined('PLAN_BUSINESS_PRICE_GBP') || define('PLAN_BUSINESS_PRICE_GBP', 49.00);
defined('ANNUAL_DISCOUNT_MONTHS') || define('ANNUAL_DISCOUNT_MONTHS', 2);
// Admin > Plans & pricing writes the complete plan configuration here. Leave empty to use defaults.
defined('ROOK_PLAN_DEFINITIONS') || define('ROOK_PLAN_DEFINITIONS', []);
