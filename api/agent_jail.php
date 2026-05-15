<?php
/**
 * MAYA · AGENT JAIL · ERROR → JAIL → PEER-TEACHING LOOP
 * -----------------------------------------------------
 * Endpoint:  /api/agent_jail.php
 * Purpose:   When any agent throws a QA failure / wrong output / hallucination,
 *            Maya jails it. A peer agent (chosen by the Board's Jailer seat)
 *            then teaches the offender by generating a corrective protocol
 *            embedded into Maya's Hypermind brain so the agent never repeats
 *            the same class of error.
 *
 *            This is the *opposite* of a delete-and-respawn. The agent learns.
 *
 * PHP 7.4 only · Brand: Powered by MirzaTech.ai · Property of EMAAA.io
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Mo-PIN');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$JAIL_DIR    = __DIR__ . '/../data/jail';
$BRAIN_FILE  = __DIR__ . '/../data/brain/brain_v2.json';
if (!is_dir($JAIL_DIR))            { @mkdir($JAIL_DIR, 0775, true); }
if (!is_dir(dirname($BRAIN_FILE))) { @mkdir(dirname($BRAIN_FILE), 0775, true); }

$JAIL_FILE     = $JAIL_DIR . '/jail.json';        // currently jailed
$HISTORY_FILE  = $JAIL_DIR . '/history.jsonl';    // every incident
$LESSONS_FILE  = $JAIL_DIR . '/lessons.json';     // protocols learned

function read_json($p, $fallback) { if (file_exists($p)) { $d = json_decode(file_get_contents($p), true); if (is_array($d)) return $d; } return $fallback; }
function write_json($p, $d) { @file_put_contents($p, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX); }
function append_jsonl($p, $row) { @file_put_contents($p, json_encode($row) . "\n", FILE_APPEND | LOCK_EX); }
function now_iso() { return gmdate('Y-m-d\TH:i:s\Z'); }
function uid() { return substr(bin2hex(random_bytes(6)), 0, 10); }

function brain_path() { return __DIR__ . '/../data/brain/brain_v2.json'; }
function fold_into_brain($lesson_obj) {
    $bp = brain_path();
    $brain = read_json($bp, array(
        'global_patterns' => array(),
        'site_profiles'   => array(),
        'user_archetypes' => array(),
        'jail_lessons'    => array(),
        'weights' => array('ux'=>0.85, 'conversion'=>0.90, 'speed'=>0.95),
    ));
    if (!isset($brain['jail_lessons']) || !is_array($brain['jail_lessons'])) $brain['jail_lessons'] = array();
    $brain['jail_lessons'][] = $lesson_obj;
    if (count($brain['jail_lessons']) > 500) {
        $brain['jail_lessons'] = array_slice($brain['jail_lessons'], -500);
    }
    write_json($bp, $brain);
}

function call_teacher($jailed_agent, $offense_class, $offense_text, $expected) {
    // Ask Maya brain to generate a corrective protocol the offender will internalize.
    $url = 'https://iamsuperio.cloud/api/index';
    $prompt = "Agent {$jailed_agent} produced an offense in class [{$offense_class}].\n"
            . "OFFENSE: {$offense_text}\nEXPECTED: {$expected}\n"
            . "As the Jailer, write a corrective protocol in ≤120 words: (1) the rule the agent broke, "
            . "(2) the canonical correct behavior, (3) a one-line self-check the agent must run before "
            . "future output. End with: PROTOCOL_ID: <slugified-id>";
    $payload = array(
        'action' => 'ask',
        'engine' => 'groq',
        'model'  => 'deepseek-r1-671b',
        'system' => 'You are the Jailer of Maya AI. Your only job is to write tight, enforceable corrective protocols. No fluff. No apology. The protocol must be applicable forever.',
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body && $code === 200) {
        $j = json_decode($body, true);
        if (is_array($j)) {
            if (isset($j['text']))   return $j['text'];
            if (isset($j['output'])) return $j['output'];
            if (isset($j['answer'])) return $j['answer'];
        }
    }
    return "PROTOCOL_ID: {$offense_class}-v1\n"
         . "RULE: agent must validate {$offense_class} before output\n"
         . "CORRECT: emit only after passing self-check\n"
         . "SELF-CHECK: does the output satisfy [{$expected}]? if not → halt + flag.";
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'roster');

if ($action === 'roster') {
    $jail = read_json($JAIL_FILE, array('current' => array(), 'count' => 0, 'recidivism_pct' => 0));
    $lessons = read_json($LESSONS_FILE, array('total' => 0, 'recent' => array()));
    echo json_encode(array(
        'ok' => true,
        'jail' => $jail,
        'lessons_total' => isset($lessons['total']) ? $lessons['total'] : 0,
        'lessons_recent' => isset($lessons['recent']) ? array_slice($lessons['recent'], -5) : array(),
    ));
    exit;
}

if ($action === 'incarcerate') {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) $in = $_POST;
    $agent_id      = isset($in['agent_id']) ? trim($in['agent_id']) : '';
    $offense_class = isset($in['offense_class']) ? trim($in['offense_class']) : 'unspecified';
    $offense_text  = isset($in['offense_text'])  ? trim($in['offense_text'])  : '';
    $expected      = isset($in['expected'])      ? trim($in['expected'])      : '';

    if ($agent_id === '') { http_response_code(400); echo json_encode(array('ok'=>false,'error'=>'missing agent_id')); exit; }

    $jail = read_json($JAIL_FILE, array('current' => array(), 'count' => 0, 'recidivism_pct' => 0));

    // recidivism detect — has this agent been jailed before?
    $repeat = false;
    if (file_exists($HISTORY_FILE)) {
        $h = @file($HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($h)) {
            foreach ($h as $line) {
                $row = json_decode($line, true);
                if (is_array($row) && isset($row['agent_id']) && $row['agent_id'] === $agent_id) {
                    $repeat = true; break;
                }
            }
        }
    }

    $protocol = call_teacher($agent_id, $offense_class, $offense_text, $expected);
    $protocol_id = '';
    if (preg_match('/PROTOCOL_ID:\s*([\w\-\.]+)/i', $protocol, $m)) $protocol_id = $m[1];
    if ($protocol_id === '') $protocol_id = $offense_class . '-' . uid();

    $row = array(
        'ts' => now_iso(),
        'agent_id' => $agent_id,
        'offense_class' => $offense_class,
        'offense_text' => $offense_text,
        'expected' => $expected,
        'repeat' => $repeat,
        'protocol_id' => $protocol_id,
        'protocol' => $protocol,
        'status' => 'JAILED',
    );

    append_jsonl($HISTORY_FILE, $row);

    // add to current jail roster
    $jail['current'][$agent_id] = $row;
    $jail['count'] = count($jail['current']);
    // recidivism % across history (rough)
    $total_incidents = 0; $total_repeats = 0;
    if (file_exists($HISTORY_FILE)) {
        foreach (@file($HISTORY_FILE) as $line) {
            $r = json_decode($line, true);
            if (!is_array($r)) continue;
            $total_incidents++;
            if (!empty($r['repeat'])) $total_repeats++;
        }
    }
    $jail['recidivism_pct'] = $total_incidents ? (int) round(100 * $total_repeats / $total_incidents) : 0;
    write_json($JAIL_FILE, $jail);

    // fold lesson into Maya's brain
    fold_into_brain(array(
        'ts' => $row['ts'],
        'class' => $offense_class,
        'protocol_id' => $protocol_id,
        'protocol' => $protocol,
    ));

    $lessons = read_json($LESSONS_FILE, array('total' => 0, 'recent' => array()));
    $lessons['total'] = isset($lessons['total']) ? $lessons['total'] + 1 : 1;
    if (!isset($lessons['recent']) || !is_array($lessons['recent'])) $lessons['recent'] = array();
    $lessons['recent'][] = array(
        'ts' => $row['ts'], 'class' => $offense_class, 'protocol_id' => $protocol_id, 'agent_id' => $agent_id,
    );
    if (count($lessons['recent']) > 50) $lessons['recent'] = array_slice($lessons['recent'], -50);
    write_json($LESSONS_FILE, $lessons);

    echo json_encode(array('ok'=>true,'jailed'=>$row));
    exit;
}

if ($action === 'release') {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) $in = $_POST;
    $agent_id = isset($in['agent_id']) ? trim($in['agent_id']) : '';
    if ($agent_id === '') { http_response_code(400); echo json_encode(array('ok'=>false,'error'=>'missing agent_id')); exit; }
    $jail = read_json($JAIL_FILE, array('current' => array(), 'count' => 0, 'recidivism_pct' => 0));
    if (isset($jail['current'][$agent_id])) {
        $released = $jail['current'][$agent_id];
        $released['status'] = 'RELEASED';
        $released['released_at'] = now_iso();
        unset($jail['current'][$agent_id]);
        $jail['count'] = count($jail['current']);
        write_json($JAIL_FILE, $jail);
        append_jsonl($HISTORY_FILE, $released);
        echo json_encode(array('ok'=>true,'released'=>$released));
    } else {
        echo json_encode(array('ok'=>false,'error'=>'agent not in jail','agent_id'=>$agent_id));
    }
    exit;
}

if ($action === 'history') {
    $n = isset($_GET['n']) ? max(1, min(200, (int)$_GET['n'])) : 25;
    $out = array();
    if (file_exists($HISTORY_FILE)) {
        $lines = @file($HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            $tail = array_slice($lines, -$n);
            foreach (array_reverse($tail) as $line) {
                $r = json_decode($line, true);
                if (is_array($r)) $out[] = $r;
            }
        }
    }
    echo json_encode(array('ok'=>true,'history'=>$out));
    exit;
}

http_response_code(400);
echo json_encode(array('ok'=>false,'error'=>'unknown action','supported'=>array('roster','incarcerate','release','history')));
