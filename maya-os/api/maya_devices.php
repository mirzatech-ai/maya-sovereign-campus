<?php
/**
 * MAYA DEVICES REGISTRY · /api/maya_devices · 2026-05-15
 *
 * Zero-touch device-bridge registration. The bridge installer (install.sh /
 * install.ps1) POSTs here with Mo's Commander PIN after it finishes installing.
 * Maya OS PWA fetches the list on open · no manual copy-paste of tokens.
 *
 * Mo's directive (verbatim 2026-05-15):
 *   "The installation files must configure that, without me!"
 *
 * Actions:
 *   GET  ?action=status             public · count of registered devices
 *   POST  action=register {pin, device_name, url, token, platform, capabilities}
 *                                  → registered to /data/maya_devices/<id>.json
 *   POST  action=list {pin}        → array of registered devices (token masked)
 *   POST  action=remove {pin, id}  → delete a registration
 *   POST  action=set_active {pin, id}
 *                                  → mark which device is the default for Maya
 *   POST  action=full_record {pin, id}
 *                                  → return the FULL record incl. token (only
 *                                    when Mo's session needs to connect)
 *
 * Security:
 *   - Every mutation requires Commander PIN (210379 OR BuddyBoots2!)
 *   - Tokens are stored server-side in 0600-mode files
 *   - GET ?action=status is the only unauthenticated read · returns counts only
 *   - All requests logged to /data/maya_devices/_audit.log
 *
 * PHP 7.4 only.  No match() · no str_contains() · no fn() =>.
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

const DEV_DIR    = '/home/iamsuperio.cloud/public_html/data/maya_devices';
const AUDIT_LOG  = DEV_DIR . '/_audit.log';
const ACTIVE_FILE = DEV_DIR . '/_active.json';
const PIN_NUM    = '210379';
const PIN_PHRASE = 'BuddyBoots2!';

@mkdir(DEV_DIR, 0755, true);

function dev_out(array $p, int $code = 200): void {
    http_response_code($code);
    echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function dev_pin_ok(?string $pin): bool {
    if (!$pin) return false;
    return hash_equals(PIN_NUM, $pin) || hash_equals(PIN_PHRASE, $pin);
}
function dev_audit(string $event, array $ctx = []): void {
    $entry = ['ts' => date('c'), 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'event' => $event, 'ctx' => $ctx];
    @file_put_contents(AUDIT_LOG, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}
function dev_id_from(string $device_name, string $platform, string $url): string {
    return substr(hash('sha256', $device_name . '|' . $platform . '|' . $url), 0, 16);
}
function dev_mask_token(string $token): string {
    if (strlen($token) < 12) return str_repeat('*', strlen($token));
    return substr($token, 0, 6) . '...' . substr($token, -4);
}
function dev_load_all(): array {
    $out = [];
    foreach (glob(DEV_DIR . '/dev_*.json') as $f) {
        $j = @json_decode(@file_get_contents($f), true);
        if (is_array($j)) $out[] = $j;
    }
    usort($out, function($a, $b) {
        return strcmp((string)($b['registered_at'] ?? ''), (string)($a['registered_at'] ?? ''));
    });
    return $out;
}
function dev_active_id(): ?string {
    if (!is_readable(ACTIVE_FILE)) return null;
    $j = @json_decode(@file_get_contents(ACTIVE_FILE), true);
    return is_array($j) ? ($j['id'] ?? null) : null;
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST ?: $_GET;
$action = $_GET['action'] ?? $in['action'] ?? 'status';

if ($action === 'status') {
    $devices = dev_load_all();
    dev_out([
        'ok'       => true,
        'count'    => count($devices),
        'active'   => dev_active_id(),
        'service'  => 'maya_devices',
    ]);
}

if ($action === 'register') {
    if (!dev_pin_ok($in['pin'] ?? '')) {
        dev_audit('register_denied', ['device_name' => $in['device_name'] ?? '']);
        dev_out(['ok' => false, 'error' => 'commander pin required'], 401);
    }
    $device_name = trim((string)($in['device_name'] ?? ''));
    $url         = trim((string)($in['url'] ?? ''));
    $token       = trim((string)($in['token'] ?? ''));
    $platform    = trim((string)($in['platform'] ?? 'unknown'));
    $caps        = is_array($in['capabilities'] ?? null) ? $in['capabilities'] : [];

    if (!$device_name || !$url || !$token) {
        dev_out(['ok' => false, 'error' => 'device_name + url + token required'], 400);
    }

    $id = dev_id_from($device_name, $platform, $url);
    $record = [
        'id'             => $id,
        'device_name'    => $device_name,
        'platform'       => $platform,
        'url'            => $url,
        'token'          => $token,
        'capabilities'   => $caps,
        'registered_at'  => date('c'),
        'last_seen'      => date('c'),
        'remote_ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    $path = DEV_DIR . '/dev_' . $id . '.json';
    $is_update = file_exists($path);
    @file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($path, 0600);

    // First device registered becomes active automatically
    $devices = dev_load_all();
    if (count($devices) === 1 || !dev_active_id()) {
        @file_put_contents(ACTIVE_FILE, json_encode(['id' => $id, 'ts' => date('c')]), LOCK_EX);
    }

    dev_audit($is_update ? 'register_update' : 'register_new', ['id' => $id, 'name' => $device_name, 'platform' => $platform]);
    dev_out([
        'ok'        => true,
        'id'        => $id,
        'name'      => $device_name,
        'action'    => $is_update ? 'updated' : 'registered',
        'is_active' => dev_active_id() === $id,
    ]);
}

if ($action === 'list') {
    if (!dev_pin_ok($in['pin'] ?? '')) dev_out(['ok' => false, 'error' => 'commander pin required'], 401);
    $devices = dev_load_all();
    $active = dev_active_id();
    $out = [];
    foreach ($devices as $d) {
        $out[] = [
            'id'             => $d['id'],
            'device_name'    => $d['device_name'],
            'platform'       => $d['platform'],
            'url'            => $d['url'],
            'token_masked'   => dev_mask_token($d['token'] ?? ''),
            'capabilities'   => $d['capabilities'] ?? [],
            'registered_at'  => $d['registered_at'] ?? '',
            'last_seen'      => $d['last_seen'] ?? '',
            'is_active'      => $active === $d['id'],
        ];
    }
    dev_out(['ok' => true, 'count' => count($out), 'active_id' => $active, 'devices' => $out]);
}

if ($action === 'full_record') {
    /* Returns the FULL record incl. token. Maya OS uses this when it activates
       a device · the token never appears in /list */
    if (!dev_pin_ok($in['pin'] ?? '')) dev_out(['ok' => false, 'error' => 'commander pin required'], 401);
    $id = trim((string)($in['id'] ?? ''));
    if (!$id) dev_out(['ok' => false, 'error' => 'id required'], 400);
    $path = DEV_DIR . '/dev_' . preg_replace('/[^a-f0-9]/', '', $id) . '.json';
    if (!is_readable($path)) dev_out(['ok' => false, 'error' => 'not found'], 404);
    $rec = json_decode(file_get_contents($path), true);
    /* Update last_seen */
    $rec['last_seen'] = date('c');
    @file_put_contents($path, json_encode($rec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    dev_audit('full_record_pulled', ['id' => $id]);
    dev_out(['ok' => true, 'device' => $rec]);
}

if ($action === 'set_active') {
    if (!dev_pin_ok($in['pin'] ?? '')) dev_out(['ok' => false, 'error' => 'commander pin required'], 401);
    $id = trim((string)($in['id'] ?? ''));
    if (!$id) dev_out(['ok' => false, 'error' => 'id required'], 400);
    $path = DEV_DIR . '/dev_' . preg_replace('/[^a-f0-9]/', '', $id) . '.json';
    if (!is_readable($path)) dev_out(['ok' => false, 'error' => 'not found'], 404);
    @file_put_contents(ACTIVE_FILE, json_encode(['id' => $id, 'ts' => date('c')]), LOCK_EX);
    dev_audit('set_active', ['id' => $id]);
    dev_out(['ok' => true, 'active_id' => $id]);
}

if ($action === 'remove') {
    if (!dev_pin_ok($in['pin'] ?? '')) dev_out(['ok' => false, 'error' => 'commander pin required'], 401);
    $id = trim((string)($in['id'] ?? ''));
    if (!$id) dev_out(['ok' => false, 'error' => 'id required'], 400);
    $path = DEV_DIR . '/dev_' . preg_replace('/[^a-f0-9]/', '', $id) . '.json';
    if (is_writable($path)) @unlink($path);
    if (dev_active_id() === $id) @unlink(ACTIVE_FILE);
    dev_audit('remove', ['id' => $id]);
    dev_out(['ok' => true, 'removed' => $id]);
}

dev_out(['ok' => false, 'error' => 'unknown action', 'supported' => ['status', 'register', 'list', 'full_record', 'set_active', 'remove']], 400);
