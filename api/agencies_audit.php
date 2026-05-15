<?php
/**
 * MAYA · AGENCIES AUDIT (truth endpoint)
 * --------------------------------------
 * Mo 2026-05-15: "How many agencies do we have? You said 58 — disk says 101."
 *
 * This endpoint reconciles three truth sources:
 *   1. /agencies/<slug>/ folders on disk (real habitats)
 *   2. /api/staff.php roster (the 58 canonical, with rich data)
 *   3. Delta (unregistered habitats)
 *
 * Output:
 *   ?action=summary  · totals + delta count
 *   ?action=disk     · array of every folder on disk
 *   ?action=roster   · array of every slug in staff.php
 *   ?action=delta    · disk-only slugs (built but not yet in roster)
 *   ?action=full     · roster entries + minimal stub entries for disk-only
 *
 * Never modifies staff.php · pure read · safe for cron + frontend polling.
 * PHP 7.4 · brand: Powered by MirzaTech.ai · Property of EMAAA.io
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
@error_reporting(0); @ini_set('display_errors', '0');

$AG_DIR = '/home/ai-staffing.agency/public_html/agencies';
$BASE   = 'https://ai-staffing.agency';

function list_disk($AG_DIR) {
    if (!is_dir($AG_DIR)) return array();
    $out = array();
    foreach (scandir($AG_DIR) as $e) {
        if ($e === '.' || $e === '..') continue;
        if (!is_dir($AG_DIR . '/' . $e)) continue;
        $out[] = $e;
    }
    sort($out);
    return array_values(array_unique($out));
}

function stub_from_folder($AG_DIR, $slug) {
    $idx = $AG_DIR . '/' . $slug . '/index.html';
    $name = ucwords(str_replace('-', ' ', $slug));
    $desc = '';
    if (file_exists($idx)) {
        $html = @file_get_contents($idx, false, null, 0, 8192);
        if (is_string($html)) {
            if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
                $title = trim(preg_replace('/\s+/', ' ', $m[1]));
                if ($title !== '') $name = $title;
            }
            if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\']([^"\']{20,})["\']/i', $html, $m)) {
                $desc = trim($m[1]);
            }
        }
    }
    return array(
        'id'          => $slug,
        'name'        => $name,
        'description' => $desc,
        'role_count'  => 0,
        'source'      => 'disk_only_unregistered',
        'habitat_url' => '/agencies/' . $slug . '/',
    );
}

function fetch_roster($BASE) {
    $ch = curl_init($BASE . '/api/staff.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $body = curl_exec($ch); curl_close($ch);
    $j = json_decode($body, true);
    return (is_array($j) && isset($j['agencies'])) ? $j['agencies'] : array();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'summary';

$disk    = list_disk($AG_DIR);
$roster  = fetch_roster($BASE);
$roster_slugs = array();
foreach ($roster as $a) {
    if (isset($a['id'])) $roster_slugs[] = $a['id'];
    elseif (isset($a['slug'])) $roster_slugs[] = $a['slug'];
}
sort($roster_slugs);
$disk_only   = array_values(array_diff($disk, $roster_slugs));
$roster_only = array_values(array_diff($roster_slugs, $disk));

// Detect duplicates / near-dupes (game-development vs gaming-development)
$dupes = array();
foreach ($disk as $a) {
    foreach ($disk as $b) {
        if ($a >= $b) continue;
        $diff = levenshtein($a, $b);
        if ($diff > 0 && $diff <= 2 && abs(strlen($a) - strlen($b)) <= 2) {
            $dupes[] = array('a' => $a, 'b' => $b, 'levenshtein' => $diff);
        }
    }
}

if ($action === 'summary') {
    echo json_encode(array(
        'ok'                   => true,
        'ts'                   => gmdate('Y-m-d\TH:i:s\Z'),
        'disk_total'           => count($disk),
        'roster_total'         => count($roster_slugs),
        'disk_only_total'      => count($disk_only),
        'roster_only_total'    => count($roster_only),
        'possible_duplicates'  => $dupes,
        'sample_disk_only'     => array_slice($disk_only, 0, 10),
        'doctrine'             => 'roster = canonical 58 with rich data · disk = 101 built habitats · delta = ' . count($disk_only) . ' habitats need to be promoted into roster OR retired',
        'enforcement'          => 'Sacred Pin S0 lists 58 agencies as canon · this audit shows the actual disk truth · Mo caught the downgrade 2026-05-15',
    ), JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'disk')   { echo json_encode(array('ok'=>true,'count'=>count($disk),'slugs'=>$disk), JSON_PRETTY_PRINT); exit; }
if ($action === 'roster') { echo json_encode(array('ok'=>true,'count'=>count($roster_slugs),'slugs'=>$roster_slugs), JSON_PRETTY_PRINT); exit; }
if ($action === 'delta')  { echo json_encode(array('ok'=>true,'disk_only'=>$disk_only,'roster_only'=>$roster_only,'possible_duplicates'=>$dupes), JSON_PRETTY_PRINT); exit; }

if ($action === 'full') {
    $merged = $roster;
    foreach ($disk_only as $slug) $merged[] = stub_from_folder($AG_DIR, $slug);
    echo json_encode(array('ok'=>true,'total'=>count($merged),'agencies'=>$merged,'roster_count'=>count($roster_slugs),'disk_only_count'=>count($disk_only)), JSON_PRETTY_PRINT);
    exit;
}

http_response_code(400);
echo json_encode(array('ok'=>false,'error'=>'unknown action','supported'=>array('summary','disk','roster','delta','full')));
