<?php
declare(strict_types=1);

function rook_default_plan_definitions(): array
{
    return [
        'free' => [
            'slug' => 'free', 'label' => 'Free', 'tagline' => 'Try RookGPT', 'description' => 'Basic chat access for trying the workspace.',
            'price_gbp' => 0.00, 'enabled' => true, 'rank' => 0, 'recommended' => false,
            'max_conversations' => 1, 'max_messages_daily' => 10, 'max_messages_per_conversation' => 0, 'max_messages_total' => 0,
            'thinking_available' => false, 'api_access' => false, 'api_call_limit' => 0,
            'custom_personality' => false, 'conversation_rename' => false, 'share_snapshots' => false, 'team_access' => false, 'team_sharing' => false,
            'features' => ['1 chat', '10 messages per day'],
        ],
        'plus' => [
            'slug' => 'plus', 'label' => 'Plus', 'tagline' => 'Reasoning unlocked', 'description' => 'Smarter day-to-day chat, reasoning, and personality controls without going full API goblin.',
            'price_gbp' => defined('PLAN_PLUS_PRICE_GBP') ? (float) PLAN_PLUS_PRICE_GBP : 9.00, 'enabled' => true, 'rank' => 10, 'recommended' => false,
            'max_conversations' => 10, 'max_messages_daily' => 0, 'max_messages_per_conversation' => 200, 'max_messages_total' => 0,
            'thinking_available' => true, 'api_access' => false, 'api_call_limit' => 0,
            'custom_personality' => true, 'conversation_rename' => true, 'share_snapshots' => true, 'team_access' => false, 'team_sharing' => false,
            'features' => ['Higher chat limits', 'Reasoning controls', 'Custom Rook personality', 'Conversation rename and sharing'],
        ],
        'pro' => [
            'slug' => 'pro', 'label' => 'Pro', 'tagline' => 'API + serious limits', 'description' => 'Best for power users who want higher limits, API access, and room to do real work without hitting ceilings.',
            'price_gbp' => defined('PLAN_PRO_PRICE_GBP') ? (float) PLAN_PRO_PRICE_GBP : 19.00, 'enabled' => true, 'rank' => 20, 'recommended' => true,
            'max_conversations' => 50, 'max_messages_daily' => 0, 'max_messages_per_conversation' => 1000, 'max_messages_total' => 0,
            'thinking_available' => true, 'api_access' => true, 'api_call_limit' => 1000,
            'custom_personality' => true, 'conversation_rename' => true, 'share_snapshots' => true, 'team_access' => false, 'team_sharing' => false,
            'features' => ['Everything in Plus', 'API keys dashboard', 'Request analytics', 'Higher usage limits'],
        ],
        'business' => [
            'slug' => 'business', 'label' => 'Business', 'tagline' => 'Teams + heavy usage', 'description' => 'Shared team access, broad limits, API access, and less chance of running into annoying caps.',
            'price_gbp' => defined('PLAN_BUSINESS_PRICE_GBP') ? (float) PLAN_BUSINESS_PRICE_GBP : 49.00, 'enabled' => true, 'rank' => 30, 'recommended' => false,
            'max_conversations' => 250, 'max_messages_daily' => 0, 'max_messages_per_conversation' => 5000, 'max_messages_total' => 0,
            'thinking_available' => true, 'api_access' => true, 'api_call_limit' => 0,
            'custom_personality' => true, 'conversation_rename' => true, 'share_snapshots' => true, 'team_access' => true, 'team_sharing' => true,
            'features' => ['Everything in Pro', 'Team workspace', 'Shared team conversations', 'Team API key management'],
        ],
    ];
}

function rook_plan_definitions(): array
{
    $defaults = rook_default_plan_definitions();
    $hasConfiguredPlans = defined('ROOK_PLAN_DEFINITIONS') && is_array(ROOK_PLAN_DEFINITIONS);
    $configured = $hasConfiguredPlans ? ROOK_PLAN_DEFINITIONS : [];

    // Once the admin plan manager writes ROOK_PLAN_DEFINITIONS, use it as the
    // source of truth. Otherwise deleted built-in plans are added back from defaults.
    $plans = $hasConfiguredPlans ? [] : $defaults;
    foreach ($configured as $slug => $plan) {
        if (!is_array($plan)) continue;
        $cleanSlug = rook_plan_slug((string)($plan['slug'] ?? $slug));
        if ($cleanSlug === '') continue;
        $base = $hasConfiguredPlans ? ['slug' => $cleanSlug] : ($plans[$cleanSlug] ?? ['slug' => $cleanSlug]);
        $plans[$cleanSlug] = array_merge($base, $plan, ['slug' => $cleanSlug]);
    }
    if (!isset($plans['free'])) {
        $plans['free'] = $defaults['free'];
    }
    foreach ($plans as $slug => $plan) {
        $plans[$slug]['slug'] = $slug;
        $plans[$slug]['label'] = trim((string)($plan['label'] ?? ucfirst($slug))) ?: ucfirst($slug);
        $plans[$slug]['tagline'] = trim((string)($plan['tagline'] ?? ''));
        $plans[$slug]['description'] = trim((string)($plan['description'] ?? ''));
        $plans[$slug]['price_gbp'] = max(0.0, (float)($plan['price_gbp'] ?? 0));
        $plans[$slug]['enabled'] = !empty($plan['enabled']);
        $plans[$slug]['rank'] = (int)($plan['rank'] ?? 0);
        $plans[$slug]['recommended'] = !empty($plan['recommended']);
        foreach (['max_conversations','max_messages_daily','max_messages_per_conversation','max_messages_total','api_call_limit'] as $key) $plans[$slug][$key] = max(0, (int)($plan[$key] ?? 0));
        foreach (['thinking_available','api_access','custom_personality','conversation_rename','share_snapshots','team_access','team_sharing'] as $key) $plans[$slug][$key] = !empty($plan[$key]);
        $features = $plan['features'] ?? [];
        if (is_string($features)) $features = preg_split('/\r\n|\r|\n/', $features) ?: [];
        $plans[$slug]['features'] = array_values(array_filter(array_map('trim', is_array($features) ? $features : []), fn($v) => $v !== ''));
    }
    if (isset($plans['free'])) { $plans['free']['enabled'] = true; $plans['free']['price_gbp'] = 0.0; $plans['free']['rank'] = 0; }
    uasort($plans, fn($a, $b) => ((int)($a['rank'] ?? 0) <=> (int)($b['rank'] ?? 0)) ?: strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));
    return $plans;
}

function rook_plan_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    return trim($value, '-_');
}

function rook_plan(string $slug): array
{
    $plans = rook_plan_definitions();
    return $plans[$slug] ?? ($plans['free'] ?? rook_default_plan_definitions()['free']);
}

function rook_plan_limits(string $slug): array { return rook_plan($slug); }
function rook_plan_label(string $slug): string { return (string)(rook_plan($slug)['label'] ?? 'Free'); }
function rook_plan_price_gbp(string $slug, float $fallback = 0.0): float { $plan = rook_plan($slug); return max(0.0, (float)($plan['price_gbp'] ?? $fallback)); }
function rook_plan_supports(string $slug, string $feature): bool { return !empty(rook_plan($slug)[$feature]); }
function rook_enabled_paid_plans(): array { return array_filter(rook_plan_definitions(), fn($p, $slug) => $slug !== 'free' && !empty($p['enabled']), ARRAY_FILTER_USE_BOTH); }
function rook_plan_rank(string $slug): int { return (int)(rook_plan($slug)['rank'] ?? 0); }
function rook_first_enabled_plan_with(string $feature, string $fallback = 'free'): string { foreach (rook_plan_definitions() as $slug => $plan) { if ($slug !== 'free' && !empty($plan['enabled']) && !empty($plan[$feature])) return $slug; } return array_key_exists($fallback, rook_plan_definitions()) ? $fallback : 'free'; }
function rook_message_allowance_label(array $plan): string
{
    if (!empty($plan['max_messages_daily'])) return (int)$plan['max_messages_daily'] . ' / day';
    if (!empty($plan['max_messages_total'])) return (int)$plan['max_messages_total'] . ' total';
    if (!empty($plan['max_messages_per_conversation'])) return (int)$plan['max_messages_per_conversation'] . ' / chat';
    return 'Unlimited';
}
