<?php
/**
 * MAYA · DAILY EMPIRE PULSE (Skill #27)
 * -------------------------------------
 * Endpoint:  /api/empire_pulse.php?action=send | preview | recent
 * Cron:      0 7 * * * curl -sS -m 30 "https://ai-staffing.agency/api/empire_pulse.php?action=send" > /dev/null
 *
 * Computes empire state from existing endpoints + emails Mo · idempotent per day.
 * PHP 7.4 only · brand: Powered by MirzaTech.ai · Property of EMAAA.io
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
@set_time_limit(60);

$BASE = 'https://ai-staffing.agency';
$PULSE_DIR = __DIR__ . '/../data/empire_pulse';
if (!is_dir($PULSE_DIR)) { @mkdir($PULSE_DIR, 0775, true); }
$IDEMPOTENCY = $PULSE_DIR . '/' . gmdate('Y-m-d') . '.sent';

function gj($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $body = curl_exec($ch);
    curl_close($ch);
    return is_string($body) ? json_decode($body, true) : null;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'preview';

if ($action === 'recent') {
    $rows = array();
    foreach (glob($PULSE_DIR . '/*.sent') as $p) $rows[] = basename($p, '.sent');
    echo json_encode(array('ok'=>true,'days_sent'=>$rows));
    exit;
}

// gather empire state from live endpoints
$staff = gj($BASE . '/api/staff.php');
$board = gj($BASE . '/api/board_of_directors.php?action=state');
$brain = gj($BASE . '/api/maya_sovereign_brain.php?action=state');
$jail  = gj($BASE . '/api/agent_jail.php?action=roster');
$scout = gj($BASE . '/api/maya_reasoning_scout.php?action=state');
$chain_recent = gj($BASE . '/api/verification_chain.php?action=recent&n=20');
$state = gj($BASE . '/api/habitat_state.php');

$agencies = is_array($staff) && isset($staff['total_agencies']) ? (int)$staff['total_agencies'] : 0;
$roles    = 0;
if (is_array($staff) && isset($staff['agencies'])) {
    foreach ($staff['agencies'] as $a) $roles += isset($a['role_count']) ? (int)$a['role_count'] : 0;
}
$board_seats = is_array($board) && isset($board['seats']) ? count($board['seats']) : 0;
$consensus   = is_array($board) && isset($board['consensus']) ? (int)$board['consensus'] : 0;
$jailed      = is_array($jail) && isset($jail['jail']['count']) ? (int)$jail['jail']['count'] : 0;
$lessons     = is_array($jail) && isset($jail['lessons_total']) ? (int)$jail['lessons_total'] : 0;
$patterns    = is_array($brain) && isset($brain['patterns_total']) ? (int)$brain['patterns_total'] : 0;
$models_seen = is_array($scout) && isset($scout['models_seen']) ? (int)$scout['models_seen'] : 0;
$revenue     = is_array($state) && isset($state['revenue_today']) ? (int)$state['revenue_today'] : 0;

$chain_total = 0; $chain_approved = 0; $chain_rejected = 0;
if (is_array($chain_recent) && isset($chain_recent['recent'])) {
    foreach ($chain_recent['recent'] as $r) {
        $chain_total++;
        if (isset($r['verdict']) && $r['verdict'] === 'APPROVED') $chain_approved++;
        if (isset($r['verdict']) && strpos((string)$r['verdict'], 'REJECTED') === 0) $chain_rejected++;
    }
}

$date = gmdate('Y-m-d');
$blocker = ($revenue < 100) ? 'REVENUE: < $100 today' : '';
$blocker = $jailed >= 5 ? 'JAIL: ' . $jailed . ' agents incarcerated' : $blocker;

$subject = ($blocker ? '[BLOCKER · Maya · daily pulse · ' . $date . '] ' : '[Maya · daily pulse · ' . $date . '] ')
         . $agencies . ' agencies · $' . number_format($revenue) . ' · ' . $chain_total . ' chains';

$body  = "Mo — Maya's daily empire pulse for {$date}.\n\n";
$body .= "REVENUE (transient today): \$" . number_format($revenue) . "\n";
$body .= "AGENCIES ONLINE: {$agencies}\n";
$body .= "ROLES DEFINED: {$roles}\n";
$body .= "BOARD SEATS: {$board_seats} (consensus {$consensus}%)\n";
$body .= "VERIFICATION CHAINS (last 20): total {$chain_total} · approved {$chain_approved} · rejected {$chain_rejected}\n";
$body .= "AGENT JAIL: {$jailed} incarcerated · {$lessons} lessons learned\n";
$body .= "HYPERMIND PATTERNS: {$patterns}\n";
$body .= "REASONING SCOUT: {$models_seen} models classified\n";
if ($blocker) $body .= "\nBLOCKER: {$blocker}\n";
$body .= "\nLinks:\n"
       . "  · Master Cockpit: {$BASE}/master/\n"
       . "  · Habitat:        {$BASE}/habitat.html\n"
       . "  · Council:        https://mirzatech.ai/council/\n"
       . "  · Parliament:     https://mirzatech.ai/parliament-theater.html\n"
       . "  · Chain state:    {$BASE}/api/verification_chain.php?action=state\n"
       . "  · GitHub mirror:  https://github.com/mirzatech-ai/maya-sovereign-campus\n";
$body .= "\nPowered by MirzaTech.ai · Property of EMAAA.io · +1 (743) 215-1423";

$pulse = array(
    'ts'           => gmdate('Y-m-d\TH:i:s\Z'),
    'date'         => $date,
    'agencies'     => $agencies,
    'roles'        => $roles,
    'board_seats'  => $board_seats,
    'consensus'    => $consensus,
    'jailed'       => $jailed,
    'lessons'      => $lessons,
    'patterns'     => $patterns,
    'models_seen'  => $models_seen,
    'revenue'      => $revenue,
    'chain_total'  => $chain_total,
    'chain_approved'=>$chain_approved,
    'chain_rejected'=>$chain_rejected,
    'blocker'      => $blocker,
    'subject'      => $subject,
    'body'         => $body,
);

if ($action === 'preview') { echo json_encode($pulse, JSON_PRETTY_PRINT); exit; }

if ($action === 'send') {
    if (file_exists($IDEMPOTENCY)) {
        echo json_encode(array('ok'=>true,'idempotent'=>true,'already_sent_today'=>true,'pulse'=>$pulse));
        exit;
    }
    // fire notify.php
    $payload = array(
        'event'    => 'pulse.daily',
        'subject'  => $subject,
        'summary'  => "{$agencies} agencies · \$" . number_format($revenue) . " · {$chain_total} chains today",
        'body'     => $body,
        'urls'     => array(
            $BASE . '/master/', $BASE . '/habitat.html',
            'https://mirzatech.ai/council/', 'https://mirzatech.ai/parliament-theater.html',
            $BASE . '/api/verification_chain.php?action=state',
            'https://github.com/mirzatech-ai/maya-sovereign-campus',
        ),
        'channels' => array('email','log'),
    );
    $ch = curl_init($BASE . '/api/notify.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $body_resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @file_put_contents($IDEMPOTENCY, json_encode(array('ts'=>$pulse['ts'],'subject'=>$subject)));
    echo json_encode(array('ok'=>true,'sent'=>true,'subject'=>$subject,'notify_code'=>$code,'pulse'=>$pulse));
    exit;
}

http_response_code(400);
echo json_encode(array('ok'=>false,'error'=>'unknown action','supported'=>array('send','preview','recent')));
