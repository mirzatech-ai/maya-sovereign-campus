<?php
/**
 * MAYA EVENT INGESTION · /api/maya_event · 2026-05-15 · Phase B spine
 *
 * Every Maya action emits an event here.  events.jsonl is the live activity
 * spine the Sovereign Campus visualizes.  Append-only · file-locked writes.
 *
 * POST { actor, action, target, status, room?, result?, parent_event_id?, ... }
 *
 * - actor   : "maya" | "kimi" | "opencode" | "vscode" | "self_edit" | "sentinel" | <agency_slug>
 * - action  : short verb · "brief" | "synth" | "send_sms" | "open_account" | "hire_agency" | "edit_file" | etc.
 * - target  : the thing acted upon · "mirzaadin@gmail.com" | "instagram.com" | "maya-os.css" | ...
 * - status  : "start" | "running" | "done" | "error"
 * - room    : optional · routes the packet to a specific campus room (maya_brain · kimi · opencode · vscode · self_edit_queue · <dynamic>)
 * - result  : optional · short string the audit log shows
 *
 * PHP 7.4 only · no match · no str_contains · no fn() =>.
 * Skill #6 hard rule (zero DB writes from STATE-stream) does NOT apply here
 * because this IS the write endpoint · the state-stream endpoint is read-only.
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

const EVENT_DIR  = '/home/iamsuperio.cloud/public_html/data/maya_events';
const EVENT_LOG  = EVENT_DIR . '/events.jsonl';
const MAX_BYTES  = 4 * 1024 * 1024;   // 4 MB · rotate beyond this
const TAIL_LIMIT = 200;                // max events returned by tail action

@mkdir(EVENT_DIR, 0755, true);

function ev_out(array $p, int $code = 200): void {
    http_response_code($code);
    echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function ev_rotate_if_needed(): void {
    if (!file_exists(EVENT_LOG)) return;
    if (filesize(EVENT_LOG) <= MAX_BYTES) return;
    $stamp = date('Ymd_His');
    @rename(EVENT_LOG, EVENT_DIR . "/events.$stamp.jsonl");
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST ?: $_GET;
$action = $_GET['action'] ?? $in['action'] ?? 'log';

/* GET ?action=tail&n=50 · read recent events for the state stream */
if ($action === 'tail') {
    $n = max(1, min(TAIL_LIMIT, (int)($_GET['n'] ?? 50)));
    if (!file_exists(EVENT_LOG)) ev_out(['ok'=>true, 'count'=>0, 'events'=>[]]);
    $lines = @file(EVENT_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $slice = array_slice($lines, -$n);
    $events = [];
    foreach ($slice as $line) {
        $e = json_decode($line, true);
        if (is_array($e)) $events[] = $e;
    }
    ev_out(['ok'=>true, 'count'=>count($events), 'events'=>$events]);
}

/* POST · log a new event */
$actor  = trim((string)($in['actor']  ?? ''));
$verb   = trim((string)($in['action'] ?? $in['verb'] ?? ''));
$target = trim((string)($in['target'] ?? ''));
$status = trim((string)($in['status'] ?? 'done'));
$room   = trim((string)($in['room']   ?? ''));
$result = trim((string)($in['result'] ?? ''));
$parent = trim((string)($in['parent_event_id'] ?? ''));

if ($actor === '' || $verb === '') {
    ev_out(['ok'=>false, 'error'=>'actor and action required'], 400);
}
$valid_status = ['start', 'running', 'done', 'error'];
if (!in_array($status, $valid_status, true)) $status = 'done';

/* Map actor → canonical room (so packet flies to the right place) */
if ($room === '') {
    $room_map = [
        'maya'      => 'maya_brain',
        'kimi'      => 'kimi',
        'opencode'  => 'opencode',
        'vscode'    => 'vscode',
        'self_edit' => 'self_edit_queue',
        'sentinel'  => 'maya_brain',  // sentinels route through Maya
    ];
    $room = isset($room_map[$actor]) ? $room_map[$actor] : 'maya_brain';
}

$event = [
    'id'        => bin2hex(random_bytes(6)),
    'ts'        => date('c'),
    'unix'      => time(),
    'actor'     => $actor,
    'action'    => $verb,
    'target'    => $target,
    'status'    => $status,
    'room'      => $room,
    'result'    => $result,
    'parent'    => $parent,
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
];

ev_rotate_if_needed();

/* Append with exclusive lock · jsonl format */
$line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
$fp = @fopen(EVENT_LOG, 'ab');
if (!$fp) ev_out(['ok'=>false, 'error'=>'cannot open event log'], 500);
@flock($fp, LOCK_EX);
@fwrite($fp, $line);
@flock($fp, LOCK_UN);
@fclose($fp);

ev_out(['ok'=>true, 'event'=>$event]);
