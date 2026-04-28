<?php
declare(strict_types=1);

function rook_ai_providers(): array
{
    return [
        'ollama' => ['name'=>'Ollama', 'kind'=>'ollama', 'model'=>'gemma4:e4b', 'endpoint'=>'http://127.0.0.1:11434/api/chat', 'models_endpoint'=>'http://127.0.0.1:11434/api/tags', 'needs_key'=>false, 'note'=>'Local/self-hosted Ollama chat endpoint.'],
        'openai' => ['name'=>'OpenAI', 'kind'=>'openai', 'model'=>'gpt-4.1-mini', 'endpoint'=>'https://api.openai.com/v1/chat/completions', 'models_endpoint'=>'https://api.openai.com/v1/models', 'needs_key'=>true, 'note'=>'OpenAI Chat Completions API.'],
        'anthropic' => ['name'=>'Anthropic Claude', 'kind'=>'anthropic', 'model'=>'claude-3-5-sonnet-latest', 'endpoint'=>'https://api.anthropic.com/v1/messages', 'models_endpoint'=>'https://api.anthropic.com/v1/models', 'needs_key'=>true, 'note'=>'Anthropic Messages API.'],
        'gemini' => ['name'=>'Google Gemini', 'kind'=>'openai', 'model'=>'gemini-1.5-flash', 'endpoint'=>'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', 'models_endpoint'=>'https://generativelanguage.googleapis.com/v1beta/models', 'needs_key'=>true, 'note'=>'Gemini via Google OpenAI-compatible endpoint.'],
        'mistral' => ['name'=>'Mistral AI', 'kind'=>'openai', 'model'=>'mistral-small-latest', 'endpoint'=>'https://api.mistral.ai/v1/chat/completions', 'models_endpoint'=>'https://api.mistral.ai/v1/models', 'needs_key'=>true, 'note'=>'Mistral chat completions endpoint.'],
        'cohere' => ['name'=>'Cohere', 'kind'=>'openai', 'model'=>'command-r', 'endpoint'=>'https://api.cohere.com/compatibility/v1/chat/completions', 'models_endpoint'=>'https://api.cohere.com/compatibility/v1/models', 'needs_key'=>true, 'note'=>'Cohere OpenAI-compatible endpoint.'],
        'groq' => ['name'=>'Groq', 'kind'=>'openai', 'model'=>'llama-3.1-8b-instant', 'endpoint'=>'https://api.groq.com/openai/v1/chat/completions', 'models_endpoint'=>'https://api.groq.com/openai/v1/models', 'needs_key'=>true, 'note'=>'Groq OpenAI-compatible endpoint.'],
        'perplexity' => ['name'=>'Perplexity', 'kind'=>'openai', 'model'=>'sonar', 'endpoint'=>'https://api.perplexity.ai/chat/completions', 'models_endpoint'=>'https://api.perplexity.ai/models', 'needs_key'=>true, 'note'=>'Perplexity Sonar chat completions endpoint.'],
        'xai' => ['name'=>'xAI', 'kind'=>'openai', 'model'=>'grok-2-latest', 'endpoint'=>'https://api.x.ai/v1/chat/completions', 'models_endpoint'=>'https://api.x.ai/v1/models', 'needs_key'=>true, 'note'=>'xAI OpenAI-compatible endpoint.'],
        'openrouter' => ['name'=>'OpenRouter', 'kind'=>'openai', 'model'=>'openai/gpt-4o-mini', 'endpoint'=>'https://openrouter.ai/api/v1/chat/completions', 'models_endpoint'=>'https://openrouter.ai/api/v1/models', 'needs_key'=>true, 'note'=>'OpenRouter multi-model gateway.'],
    ];
}

function rook_ai_provider_key(): string
{
    $provider = defined('AI_PROVIDER') ? strtolower((string) AI_PROVIDER) : (defined('OLLAMA_URL') ? 'ollama' : 'openai');
    return array_key_exists($provider, rook_ai_providers()) ? $provider : 'ollama';
}
function rook_ai_provider(): array { $providers = rook_ai_providers(); return $providers[rook_ai_provider_key()] ?? $providers['ollama']; }
function rook_ai_kind(): string { return (string) (rook_ai_provider()['kind'] ?? 'openai'); }
function rook_ai_is_ollama(): bool { return rook_ai_kind() === 'ollama'; }
function rook_ai_is_anthropic(): bool { return rook_ai_kind() === 'anthropic'; }
function rook_ai_label(): string { return (string) (rook_ai_provider()['name'] ?? 'AI provider'); }
function rook_ai_model(): string
{
    if (defined('AI_MODEL') && trim((string) AI_MODEL) !== '') return (string) AI_MODEL;
    if (defined('OLLAMA_MODEL') && trim((string) OLLAMA_MODEL) !== '') return (string) OLLAMA_MODEL;
    return (string) (rook_ai_provider()['model'] ?? '');
}
function rook_ai_endpoint(): string
{
    if (defined('AI_BASE_URL') && trim((string) AI_BASE_URL) !== '') return (string) AI_BASE_URL;
    if (rook_ai_is_ollama() && defined('OLLAMA_URL') && trim((string) OLLAMA_URL) !== '') return (string) OLLAMA_URL;
    return (string) (rook_ai_provider()['endpoint'] ?? '');
}
function rook_ai_api_key(): string { return defined('AI_API_KEY') ? trim((string) AI_API_KEY) : ''; }


function rook_ai_models_endpoint_for(string $providerKey, string $chatEndpoint = ''): string
{
    $providers = rook_ai_providers();
    $provider = $providers[$providerKey] ?? null;
    if (!$provider) return '';
    $default = (string)($provider['models_endpoint'] ?? '');
    $chatEndpoint = trim($chatEndpoint);
    if ($providerKey === 'ollama') {
        $base = $chatEndpoint !== '' ? preg_replace('#/api/chat/?$#', '', rtrim($chatEndpoint, '/')) : preg_replace('#/api/tags/?$#', '', rtrim($default, '/'));
        return rtrim((string)$base, '/') . '/api/tags';
    }
    if ($chatEndpoint !== '') {
        if (preg_match('#/chat/completions/?$#', $chatEndpoint)) return preg_replace('#/chat/completions/?$#', '/models', $chatEndpoint) ?: $default;
        if (preg_match('#/messages/?$#', $chatEndpoint)) return preg_replace('#/messages/?$#', '/models', $chatEndpoint) ?: $default;
    }
    return $default;
}

function rook_ai_model_fetch_headers(string $providerKey, string $apiKey): array
{
    $headers = ['Accept: application/json'];
    $apiKey = trim($apiKey);
    if ($apiKey === '') return $headers;
    if ($providerKey === 'anthropic') {
        $headers[] = 'x-api-key: ' . $apiKey;
        $headers[] = 'anthropic-version: 2023-06-01';
    } elseif ($providerKey === 'gemini') {
        $headers[] = 'x-goog-api-key: ' . $apiKey;
    } else {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }
    if ($providerKey === 'openrouter') {
        $headers[] = 'HTTP-Referer: ' . ((isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '') ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) : 'http://localhost');
        $headers[] = 'X-Title: RookGPT';
    }
    return $headers;
}

function rook_ai_extract_model_ids(string $providerKey, array $data): array
{
    $ids = [];
    if ($providerKey === 'ollama') {
        foreach (($data['models'] ?? []) as $model) if (is_array($model) && !empty($model['name'])) $ids[] = (string)$model['name'];
    } elseif ($providerKey === 'gemini') {
        foreach (($data['models'] ?? []) as $model) {
            if (!is_array($model)) continue;
            $name = (string)($model['name'] ?? '');
            if ($name === '') continue;
            $id = preg_replace('#^models/#', '', $name);
            if ($id !== '') $ids[] = $id;
        }
    } else {
        foreach (($data['data'] ?? $data['models'] ?? []) as $model) {
            if (is_string($model)) $ids[] = $model;
            elseif (is_array($model) && !empty($model['id'])) $ids[] = (string)$model['id'];
            elseif (is_array($model) && !empty($model['name'])) $ids[] = (string)$model['name'];
        }
    }
    $ids = array_values(array_unique(array_filter($ids, fn($id) => trim((string)$id) !== '')));
    natcasesort($ids);
    return array_values($ids);
}

function rook_ai_fetch_models(string $providerKey, string $chatEndpoint = '', string $apiKey = '', int $timeout = 20): array
{
    $providers = rook_ai_providers();
    if (!isset($providers[$providerKey])) throw new RuntimeException('Unsupported AI provider.');
    if (!empty($providers[$providerKey]['needs_key']) && trim($apiKey) === '') throw new RuntimeException($providers[$providerKey]['name'] . ' needs an API key before models can be fetched.');
    $url = rook_ai_models_endpoint_for($providerKey, $chatEndpoint);
    if ($url === '' || !preg_match('#^https?://#i', $url)) throw new RuntimeException('Model list endpoint is not configured.');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>rook_ai_model_fetch_headers($providerKey, $apiKey), CURLOPT_TIMEOUT=>$timeout]);
    $raw = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
    if ($raw === false || $status < 200 || $status >= 300) {
        $detail = is_string($raw) && $raw !== '' ? mb_substr($raw, 0, 500) : '';
        throw new RuntimeException(($error ?: ('Could not fetch models. HTTP ' . $status)) . ($detail !== '' ? ': ' . $detail : ''));
    }
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) throw new RuntimeException('The provider returned an invalid model list.');
    $models = rook_ai_extract_model_ids($providerKey, $data);
    if (!$models) throw new RuntimeException('No models were returned for this provider.');
    return $models;
}

function rook_ai_headers(): array
{
    $headers = ['Content-Type: application/json'];
    $key = rook_ai_api_key();
    if ($key !== '') {
        if (rook_ai_is_anthropic()) {
            $headers[] = 'x-api-key: ' . $key;
            $headers[] = 'anthropic-version: 2023-06-01';
        } else {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
    }
    if (rook_ai_provider_key() === 'openrouter') {
        $headers[] = 'HTTP-Referer: ' . ((isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '') ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) : 'http://localhost');
        $headers[] = 'X-Title: RookGPT';
    }
    return $headers;
}

function rook_ai_payload(array $messages, bool $stream, bool $think = false, float $temperature = 0.6, array $extraOptions = []): array
{
    if (rook_ai_is_ollama()) {
        $ollamaOptions = array_replace(['temperature'=>$temperature], $extraOptions);
        $payload = ['model'=>rook_ai_model(), 'messages'=>$messages, 'stream'=>$stream, 'think'=>$think, 'options'=>$ollamaOptions];
        if (isset($ollamaOptions['format'])) { $payload['format'] = $ollamaOptions['format']; unset($payload['options']['format']); }
        return $payload;
    }
    if (rook_ai_is_anthropic()) {
        $system = ''; $anthropicMessages = [];
        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');
            if ($role === 'system') { $system .= ($system === '' ? '' : "\n\n") . (string) ($message['content'] ?? ''); continue; }
            $anthropicMessages[] = ['role'=>$role === 'assistant' ? 'assistant' : 'user', 'content'=>is_array($message['content'] ?? null) ? (string) json_encode($message['content'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) ($message['content'] ?? '')];
        }
        $payload = ['model'=>rook_ai_model(), 'messages'=>$anthropicMessages, 'max_tokens'=>(int)($extraOptions['max_tokens'] ?? 4096), 'temperature'=>$temperature, 'stream'=>false];
        if ($system !== '') $payload['system'] = $system;
        return $payload;
    }
    $payload = ['model'=>rook_ai_model(), 'messages'=>$messages, 'stream'=>$stream, 'temperature'=>$temperature];
    foreach (['top_p','max_tokens','response_format'] as $key) if (array_key_exists($key, $extraOptions)) $payload[$key] = $extraOptions[$key];
    return $payload;
}

function rook_ai_response_text(array $data): string
{
    if (rook_ai_is_ollama()) return (string) ($data['message']['content'] ?? $data['response'] ?? '');
    if (rook_ai_is_anthropic()) { $out=''; foreach (($data['content'] ?? []) as $part) if (is_array($part) && ($part['type'] ?? '') === 'text') $out .= (string) ($part['text'] ?? ''); return $out; }
    return (string) ($data['choices'][0]['message']['content'] ?? $data['choices'][0]['delta']['content'] ?? '');
}
function rook_ai_response_thinking(array $data): string { return rook_ai_is_ollama() ? (string) ($data['message']['thinking'] ?? '') : ''; }
function rook_ai_usage_from_response(array $data): array
{
    if (rook_ai_is_ollama()) return ['prompt_eval_count'=>(int)($data['prompt_eval_count'] ?? 0), 'eval_count'=>(int)($data['eval_count'] ?? 0), 'total_duration'=>(int)($data['total_duration'] ?? 0), 'eval_duration'=>(int)($data['eval_duration'] ?? 0)];
    $usage = $data['usage'] ?? [];
    return ['prompt_eval_count'=>(int)($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0), 'eval_count'=>(int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0), 'total_duration'=>0, 'eval_duration'=>0];
}
function rook_ai_post_json(array $payload, int $timeout = 600): array
{
    $endpoint = rook_ai_endpoint();
    if ($endpoint === '') throw new RuntimeException('AI provider endpoint is not configured.');
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>rook_ai_headers(), CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), CURLOPT_TIMEOUT=>$timeout]);
    $raw = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
    if ($raw === false || $status < 200 || $status >= 300) { $detail = is_string($raw) && $raw !== '' ? mb_substr($raw, 0, 500) : ''; throw new RuntimeException(($error ?: (rook_ai_label() . ' request failed with HTTP ' . $status)) . ($detail !== '' ? ': ' . $detail : '')); }
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) throw new RuntimeException('Invalid ' . rook_ai_label() . ' response.');
    return $data;
}
