<?php
/**
 * MAYA VISION · /api/maya_vision · 2026-05-15
 *
 * Mo's directive 2026-05-15: "take a look at NVIDIA or hugging face and check if
 * there are any [vision models] you can wire to Maya. the vision model integration."
 *
 * Takes a base64 image (typically captured via the bridge /screenshot-screen) + a
 * question, routes to NVIDIA NIM's meta/llama-3.2-90b-vision-instruct (free tier),
 * returns the answer. Maya can now SEE what Mo sees.
 *
 * POST {
 *   question: "what's on my screen right now?" (default: "describe what you see"),
 *   image_base64: "iVBORw0KGgo..." (PNG bytes base64),
 *   model: optional override (default meta/llama-3.2-90b-vision-instruct),
 *   pin: "210379" (PIN-gated - this uses Mo's NVIDIA quota)
 * }
 *
 * PHP 7.4 only.
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

const ENV_FILE  = '/home/iamsuperio.cloud/public_html/api/.maya_master_keys.env';
const LOG_FILE  = '/home/iamsuperio.cloud/public_html/data/maya_vision.log';
const PIN_NUM    = '210379';
const PIN_PHRASE = 'BuddyBoots2!';
const DEFAULT_MODEL = 'meta/llama-3.2-90b-vision-instruct';

function v_out(array $p, int $code = 200): void {
    http_response_code($code);
    echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
function v_pin_ok(?string $pin): bool {
    if (!$pin) return false;
    return hash_equals(PIN_NUM, $pin) || hash_equals(PIN_PHRASE, $pin);
}
function v_env(string $key): ?string {
    if (!file_exists(ENV_FILE)) return null;
    foreach (file(ENV_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (strpos($line, $key . '=') === 0) return trim(substr($line, strlen($key . '=')));
    }
    return null;
}
function v_nim_keys(): array {
    /* Collect all NVIDIA_NIM* keys from env so we can rotate on rate-limit */
    $keys = [];
    if (!file_exists(ENV_FILE)) return $keys;
    foreach (file(ENV_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (preg_match('/^(NVIDIA_NIM[A-Z0-9_]*|NV_NIM[A-Z0-9_]*|NIM_KEY[A-Z0-9_]*)\s*=\s*(nvapi-\S+)/', $line, $m)) {
            $keys[] = $m[2];
        }
    }
    return array_unique($keys);
}
function v_log(string $event, array $ctx = []): void {
    $entry = ['ts' => date('c'), 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'event' => $event, 'ctx' => $ctx];
    @file_put_contents(LOG_FILE, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) v_out(['ok' => false, 'error' => 'json body required'], 400);

if (!v_pin_ok($in['pin'] ?? '')) v_out(['ok' => false, 'error' => 'commander pin required'], 401);

$question = trim((string)($in['question'] ?? 'Describe in plain English what you see in this image. Focus on what action Mo might want help with.'));
$image_b64 = (string)($in['image_base64'] ?? '');
$model = (string)($in['model'] ?? DEFAULT_MODEL);

if (strlen($image_b64) < 100) {
    v_out(['ok' => false, 'error' => 'image_base64 required (at least 100 chars of base64 PNG)'], 400);
}

/* NIM vision-instruct models accept data URI in image_url */
$keys = v_nim_keys();
if (empty($keys)) v_out(['ok' => false, 'error' => 'no NVIDIA_NIM keys in env'], 500);

$payload = [
    'model' => $model,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $question],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . $image_b64]],
            ],
        ],
    ],
    'max_tokens' => 1024,
    'temperature' => 0.4,
    'stream' => false,
];

$last_err = '';
foreach ($keys as $i => $key) {
    $ch = curl_init('https://integrate.api.nvidia.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($code === 200) {
        $j = json_decode($body, true);
        $answer = $j['choices'][0]['message']['content'] ?? '';
        v_log('vision_ok', ['model' => $model, 'key_idx' => $i, 'answer_len' => strlen($answer), 'image_kb' => (int)(strlen($image_b64) / 1024)]);
        v_out([
            'ok' => true,
            'model' => $model,
            'question' => $question,
            'answer' => $answer,
            'usage' => $j['usage'] ?? null,
            'key_used' => $i,
        ]);
    }
    $last_err = "key $i: HTTP $code · " . substr($body, 0, 240);
    v_log('vision_key_failed', ['key_idx' => $i, 'http' => $code, 'err' => substr($body, 0, 200)]);
}

v_out(['ok' => false, 'error' => 'all NIM keys failed', 'last_err' => $last_err, 'tried_keys' => count($keys)], 502);
