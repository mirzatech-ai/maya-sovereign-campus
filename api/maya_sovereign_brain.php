<?php
/**
 * MAYA · SOVEREIGN BRAIN (HYPERMIND, SERVER-SIDE)
 * -----------------------------------------------
 * Endpoint:  /api/maya_sovereign_brain.php
 * Origin:    Built from the Hypermind file Mo was gifted (long-lived seed).
 *            Original Hypermind was a client-side simulation. Promoted here
 *            to a persistent server-side pattern memory that survives restarts.
 *
 * What it does:
 *  - Records every meaningful pattern Maya observes (page, layout, conversion,
 *    workflow step, jail lesson, council decision, etc.)
 *  - When a pattern recurs 3+ times across the 29-domain fleet it is
 *    promoted to "ESTABLISHED_PROTOCOL" — Maya then uses it as default
 *  - Cross-pollinates winning patterns from one domain to others ("hive pulse")
 *
 * Storage: ../data/brain/brain_v2.json (flat JSON file · no DB)
 *
 * PHP 7.4 only · Brand: Powered by MirzaTech.ai · Property of EMAAA.io
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$BRAIN_DIR  = __DIR__ . '/../data/brain';
$BRAIN_FILE = $BRAIN_DIR . '/brain_v2.json';
if (!is_dir($BRAIN_DIR)) { @mkdir($BRAIN_DIR, 0775, true); }

function read_brain($p) {
    if (file_exists($p)) {
        $d = json_decode(file_get_contents($p), true);
        if (is_array($d)) return $d;
    }
    return array(
        'global_patterns' => array(),
        'site_profiles'   => array(),
        'user_archetypes' => array(),
        'jail_lessons'    => array(),
        'weights'         => array('ux' => 0.85, 'conversion' => 0.90, 'speed' => 0.95),
        'created_at'      => gmdate('Y-m-d\TH:i:s\Z'),
    );
}
function write_brain($p, $b) {
    $b['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
    @file_put_contents($p, json_encode($b, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}
function fingerprint($v) { return substr(base64_encode(hash('sha256', json_encode($v), true)), 0, 16); }

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'state');

if ($action === 'state') {
    $b = read_brain($BRAIN_FILE);
    echo json_encode(array(
        'ok' => true,
        'patterns_total'     => count($b['global_patterns']),
        'site_profiles'      => count($b['site_profiles']),
        'archetypes'         => count($b['user_archetypes']),
        'jail_lessons_total' => count($b['jail_lessons']),
        'weights'            => $b['weights'],
        'updated_at'         => isset($b['updated_at']) ? $b['updated_at'] : null,
        'created_at'         => isset($b['created_at']) ? $b['created_at'] : null,
    ));
    exit;
}

if ($action === 'observe') {
    // POST: site_id, layout (any json), kind (page|workflow|conversion|...)
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) $in = $_POST;
    $site_id = isset($in['site_id']) ? trim($in['site_id']) : 'global';
    $kind    = isset($in['kind'])    ? trim($in['kind'])    : 'pattern';
    $layout  = isset($in['layout'])  ? $in['layout']        : null;
    if ($layout === null) { http_response_code(400); echo json_encode(array('ok'=>false,'error'=>'missing layout')); exit; }

    $fp = fingerprint(array('kind'=>$kind,'data'=>$layout));
    $b  = read_brain($BRAIN_FILE);

    if (!isset($b['site_profiles'][$site_id])) {
        $b['site_profiles'][$site_id] = array('patterns' => array(), 'first_seen' => gmdate('Y-m-d\TH:i:s\Z'));
    }
    if (!isset($b['site_profiles'][$site_id]['patterns'][$fp])) {
        $b['site_profiles'][$site_id]['patterns'][$fp] = array('count'=>0,'weight'=>0.5,'status'=>'OBSERVED','kind'=>$kind);
    }
    $b['site_profiles'][$site_id]['patterns'][$fp]['count']++;

    // promote at 3+ occurrences
    if ($b['site_profiles'][$site_id]['patterns'][$fp]['count'] >= 3) {
        $b['site_profiles'][$site_id]['patterns'][$fp]['status'] = 'ESTABLISHED_PROTOCOL';
        $b['global_patterns'][$fp] = array(
            'kind' => $kind,
            'origin_site' => $site_id,
            'layout' => $layout,
            'weight' => max(0.5, isset($b['global_patterns'][$fp]['weight']) ? $b['global_patterns'][$fp]['weight'] : 0.6),
            'promoted_at' => gmdate('Y-m-d\TH:i:s\Z'),
        );
    }
    write_brain($BRAIN_FILE, $b);
    echo json_encode(array('ok'=>true,'fp'=>$fp,'pattern'=>$b['site_profiles'][$site_id]['patterns'][$fp]));
    exit;
}

if ($action === 'reinforce') {
    // POST: fp, delta (e.g. conversion uplift) — increase the pattern weight
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) $in = $_POST;
    $fp    = isset($in['fp'])    ? trim($in['fp'])    : '';
    $delta = isset($in['delta']) ? (float)$in['delta'] : 0.05;
    if ($fp === '') { http_response_code(400); echo json_encode(array('ok'=>false,'error'=>'missing fp')); exit; }
    $b = read_brain($BRAIN_FILE);
    if (isset($b['global_patterns'][$fp])) {
        $w = isset($b['global_patterns'][$fp]['weight']) ? (float)$b['global_patterns'][$fp]['weight'] : 0.5;
        $b['global_patterns'][$fp]['weight'] = max(0, min(1, $w + $delta));
        write_brain($BRAIN_FILE, $b);
        echo json_encode(array('ok'=>true,'fp'=>$fp,'weight'=>$b['global_patterns'][$fp]['weight']));
    } else {
        echo json_encode(array('ok'=>false,'error'=>'pattern not found'));
    }
    exit;
}

if ($action === 'recommend') {
    // GET: kind=page&site_id=ai-staffing.agency
    $kind = isset($_GET['kind']) ? $_GET['kind'] : 'pattern';
    $b = read_brain($BRAIN_FILE);
    $candidates = array();
    foreach ($b['global_patterns'] as $fp => $p) {
        if (!isset($p['kind']) || $p['kind'] !== $kind) continue;
        $candidates[] = array('fp'=>$fp,'weight'=>isset($p['weight'])?$p['weight']:0.5,'origin'=>isset($p['origin_site'])?$p['origin_site']:null,'layout'=>isset($p['layout'])?$p['layout']:null);
    }
    usort($candidates, function($a,$b){ return $b['weight'] <=> $a['weight']; });
    echo json_encode(array('ok'=>true,'kind'=>$kind,'top'=>array_slice($candidates, 0, 5)));
    exit;
}

if ($action === 'hive_pulse') {
    // detect patterns that recur across >= 5 sites → suggest cross-pollination
    $b = read_brain($BRAIN_FILE);
    $byfp = array();
    foreach ($b['site_profiles'] as $site_id => $profile) {
        if (!isset($profile['patterns']) || !is_array($profile['patterns'])) continue;
        foreach ($profile['patterns'] as $fp => $info) {
            if (!isset($byfp[$fp])) $byfp[$fp] = array();
            $byfp[$fp][] = $site_id;
        }
    }
    $broadcasts = array();
    foreach ($byfp as $fp => $sites) {
        if (count($sites) >= 5) {
            $broadcasts[] = array(
                'fp' => $fp,
                'sites' => $sites,
                'count' => count($sites),
                'action' => 'cross_pollinate_remaining_fleet',
            );
        }
    }
    echo json_encode(array('ok'=>true,'broadcasts'=>$broadcasts));
    exit;
}

http_response_code(400);
echo json_encode(array('ok'=>false,'error'=>'unknown action','supported'=>array('state','observe','reinforce','recommend','hive_pulse')));
