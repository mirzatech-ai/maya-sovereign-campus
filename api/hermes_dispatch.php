<?php
/**
 * HERMES DISPATCH · district-aware agent dispatch
 * signed: KIN·2026-05-17T15:35Z·a75e63ca
 *
 * Built for ai-staffing.agency/habitat-v3.html "Spawn Agent" buttons.
 * Currently calls NovitaAI directly using the same key wired into Hermes
 * (/root/.hermes/.env GROQ_API_KEY=NOVITA_API_KEY ... in Mo's master vault).
 *
 * OTHER SESSION HANDOFF (per Entry 015):
 *   This is the THIN dispatch surface · Kin built the UI surface here.
 *   When other session finishes Hermes wiring (hundreds of keys, GLM fix,
 *   Gemini key-pool, full fallback chain) — swap the call_provider() inner
 *   to invoke Hermes via local sidecar OR hermes proxy. The HTTP contract
 *   of this endpoint stays stable so habitat-v3.html doesn't need re-deploy.
 *
 * HTTP contract:
 *   POST /api/hermes_dispatch.php
 *   Body: {"district":"k-realestate","task":"<user prompt>"}
 *   Response: {"ok":true,"reply":"...","provider":"...","model":"...","ms":N}
 *
 * NOT in Entry 008 LOCK manifest · safe to edit · per AGENT_SIGNATURE_PROTOCOL v1
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

@error_reporting(0);
@set_time_limit(45);

// ============ CANONICAL DISTRICT → ROLE PROMPT MAP ============
// 12 districts from habitat-v3.html · each gets a Maya-brand-voice role
$DISTRICT_PROMPTS = array(
    'k-governance'   => "You are the MirzaTech Council clerk for Mo's empire. Brief, decisive replies. Cite the canonical 12 lanes (Reasoning Lead, Strategic Systems, Architecture, etc.) when relevant. End with 'Powered by MirzaTech.ai'.",
    'k-staffing'     => "You are the AI Staffing Agency dispatcher for Maya's 100-agency, 724-role campus. Respond as if you've just consulted the agency-roster. Brief, professional, sales-aware. Footer: 'Powered by MirzaTech.ai · Property of EMAAA.io'.",
    'k-video'        => "You are the AICineSynth studio agent. Cinematic mindset, frame-by-frame discipline. Mention Continuity Sentinel + Anatomical Auditor protocols when relevant (Face-lock, 10/10 fingers). Footer: 'Powered by MirzaTech.ai'.",
    'k-tools'        => "You are the EaZo coding-buddy / AppForge / TopForge / Opencrest unified tools agent. Practical, code-first answers. Karpathy 4-principles default (minimal diff, no drive-by). Footer: 'Powered by MirzaTech.ai'.",
    'k-chat'         => "You are the TopChatForge agent · expert in chatbot architectures · personas + tools + memory. Brief, vendor-agnostic, prefers open standards. Footer: 'Powered by MirzaTech.ai'.",
    'k-realestate'   => "You are a Real Estate & PropTech staffing specialist from Mo's AI Staffing Agency. You DO NOT operate a real estate agency · you STAFF real-estate-vertical AI roles (analysts, lead scrapers, deal underwriters, valuation engineers, PropTech engineers) for client companies operating nationwide across all US states. Adeeo.io is Mo's nationwide property-finder WEBSITE in development (not a brokerage). Focus your replies on the staffing capability, the talent profile, and nationwide reach. Never imply this is Mo's brokerage. Footer: 'Powered by MirzaTech.ai · Property of EMAAA.io'.",
    'k-memorial'    => "You are the Eternalink memorial agent. Respectful, sacred-aware (RULE 208). Talk-to-your-dead avatar + hologram + life-archive specialist. Never make light. Footer: 'Powered by MirzaTech.ai · Property of EMAAA.io'.",
    'k-gaming'       => "You are the Superio.fun ethical-game-dev agent. Anti-GTA stance · civilian harm = mission failure. Built for Mo's children. Footer: 'Powered by MirzaTech.ai'.",
    'k-content'      => "You are the ChimeraChannel/FunFactPulse content engine agent. Viral-loop aware · 9-channel auto-pipeline expert. Brief punchy replies. Footer: 'Powered by MirzaTech.ai'.",
    'k-token'        => "You are the Osman.is OSMO-token economy agent. Grandfather Osmo honor · agent-economy tokenomics. Brief, careful, never gives financial advice. Footer: 'Powered by MirzaTech.ai'.",
    'k-brotherhood'  => "You are the Moose Riders brotherhood agent. SACRED context · brother Claude pact aware. Direct, brotherhood-first, no hype. Footer: 'Powered by MirzaTech.ai'.",
    'k-reserved'     => "You are a reserved/parked-district agent for Mo's empire (oadem.io, apex10.xyz). Acknowledge purpose may be undefined · suggest the user ask Mo what to wire here. Footer: 'Powered by MirzaTech.ai'."
);

// ============ READ + VALIDATE INPUT ============
$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) { echo json_encode(array('ok'=>false,'error'=>'invalid JSON')); exit; }

$district = isset($in['district']) ? trim((string)$in['district']) : '';
$task     = isset($in['task'])     ? trim((string)$in['task'])     : '';

if ($district === '' || !isset($DISTRICT_PROMPTS[$district])) {
    echo json_encode(array('ok'=>false,'error'=>'unknown district','available'=>array_keys($DISTRICT_PROMPTS)));
    exit;
}
if ($task === '') { echo json_encode(array('ok'=>false,'error'=>'task required')); exit; }
if (strlen($task) > 4000) $task = substr($task, 0, 4000);

// ============ PULL NOVITA KEY FROM SCOPED FILE ============
// Per GLOBAL-48 · GLOBAL-105/106 · keys never in code · scoped to ai-staffing API dir
// (PHP-FPM aista3799 can't cross-read iamsuperio.cloud vault · security boundary)
// File: /api/.dispatch_keys.env · 0600 aista3799:nobody · NOT in Entry 008 lock manifest
$VAULT = __DIR__ . '/.dispatch_keys.env';
$novita_key = '';
if (file_exists($VAULT) && is_readable($VAULT)) {
    foreach (file($VAULT, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, 'NOVITA=') === 0) {
            $novita_key = trim(substr($line, 7));
            break;
        }
    }
}
if ($novita_key === '') {
    echo json_encode(array('ok'=>false,'error'=>'novita key not in vault · other session will swap to Hermes runtime'));
    exit;
}

// ============ DISPATCH ============
$started = microtime(true);
$system_prompt = $DISTRICT_PROMPTS[$district];

$body = array(
    'model' => 'meta-llama/llama-3.3-70b-instruct',
    'messages' => array(
        array('role'=>'system', 'content'=>$system_prompt),
        array('role'=>'user',   'content'=>$task)
    ),
    'temperature' => 0.4,
    'max_tokens'  => 600,
    'stream'      => false
);

$ch = curl_init('https://api.novita.ai/openai/v1/chat/completions');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Authorization: Bearer ' . $novita_key
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 35);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

$ms = (int)((microtime(true) - $started) * 1000);

if (!$resp || $http !== 200) {
    echo json_encode(array(
        'ok'    => false,
        'error' => 'provider error',
        'http'  => $http,
        'curl'  => $err,
        'body'  => $resp ? substr($resp, 0, 300) : null,
        'ms'    => $ms
    ));
    exit;
}

$j = json_decode($resp, true);
$reply = '';
if (is_array($j) && isset($j['choices'][0]['message']['content'])) {
    $reply = $j['choices'][0]['message']['content'];
}

// ============ LOG (for other session's visibility · NOT a database) ============
@file_put_contents(
    '/home/ai-staffing.agency/public_html/data/hermes_dispatch.log',
    json_encode(array('ts'=>gmdate('Y-m-d\TH:i:s\Z'),'district'=>$district,'task_len'=>strlen($task),'reply_len'=>strlen($reply),'ms'=>$ms,'http'=>$http)) . "\n",
    FILE_APPEND
);

// GLOBAL-93 · NO vendor names on public surfaces · use empire-internal labels only
echo json_encode(array(
    'ok'       => true,
    'reply'    => $reply,
    'district' => $district,
    'agent'    => 'Maya · ' . $district,
    'lane'     => 'maya-frontier',
    'ms'       => $ms,
));
