<?php
/**
 * MAYA REMINDERS · /api/maya_reminders · 2026-05-15
 *
 * Mo's directive 2026-05-15: "make sure that she reminds me of this job
 * frequently and keep reminding me of it until I sit down and do it, actually.
 * There's no limit to how many times she needs to remind me until I do it."
 *
 * Persistent reminder system Maya never forgets. Each reminder has a frequency
 * (hours) · she nags Mo every N hours via the bell badge + drawer until Mo
 * explicitly marks it done. Snooze pushes one frequency-cycle later.
 *
 * Actions:
 *   GET  ?action=list_public           - public list (titles only, no PIN required)
 *                                        used by bell badge to count overdue
 *   POST action=list {pin}             - full list with bodies + links
 *   POST action=add {pin, title, body, link?, frequency_hours?, priority?}
 *   POST action=snooze {pin, id, hours?} - push due_at forward (default 1 freq cycle)
 *   POST action=done {pin, id}         - mark complete · stops nagging
 *   POST action=reopen {pin, id}       - re-activate a done reminder
 *   POST action=delete {pin, id}       - hard delete
 *
 * Storage: /data/reminders/<id>.json (mode 600). Each file = one reminder.
 * PHP 7.4 only.
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

const REM_DIR = '/home/iamsuperio.cloud/public_html/data/reminders';
const PIN_NUM    = '210379';
const PIN_PHRASE = 'BuddyBoots2!';

@mkdir(REM_DIR, 0755, true);

function r_out(array $p, int $code = 200): void {
    http_response_code($code);
    echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function r_pin_ok(?string $pin): bool {
    if (!$pin) return false;
    return hash_equals(PIN_NUM, $pin) || hash_equals(PIN_PHRASE, $pin);
}
function r_load_all(bool $include_done = false): array {
    $out = [];
    foreach (glob(REM_DIR . '/rem_*.json') as $f) {
        $j = @json_decode(@file_get_contents($f), true);
        if (!is_array($j)) continue;
        if (!$include_done && !empty($j['done_at'])) continue;
        $out[] = $j;
    }
    /* Sort: overdue first, then by priority desc, then by due_at asc */
    $now = time();
    usort($out, function($a, $b) use ($now) {
        $ao = ($a['due_at'] ?? 0) <= $now ? 0 : 1;
        $bo = ($b['due_at'] ?? 0) <= $now ? 0 : 1;
        if ($ao !== $bo) return $ao - $bo;
        $ap = (int)($a['priority'] ?? 50);
        $bp = (int)($b['priority'] ?? 50);
        if ($ap !== $bp) return $bp - $ap;
        return ($a['due_at'] ?? 0) - ($b['due_at'] ?? 0);
    });
    return $out;
}
function r_overdue_count(): int {
    $now = time();
    $c = 0;
    foreach (r_load_all(false) as $r) {
        if (($r['due_at'] ?? 0) <= $now) $c++;
    }
    return $c;
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST ?: $_GET;
$action = $_GET['action'] ?? $in['action'] ?? 'list_public';

if ($action === 'list_public') {
    /* No-auth count + titles · used by bell badge poller */
    $items = [];
    foreach (r_load_all(false) as $r) {
        $items[] = [
            'id'         => $r['id'],
            'title'      => $r['title'],
            'priority'   => $r['priority'] ?? 50,
            'overdue'    => ($r['due_at'] ?? 0) <= time(),
            'due_at'     => $r['due_at'] ?? null,
        ];
    }
    r_out([
        'ok'            => true,
        'count'         => count($items),
        'overdue_count' => r_overdue_count(),
        'items'         => $items,
    ]);
}

if (!r_pin_ok($in['pin'] ?? '')) r_out(['ok' => false, 'error' => 'commander pin required'], 401);

if ($action === 'list') {
    r_out(['ok' => true, 'count' => 0, 'reminders' => r_load_all(false)]);
}

if ($action === 'list_all') {
    r_out(['ok' => true, 'reminders' => r_load_all(true)]);
}

if ($action === 'add') {
    $title = trim((string)($in['title'] ?? ''));
    $body  = trim((string)($in['body'] ?? ''));
    $link  = trim((string)($in['link'] ?? ''));
    $freq  = max(1, (int)($in['frequency_hours'] ?? 24));   // default 24h cycle
    $prio  = max(0, min(100, (int)($in['priority'] ?? 50)));
    if (!$title) r_out(['ok' => false, 'error' => 'title required'], 400);
    $id = 'rem_' . substr(hash('sha256', $title . microtime()), 0, 14);
    $rec = [
        'id'              => $id,
        'title'           => $title,
        'body'            => $body,
        'link'            => $link,
        'priority'        => $prio,
        'frequency_hours' => $freq,
        'created_at'      => time(),
        'due_at'          => time(),    // fires immediately by default · Mo can snooze
        'snooze_count'    => 0,
        'fire_count'      => 0,
        'done_at'         => null,
    ];
    $p = REM_DIR . '/' . $id . '.json';
    @file_put_contents($p, json_encode($rec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($p, 0600);
    r_out(['ok' => true, 'id' => $id, 'reminder' => $rec]);
}

if ($action === 'snooze') {
    $id = preg_replace('/[^a-z0-9_]/', '', (string)($in['id'] ?? ''));
    if (!$id) r_out(['ok' => false, 'error' => 'id required'], 400);
    $p = REM_DIR . '/' . $id . '.json';
    if (!is_readable($p)) r_out(['ok' => false, 'error' => 'not found'], 404);
    $rec = json_decode(file_get_contents($p), true);
    $hours = (int)($in['hours'] ?? $rec['frequency_hours'] ?? 24);
    $rec['due_at'] = time() + ($hours * 3600);
    $rec['snooze_count'] = ($rec['snooze_count'] ?? 0) + 1;
    $rec['last_snoozed_at'] = time();
    @file_put_contents($p, json_encode($rec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    r_out(['ok' => true, 'id' => $id, 'snoozed_until' => date('c', $rec['due_at']), 'snooze_count' => $rec['snooze_count']]);
}

if ($action === 'done') {
    $id = preg_replace('/[^a-z0-9_]/', '', (string)($in['id'] ?? ''));
    if (!$id) r_out(['ok' => false, 'error' => 'id required'], 400);
    $p = REM_DIR . '/' . $id . '.json';
    if (!is_readable($p)) r_out(['ok' => false, 'error' => 'not found'], 404);
    $rec = json_decode(file_get_contents($p), true);
    $rec['done_at'] = time();
    @file_put_contents($p, json_encode($rec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    r_out(['ok' => true, 'id' => $id, 'done_at' => date('c', $rec['done_at'])]);
}

if ($action === 'reopen') {
    $id = preg_replace('/[^a-z0-9_]/', '', (string)($in['id'] ?? ''));
    if (!$id) r_out(['ok' => false, 'error' => 'id required'], 400);
    $p = REM_DIR . '/' . $id . '.json';
    if (!is_readable($p)) r_out(['ok' => false, 'error' => 'not found'], 404);
    $rec = json_decode(file_get_contents($p), true);
    $rec['done_at'] = null;
    $rec['due_at'] = time();
    @file_put_contents($p, json_encode($rec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    r_out(['ok' => true, 'id' => $id]);
}

if ($action === 'delete') {
    $id = preg_replace('/[^a-z0-9_]/', '', (string)($in['id'] ?? ''));
    if (!$id) r_out(['ok' => false, 'error' => 'id required'], 400);
    $p = REM_DIR . '/' . $id . '.json';
    if (is_writable($p)) @unlink($p);
    r_out(['ok' => true, 'id' => $id, 'deleted' => true]);
}

/* /tick endpoint · cron hits this hourly · re-arms recurring reminders that fired but weren't completed */
if ($action === 'tick') {
    $now = time();
    $rearmed = 0;
    foreach (r_load_all(false) as $r) {
        if (($r['due_at'] ?? 0) > $now) continue;  // not yet due
        $p = REM_DIR . '/' . $r['id'] . '.json';
        $rec = json_decode(file_get_contents($p), true);
        /* If it's overdue by more than the frequency, leave it overdue · Mo sees red */
        /* No auto-reschedule here · the FRONTEND reminds. Tick just counts fires. */
        $rec['fire_count'] = ($rec['fire_count'] ?? 0) + 1;
        $rec['last_fired_at'] = $now;
        @file_put_contents($p, json_encode($rec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $rearmed++;
    }
    r_out(['ok' => true, 'rearmed' => $rearmed]);
}

r_out(['ok' => false, 'error' => 'unknown action', 'supported' => ['list_public','list','list_all','add','snooze','done','reopen','delete','tick']], 400);
