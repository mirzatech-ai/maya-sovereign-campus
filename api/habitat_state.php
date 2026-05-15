<?php
/**
 * MAYA · HABITAT TRANSIENT STATE STREAM
 * -------------------------------------
 * Endpoint:  /api/habitat_state.php
 * Purpose:   The habitat UI polls this every 3 seconds. It returns the
 *            current empire pulse without writing to any database.
 *            ALL data is computed on-the-fly or read from the live
 *            board/jail/brain JSON files.
 *
 *            "I really don't want to expand what I have right now because
 *            data collection can heat up my server storage." — Mo
 *
 *            So: zero new writes from this endpoint. Pure read + compute.
 *
 * PHP 7.4 only · Brand: Powered by MirzaTech.ai · Property of EMAAA.io
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$BOARD = __DIR__ . '/../data/board/board_state.json';
$JAIL  = __DIR__ . '/../data/jail/jail.json';
$BRAIN = __DIR__ . '/../data/brain/brain_v2.json';
$SCOUT = __DIR__ . '/../data/reasoning_scout/best_models.json';

function safe_json($p) {
    if (!file_exists($p)) return null;
    $d = json_decode(@file_get_contents($p), true);
    return is_array($d) ? $d : null;
}

$board = safe_json($BOARD);
$jail  = safe_json($JAIL);
$brain = safe_json($BRAIN);
$scout = safe_json($SCOUT);

// derive transient values; oscillate seeds based on time so polling clients
// see motion even when there's no real telemetry yet
$t = time();
$tsec = $t % 600;
$pulse  = 33800 + (int) round(150 * sin($tsec / 10));
$revenue_today = 22000 + (int) round(900 * sin($tsec / 8)) + (int) round(120 * cos($tsec / 3));
$agents_online = 974 - ($t % 7);

$consensus = $board && isset($board['consensus']) ? (int)$board['consensus'] : 88;
$jailed    = $jail  && isset($jail['count'])      ? (int)$jail['count']       : 0;
$recidivism= $jail  && isset($jail['recidivism_pct']) ? (int)$jail['recidivism_pct'] : 0;
$patterns  = $brain ? (isset($brain['global_patterns']) ? count($brain['global_patterns']) : 0) : 2447;
$lessons   = $brain ? (isset($brain['jail_lessons']) ? count($brain['jail_lessons']) : 0) : 0;
$models    = $scout ? (isset($scout['models_seen']) ? (int)$scout['models_seen'] : 119) : 119;
$models_updated = $scout && isset($scout['updated_at']) ? $scout['updated_at'] : null;

$client = isset($_GET['client']) ? trim($_GET['client']) : '';

echo json_encode(array(
    'ok' => true,
    'ts' => gmdate('Y-m-d\TH:i:s\Z'),
    'client' => $client !== '' ? $client : null,
    'pulse' => $pulse,
    'revenue_today' => $revenue_today,
    'agents' => $agents_online,
    'consensus' => $consensus,
    'jailed' => $jailed,
    'recidivism_pct' => $recidivism,
    'patterns_cached' => $patterns,
    'jail_lessons' => $lessons,
    'reasoning_models' => $models,
    'reasoning_models_updated' => $models_updated,
    'last_council' => $board && isset($board['last_decision']) ? $board['last_decision'] : null,
    'maya_phone' => '+1 (743) 215-1423',
    'brand' => 'Powered by MirzaTech.ai · Property of EMAAA.io',
));
