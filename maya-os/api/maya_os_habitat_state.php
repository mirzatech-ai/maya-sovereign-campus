<?php
/**
 * MAYA OS · HABITAT STATE STREAM — Skill #6 (Transient State Stream) · GLOBAL-92
 *
 * Surfaces the live state of Mo's personal Sovereign Campus inside Maya OS.
 * 5 rooms tracked: maya_brain · kimi · opencode · vscode · self_edit_queue
 *
 * Hard rules (Skill #6):
 *   - ZERO DB writes from this endpoint. Read-only.
 *   - Pulse/agent counts oscillate via time()%600 so UI sees motion before real telemetry.
 *   - Cache-Control: no-store mandatory.
 *   - 3-second polling interval at the UI · don't poll faster.
 *
 * Endpoint:  GET https://iamsuperio.cloud/api/maya_os_habitat_state
 *
 * Returns:
 *   { ok, ts, hub: {label, state, consensus_pct, breathing},
 *     rooms: [{id,label,state,sprites,latency_ms,task,tasks_24h,...}],
 *     pulse: {throughput_per_min, edits_today, sentinels_active},
 *     streams: [{from, to, packets}] }
 *
 * PHP 7.4 compatible. No match(). No str_contains(). Traditional syntax only.
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

/** Skill #1 biolum states · Blue idle · White executing · Gold council · Red qa-error */
function pick_state(int $seed): string {
    $states = ['idle', 'executing', 'council', 'idle', 'idle']; // weighted toward idle
    return $states[$seed % count($states)];
}

/** Time-oscillator so the UI sees life even with no real telemetry yet. */
$slot = (int) floor(time() / 4.5);   // state churns every 4.5s per Skill #1
$min_slot = (int) floor(time() / 60);

/* Try to read real coding-agent status from local proxies if Kin's ports are reachable.
   These are LOCAL ports on Mo's desktop · not VPS · so from VPS they'll fail.
   When UI runs locally OR when an MCP bridge fwds, we'll see real data. */
$probe = function(string $url, int $timeout = 1): array {
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
    $start = microtime(true);
    $body = @file_get_contents($url, false, $ctx);
    $elapsed = (int) round((microtime(true) - $start) * 1000);
    return ['ok' => $body !== false, 'latency_ms' => $elapsed, 'body' => $body];
};

/* Rooms · loaded from canonical JSON registry · Mo's directive 2026-05-15:
   "each time we add a new sibling, it must be there by default, preconfigured as the others"
   Add a sibling = append one entry to data/siblings.json + push · the campus auto-renders.
   Fallback to hardcoded list only if registry file is missing (defensive). */
$rooms = [];
$siblings_file = '/home/iamsuperio.cloud/public_html/api/data/siblings.json';
if (is_readable($siblings_file)) {
    $reg = @json_decode(@file_get_contents($siblings_file), true);
    if (is_array($reg) && isset($reg['siblings']) && is_array($reg['siblings'])) {
        foreach ($reg['siblings'] as $i => $s) {
            $rooms[] = [
                'id'         => (string)($s['id'] ?? "sibling_$i"),
                'label'      => (string)($s['label'] ?? $s['id'] ?? "Sibling $i"),
                'role'       => (string)($s['role'] ?? ''),
                'state'      => isset($s['is_hub']) && $s['is_hub'] ? pick_state($slot + 0) : pick_state($slot + $i),
                'sprites'    => isset($s['is_hub']) && $s['is_hub'] ? 3 : 2,
                'tasks_24h'  => (int)($s['tasks_24h_base'] ?? 0) + ($min_slot % 9),
                'latency_ms' => (int)($s['latency_base_ms'] ?? 0) + (($slot * (23 + $i * 4)) % 400),
                'task'       => (string)($s['task_default'] ?? 'standby'),
                'engines'    => (string)($s['engines'] ?? ''),
                'icon'       => (string)($s['icon'] ?? '◇'),
            ];
        }
    }
}
/* Defensive fallback if registry missing or empty */
if (empty($rooms)) {
    $rooms[] = ['id'=>'maya_brain','label'=>'Maya Brain','role'=>'perception · routing · voice','state'=>pick_state($slot),'sprites'=>3,'tasks_24h'=>47,'latency_ms'=>180,'task'=>'awaiting input','engines'=>'multi-engine','icon'=>'🧠'];
}

/* ── PHASE B SPINE · Read real events from events.jsonl · 2026-05-15 ──
   The 5-room oscillator above gives ambient motion when nothing's happening.
   When real events fire, they OVERRIDE the oscillator state on the matching room
   for the next 6s, and add an "active" stream from the actor's room → maya_brain hub.
*/
$EVENT_LOG = '/home/iamsuperio.cloud/public_html/data/maya_events/events.jsonl';
$active_window_s = 6;       // a "running" event keeps the room hot for 6s after last update
$real_events = [];
if (is_readable($EVENT_LOG)) {
    /* read last ~50 lines · avoid loading the whole file */
    $lines = [];
    $fp = @fopen($EVENT_LOG, 'rb');
    if ($fp) {
        $buf = ''; $pos = -1; $line_count = 0; $want = 80;
        @fseek($fp, 0, SEEK_END);
        $size = ftell($fp);
        while ($size + $pos > 0 && $line_count < $want) {
            @fseek($fp, $pos--, SEEK_END);
            $ch = @fgetc($fp);
            if ($ch === false) break;
            if ($ch === "\n" && $buf !== '') { $lines[] = strrev($buf); $buf = ''; $line_count++; }
            else $buf .= $ch;
        }
        if ($buf !== '') $lines[] = strrev($buf);
        @fclose($fp);
        $lines = array_reverse($lines);
    }
    foreach ($lines as $line) {
        $e = json_decode(trim($line), true);
        if (is_array($e) && !empty($e['unix'])) $real_events[] = $e;
    }
}

/* Override room states from recent active events */
$now_unix = time();
$active_streams_map = [];   // room_id => packets
$active_room_map    = [];   // room_id => latest_event
foreach ($real_events as $e) {
    $age = $now_unix - (int)$e['unix'];
    if ($age > $active_window_s && $e['status'] !== 'running') continue;
    $r = $e['room'] ?? 'maya_brain';
    if ($e['status'] === 'start' || $e['status'] === 'running') {
        $active_room_map[$r] = $e;
        $active_streams_map[$r] = ($active_streams_map[$r] ?? 0) + 1;
    } else if ($e['status'] === 'done' && $age <= $active_window_s) {
        /* recent completion · brief packet flash · gold for council/brief, green for done */
        $active_streams_map[$r] = ($active_streams_map[$r] ?? 0) + 1;
    } else if ($e['status'] === 'error') {
        $active_room_map[$r] = $e;
    }
}
/* Apply state overrides */
foreach ($rooms as &$room) {
    $rid = $room['id'];
    if (isset($active_room_map[$rid])) {
        $ev = $active_room_map[$rid];
        if ($ev['status'] === 'error') $room['state'] = 'error';
        else $room['state'] = 'executing';
        $room['task'] = ($ev['action'] ?? '') . ($ev['target'] ? ' · ' . $ev['target'] : '');
    }
}
unset($room);

/* Hub · Maya brain's breathing orb · consensus % comes from Skill #2 board_state.json if present */
$consensus_pct = 88;   // canonical default per ai-staffing habitat
$board_state_file = '/home/iamsuperio.cloud/public_html/data/board_state.json';
if (is_readable($board_state_file)) {
    $bs = @json_decode(@file_get_contents($board_state_file), true);
    if (is_array($bs) && isset($bs['last_consensus_pct'])) $consensus_pct = (int) $bs['last_consensus_pct'];
}

$hub = [
    'label'         => 'Maya · Sovereign',
    'subtitle'      => 'Mo\'s personal command surface',
    'state'         => 'breathing',
    'consensus_pct' => $consensus_pct,
    'pulse_hz'      => 0.4,     // 0.4 Hz = 2.5s breath cycle
];

/* Pulse · empire-level rolling numbers · oscillate per Skill #6 */
$pulse = [
    'edits_today'         => 5 + ($min_slot % 12),
    'throughput_per_min'  => 17 + ($slot % 8),
    'sentinels_active'    => 27866,    // canonical from MEMORY · sentinel module count
    'coding_agents_idle'  => 3,
    'unread_email_24h'    => null,     // populated by /api/maya_gmail?action=unread_count separately
];

/* Streams · ambient (always-on) low-opacity + active (event-driven) bright
   Active streams override ambient when the same edge fires */
$streams = [
    ['from' => 'maya_brain', 'to' => 'kimi',            'packets' => 1, 'color' => 'cyan', 'active' => false],
    ['from' => 'maya_brain', 'to' => 'opencode',        'packets' => 1, 'color' => 'cyan', 'active' => false],
    ['from' => 'maya_brain', 'to' => 'vscode',          'packets' => 1, 'color' => 'cyan', 'active' => false],
    ['from' => 'maya_brain', 'to' => 'self_edit_queue', 'packets' => 1, 'color' => 'gold', 'active' => false],
];
/* Overlay live streams from recent events · packet flies room → maya_brain (incoming work)
   or maya_brain → room (outgoing dispatch) based on actor */
foreach ($active_streams_map as $room_id => $packet_count) {
    if ($room_id === 'maya_brain') continue;
    $clr = (isset($active_room_map[$room_id]) && $active_room_map[$room_id]['status'] === 'error') ? 'red'
         : ((isset($active_room_map[$room_id]) && $active_room_map[$room_id]['status'] === 'running') ? 'green' : 'gold');
    $streams[] = [
        'from'    => 'maya_brain',
        'to'      => $room_id,
        'packets' => min(4, max(1, $packet_count + 1)),
        'color'   => $clr,
        'active'  => true,
    ];
}

/* Audit log · real events from events.jsonl · falls back to system rows if empty */
$audit_pool = [];
$rel_time = function(int $unix) use ($now_unix): string {
    $diff = max(0, $now_unix - $unix);
    if ($diff < 5)    return 'now';
    if ($diff < 60)   return '-' . $diff . 's';
    if ($diff < 3600) return '-' . intdiv($diff, 60) . 'm';
    if ($diff < 86400) return '-' . intdiv($diff, 3600) . 'h';
    return '-' . intdiv($diff, 86400) . 'd';
};
$recent_events = array_slice(array_reverse($real_events), 0, 12);
foreach ($recent_events as $e) {
    $kind = $e['actor'] === 'maya' ? 'brain' : ($e['actor'] === 'self_edit' ? 'edit' : 'agent');
    if ($e['status'] === 'error') $kind = 'error';
    $msg = strtoupper((string)$e['action']);
    if (!empty($e['target'])) $msg .= ' · ' . $e['target'];
    if (!empty($e['result'])) $msg .= ' · ' . substr((string)$e['result'], 0, 80);
    $audit_pool[] = [
        'ts'   => $rel_time((int)$e['unix']),
        'kind' => $kind,
        'msg'  => $msg,
    ];
}
/* Pad with system context if log is sparse */
if (count($audit_pool) < 3) {
    $audit_pool[] = ['ts' => 'boot', 'kind' => 'system', 'msg' => 'SOVEREIGN CAMPUS v1.10.3 · 5 rooms · event spine ready'];
    $audit_pool[] = ['ts' => 'boot', 'kind' => 'agent',  'msg' => 'Coding agents 200 req/min · Kimi 8086 · OpenCode 8087 · VS Code 8088'];
    $audit_pool[] = ['ts' => 'boot', 'kind' => 'system', 'msg' => 'POST /api/maya_event to emit · GET ?action=tail to read'];
}

$out = [
    'ok'         => true,
    'ts'         => date('c'),
    'hub'        => $hub,
    'rooms'      => $rooms,
    'pulse'      => $pulse,
    'streams'    => $streams,
    'audit'      => $audit_pool,
    'skill'      => 'Skill #6 transient_state · GLOBAL-92',
    'phase'      => 'A',
    'note'       => 'Phase A · stub rooms + oscillator. Phase B wires real queue backend.',
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
