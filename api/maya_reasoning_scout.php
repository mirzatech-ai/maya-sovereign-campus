<?php
/**
 * MAYA · REASONING MODEL SCOUT
 * ----------------------------
 * Endpoint:  /api/maya_reasoning_scout.php
 * Purpose:   Maya must ALWAYS be using the best available reasoning model
 *            for each lane (strategy/analysis/legal/verifier/etc.). This
 *            scout polls NVIDIA NIM (and any other catalog Maya can reach)
 *            once a day, classifies each model by capability, and writes
 *            the result to /data/reasoning_scout/best_models.json which the
 *            Board of Directors uses to pick its seat models.
 *
 *            "She must be tasked to always search out for and employ" — Mo
 *
 * PHP 7.4 only · Brand: Powered by MirzaTech.ai · Property of EMAAA.io
 *
 * Trigger:   cron-curl or hit /api/maya_reasoning_scout.php?action=refresh
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$OUT_DIR  = __DIR__ . '/../data/reasoning_scout';
$OUT_FILE = $OUT_DIR . '/best_models.json';
$CATALOG  = $OUT_DIR . '/catalog_raw.json';
if (!is_dir($OUT_DIR)) { @mkdir($OUT_DIR, 0775, true); }

// ----- which keys can the scout use? -----
// reads .env shipped on the server: NVIDIA_NIM_KEYS=key1,key2,...
function load_nim_keys() {
    $env_paths = array(
        __DIR__ . '/.nim_keys.env',           // PHP-readable copy (canonical)
        __DIR__ . '/../.env',
        '/home/mirzatech.ai/public_html/.env',
        '/home/iamsuperio.cloud/public_html/api/.maya_master_keys.env',
    );
    $keys = array();
    foreach ($env_paths as $p) {
        if (!file_exists($p)) continue;
        $body = @file_get_contents($p);
        if (!$body) continue;
        // accept any NVIDIA_*, NV_*, NIM_* env line whose value is an nvapi-* token
        preg_match_all('/^(?:NVIDIA_[A-Z0-9_]+|NV_[A-Z0-9_]+|NIM_[A-Z0-9_]+|NVAPI_[A-Z0-9_]+)\s*=\s*["\']?(nvapi-[A-Za-z0-9_\-\.]+)/m', $body, $m);
        if (!empty($m[1])) $keys = array_merge($keys, $m[1]);
        // also accept bare nvapi-... lines from key bucket files
        preg_match_all('/(nvapi-[A-Za-z0-9_\-]{40,})/', $body, $m2);
        if (!empty($m2[1])) $keys = array_merge($keys, $m2[1]);
    }
    return array_values(array_unique($keys));
}

function fetch_nim_catalog($key) {
    $ch = curl_init('https://integrate.api.nvidia.com/v1/models');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$body) return null;
    return json_decode($body, true);
}

function classify($name) {
    $n = strtolower($name);
    $lanes = array();

    // reasoning-deep: long-chain thinkers
    if (strpos($n, 'r1') !== false || strpos($n, 'thinking') !== false || strpos($n, 'o1') !== false || strpos($n, 'reasoning') !== false) {
        $lanes[] = 'reasoning_deep';
    }
    // reasoning-fast: medium reasoning, faster
    if (strpos($n, 'qwen3') !== false || strpos($n, 'gpt-oss') !== false || strpos($n, 'glm-4') !== false || strpos($n, 'mistral-large') !== false) {
        $lanes[] = 'reasoning_fast';
    }
    // instruction-strict: legal/compliance writing
    if (strpos($n, 'nemotron') !== false || strpos($n, 'llama-3.3') !== false || strpos($n, 'mistral') !== false) {
        $lanes[] = 'instruction_strict';
    }
    // verifier: large guard / verifier types
    if (strpos($n, 'ultra') !== false || strpos($n, 'guard') !== false || strpos($n, '340b') !== false || strpos($n, 'coder') !== false) {
        $lanes[] = 'verifier';
    }
    // tools-capable: known good tool-callers
    if (strpos($n, 'coder') !== false || strpos($n, 'kimi') !== false || strpos($n, 'gpt-oss') !== false || strpos($n, 'qwen3-coder') !== false) {
        $lanes[] = 'tools_capable';
    }
    // teacher: best at explaining + correcting
    if (strpos($n, 'r1') !== false || strpos($n, 'nemotron') !== false || strpos($n, 'thinking') !== false) {
        $lanes[] = 'teacher';
    }
    return array_values(array_unique($lanes));
}

function rank_score($name) {
    // crude param-size + family heuristic; replace with real benchmarks in v2
    $n = strtolower($name);
    $score = 0;
    if (preg_match('/(\d{2,4})b/', $n, $m)) $score += min(100, (int)$m[1]);
    if (strpos($n, 'thinking') !== false) $score += 40;
    if (strpos($n, 'r1') !== false)       $score += 50;
    if (strpos($n, 'ultra') !== false)    $score += 30;
    if (strpos($n, 'preview') !== false)  $score += 8;
    if (strpos($n, 'coder') !== false)    $score += 12;
    return $score;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'state';

if ($action === 'refresh') {
    $keys = load_nim_keys();
    if (empty($keys)) {
        echo json_encode(array('ok'=>false,'error'=>'no NIM keys found in env files'));
        exit;
    }
    $models = array();
    foreach ($keys as $k) {
        $cat = fetch_nim_catalog($k);
        if (!is_array($cat) || !isset($cat['data'])) continue;
        foreach ($cat['data'] as $m) {
            $id = isset($m['id']) ? $m['id'] : (isset($m['name']) ? $m['name'] : null);
            if (!$id) continue;
            $models[$id] = isset($models[$id]) ? $models[$id] : array(
                'id' => $id,
                'lanes' => classify($id),
                'score' => rank_score($id),
                'seen_by_keys' => 0,
            );
            $models[$id]['seen_by_keys']++;
        }
    }
    // pick top-N per lane
    $by_lane = array(
        'reasoning_deep' => array(), 'reasoning_fast' => array(), 'instruction_strict' => array(),
        'verifier' => array(),       'tools_capable' => array(),  'teacher' => array(),
    );
    foreach ($models as $m) {
        foreach ($m['lanes'] as $lane) {
            if (!isset($by_lane[$lane])) continue;
            $by_lane[$lane][] = $m;
        }
    }
    foreach ($by_lane as $lane => &$arr) {
        usort($arr, function($a,$b){ return $b['score'] <=> $a['score']; });
        $arr = array_slice(array_map(function($x){ return $x['id']; }, $arr), 0, 6);
    }
    unset($arr);

    $out = array(
        'updated_at'  => gmdate('Y-m-d\TH:i:s\Z'),
        'keys_used'   => count($keys),
        'models_seen' => count($models),
        'reasoning_deep'     => $by_lane['reasoning_deep'],
        'reasoning_fast'     => $by_lane['reasoning_fast'],
        'instruction_strict' => $by_lane['instruction_strict'],
        'verifier'           => $by_lane['verifier'],
        'tools_capable'      => $by_lane['tools_capable'],
        'teacher'            => $by_lane['teacher'],
    );
    @file_put_contents($OUT_FILE, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @file_put_contents($CATALOG,  json_encode(array_values($models), JSON_PRETTY_PRINT), LOCK_EX);
    echo json_encode($out);
    exit;
}

if ($action === 'state') {
    if (file_exists($OUT_FILE)) {
        echo file_get_contents($OUT_FILE);
    } else {
        echo json_encode(array(
            'ok' => false,
            'error' => 'scout has not refreshed yet · call ?action=refresh',
            'defaults' => array(
                'reasoning_deep' => array('deepseek-r1-671b','kimi-thinking-preview','qwen3-thinking-235b'),
                'reasoning_fast' => array('qwen3-thinking-235b','gpt-oss-120b','glm-4.7-355b'),
            ),
        ));
    }
    exit;
}

http_response_code(400);
echo json_encode(array('ok'=>false,'error'=>'unknown action','supported'=>array('state','refresh')));
