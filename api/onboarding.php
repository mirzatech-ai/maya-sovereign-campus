<?php
/**
 * MAYA · CUSTOMER ONBOARDING · "leave no loose ends" wizard backend
 * -----------------------------------------------------------------
 * Endpoint:  /api/onboarding.php
 * Purpose:   Persist the full customer onboarding profile (one shot, all
 *            info collected up-front) so Maya never has to bug the customer
 *            for a missing detail later.
 *
 * PHP 7.4 only · Brand: Powered by MirzaTech.ai · Property of EMAAA.io
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Mo-PIN');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$ROOT = __DIR__ . '/../data/onboarding';
$PROFILES = $ROOT . '/profiles';
$INDEX = $ROOT . '/index.jsonl';
if (!is_dir($PROFILES)) { @mkdir($PROFILES, 0775, true); }

function now_iso() { return gmdate('Y-m-d\TH:i:s\Z'); }
function uid() { return substr(bin2hex(random_bytes(8)), 0, 12); }
function slug($s) { return preg_replace('/[^a-z0-9]+/', '-', strtolower(trim((string)$s, '-'))); }

$action = isset($_GET['action']) ? $_GET['action'] : 'submit';

if ($action === 'submit') {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) { http_response_code(400); echo json_encode(array('ok'=>false,'error'=>'invalid JSON body')); exit; }

    // hard validation — leave no loose ends
    $required = array('company_name','primary_email','timezone','ideal_customer','goal_30d','risk_appetite');
    $missing = array();
    foreach ($required as $f) { if (empty($in[$f])) $missing[] = $f; }
    if (!empty($missing)) {
        http_response_code(422);
        echo json_encode(array('ok'=>false,'error'=>'missing required fields','missing'=>$missing));
        exit;
    }

    // count subscribed services
    $svc_keys = array('svc_aicinesynth','svc_workflow','svc_realestate','svc_storefront','svc_council','svc_gaming','svc_staffing');
    $services_count = 0;
    foreach ($svc_keys as $k) { if (!empty($in[$k])) $services_count++; }

    $cid = 'cust_' . (slug($in['company_name']) ?: uid()) . '_' . uid();
    $profile = array(
        'customer_id' => $cid,
        'created_at'  => now_iso(),
        'services_count' => $services_count,
        'data' => $in,
    );

    $path = $PROFILES . '/' . $cid . '.json';
    @file_put_contents($path, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @file_put_contents($INDEX, json_encode(array(
        'ts' => $profile['created_at'],
        'customer_id' => $cid,
        'company' => $in['company_name'],
        'email'   => $in['primary_email'],
        'services_count' => $services_count,
    )) . "\n", FILE_APPEND | LOCK_EX);

    echo json_encode(array(
        'ok' => true,
        'customer_id' => $cid,
        'services_count' => $services_count,
        'next_steps' => 'Maya is configuring each subscribed service. Confirmation email queued.',
    ));
    exit;
}

if ($action === 'get') {
    $cid = isset($_GET['cid']) ? preg_replace('/[^a-z0-9_\-]/','',$_GET['cid']) : '';
    if (!$cid) { http_response_code(400); echo json_encode(array('ok'=>false,'error'=>'missing cid')); exit; }
    $path = $PROFILES . '/' . $cid . '.json';
    if (!file_exists($path)) { http_response_code(404); echo json_encode(array('ok'=>false,'error'=>'not found')); exit; }
    echo file_get_contents($path);
    exit;
}

if ($action === 'list') {
    $n = isset($_GET['n']) ? max(1, min(100, (int)$_GET['n'])) : 25;
    $rows = array();
    if (file_exists($INDEX)) {
        $lines = @file($INDEX, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            $tail = array_slice($lines, -$n);
            foreach (array_reverse($tail) as $line) {
                $r = json_decode($line, true);
                if (is_array($r)) $rows[] = $r;
            }
        }
    }
    echo json_encode(array('ok'=>true,'recent'=>$rows));
    exit;
}

http_response_code(400);
echo json_encode(array('ok'=>false,'error'=>'unknown action','supported'=>array('submit','get','list')));
