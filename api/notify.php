<?php
/**
 * MAYA · NOTIFICATION BUS (Skill #24 · GLOBAL-94)
 * -----------------------------------------------
 * Endpoint:  /api/notify.php
 * Purpose:   Maya broadcasts empire events to Mo (email/SMS/log).
 *
 *   POST body:
 *     event:    chain.approved | agent.built | jail.incarcerated | sovereign_override | session.summary | vendor.seat.claimed | custom
 *     summary:  one-line human readable
 *     body:     optional longer body (for email)
 *     urls:     optional array of links
 *     channels: optional array · default ['log','email']
 *     subject:  optional override
 *
 * PHP 7.4 only · brand: Powered by MirzaTech.ai · Property of EMAAA.io
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Mo-PIN');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$BUS_DIR = __DIR__ . '/../data/notification_bus';
if (!is_dir($BUS_DIR)) { @mkdir($BUS_DIR, 0775, true); }
$LOG_FILE = $BUS_DIR . '/notifications.jsonl';

$MO_EMAIL = 'mirzaadin@gmail.com';   // GLOBAL-52 primary inbox
$MO_PHONE = '+14047849898';          // sparingly · only critical · per memory
$FROM_EMAIL = 'maya@emaaa.io';
$FROM_NAME  = 'Maya · MirzaTech.ai';

function now_iso() { return gmdate('Y-m-d\TH:i:s\Z'); }
function append_log($p, $row) { @file_put_contents($p, json_encode($row) . "\n", FILE_APPEND | LOCK_EX); }

function send_email($to, $from_email, $from_name, $subject, $body_html, $body_text) {
    // Use Resend if RESEND_KEY available · fallback to mail() · always log
    $resend_key = getenv('RESEND_KEY');
    if (!$resend_key) {
        // try .env files
        foreach (array(__DIR__ . '/.maya_email.env', '/home/iamsuperio.cloud/public_html/api/.maya_master_keys.env', '/home/mirzatech.ai/public_html/.env') as $envf) {
            if (file_exists($envf)) {
                $env = @file_get_contents($envf);
                if (preg_match('/^RESEND[_A-Z]*\s*=\s*["\']?(re_[A-Za-z0-9_]+)/m', $env, $m)) {
                    $resend_key = $m[1]; break;
                }
            }
        }
    }
    if ($resend_key) {
        $payload = array(
            'from'     => $from_name . ' <' . $from_email . '>',
            'to'       => array($to),
            'subject'  => $subject,
            'html'     => $body_html,
            'text'     => $body_text,
            'reply_to' => 'mo@emaaa.io',
        );
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $resend_key,
            'Content-Type: application/json',
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array('ok' => $code >= 200 && $code < 300, 'provider' => 'resend', 'code' => $code, 'body' => substr((string)$body, 0, 240));
    }
    // PHP mail() fallback
    $headers = "From: {$from_name} <{$from_email}>\r\n"
             . "Reply-To: mo@emaaa.io\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n";
    $ok = @mail($to, $subject, $body_html, $headers);
    return array('ok' => (bool)$ok, 'provider' => 'mail()', 'code' => $ok ? 200 : 500);
}

function send_telnyx_sms($to, $msg) {
    $env_paths = array('/home/iamsuperio.cloud/public_html/api/.maya_master_keys.env', '/home/mirzatech.ai/public_html/.env');
    $api_key = null; $from_number = '+17432151423';
    foreach ($env_paths as $p) {
        if (!file_exists($p)) continue;
        $env = @file_get_contents($p);
        if (preg_match('/TELNYX[_A-Z]*\s*=\s*["\']?(KEY[A-Za-z0-9_\-]+)/m', $env, $m)) { $api_key = $m[1]; break; }
    }
    if (!$api_key) return array('ok' => false, 'error' => 'no telnyx key in env');
    $payload = array('from' => $from_number, 'to' => $to, 'text' => substr($msg, 0, 160));
    $ch = curl_init('https://api.telnyx.com/v2/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $api_key, 'Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => substr((string)$body, 0, 240));
}

$action = isset($_GET['action']) ? $_GET['action'] : 'notify';

if ($action === 'recent') {
    $n = isset($_GET['n']) ? max(1, min(100, (int)$_GET['n'])) : 20;
    $rows = array();
    if (file_exists($LOG_FILE)) {
        $lines = @file($LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach (array_reverse(array_slice($lines, -$n)) as $line) {
                $r = json_decode($line, true);
                if (is_array($r)) $rows[] = $r;
            }
        }
    }
    echo json_encode(array('ok'=>true,'recent'=>$rows));
    exit;
}

// ===== default action: notify =====
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;
$event    = isset($in['event'])    ? trim($in['event'])    : 'custom';
$summary  = isset($in['summary'])  ? trim($in['summary'])  : '';
$body     = isset($in['body'])     ? trim($in['body'])     : $summary;
$urls     = isset($in['urls']) && is_array($in['urls']) ? $in['urls'] : array();
$channels = isset($in['channels']) && is_array($in['channels']) ? $in['channels'] : array('log','email');
$subject  = isset($in['subject'])  ? trim($in['subject'])  : ('[Maya · ' . $event . '] ' . substr($summary, 0, 80));

if ($summary === '') { http_response_code(400); echo json_encode(array('ok'=>false,'error'=>'missing summary')); exit; }

$results = array();
$ts = now_iso();

// Always log
$row = array('ts'=>$ts,'event'=>$event,'summary'=>$summary,'body'=>$body,'urls'=>$urls,'channels'=>$channels);
append_log($LOG_FILE, $row);
$results['log'] = array('ok' => true);

if (in_array('email', $channels)) {
    $url_block_html = '';
    $url_block_text = '';
    foreach ($urls as $u) {
        $url_block_html .= '<li><a href="' . htmlspecialchars($u) . '">' . htmlspecialchars($u) . '</a></li>';
        $url_block_text .= "  · " . $u . "\n";
    }
    $body_html = '<div style="font-family:Inter,system-ui,sans-serif;color:#1a1a1a;max-width:640px;margin:0 auto;padding:24px">'
               . '<div style="background:#03060c;color:#dbe6ff;padding:18px 22px;border-radius:14px 14px 0 0;border:1px solid #f5c542">'
               . '<div style="color:#f5c542;letter-spacing:.22em;text-transform:uppercase;font-size:11px;margin-bottom:6px">Maya · MirzaTech.ai · ' . htmlspecialchars($event) . '</div>'
               . '<div style="color:#fff;font-size:18px;font-family:\'Space Grotesk\',sans-serif">' . htmlspecialchars($summary) . '</div>'
               . '</div>'
               . '<div style="background:#fff;padding:22px;border:1px solid #e0e0e0;border-top:0;border-radius:0 0 14px 14px">'
               . '<p style="margin:0 0 14px;line-height:1.6">' . nl2br(htmlspecialchars($body)) . '</p>'
               . ($url_block_html ? '<p style="margin:14px 0 6px;font-weight:600">Links:</p><ul>' . $url_block_html . '</ul>' : '')
               . '<hr style="border:0;border-top:1px solid #e0e0e0;margin:24px 0">'
               . '<div style="font-size:11px;color:#888;letter-spacing:.12em;text-transform:uppercase">Powered by MirzaTech.ai · Property of EMAAA.io · +1 (743) 215-1423</div>'
               . '</div></div>';
    $body_text = "Maya · " . $event . "\n\n" . $summary . "\n\n" . $body . "\n\n" . ($url_block_text ? "Links:\n" . $url_block_text . "\n" : '') . "\nPowered by MirzaTech.ai · Property of EMAAA.io";
    $results['email'] = send_email($MO_EMAIL, $FROM_EMAIL, $FROM_NAME, $subject, $body_html, $body_text);
}

if (in_array('sms', $channels)) {
    $msg = '[Maya] ' . substr($summary, 0, 140);
    $results['sms'] = send_telnyx_sms($MO_PHONE, $msg);
}

echo json_encode(array('ok'=>true,'ts'=>$ts,'event'=>$event,'channels_attempted'=>$channels,'results'=>$results));
