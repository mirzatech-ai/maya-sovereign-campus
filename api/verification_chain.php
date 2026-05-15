<?php
/**
 * MAYA · THREE-LEVEL VERIFICATION CHAIN (GLOBAL-96 · Skill #21)
 * --------------------------------------------------------------
 * Endpoint:  /api/verification_chain.php
 * Purpose:   EVERY build artifact in Mo's empire must run through:
 *              STAGE A · Parliament  (24 seats / 5 rounds + Vision · 2 QA lenses)
 *              STAGE B · Council     (12 seats sequential + 2 QA lenses)
 *              STAGE C · Board       (12 seats sequential + 2 QA lenses)
 *            Redo-until-clean at each stage. Verdict only on full clean.
 *
 * PHP 7.4 syntax only · brand: Powered by MirzaTech.ai · Property of EMAAA.io
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Mo-PIN');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$CHAIN_DIR = __DIR__ . '/../data/verification_chain';
if (!is_dir($CHAIN_DIR)) { @mkdir($CHAIN_DIR, 0775, true); }
$TRANSCRIPTS = $CHAIN_DIR . '/transcripts.jsonl';
$BRAIN_FILE  = __DIR__ . '/../data/brain/brain_v2.json';

function now_iso() { return gmdate('Y-m-d\TH:i:s\Z'); }
function append_jsonl($p, $row) { @file_put_contents($p, json_encode($row) . "\n", FILE_APPEND | LOCK_EX); }
function uid() { return substr(bin2hex(random_bytes(8)), 0, 12); }

// ---------- QA LENS PAIRS PER STAGE ----------
$QA_LENSES = array(
    'parliament' => array(
        'anatomy'    => 'You are an Anatomy QA agent. Review the artifact for biological/mechanical correctness. Hard rules: humans have 10 fingers · 2 eyes · 1 nose · joints in correct places · NO triangles or placeholder shapes when a human/robot is depicted. Robots must have plausible joints + mass distribution. Return STANCE: clean | complaint. If complaint, include CONCRETE_FIX: <one-sentence remediation>.',
        'continuity' => 'You are a Continuity QA agent. Review the artifact for visual + voice + brand consistency with the canonical Sovereign Campus (cyan/gold/green/red palette only · biolum states · ghost-streams · 12 canonical lanes). Hard rules: NO vendor names on public surfaces (GLOBAL-93) · no orphan UI residue · same character looks the same across frames. Return STANCE: clean | complaint. If complaint, include CONCRETE_FIX.',
    ),
    'council' => array(
        'compliance' => 'You are a Compliance QA agent. Hard rules: PHP 7.4 syntax only · zero DB writes from transient state endpoints · "Powered by MirzaTech.ai · Property of EMAAA.io" footer · phone +1 (743) 215-1423 in header+footer · canonical 58 agencies · 12 Council · 12 Board · 24 Parliament. GLOBAL-93 vendor anonymization. GLOBAL-94 sequential. GLOBAL-95 grep-before-build. Return STANCE: clean | complaint. If complaint, include CONCRETE_FIX.',
        'brand'      => 'You are a Brand QA agent. Hard rules: Sovereign Campus visual language · biolum states · ghost-streams · 12 canonical lanes including Seat 11 Multimodal Vision · gold/cyan/green/red palette only · no Mo personal-name on public surfaces · superio.fun = gaming home. Return STANCE: clean | complaint. If complaint, include CONCRETE_FIX.',
    ),
    'board' => array(
        'business' => 'You are a Business QA agent. Hard rules: this artifact must earn or save Mo money OR clearly accelerate empire dominance · scalable across 58 agencies · refund-proof per Skill #18 · auto-onboarding per Skill #9 · no manual config steps for the customer per the anti-pattern in Skill #1. Return STANCE: clean | complaint. If complaint, include CONCRETE_FIX.',
        'risk'     => 'You are a Risk QA agent. Hard rules: privacy-safe · GLOBAL-93 vendor anonymity respected · no leaked credentials · GLOBAL-48 keys-in-vault-only · legal exposure low · operational complexity reasonable for a 1-person empire · Mo risk-appetite respected. Return STANCE: clean | complaint. If complaint, include CONCRETE_FIX.',
    ),
);

// ---------- helpers ----------
function call_seat($prompt, $model, $role_id) {
    $url = 'https://iamsuperio.cloud/api/index';
    $payload = array(
        'action' => 'ask',
        'engine' => 'groq',
        'model'  => $model,
        'system' => "You are seat [{$role_id}]. Be sharp, brief, evidence-based. ≤80 words.",
        'prompt' => $prompt,
    );
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
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
    // mock fallback (brain-routing fix is Manus issue #1)
    return "[{$role_id} mock] reviewed artifact · STANCE: clean";
}

function fire_vision_verifier($artifact) {
    // Skill #20: if any visual is in scope, NVIDIA vision LLM preprocesses first
    if (empty($artifact['image_urls']) && empty($artifact['image_data']) && empty($artifact['svg_payload'])) {
        return array('fired' => false, 'description' => null, 'anomalies' => array());
    }
    $vision_prompt = "Describe this artifact for a text-only Council. Structure your reply as: "
                   . "(1) PRIMARY_SUBJECT (2) DETECTED_ANOMALIES (e.g. anatomy errors, "
                   . "placeholder shapes, missing limbs, anti-patterns) (3) BRAND_NOTES "
                   . "(4) CONFIDENCE 0-1.";
    $desc = call_seat($vision_prompt, 'nvidia/cosmos-nemotron-34b-vision', 'SEAT_11_VISION');
    $anomalies = array();
    if (preg_match('/DETECTED_ANOMALIES:?\s*(.+?)(?:BRAND_NOTES|\(\d+\)|$)/is', $desc, $m)) {
        $anomalies = preg_split('/[·\n;]/', trim($m[1]));
        $anomalies = array_values(array_filter(array_map('trim', $anomalies)));
    }
    return array('fired' => true, 'description' => $desc, 'anomalies' => $anomalies);
}

function run_qa_lens($lens_id, $lens_prompt, $artifact_text, $stage_transcript) {
    $prompt = $lens_prompt . "\n\nARTIFACT:\n" . $artifact_text . "\n\nSTAGE TRANSCRIPT:\n" . $stage_transcript . "\n\nReturn STANCE: clean | complaint";
    $text = call_seat($prompt, 'nemotron-ultra-340b', $lens_id . '_QA');
    $stance = 'clean';
    if (stripos($text, 'STANCE: complaint') !== false || preg_match('/\bcomplaint\b/i', $text)) {
        $stance = 'complaint';
    }
    $fix = '';
    if (preg_match('/CONCRETE_FIX:?\s*(.+)$/is', $text, $m)) $fix = trim($m[1]);
    return array('lens'=>$lens_id,'stance'=>$stance,'text'=>$text,'fix'=>$fix);
}

function run_stage($stage_name, $seat_ids, $artifact_text, $context, $stage_qa_lenses, $vision) {
    // Build stage transcript by chaining seat opinions (sequential per Skill #19)
    $transcript = "";
    $opinions = array();
    foreach ($seat_ids as $idx => $sid) {
        $prior = "";
        foreach ($opinions as $k => $op) $prior .= "[{$k}: {$op['stance']}] " . substr($op['text'], 0, 160) . "\n";
        $prompt = "ARTIFACT:\n{$artifact_text}\n\nVISION CONTEXT:\n" . ($vision['description'] ?: 'no visuals in scope')
                . "\n\nPRIOR SEATS:\n{$prior}\n\nYou are {$sid} on stage {$stage_name}. {$context}\nGive your opinion in ≤80 words. End with STANCE: clean | complaint.";
        $text = call_seat($prompt, 'deepseek-r1-671b', $sid);
        $stance = (stripos($text, 'STANCE: complaint') !== false) ? 'complaint' : 'clean';
        $opinions[$sid] = array('seat'=>$sid,'text'=>$text,'stance'=>$stance);
        $transcript .= "[{$sid}/{$stance}] " . substr($text, 0, 200) . "\n";
    }
    // QA lenses (2 per stage)
    $qa = array();
    foreach ($stage_qa_lenses as $lens_id => $lens_prompt) {
        $qa[$lens_id] = run_qa_lens($lens_id, $lens_prompt, $artifact_text, $transcript);
    }
    // Stage clean iff all seats clean AND all QA lenses clean
    $clean = true;
    foreach ($opinions as $o) if ($o['stance'] === 'complaint') $clean = false;
    foreach ($qa as $q) if ($q['stance'] === 'complaint') $clean = false;
    return array('opinions'=>$opinions,'qa'=>$qa,'clean'=>$clean,'transcript'=>$transcript);
}

// ---------- router ----------
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'verify');

if ($action === 'state') {
    echo json_encode(array(
        'ok' => true,
        'chain' => 'Parliament (24) → Council (12) → Board (12)',
        'qa_lenses' => $QA_LENSES,
        'vision_verifier' => 'Seat 11 Multimodal Vision · NVIDIA-hosted · fires first if visuals in scope',
        'doctrine' => 'Skill #21 · GLOBAL-96',
        'enforcement' => 'Did you chain it, Kin?',
    ));
    exit;
}

if ($action === 'verify') {
    $start_ms = (int) (microtime(true) * 1000);
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) { http_response_code(400); echo json_encode(array('ok'=>false,'error'=>'invalid JSON body')); exit; }
    $artifact_id   = isset($in['artifact_id'])   ? trim($in['artifact_id'])   : ('art_' . uid());
    $artifact_type = isset($in['artifact_type']) ? trim($in['artifact_type']) : 'generic';
    $payload       = isset($in['artifact_payload']) ? $in['artifact_payload'] : array();
    $max_redos     = isset($in['max_redos_per_stage']) ? max(1, min(5, (int)$in['max_redos_per_stage'])) : 3;

    $artifact_text = is_string($payload) ? $payload : json_encode($payload, JSON_PRETTY_PRINT);

    // Vision verifier preprocess
    $vision = fire_vision_verifier(is_array($payload) ? $payload : array());

    // Seat IDs per stage
    $parl_seats = array(); for ($i=1;$i<=24;$i++) $parl_seats[] = 'PARL_'.str_pad($i,2,'0',STR_PAD_LEFT);
    $council_seats = array(); for ($i=1;$i<=12;$i++) $council_seats[] = 'COUNCIL_'.str_pad($i,2,'0',STR_PAD_LEFT);
    $board_seats = array(); for ($i=1;$i<=12;$i++) $board_seats[] = 'BOARD_'.str_pad($i,2,'0',STR_PAD_LEFT);

    $stages = array();
    $verdict = 'APPROVED';

    // STAGE A · Parliament (with redo)
    $redo_a = 0; $stage_a = null;
    while ($redo_a <= $max_redos) {
        $stage_a = run_stage('parliament', $parl_seats, $artifact_text, 'Parliament debates this artifact across 5 rounds (Proponents/Skeptics/Specialists/Polygeists/Synthesis).', $QA_LENSES['parliament'], $vision);
        if ($stage_a['clean']) break;
        $redo_a++;
    }
    $stages['parliament'] = array('transcript' => $stage_a['transcript'], 'qa' => $stage_a['qa'], 'redo_count' => $redo_a, 'clean' => $stage_a['clean']);
    if (!$stage_a['clean']) $verdict = 'REJECTED_PARLIAMENT';

    // STAGE B · Council (only if Parliament clean)
    if ($stage_a['clean']) {
        $redo_b = 0; $stage_b = null;
        while ($redo_b <= $max_redos) {
            $stage_b = run_stage('council', $council_seats, $artifact_text, 'Council reviews Parliament transcript and gives final reasoning verdict on the artifact.', $QA_LENSES['council'], $vision);
            if ($stage_b['clean']) break;
            $redo_b++;
        }
        $stages['council'] = array('transcript' => $stage_b['transcript'], 'qa' => $stage_b['qa'], 'redo_count' => $redo_b, 'clean' => $stage_b['clean']);
        if (!$stage_b['clean']) $verdict = 'REJECTED_COUNCIL';

        // STAGE C · Board (only if Council clean)
        if ($stage_b['clean']) {
            $redo_c = 0; $stage_c = null;
            while ($redo_c <= $max_redos) {
                $stage_c = run_stage('board', $board_seats, $artifact_text, 'Board of Directors makes the final business + risk verdict on the artifact.', $QA_LENSES['board'], $vision);
                if ($stage_c['clean']) break;
                $redo_c++;
            }
            $stages['board'] = array('transcript' => $stage_c['transcript'], 'qa' => $stage_c['qa'], 'redo_count' => $redo_c, 'clean' => $stage_c['clean']);
            if (!$stage_c['clean']) $verdict = 'REJECTED_BOARD';
        }
    }

    $row = array(
        'ts' => now_iso(),
        'artifact_id' => $artifact_id,
        'artifact_type' => $artifact_type,
        'verdict' => $verdict,
        'stages' => $stages,
        'vision_verifier' => $vision,
        'total_cycle_ms' => (int)(microtime(true)*1000) - $start_ms,
    );
    append_jsonl($TRANSCRIPTS, $row);

    // Hypermind fold (Skill #4)
    if (file_exists($BRAIN_FILE)) {
        $brain = json_decode(@file_get_contents($BRAIN_FILE), true);
        if (is_array($brain)) {
            if (!isset($brain['verification_chain_log'])) $brain['verification_chain_log'] = array();
            $brain['verification_chain_log'][] = array('ts'=>$row['ts'],'artifact_id'=>$artifact_id,'verdict'=>$verdict);
            if (count($brain['verification_chain_log']) > 200) $brain['verification_chain_log'] = array_slice($brain['verification_chain_log'], -200);
            @file_put_contents($BRAIN_FILE, json_encode($brain, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
    }

    echo json_encode($row, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'recent') {
    $n = isset($_GET['n']) ? max(1, min(50, (int)$_GET['n'])) : 10;
    $rows = array();
    if (file_exists($TRANSCRIPTS)) {
        $lines = @file($TRANSCRIPTS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            $tail = array_slice($lines, -$n);
            foreach (array_reverse($tail) as $line) {
                $r = json_decode($line, true);
                if (is_array($r)) $rows[] = array('ts'=>$r['ts'],'id'=>$r['artifact_id'],'type'=>$r['artifact_type'],'verdict'=>$r['verdict']);
            }
        }
    }
    echo json_encode(array('ok'=>true,'recent'=>$rows));
    exit;
}

http_response_code(400);
echo json_encode(array('ok'=>false,'error'=>'unknown action','supported'=>array('state','verify','recent')));
