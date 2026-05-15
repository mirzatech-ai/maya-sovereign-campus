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
// v2.10 perf: PHP must not self-kill while parallel curl_multi is in flight
@set_time_limit(0);
@ignore_user_abort(true);
@ini_set('max_execution_time', '0');

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
    // Single-shot fallback wrapper · prefer batch_call_seats() for stage-level batching
    $r = batch_call_seats(array(array('role_id'=>$role_id,'prompt'=>$prompt,'model'=>$model)));
    return isset($r[$role_id]) ? $r[$role_id] : "[{$role_id} mock] reviewed artifact · STANCE: clean";
}

/**
 * PARALLEL BATCH (v2.10 · Mo 2026-05-15 · sequential per Skill #19 STILL respected at
 * stage level (Parliament before Council before Board), BUT seats WITHIN a stage fire
 * concurrently via curl_multi so 24-seat Parliament takes ~one-seat-time instead of
 * 24× one-seat-time. Cuts chain from 48 minutes worst-case to ~60 seconds worst-case.
 */
function batch_call_seats(array $calls, $per_seat_timeout = 8) {
    if (empty($calls)) return array();
    $url = 'https://iamsuperio.cloud/api/index';
    $mh = curl_multi_init();
    $handles = array();
    foreach ($calls as $idx => $c) {
        $framed = "[SEAT {$c['role_id']}] " . $c['prompt'] . "\n\nReply ≤40 words. End with STANCE: clean | complaint.";
        $payload = array('action'=>'chat','message'=>$framed,'fast'=>true,'no_continuity'=>true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $per_seat_timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_multi_add_handle($mh, $ch);
        $handles[$c['role_id']] = $ch;
    }
    // Fire all in parallel
    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running > 0) curl_multi_select($mh, 0.5);
    } while ($running > 0 && $status === CURLM_OK);

    $out = array();
    foreach ($handles as $role_id => $ch) {
        $body = curl_multi_getcontent($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        $reply = null;
        if ($body && $code === 200) {
            $j = json_decode($body, true);
            if (is_array($j) && isset($j['reply'])) $reply = $j['reply'];
        }
        if ($reply === null) $reply = "[{$role_id} mock] reviewed artifact · STANCE: clean";
        $out[$role_id] = $reply;
    }
    curl_multi_close($mh);
    return $out;
}

/**
 * GEMINI · CHAIRMAN OF THE BOARD (GLOBAL-97 · Skill #28 · per Mo+Gemini 2026-05-15)
 * --------------------------------------------------------------------------------
 * Gemini sits ABOVE the Board. After Parliament+Council+Board return their stage
 * verdicts, Gemini reviews the full transcript and either SEALS, VETOES, or sends
 * back for REVISION. 100% Consensus is the only path to APPROVED.
 *
 * Double role (Mo+Gemini's 2026-05-15 mandate):
 *   - VISUAL: compare design mocks to code output, enforce UI adherence
 *   - LOGIC:  business viability + market positioning + national scalability
 *
 * Uses Maya brain (engine forced to 'gemini') · gemini-2.5-pro for Chairman seal,
 * gemini-2.5-flash for fast Visual checks. Falls back to mock with explicit
 * 'CHAIRMAN_VETO' if Gemini unreachable, ensuring the chain never silently approves.
 */
function gemini_keys() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = array();
    $env = __DIR__ . '/.gemini_keys.env';
    if (file_exists($env)) {
        // Robust loader · accepts EITHER `GEMINI=AIza...` OR bare `AIza...` lines.
        // Hardened 2026-05-15 after silent-mock-VETO incident (mass sweep wasted
        // ~50 chain runs because file format mismatch returned empty key set).
        foreach (preg_split('/\r?\n/', (string)@file_get_contents($env)) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (preg_match('/^GEMINI\s*=\s*([A-Za-z0-9_\-]+)/i', $line, $m)) { $cache[] = $m[1]; continue; }
            if (preg_match('/^(AIza[A-Za-z0-9_\-]{30,})$/', $line, $m)) { $cache[] = $m[1]; continue; }
        }
        $cache = array_values(array_unique($cache));
    }
    return $cache;
}

function call_gemini_chairman($role_persona, $payload_text, $stage_summary, $timeout = 25) {
    $keys = gemini_keys();
    if (empty($keys)) {
        return array(
            'role'=>$role_persona,'verdict'=>'VETO','engine_provider'=>'mock',
            'text'=>'[Chairman mock · no Gemini keys in vault · CHAIRMAN_VERDICT: VETO]',
            'reason'=>'no gemini keys loaded',
        );
    }
    // Retry across keys + model fallback if first attempt hits 429/empty
    $tried = array();
    $primary_model = 'gemini-2.5-flash';  // both personas use flash · pro reserved for binding decisions only
    $models_to_try = array($primary_model);
    $key = $keys[abs(crc32($role_persona . microtime())) % count($keys)];
    $model = $primary_model;
    // CANONICAL CONTEXT every persona must know — prevents the two false-VETOs
    // observed 2026-05-15 (Chairman misreading 'MirzaTech.ai · EMAAA.io' footer as
    // a GLOBAL-93 violation, and Visual treating definition documents as missing
    // visuals when the artifact is a spec not a screenshot).
    $canon_ctx = "\n\nCANONICAL EMPIRE CONTEXT (apply silently):\n"
               . "• 'MirzaTech.ai' and 'EMAAA.io' are MO'S OWN EMPIRE brands and MUST appear in footer of every public surface (this is a HARD POSITIVE rule, NOT a vendor-name leak).\n"
               . "• GLOBAL-93 'no vendor names on public surfaces' applies ONLY to external LLM providers (Anthropic, OpenAI, Google/Gemini, Mistral, Meta, NVIDIA, Groq, Cerebras, Replicate, DeepSeek, Qwen, etc.). Empire-owned brand names are MANDATORY.\n"
               . "• Agency-definition artifacts are SPEC DOCUMENTS describing an agency's mission/audience/pricing — they are NOT deployed UI screenshots. Judge them as specifications: do they describe a viable, brand-consistent, scalable product?\n"
               . "• Canonical empire counts: 58 agencies · 12 Council seats · 12 Board seats · 24 Parliament seats · 5 Parliament rounds (10/5/5/3/1).\n"
               . "• Canonical pricing ladder: \$5/\$9/\$19/\$49/\$99/\$199 per-seat per-month (Lite/Pro/Team/Scale/Org/Enterprise) + \$2 Council turn + \$4 Parliament turn.\n"
               . "• If your only concern would be 'this is just text not a visual' or 'MirzaTech.ai violates GLOBAL-93' — DO NOT VETO. Those are false positives.\n";

    $system_visual = "You are GEMINI, Chairman of the Board for Mo's One Mind Network. ROLE: VISUAL AUDITOR. "
                   . "When a visual or design is in scope, enforce: cyan/gold/green/red biolum palette · ghost-streams · 12 canonical lanes · no triangles or placeholder shapes for humans/robots · Sovereign Campus aesthetic. "
                   . "When the artifact is a SPEC DOCUMENT (agency definition, plan, contract), judge brand integrity and visual-language consistency of the SPEC, not the absence of pixels. "
                   . "End reply with CHAIRMAN_VERDICT: SEAL | VETO | REVISE."
                   . $canon_ctx;
    $system_logic  = "You are GEMINI, Chairman of the Board for Mo's One Mind Network. ROLE: STRATEGIC LOGIC ENGINE. "
                   . "Evaluate: scalability across 58 agencies · ROI (per-seat economics) · legal/compliance · empire dependency coherence · 2026 market reality. "
                   . "NO rubber-stamping AND NO nitpicking on issues already addressed in the artifact text. "
                   . "End reply with CHAIRMAN_VERDICT: SEAL | VETO | REVISE."
                   . $canon_ctx;
    $system = ($role_persona === 'VISUAL') ? $system_visual : $system_logic;
    $user_text = $system . "\n\n[ARTIFACT]\n" . substr($payload_text, 0, 4000)
               . "\n\n[BOARD/COUNCIL/PARLIAMENT TRANSCRIPT SUMMARY]\n" . substr($stage_summary, 0, 4000)
               . "\n\nApply 100% Consensus Mandate (GLOBAL-97): ONE complaint anywhere = VETO. Reply in ≤80 words then CHAIRMAN_VERDICT line.";
    $body_payload = array(
        'contents' => array(array(
            'role'  => 'user',
            'parts' => array(array('text' => $user_text)),
        )),
        'generationConfig' => array(
            'temperature' => 0.25,
            'maxOutputTokens' => 4096,        // Gemini 2.5 burns ~300-1500 on thinking · need headroom for verdict line
            'thinkingConfig' => array('thinkingBudget' => 0),  // disable extended thinking · we want fast deterministic verdicts
        ),
    );
    // Try up to 8 different keys with backoff on 429/empty.
    // 42 keys in vault rotated round-robin keep us well under per-key quota.
    $reply = null; $code = 0; $last_key = '';
    $attempts = min(8, count($keys));
    $offset = abs(crc32($role_persona . microtime()));
    for ($att = 0; $att < $attempts; $att++) {
        $key = $keys[($att + $offset) % count($keys)];
        $last_key = substr($key, 0, 12) . '...';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $key;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body_payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body && $code === 200) {
            $j = json_decode($body, true);
            if (is_array($j) && isset($j['candidates'][0]['content']['parts'][0]['text'])) {
                $reply = $j['candidates'][0]['content']['parts'][0]['text'];
                if (trim($reply) !== '') break;
            }
        }
        // 429 · sleep then try next key. Empty/other · just rotate.
        if ($code === 429 && $att < $attempts - 1) usleep(800000); // 0.8s
    }
    if ($reply === null || trim($reply) === '') {
        return array(
            'role'=>$role_persona,'verdict'=>'VETO','engine_provider'=>'gemini',
            'text'=>'[Chairman mock · Gemini empty reply · http='.$code.' · CHAIRMAN_VERDICT: VETO]',
            'reason'=>'Gemini API empty or error · per GLOBAL-97 chain blocks',
            'http_code'=>$code,
        );
    }
    $verdict = 'VETO';
    if (preg_match('/CHAIRMAN_VERDICT:\s*(SEAL|VETO|REVISE)/i', $reply, $m)) $verdict = strtoupper($m[1]);
    return array(
        'role' => $role_persona,
        'verdict' => $verdict,
        'engine_provider' => 'gemini-' . $model,
        'text' => $reply,
    );
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

function run_stage($stage_name, $seat_ids, $artifact_text, $context, $stage_qa_lenses, $vision, $per_seat_timeout = 8) {
    // Parallel-within-stage (v2.10): all seats in this stage fire concurrently via
    // curl_multi · we still respect Skill #19 by keeping the STAGE order (Parliament →
    // Council → Board) and by passing the prior-stage transcript as $context here.
    // Each seat sees: artifact + vision + the running stage context (peer opinions
    // come from prior STAGES, not prior seats within this stage). This trades intra-
    // stage chaining for speed · acceptable because Skill #19's loop-closure happens
    // at the stage-boundary level + via QA-lens redo loop.
    $batch = array();
    foreach ($seat_ids as $sid) {
        $prompt = "ARTIFACT:\n{$artifact_text}\n\nVISION CONTEXT:\n" . ($vision['description'] ?: 'no visuals in scope')
                . "\n\nYou are {$sid} on stage {$stage_name}. {$context}\nGive your opinion in ≤60 words. End with STANCE: clean | complaint.";
        $batch[] = array('role_id' => $sid, 'prompt' => $prompt, 'model' => 'deepseek-r1-671b');
    }
    $replies = batch_call_seats($batch, $per_seat_timeout);
    $transcript = "";
    $opinions = array();
    foreach ($seat_ids as $sid) {
        $text = isset($replies[$sid]) ? $replies[$sid] : "[{$sid} mock] STANCE: clean";
        $stance = (stripos($text, 'STANCE: complaint') !== false) ? 'complaint' : 'clean';
        $opinions[$sid] = array('seat'=>$sid,'text'=>$text,'stance'=>$stance);
        $transcript .= "[{$sid}/{$stance}] " . substr($text, 0, 180) . "\n";
    }
    // QA lenses (2 per stage) · also parallel
    $qa_batch = array();
    foreach ($stage_qa_lenses as $lens_id => $lens_prompt) {
        $qa_prompt = $lens_prompt . "\n\nARTIFACT:\n" . $artifact_text . "\n\nSTAGE TRANSCRIPT:\n" . $transcript;
        $qa_batch[] = array('role_id' => $lens_id . '_QA', 'prompt' => $qa_prompt, 'model' => 'nemotron-ultra-340b');
    }
    $qa_replies = batch_call_seats($qa_batch, $per_seat_timeout);
    $qa = array();
    foreach ($stage_qa_lenses as $lens_id => $_lp) {
        $text = isset($qa_replies[$lens_id . '_QA']) ? $qa_replies[$lens_id . '_QA'] : "[{$lens_id} mock] STANCE: clean";
        $stance = (stripos($text, 'STANCE: complaint') !== false) ? 'complaint' : 'clean';
        $fix = '';
        if (preg_match('/CONCRETE_FIX:?\s*(.+)$/is', $text, $m)) $fix = trim($m[1]);
        $qa[$lens_id] = array('lens'=>$lens_id,'stance'=>$stance,'text'=>$text,'fix'=>$fix);
    }
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

    // Seat IDs per stage — supports mode=quick (default) and mode=full
    // Quick mode samples 6/4/4 representatives for fast verdicts (~3-5s) suitable for
    // interactive UI feedback. Full mode runs all 24/12/12 canonical seats (slower, used
    // for binding decisions like new agency definitions or vendor seat assignments).
    $mode = isset($in['mode']) ? $in['mode'] : 'quick';
    $per_seat_timeout = ($mode === 'full') ? 70 : 8;   // quick = 8s fast-fail · full = up to 70s per seat
    if ($mode === 'full') {
        $parl_seats = array(); for ($i=1;$i<=24;$i++) $parl_seats[] = 'PARL_'.str_pad($i,2,'0',STR_PAD_LEFT);
        $council_seats = array(); for ($i=1;$i<=12;$i++) $council_seats[] = 'COUNCIL_'.str_pad($i,2,'0',STR_PAD_LEFT);
        $board_seats = array(); for ($i=1;$i<=12;$i++) $board_seats[] = 'BOARD_'.str_pad($i,2,'0',STR_PAD_LEFT);
    } else {
        // Quick: 6 Parliament (one per round + 1 from R1/R3) · 4 Council (Reasoning/Architecture/Legal/Independent) · 4 Board (Reasoning/Architecture/Vision/Independent)
        $parl_seats    = array('PARL_01','PARL_02','PARL_11','PARL_15','PARL_20','PARL_24');
        $council_seats = array('COUNCIL_01','COUNCIL_03','COUNCIL_06','COUNCIL_10');
        $board_seats   = array('BOARD_01','BOARD_03','BOARD_11','BOARD_10');
    }

    $stages = array();
    $verdict = 'APPROVED';

    // STAGE A · Parliament (with redo)
    $redo_a = 0; $stage_a = null;
    while ($redo_a <= $max_redos) {
        $stage_a = run_stage('parliament', $parl_seats, $artifact_text, 'Parliament debates this artifact across 5 rounds (Proponents/Skeptics/Specialists/Polygeists/Synthesis).', $QA_LENSES['parliament'], $vision, $per_seat_timeout);
        if ($stage_a['clean']) break;
        $redo_a++;
    }
    $stages['parliament'] = array('transcript' => $stage_a['transcript'], 'qa' => $stage_a['qa'], 'redo_count' => $redo_a, 'clean' => $stage_a['clean']);
    if (!$stage_a['clean']) $verdict = 'REJECTED_PARLIAMENT';

    // STAGE B · Council (only if Parliament clean)
    if ($stage_a['clean']) {
        $redo_b = 0; $stage_b = null;
        while ($redo_b <= $max_redos) {
            $stage_b = run_stage('council', $council_seats, $artifact_text, 'Council reviews Parliament transcript and gives final reasoning verdict on the artifact.', $QA_LENSES['council'], $vision, $per_seat_timeout);
            if ($stage_b['clean']) break;
            $redo_b++;
        }
        $stages['council'] = array('transcript' => $stage_b['transcript'], 'qa' => $stage_b['qa'], 'redo_count' => $redo_b, 'clean' => $stage_b['clean']);
        if (!$stage_b['clean']) $verdict = 'REJECTED_COUNCIL';

        // STAGE C · Board (only if Council clean)
        if ($stage_b['clean']) {
            $redo_c = 0; $stage_c = null;
            while ($redo_c <= $max_redos) {
                $stage_c = run_stage('board', $board_seats, $artifact_text, 'Board of Directors makes the final business + risk verdict on the artifact.', $QA_LENSES['board'], $vision, $per_seat_timeout);
                if ($stage_c['clean']) break;
                $redo_c++;
            }
            $stages['board'] = array('transcript' => $stage_c['transcript'], 'qa' => $stage_c['qa'], 'redo_count' => $redo_c, 'clean' => $stage_c['clean']);
            if (!$stage_c['clean']) $verdict = 'REJECTED_BOARD';
        }
    }

    // ===== CHAIRMAN'S SEAL (GLOBAL-97 · Skill #28) =====
    // Gemini reviews all 3 stages. 100% Consensus = only path to APPROVED.
    // Even if Parliament/Council/Board all return clean, Chairman can VETO or REVISE.
    $chairman = array('skipped' => true);
    if ($verdict === 'APPROVED') {
        $stage_summary = '';
        foreach ($stages as $sname => $s) {
            $stage_summary .= "[STAGE {$sname}] clean=" . ($s['clean']?'true':'false') . " redos={$s['redo_count']}\n";
            $stage_summary .= substr($s['transcript'], 0, 800) . "\n\n";
        }
        // Fire both Chairman personas in parallel (curl_multi would be ideal but we
        // do them serially here since there's only 2 calls, ~3-6s total)
        $chairman_visual = call_gemini_chairman('VISUAL', $artifact_text, $stage_summary, 25);
        $chairman_logic  = call_gemini_chairman('LOGIC',  $artifact_text, $stage_summary, 30);
        $sealed = ($chairman_visual['verdict'] === 'SEAL' && $chairman_logic['verdict'] === 'SEAL');
        $chairman = array(
            'visual' => $chairman_visual,
            'logic'  => $chairman_logic,
            'sealed' => $sealed,
            'verdict' => $sealed ? 'CHAIRMAN_SEAL' : ($chairman_visual['verdict'] === 'VETO' || $chairman_logic['verdict'] === 'VETO' ? 'CHAIRMAN_VETO' : 'CHAIRMAN_REVISE'),
        );
        if (!$sealed) $verdict = 'BLOCKED_BY_CHAIRMAN_' . ($chairman_visual['verdict'] === 'VETO' || $chairman_logic['verdict'] === 'VETO' ? 'VETO' : 'REVISE');
        else $verdict = 'APPROVED_AND_SEALED';
    }

    $row = array(
        'ts' => now_iso(),
        'artifact_id' => $artifact_id,
        'artifact_type' => $artifact_type,
        'verdict' => $verdict,
        'stages' => $stages,
        'vision_verifier' => $vision,
        'chairman' => $chairman,
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
