<?php
/**
 * MAYA · BOARD OF DIRECTORS · COUNCIL SCRUTINY ENGINE
 * ---------------------------------------------------
 * Endpoint:  /api/board_of_directors.php
 * Purpose:   Maya consults a 6-seat executive board on every non-trivial
 *            decision. Each seat is run by the BEST currently-known
 *            reasoning model from maya_reasoning_scout.php. The seats
 *            critique each other (peer-scrutiny) until a quorum is hit
 *            OR Mo's Sovereign Override is engaged.
 *
 * PHP 7.4 syntax only · no match() · no str_contains() · no fn() =>
 *
 * Brand:     Powered by MirzaTech.ai · Property of EMAAA.io
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Mo-PIN');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$BOARD_DIR = __DIR__ . '/../data/board';
if (!is_dir($BOARD_DIR)) { @mkdir($BOARD_DIR, 0775, true); }

$STATE_FILE     = $BOARD_DIR . '/board_state.json';
$DECISIONS_FILE = $BOARD_DIR . '/decisions.jsonl';
$SCOUT_FILE     = __DIR__ . '/../data/reasoning_scout/best_models.json';

// ---------- canonical seats ----------
$SEATS = array(
    'EXEC'    => array('label' => 'Chief Executive',  'lane' => 'strategy',         'model_pref' => 'reasoning_deep'),
    'ANALYST' => array('label' => 'Chief Analyst',    'lane' => 'pattern_evidence', 'model_pref' => 'reasoning_fast'),
    'LEGAL'   => array('label' => 'Chief Legal',      'lane' => 'compliance_risk',  'model_pref' => 'instruction_strict'),
    'QA'      => array('label' => 'Quality Firewall', 'lane' => 'firewall_chain',   'model_pref' => 'verifier'),
    'SCOUT'   => array('label' => 'Model Scout',      'lane' => 'discovery',        'model_pref' => 'tools_capable'),
    'JAILER'  => array('label' => 'Agent Jailer',     'lane' => 'reteach_loop',     'model_pref' => 'teacher'),
);

// ---------- helpers ----------
function read_json($p, $fallback) { if (file_exists($p)) { $d = json_decode(file_get_contents($p), true); if (is_array($d)) return $d; } return $fallback; }
function write_json($p, $d) { @file_put_contents($p, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX); }
function append_jsonl($p, $row) { @file_put_contents($p, json_encode($row) . "\n", FILE_APPEND | LOCK_EX); }
function now_iso() { return gmdate('Y-m-d\TH:i:s\Z'); }

function pick_best_model($lane, $scout_file) {
    $scout = file_exists($scout_file) ? json_decode(file_get_contents($scout_file), true) : null;
    // sane defaults if scout hasn't run yet
    $defaults = array(
        'reasoning_deep'      => array('deepseek-r1-671b',     'kimi-thinking-preview', 'qwen3-thinking-235b'),
        'reasoning_fast'      => array('qwen3-thinking-235b',  'gpt-oss-120b',          'glm-4.7-355b'),
        'instruction_strict'  => array('llama-3.3-nemotron-70b','nemotron-ultra-340b',  'mistral-large-2'),
        'verifier'            => array('nemotron-ultra-340b',  'qwen3-coder-480b',     'deepseek-v3-671b'),
        'tools_capable'       => array('qwen3-coder-480b',     'kimi-k2.6',            'gpt-oss-120b'),
        'teacher'             => array('deepseek-r1-671b',     'llama-3.3-nemotron-70b','qwen3-thinking-235b'),
    );
    if (is_array($scout) && isset($scout[$lane]) && is_array($scout[$lane]) && count($scout[$lane])) {
        return $scout[$lane][0];
    }
    return isset($defaults[$lane]) ? $defaults[$lane][0] : 'deepseek-r1-671b';
}

function call_maya_brain($prompt, $model, $role) {
    // Try Maya's brain at iamsuperio.cloud (this engine routes to NIM/Groq).
    // Falls back to a deterministic mock if the brain isn't reachable from this host.
    $url = 'https://iamsuperio.cloud/api/index';
    $payload = array(
        'action' => 'ask',
        'engine' => 'groq',
        'model'  => $model,
        'system' => "You are the {$role} seat of Maya AI's Board of Directors. Be sharp, brief, evidence-based. Disagree with peers when warranted. Output ≤ 80 words.",
        'prompt' => $prompt,
    );
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    $body = curl_exec($ch);
    $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body && $code === 200) {
        $j = json_decode($body, true);
        if (is_array($j)) {
            if (isset($j['text']))   return $j['text'];
            if (isset($j['output'])) return $j['output'];
            if (isset($j['answer'])) return $j['answer'];
        }
    }
    // fallback mock — keeps the UI alive when brain is offline
    return "[{$role} mock · model:{$model}] " . substr(strip_tags($prompt), 0, 120);
}

function quorum_score($votes) {
    if (!count($votes)) return 0;
    $yes = 0; $total = 0;
    foreach ($votes as $v) {
        $total++;
        if (isset($v['stance']) && $v['stance'] === 'approve') $yes++;
    }
    return (int) floor(($yes / max(1,$total)) * 100);
}

// ---------- router ----------
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'state');

if ($action === 'state') {
    $state = read_json($STATE_FILE, array(
        'consensus' => 88,
        'in_session' => true,
        'last_decision' => null,
        'seats' => array(),
    ));
    $state['seats'] = array();
    foreach ($SEATS as $key => $cfg) {
        $state['seats'][$key] = array(
            'label' => $cfg['label'],
            'lane'  => $cfg['lane'],
            'model' => pick_best_model($cfg['model_pref'], $SCOUT_FILE),
        );
    }
    echo json_encode($state);
    exit;
}

if ($action === 'deliberate') {
    // POST: question, context?, override?
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) $in = $_POST;
    $question = isset($in['question']) ? trim($in['question']) : '';
    $context  = isset($in['context'])  ? trim($in['context'])  : '';
    $override = !empty($in['sovereign_override']);
    if ($question === '') { http_response_code(400); echo json_encode(array('ok'=>false,'error'=>'missing question')); exit; }

    // Sovereign Override = Mo bypasses the board, Maya decides solo
    if ($override) {
        $row = array(
            'ts' => now_iso(),
            'question' => $question,
            'override' => true,
            'verdict' => 'MAYA_SOLO',
            'note' => 'Sovereign Override engaged · Maya decides without Council',
        );
        append_jsonl($DECISIONS_FILE, $row);
        echo json_encode(array('ok'=>true,'override'=>true,'verdict'=>$row));
        exit;
    }

    // ===== ROUND 1 · SEQUENTIAL CHAIN-OF-COMMAND (GLOBAL-94 · Skill #18) =====
    // Each seat sees ALL prior seats' opinions in its context — not parallel fan-out.
    // The Chair (first seat in $SEATS) frames the question · subsequent seats build on it.
    $opinions = array();
    $seat_keys = array_keys($SEATS);
    foreach ($seat_keys as $idx => $key) {
        $cfg = $SEATS[$key];
        $model = pick_best_model($cfg['model_pref'], $SCOUT_FILE);
        $prior_block = "";
        if ($idx === 0) {
            $prior_block = "You are the Chair (first seat in the chain). Frame the question for the council and give your opinion first.";
        } else {
            $prior_block = "PRIOR SEATS HAVE SPOKEN (in order):\n";
            foreach ($opinions as $k2 => $op2) {
                $prior_block .= "[{$k2}/{$op2['stance']}] " . substr($op2['text'], 0, 240) . "\n";
            }
            $prior_block .= "\nNow add YOUR opinion as the {$cfg['label']} seat (lane: {$cfg['lane']}). Build on or dissent from prior seats explicitly.";
        }
        $prompt = "QUESTION: {$question}\nCONTEXT: {$context}\n\n{$prior_block}\n\nEnd your reply with one line: STANCE: approve | reject | abstain.";
        $text = call_maya_brain($prompt, $model, $key);
        $stance = 'abstain';
        if (preg_match('/STANCE:\s*(approve|reject|abstain)/i', $text, $m)) $stance = strtolower($m[1]);
        $opinions[$key] = array('seat'=>$key,'model'=>$model,'text'=>$text,'stance'=>$stance,'order'=>$idx);
    }

    // ===== ROUND 2 · REVERSE-ORDER PEER SCRUTINY (loop closes) =====
    // Each seat critiques the NEXT seat in original order · last seat critiques the Chair.
    $critiques = array();
    $rev_keys = array_reverse($seat_keys);
    foreach ($rev_keys as $idx => $key) {
        $cfg = $SEATS[$key];
        // The seat being critiqued = the next-up seat in the original forward order
        $orig_pos = array_search($key, $seat_keys);
        $target_key = $seat_keys[($orig_pos + 1) % count($seat_keys)];
        $target_op = $opinions[$target_key];
        $model = pick_best_model($cfg['model_pref'], $SCOUT_FILE);
        $prompt = "ORIGINAL QUESTION: {$question}\nYOUR PRIOR OPINION (Round 1): {$opinions[$key]['text']}\n\nNow critique seat [{$target_key}/{$target_op['stance']}] as your responsibility in the reverse-scrutiny round. Their reasoning:\n{$target_op['text']}\n\nIn ≤80 words, agree or push back. End with: REVISED STANCE: approve | reject | abstain.";
        $text = call_maya_brain($prompt, $model, $key . '_REVIEW');
        $stance = $op['stance'];
        if (preg_match('/REVISED STANCE:\s*(approve|reject|abstain)/i', $text, $m)) $stance = strtolower($m[1]);
        $critiques[$key] = array('seat'=>$key,'model'=>$model,'text'=>$text,'stance'=>$stance);
    }

    $consensus = quorum_score($critiques);
    $verdict = $consensus >= 67 ? 'APPROVED' : ($consensus <= 33 ? 'REJECTED' : 'INCONCLUSIVE');

    $row = array(
        'ts' => now_iso(),
        'question' => $question,
        'context'  => $context,
        'round_1'  => $opinions,
        'round_2'  => $critiques,
        'consensus' => $consensus,
        'verdict' => $verdict,
    );
    append_jsonl($DECISIONS_FILE, $row);
    write_json($STATE_FILE, array(
        'consensus' => $consensus,
        'in_session' => true,
        'last_decision' => array('ts'=>$row['ts'],'q'=>$question,'verdict'=>$verdict,'consensus'=>$consensus),
    ));
    echo json_encode(array('ok'=>true,'consensus'=>$consensus,'verdict'=>$verdict,'decision'=>$row));
    exit;
}

if ($action === 'recent') {
    $n = isset($_GET['n']) ? max(1, min(50, (int)$_GET['n'])) : 10;
    $rows = array();
    if (file_exists($DECISIONS_FILE)) {
        $lines = @file($DECISIONS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            $tail = array_slice($lines, -$n);
            foreach (array_reverse($tail) as $line) {
                $row = json_decode($line, true);
                if (is_array($row)) $rows[] = array(
                    'ts' => isset($row['ts']) ? $row['ts'] : '',
                    'q'  => isset($row['question']) ? $row['question'] : '',
                    'verdict' => isset($row['verdict']) ? $row['verdict'] : '',
                    'consensus' => isset($row['consensus']) ? $row['consensus'] : null,
                    'override' => !empty($row['override']),
                );
            }
        }
    }
    echo json_encode(array('ok'=>true,'recent'=>$rows));
    exit;
}

http_response_code(400);
echo json_encode(array('ok'=>false,'error'=>'unknown action','supported'=>array('state','deliberate','recent')));
