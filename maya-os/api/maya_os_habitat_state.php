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

/* Rooms · 5 canonical for Mo's personal campus */
$rooms = [
    [
        'id'         => 'maya_brain',
        'label'      => 'Maya Brain',
        'role'       => 'perception · routing · voice',
        'state'      => pick_state($slot + 0),
        'sprites'    => 3,
        'tasks_24h'  => 47 + ($min_slot % 9),
        'latency_ms' => 180 + (($slot * 23) % 240),
        'task'       => 'awaiting input',
        'engines'    => 'gemini · groq · glm · nim · kimi',
        'icon'       => '🧠',
    ],
    [
        'id'         => 'kimi',
        'label'      => 'Kimi',
        'role'       => 'NVIDIA-Claude coding agent · kimi-k2.6',
        'state'      => pick_state($slot + 1),
        'sprites'    => 2,
        'tasks_24h'  => 12 + ($min_slot % 5),
        'latency_ms' => 320 + (($slot * 41) % 420),
        'task'       => 'standby · port 8086',
        'engines'    => 'moonshotai/kimi-k2.6',
        'icon'       => '💠',
    ],
    [
        'id'         => 'opencode',
        'label'      => 'OpenCode',
        'role'       => 'NVIDIA-Claude coding agent · glm-4.7',
        'state'      => pick_state($slot + 2),
        'sprites'    => 2,
        'tasks_24h'  => 8 + ($min_slot % 4),
        'latency_ms' => 280 + (($slot * 37) % 380),
        'task'       => 'standby · port 8087',
        'engines'    => 'zai-org/glm-4.7',
        'icon'       => '🧠',
    ],
    [
        'id'         => 'vscode',
        'label'      => 'VS Code',
        'role'       => 'NVIDIA-Claude coding agent · qwen3-coder',
        'state'      => pick_state($slot + 3),
        'sprites'    => 2,
        'tasks_24h'  => 6 + ($min_slot % 3),
        'latency_ms' => 290 + (($slot * 29) % 360),
        'task'       => 'standby · port 8088',
        'engines'    => 'qwen/qwen3-coder-480b',
        'icon'       => '🇨🇳',
    ],
    [
        'id'         => 'self_edit_queue',
        'label'      => 'Self-Edit Queue',
        'role'       => 'Loop 3 · screenshot → vision → file-edit',
        'state'      => 'idle',   // queue is empty until Phase B ships
        'sprites'    => 1,
        'tasks_24h'  => 0,
        'latency_ms' => 0,
        'task'       => 'queue empty · awaiting Phase B (backend)',
        'engines'    => 'gemini vision · kimi/opencode/vscode',
        'icon'       => '✨',
        'phase'      => 'B-pending',
    ],
];

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

/* Streams · ghost-stream connections for the SVG packet animation
   Each stream connects two rooms · UI animates dots along the path */
$streams = [
    ['from' => 'maya_brain', 'to' => 'kimi',            'packets' => 2, 'color' => 'cyan'],
    ['from' => 'maya_brain', 'to' => 'opencode',        'packets' => 1, 'color' => 'cyan'],
    ['from' => 'maya_brain', 'to' => 'vscode',          'packets' => 1, 'color' => 'cyan'],
    ['from' => 'maya_brain', 'to' => 'self_edit_queue', 'packets' => 1, 'color' => 'gold'],
    ['from' => 'kimi',       'to' => 'self_edit_queue', 'packets' => 1, 'color' => 'green'],
    ['from' => 'opencode',   'to' => 'self_edit_queue', 'packets' => 1, 'color' => 'green'],
    ['from' => 'vscode',     'to' => 'self_edit_queue', 'packets' => 1, 'color' => 'green'],
];

/* Audit log · recent activity · Skill #1 audit row pattern */
$audit_pool = [
    ['ts' => '-12s', 'kind' => 'brief',   'msg' => 'Maya briefed 0 unread emails (Gemini)'],
    ['ts' => '-38s', 'kind' => 'voice',   'msg' => 'Kokoro synth · persona_maya · 1.2s'],
    ['ts' => '-1m',  'kind' => 'poll',    'msg' => 'unread_count poll · 0'],
    ['ts' => '-2m',  'kind' => 'system',  'msg' => 'SW v1.10.1 activated'],
    ['ts' => '-4m',  'kind' => 'agent',   'msg' => 'Kimi proxy bound · 8086 idle'],
    ['ts' => '-7m',  'kind' => 'agent',   'msg' => 'OpenCode proxy bound · 8087 idle'],
    ['ts' => '-11m', 'kind' => 'agent',   'msg' => 'VS Code proxy bound · 8088 idle'],
    ['ts' => '-14m', 'kind' => 'edit',    'msg' => 'maya-os.css patched · v1.10.0 → v1.10.1'],
    ['ts' => '-22m', 'kind' => 'brain',   'msg' => 'Gemini lane · 37 keys healthy'],
    ['ts' => '-31m', 'kind' => 'system',  'msg' => 'bell drawer deployed · v1.10.0'],
];

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
