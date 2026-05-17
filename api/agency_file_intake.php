<?php
/**
 * AGENCY FILE INTAKE · /api/agency_file_intake.php
 * signed: KIN·2026-05-17T17:50Z·a75e63ca
 *
 * Mo verbatim 2026-05-17: "when I click on one of the buildings That represent the
 * agency, I don't have an option to attach any files That I would like to share."
 *
 * Accepts file uploads from the habitat-v4.html office-interior Data Dropzone.
 * Stores files at /data/agency_intake/<agency_slug>/<utc-ts>__<sanitized-filename>
 * Logs metadata only (filename · size · type · agency · timestamp) for any
 * sibling AI to triage.
 *
 * NOT in Entry 008 LOCK manifest · new file · per AGENT_SIGNATURE_PROTOCOL v1.
 * GLOBAL-93 compliant · no vendor names in response.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

@error_reporting(0);
@ini_set('display_errors', '0');
@set_time_limit(60);

// Limits (sensible defaults · prevent VPS storage bloat)
$MAX_BYTES = 25 * 1024 * 1024;   // 25 MB per file
$INTAKE_DIR_BASE = '/home/ai-staffing.agency/public_html/data/agency_intake';
$LOG = '/home/ai-staffing.agency/public_html/data/agency_intake.log';

function sanitize_slug($s){
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/[^a-z0-9_-]/', '-', $s);
    return substr($s, 0, 80);
}
function sanitize_filename($s){
    $s = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$s);
    return substr($s, 0, 200);
}

if (!is_dir($INTAKE_DIR_BASE)) { @mkdir($INTAKE_DIR_BASE, 0775, true); }

// Two upload modes: (a) multipart/form-data with $_FILES, (b) JSON body with base64 content
$agency_slug = '';
$task_note   = '';
$ts          = gmdate('Y-m-d\TH-i-s\Z');
$received    = [];

if (!empty($_FILES)) {
    // Multipart upload (preferred · streams direct to disk · no base64 overhead)
    $agency_slug = sanitize_slug($_POST['agency_slug'] ?? 'unspecified');
    $task_note   = substr(trim((string)($_POST['task_note'] ?? '')), 0, 2000);
    $dir = $INTAKE_DIR_BASE . '/' . $agency_slug;
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    foreach ($_FILES as $field => $f) {
        if (!isset($f['name']) || !is_array($f)) continue;
        $names = (array)$f['name'];
        $tmps  = (array)$f['tmp_name'];
        $sizes = (array)$f['size'];
        $types = (array)$f['type'];
        $errs  = (array)$f['error'];
        for ($i = 0; $i < count($names); $i++) {
            if ((int)$errs[$i] !== UPLOAD_ERR_OK) {
                $received[] = ['name'=>$names[$i],'ok'=>false,'error'=>'upload error code '.$errs[$i]];
                continue;
            }
            if ((int)$sizes[$i] > $MAX_BYTES) {
                $received[] = ['name'=>$names[$i],'ok'=>false,'error'=>'file exceeds 25 MB limit'];
                continue;
            }
            $safe = sanitize_filename($names[$i]);
            $dest = $dir . '/' . $ts . '__' . $safe;
            if (@move_uploaded_file($tmps[$i], $dest)) {
                $received[] = ['name'=>$names[$i],'ok'=>true,'bytes'=>(int)$sizes[$i],'type'=>$types[$i],'stored_at'=>'/data/agency_intake/'.$agency_slug.'/'.$ts.'__'.$safe];
            } else {
                $received[] = ['name'=>$names[$i],'ok'=>false,'error'=>'move_uploaded_file failed'];
            }
        }
    }
} else {
    // JSON body mode (single small file · base64-encoded · for browser-FileReader dropzone)
    $raw = file_get_contents('php://input');
    $in = json_decode($raw, true);
    if (!is_array($in)) { echo json_encode(['ok'=>false,'error'=>'no files in multipart and no JSON body']); exit; }
    $agency_slug = sanitize_slug($in['agency_slug'] ?? 'unspecified');
    $task_note   = substr(trim((string)($in['task_note'] ?? '')), 0, 2000);
    $files       = isset($in['files']) && is_array($in['files']) ? $in['files'] : [];
    $dir = $INTAKE_DIR_BASE . '/' . $agency_slug;
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    foreach ($files as $f) {
        $name = sanitize_filename($f['name'] ?? 'unnamed');
        $b64  = $f['data_b64'] ?? '';
        if (!$b64) { $received[] = ['name'=>$name,'ok'=>false,'error'=>'no data_b64']; continue; }
        $bin = base64_decode($b64, true);
        if ($bin === false) { $received[] = ['name'=>$name,'ok'=>false,'error'=>'bad base64']; continue; }
        if (strlen($bin) > $MAX_BYTES) { $received[] = ['name'=>$name,'ok'=>false,'error'=>'exceeds 25 MB']; continue; }
        $dest = $dir . '/' . $ts . '__' . $name;
        if (@file_put_contents($dest, $bin) !== false) {
            $received[] = ['name'=>$name,'ok'=>true,'bytes'=>strlen($bin),'type'=>$f['type'] ?? '','stored_at'=>'/data/agency_intake/'.$agency_slug.'/'.$ts.'__'.$name];
        } else {
            $received[] = ['name'=>$name,'ok'=>false,'error'=>'write failed'];
        }
    }
}

// Log metadata (no content) for any sibling triage
$log_row = [
    'ts'           => gmdate('Y-m-d\TH:i:s\Z'),
    'agency_slug'  => $agency_slug,
    'task_note_len'=> strlen($task_note),
    'count'        => count($received),
    'ok_count'     => count(array_filter($received, function($r){ return !empty($r['ok']); })),
    'total_bytes'  => array_sum(array_map(function($r){ return !empty($r['ok']) ? (int)($r['bytes'] ?? 0) : 0; }, $received))
];
@file_put_contents($LOG, json_encode($log_row) . "\n", FILE_APPEND);

echo json_encode([
    'ok'          => true,
    'agency_slug' => $agency_slug,
    'received'    => $received,
    'task_note'   => $task_note ? '[length '.strlen($task_note).' chars]' : null,
    'storage'     => '/data/agency_intake/' . $agency_slug . '/',
    'note'        => 'Files stored on VPS · accessible to Maya agent dispatchers via filesystem · metadata logged · per GLOBAL-93 no vendor names',
]);
