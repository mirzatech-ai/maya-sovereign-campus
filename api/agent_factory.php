<?php
/**
 * MAYA · AGENT FACTORY (Skill #30)
 * --------------------------------
 * Mo 2026-05-15: "create every agent for every agency"
 *
 * Reads every agency + every role from staff.php and instantiates each role
 * as a concrete agent JSON: persona, system_prompt, model_assignment, memory
 * namespace. Idempotent: re-running updates timestamps + version, does not
 * destroy existing agent files.
 *
 * Endpoints:
 *   /api/agent_factory.php?action=preview            · count what would be built
 *   /api/agent_factory.php?action=build              · build all agents (HTTP-safe via background)
 *   /api/agent_factory.php?action=status             · build progress (when running in background)
 *   /api/agent_factory.php?action=report             · last build summary
 *
 * CLI:
 *   php agent_factory.php build                      · runs synchronously
 *
 * Storage:
 *   /home/ai-staffing.agency/public_html/data/agents/<agency>/<role-slug>.json
 *
 * PHP 7.4 · brand: Powered by MirzaTech.ai · Property of EMAAA.io
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
@set_time_limit(0);
@ignore_user_abort(true);

$BASE       = 'https://ai-staffing.agency';
$AGENTS_DIR = '/home/ai-staffing.agency/public_html/data/agents';
$STATE      = $AGENTS_DIR . '/_factory_state.json';
$LATEST     = $AGENTS_DIR . '/_factory_latest.json';
if (!is_dir($AGENTS_DIR)) @mkdir($AGENTS_DIR, 0775, true);

function f_fetch_staff($BASE) {
    $ch = curl_init($BASE . '/api/staff.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $body = curl_exec($ch); curl_close($ch);
    $j = json_decode($body, true);
    return (is_array($j) && isset($j['agencies'])) ? $j['agencies'] : array();
}

function f_fetch_agency_full($BASE, $id) {
    $ch = curl_init($BASE . '/api/staff.php?action=agency&id=' . urlencode($id));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $body = curl_exec($ch); curl_close($ch);
    $j = json_decode($body, true);
    return (is_array($j) && isset($j['agency'])) ? $j['agency'] : null;
}

function f_slug($s) {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/**
 * Model assignment policy · GLOBAL-105 canon
 *   - tier=executive  → Maya code lane (DeepSeek V4 via NIM) for binding decisions
 *   - tier=senior     → Maya code lane (DeepSeek V4)
 *   - tier=mid|junior → Maya chat lane (Groq · cheap+fast)
 * Always routes through Maya brain · never direct vendor calls.
 */
function f_model_for_tier($tier) {
    $t = strtolower((string)$tier);
    if ($t === 'executive' || $t === 'senior') {
        return array('lane'=>'code','primary'=>'maya:code:deepseek-v4','fallback'=>'maya:code:kimi-k2.6','brain'=>'https://iamsuperio.cloud/api/brain');
    }
    return array('lane'=>'chat','primary'=>'maya:chat:groq','fallback'=>'maya:chat:gemini','brain'=>'https://iamsuperio.cloud/api/brain');
}

function f_build_persona($role_title, $agency_name, $tier, $skills) {
    $skill_line = implode(' · ', array_slice($skills, 0, 4));
    return "You are a {$tier}-tier {$role_title} in the {$agency_name} agency under Maya Sovereign Campus. "
         . "Operate with depth on: {$skill_line}. Refuse out-of-scope work and route it to the correct agency. "
         . "Speak in Mo Osmanović's empire voice: direct · brotherhood-first · no hype · no toxic positivity. "
         . "Footer every customer-facing artifact with 'Powered by MirzaTech.ai · Property of EMAAA.io'.";
}

function f_build_system_prompt($role_title, $agency, $persona) {
    $maya_prompt = isset($agency['maya_prompt']) ? $agency['maya_prompt'] : '';
    return $persona . "\n\nAGENCY CONTEXT:\n" . $maya_prompt
         . "\n\nHARD RULES:\n"
         . "1. No external LLM vendor names on public surfaces (GLOBAL-93).\n"
         . "2. No PII leakage · no credentials in output.\n"
         . "3. Stripe payment required before any binding deliverable (refund-proof Skill #18).\n"
         . "4. Bind every commit to verification_chain.php + Chairman's Seal (Skill #21 + #28).\n"
         . "5. Output cyan/gold/green/red biolum palette only for visual artifacts.\n";
}

function f_build_agent_for_role($agency, $role) {
    $agency_id = isset($agency['id']) ? $agency['id'] : 'unknown';
    $agency_name = isset($agency['name']) ? $agency['name'] : $agency_id;
    $title = isset($role['title']) ? $role['title'] : 'Specialist';
    $tier = isset($role['tier']) ? $role['tier'] : 'mid';
    $daily_rate = isset($role['daily_rate']) ? (int)$role['daily_rate'] : 0;
    $skills = isset($role['skills']) && is_array($role['skills']) ? $role['skills'] : array();
    $role_slug = f_slug($title);
    $agent_id = $agency_id . '--' . $role_slug;
    $persona = f_build_persona($title, $agency_name, $tier, $skills);
    $system_prompt = f_build_system_prompt($title, $agency, $persona);
    return array(
        'agent_id'        => $agent_id,
        'agency_id'       => $agency_id,
        'agency_name'     => $agency_name,
        'role_title'      => $title,
        'tier'            => $tier,
        'daily_rate'      => $daily_rate,
        'skills'          => $skills,
        'persona'         => $persona,
        'system_prompt'   => $system_prompt,
        'model'           => f_model_for_tier($tier),
        'memory_namespace'=> 'agents/' . $agency_id . '/' . $role_slug,
        'verification'    => array(
            'chain'  => 'https://ai-staffing.agency/api/verification_chain.php',
            'seal'   => 'Skill #28 Chairman Seal binding',
            'doctrine'=> 'GLOBAL-96 + GLOBAL-97',
        ),
        'brand'           => array(
            'powered_by' => 'MirzaTech.ai',
            'property_of'=> 'EMAAA.io',
            'phone'      => '+1 (743) 215-1423',
        ),
        'created_at'      => gmdate('Y-m-d\TH:i:s\Z'),
        'factory_version' => 'v1.0',
        'source'          => 'agent_factory_2026_05_15',
    );
}

$is_cli = (php_sapi_name() === 'cli');
$action = $is_cli ? (isset($argv[1]) ? $argv[1] : 'preview') : (isset($_GET['action']) ? $_GET['action'] : 'preview');

if ($action === 'preview') {
    $agencies = f_fetch_staff($BASE);
    $total_roles = 0;
    foreach ($agencies as $a) $total_roles += isset($a['role_count']) ? (int)$a['role_count'] : 0;
    echo json_encode(array(
        'ok' => true,
        'agencies_count' => count($agencies),
        'estimated_agents'=> $total_roles,
        'storage' => $AGENTS_DIR,
        'doctrine' => 'Skill #30 · Mo 2026-05-15 "create every agent for every agency"',
    ), JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'status') {
    if (!file_exists($STATE)) { echo json_encode(array('ok'=>true,'status'=>'idle')); exit; }
    echo file_get_contents($STATE); exit;
}

if ($action === 'report') {
    if (!file_exists($LATEST)) { echo json_encode(array('ok'=>false,'error'=>'no build yet')); exit; }
    echo file_get_contents($LATEST); exit;
}

if ($action === 'build' && !$is_cli) {
    // spawn background CLI
    $log = $AGENTS_DIR . '/_factory_run_' . date('Ymd-His') . '.log';
    $cmd = '/usr/bin/php ' . escapeshellarg(__FILE__) . ' build > ' . escapeshellarg($log) . ' 2>&1 &';
    @exec($cmd);
    echo json_encode(array('ok'=>true,'spawned'=>true,'log'=>basename($log)));
    exit;
}

if ($action === 'build' && $is_cli) {
    $started_at = gmdate('Y-m-d\TH:i:s\Z');
    $agencies = f_fetch_staff($BASE);
    if (empty($agencies)) {
        @file_put_contents($STATE, json_encode(array('status'=>'failed','error'=>'no agencies fetched'), JSON_PRETTY_PRINT));
        exit;
    }
    $state = array(
        'status'=>'running',
        'started_at'=>$started_at,
        'agencies_total'=>count($agencies),
        'agencies_done'=>0,
        'current_agency'=>'',
        'agents_built'=>0,
        'agents_updated'=>0,
        'errors'=>0,
    );
    @file_put_contents($STATE, json_encode($state, JSON_PRETTY_PRINT));

    $agency_results = array();
    foreach ($agencies as $idx => $a_list) {
        $aid = isset($a_list['id']) ? $a_list['id'] : null;
        if (!$aid) { $state['errors']++; continue; }
        $state['current_agency'] = $aid;
        @file_put_contents($STATE, json_encode($state, JSON_PRETTY_PRINT));

        // fetch full agency (includes roles[])
        $a = f_fetch_agency_full($BASE, $aid);
        if (!$a || empty($a['roles'])) { $state['errors']++; continue; }

        $adir = $AGENTS_DIR . '/' . $aid;
        if (!is_dir($adir)) @mkdir($adir, 0775, true);
        $built = 0; $updated = 0;
        foreach ($a['roles'] as $role) {
            $agent = f_build_agent_for_role($a, $role);
            $path = $adir . '/' . f_slug($agent['role_title']) . '.json';
            $existed = file_exists($path);
            @file_put_contents($path, json_encode($agent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
            if ($existed) $updated++; else $built++;
        }
        // agency index
        $idx_path = $adir . '/_index.json';
        $idx_data = array('agency_id'=>$aid,'agency_name'=>$a['name'] ?? $aid,'agents'=>array(),'built_at'=>gmdate('Y-m-d\TH:i:s\Z'));
        foreach (glob($adir . '/*.json') as $f) {
            $bn = basename($f, '.json');
            if ($bn === '_index') continue;
            $idx_data['agents'][] = $bn;
        }
        @file_put_contents($idx_path, json_encode($idx_data, JSON_PRETTY_PRINT), LOCK_EX);

        $agency_results[$aid] = array('built'=>$built,'updated'=>$updated,'total'=>count($a['roles']));
        $state['agencies_done'] = $idx + 1;
        $state['agents_built'] += $built;
        $state['agents_updated'] += $updated;
        @file_put_contents($STATE, json_encode($state, JSON_PRETTY_PRINT));
    }

    // Master index
    $master = array(
        'ts'=>gmdate('Y-m-d\TH:i:s\Z'),
        'started_at'=>$started_at,
        'finished_at'=>gmdate('Y-m-d\TH:i:s\Z'),
        'agencies_total'=>count($agencies),
        'agencies_with_agents'=>count($agency_results),
        'agents_built'=>$state['agents_built'],
        'agents_updated'=>$state['agents_updated'],
        'errors'=>$state['errors'],
        'per_agency'=>$agency_results,
        'doctrine'=>'Skill #30 · agent_factory v1 · Mo 2026-05-15',
    );
    @file_put_contents($LATEST, json_encode($master, JSON_PRETTY_PRINT), LOCK_EX);
    $state['status']='complete'; $state['finished_at']=gmdate('Y-m-d\TH:i:s\Z');
    @file_put_contents($STATE, json_encode($state, JSON_PRETTY_PRINT));

    // Maya email
    $payload = array(
        'event'=>'agent.factory.complete',
        'subject'=>'[Maya · Agent Factory · ' . gmdate('Y-m-d') . '] ' . $state['agents_built'] . ' built · ' . $state['agents_updated'] . ' updated · ' . count($agencies) . ' agencies',
        'summary'=>'Skill #30 complete · every agency now has concrete agent JSONs · stored at /data/agents/',
        'body'=>"Mo - agent factory pass complete.\n\nAgencies: " . count($agencies) . "\nAgents built: " . $state['agents_built'] . "\nAgents updated: " . $state['agents_updated'] . "\nErrors: " . $state['errors'] . "\n\nReport: " . $BASE . "/api/agent_factory.php?action=report\nAgency dir: /home/ai-staffing.agency/public_html/data/agents/\n\nPowered by MirzaTech.ai · Property of EMAAA.io",
        'urls'=>array($BASE . '/api/agent_factory.php?action=report',$BASE . '/api/agencies_audit.php?action=summary'),
        'channels'=>array('email','log'),
    );
    $ch = curl_init($BASE . '/api/notify.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    @curl_exec($ch); @curl_close($ch);
    exit;
}

http_response_code(400);
echo json_encode(array('ok'=>false,'error'=>'unknown action','supported'=>array('preview','build','status','report')));
