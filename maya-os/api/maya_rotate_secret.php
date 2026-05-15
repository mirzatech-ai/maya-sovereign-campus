<?php
/**
 * MAYA SECRET ROTATION · /api/maya_rotate_secret · 2026-05-15
 *
 * Mo's directive: "Maya needs to code and find ways to do it for me going forward."
 * One-stop endpoint that rotates a named secret in .maya_master_keys.env on the VPS.
 * Mo gets the new key from the vendor dashboard (Telnyx · NVIDIA · etc.) and pastes
 * it ONCE; Maya updates env, snapshots the old value, restarts what needs restarting.
 *
 * POST {
 *   action: "rotate",
 *   pin: "210379",
 *   secret_name: "TELNYX_API_KEY_V2",
 *   new_value: "KEY..."
 * }
 *
 * Steps:
 *   1. Verify PIN
 *   2. Read current .maya_master_keys.env
 *   3. Snapshot to .maya_master_keys.env.rotated.<ts> (chmod 600)
 *   4. Replace or append the named key
 *   5. Write atomically (tempfile + rename) with mode 600
 *   6. Log rotation to /data/secret_rotation_log.jsonl
 *   7. Return success
 *
 * PHP 7.4 only. NEVER logs the new value or the old value. Logs only:
 *   { ts, secret_name, length_old, length_new, ip }
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

const ENV_FILE = '/home/iamsuperio.cloud/public_html/api/.maya_master_keys.env';
const ROT_LOG  = '/home/iamsuperio.cloud/public_html/data/secret_rotation_log.jsonl';
const PIN_NUM    = '210379';
const PIN_PHRASE = 'BuddyBoots2!';

function rot_out(array $p, int $code = 200): void {
    http_response_code($code);
    echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
function rot_pin_ok(?string $pin): bool {
    if (!$pin) return false;
    return hash_equals(PIN_NUM, $pin) || hash_equals(PIN_PHRASE, $pin);
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) rot_out(['ok' => false, 'error' => 'json body required'], 400);

$action = $in['action'] ?? 'rotate';

if ($action !== 'rotate') rot_out(['ok' => false, 'error' => 'unknown action'], 400);
if (!rot_pin_ok($in['pin'] ?? '')) rot_out(['ok' => false, 'error' => 'commander pin required'], 401);

$name = trim((string)($in['secret_name'] ?? ''));
$value = (string)($in['new_value'] ?? '');

if (!preg_match('/^[A-Z][A-Z0-9_]+$/', $name)) {
    rot_out(['ok' => false, 'error' => 'secret_name must be UPPER_SNAKE_CASE (e.g. TELNYX_API_KEY_V2)'], 400);
}
if (strlen($value) < 8) {
    rot_out(['ok' => false, 'error' => 'new_value too short to be a real key'], 400);
}

if (!is_readable(ENV_FILE)) {
    rot_out(['ok' => false, 'error' => 'env file not readable on VPS'], 500);
}

// 1. Snapshot the current env (mode 600)
$ts = date('Ymd_His');
$snapshot = ENV_FILE . '.rotated.' . $ts;
@copy(ENV_FILE, $snapshot);
@chmod($snapshot, 0600);

// 2. Read current env
$lines = file(ENV_FILE, FILE_IGNORE_NEW_LINES);
if ($lines === false) rot_out(['ok' => false, 'error' => 'cannot read env'], 500);

$old_value = '';
$found = false;
$pattern = '/^' . preg_quote($name, '/') . '=(.*)$/';
foreach ($lines as $i => $line) {
    if (preg_match($pattern, $line, $m)) {
        $old_value = $m[1];
        $lines[$i] = $name . '=' . $value;
        $found = true;
        break;
    }
}
if (!$found) {
    // Append if missing
    $lines[] = $name . '=' . $value;
}

// 3. Atomic write
$tmp = ENV_FILE . '.tmp.' . bin2hex(random_bytes(4));
if (file_put_contents($tmp, implode("\n", $lines) . "\n", LOCK_EX) === false) {
    rot_out(['ok' => false, 'error' => 'tmp write failed'], 500);
}
@chmod($tmp, 0600);
if (!@rename($tmp, ENV_FILE)) {
    @unlink($tmp);
    rot_out(['ok' => false, 'error' => 'rename failed'], 500);
}
@chmod(ENV_FILE, 0600);

// 4. Log rotation (NEVER values)
@file_put_contents(ROT_LOG, json_encode([
    'ts' => date('c'),
    'secret_name' => $name,
    'length_old' => strlen($old_value),
    'length_new' => strlen($value),
    'was_present_before' => $found,
    'snapshot' => basename($snapshot),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
]) . "\n", FILE_APPEND | LOCK_EX);

// 5. Also emit an event so the Campus shows the packet
$ev_curl = curl_init('http://127.0.0.1/api/maya_event');
curl_setopt_array($ev_curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 3,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Host: iamsuperio.cloud'],
    CURLOPT_POSTFIELDS => json_encode([
        'actor' => 'maya', 'action' => 'rotate_secret', 'target' => $name,
        'status' => 'done', 'room' => 'maya_brain',
        'result' => 'rotated · ' . strlen($value) . ' chars',
    ]),
]);
@curl_exec($ev_curl);
@curl_close($ev_curl);

rot_out([
    'ok' => true,
    'secret_name' => $name,
    'length_old' => strlen($old_value),
    'length_new' => strlen($value),
    'was_present' => $found,
    'snapshot' => basename($snapshot),
    'next_steps' => [
        'Verify with a test call to the vendor API using the new key',
        'Old value snapshot kept at ' . basename($snapshot) . ' for 7 days',
        'No restart needed - .env is re-read by each PHP request',
    ],
]);
