<?php
/**
 * MAYA GMAIL — /api/maya_gmail
 * OAuth2 flow for Gmail read + triage + draft. RULE 84 Phase 1.
 */
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

function mg_out($p, $code=200){ http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($p, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); exit; }

$ENV = '/home/iamsuperio.cloud/public_html/api/.maya_master_keys.env';
$TOKEN_DIR = '/home/iamsuperio.cloud/public_html/data/gmail/tokens';
@mkdir($TOKEN_DIR, 0755, true);

function mg_env(string $key): ?string {
    global $ENV;
    if (!file_exists($ENV)) return null;
    foreach (file($ENV, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (strpos($line, $key.'=') === 0) return trim(substr($line, strlen($key.'=')));
    }
    return null;
}
function mg_commander_ok(string $pin): bool { $c = mg_env('COMMANDER_PASSWORD'); return $c && hash_equals($c, $pin); }

function mg_oauth_config(): array {
    return [
        'client_id' => mg_env('YOUTUBE_OAUTH_CLIENT_ID') ?: mg_env('GMAIL_OAUTH_CLIENT_ID'),
        'client_secret' => mg_env('YOUTUBE_OAUTH_CLIENT_SECRET') ?: mg_env('GMAIL_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => 'https://iamsuperio.cloud/api/maya_gmail?action=callback',
    ];
}

function mg_curl(string $url, array $opts = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts + [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>60, CURLOPT_SSL_VERIFYPEER=>true]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['ok'=>$code>=200&&$code<300, 'status'=>$code, 'body'=>$body, 'err'=>$err];
}

function mg_email_slug(string $email): string { return preg_replace('/[^a-z0-9._-]/', '_', strtolower(trim($email))); }
function mg_token_path(string $email): string { global $TOKEN_DIR; return $TOKEN_DIR . '/' . mg_email_slug($email) . '.json'; }
function mg_load_token(string $email): ?array { $p = mg_token_path($email); return file_exists($p) ? json_decode(file_get_contents($p), true) : null; }
function mg_save_token(string $email, array $data): void { $p = mg_token_path($email); @file_put_contents($p, json_encode($data, JSON_PRETTY_PRINT)); @chmod($p, 0600); }

function mg_access_token(string $email): ?string {
    $tok = mg_load_token($email);
    if (!$tok || empty($tok['refresh_token'])) return null;
    if (!empty($tok['access_token']) && (int)($tok['expires_at'] ?? 0) > time() + 60) return $tok['access_token'];
    $cfg = mg_oauth_config();
    if (!$cfg['client_id'] || !$cfg['client_secret']) return null;
    $r = mg_curl('https://oauth2.googleapis.com/token', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['client_id'=>$cfg['client_id'],'client_secret'=>$cfg['client_secret'],'refresh_token'=>$tok['refresh_token'],'grant_type'=>'refresh_token']),
    ]);
    if (!$r['ok']) return null;
    $j = json_decode($r['body'], true);
    if (empty($j['access_token'])) return null;
    $tok['access_token'] = $j['access_token'];
    $tok['expires_at'] = time() + (int)($j['expires_in'] ?? 3600);
    mg_save_token($email, $tok);
    return $tok['access_token'];
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?: $_POST;
$action = $_GET['action'] ?? $input['action'] ?? 'status';

if ($action === 'status') {
    $count = count(glob($TOKEN_DIR . '/*.json'));
    $cfg = mg_oauth_config();
    mg_out(['ok'=>true, 'service'=>'maya_gmail', 'rule'=>'RULE 84 Phase 1', 'oauth_configured'=>!empty($cfg['client_id']), 'accounts_connected'=>$count]);
}

if ($action === 'connect') {
    $cfg = mg_oauth_config();
    if (!$cfg['client_id']) mg_out(['ok'=>false, 'error'=>'OAuth client not configured'], 500);
    $scopes = 'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.compose https://www.googleapis.com/auth/gmail.send';
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'=>$cfg['client_id'], 'redirect_uri'=>$cfg['redirect_uri'], 'response_type'=>'code',
        'scope'=>$scopes, 'access_type'=>'offline', 'prompt'=>'consent select_account', 'state'=>bin2hex(random_bytes(8)),
    ]);
    header('Location: ' . $url, true, 302); exit;
}

if ($action === 'callback') {
    $code = $_GET['code'] ?? '';
    if (!$code) mg_out(['ok'=>false, 'error'=>'no code'], 400);
    $cfg = mg_oauth_config();
    $r = mg_curl('https://oauth2.googleapis.com/token', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['code'=>$code,'client_id'=>$cfg['client_id'],'client_secret'=>$cfg['client_secret'],'redirect_uri'=>$cfg['redirect_uri'],'grant_type'=>'authorization_code']),
    ]);
    if (!$r['ok']) mg_out(['ok'=>false,'error'=>'token exchange failed','body'=>$r['body']], 500);
    $j = json_decode($r['body'], true);
    if (empty($j['refresh_token'])) mg_out(['ok'=>false,'error'=>'no refresh_token','body'=>$j], 500);
    $u = mg_curl('https://www.googleapis.com/oauth2/v2/userinfo', [CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$j['access_token']]]);
    $email = 'unknown';
    if ($u['ok']) { $ui = json_decode($u['body'], true); $email = $ui['email'] ?? 'unknown'; }
    mg_save_token($email, [
        'email'=>$email, 'refresh_token'=>$j['refresh_token'], 'access_token'=>$j['access_token'],
        'expires_at'=>time() + (int)($j['expires_in'] ?? 3600), 'connected_at'=>date('c'),
    ]);
    mg_out(['ok'=>true, 'message'=>'Gmail connected for '.$email, 'email'=>$email]);
}

if ($action === 'accounts') {
    global $TOKEN_DIR;
    $out = [];
    foreach (glob($TOKEN_DIR . '/*.json') as $f) {
        $t = json_decode(file_get_contents($f), true);
        $out[] = ['email'=>$t['email'] ?? basename($f,'.json'), 'connected_at'=>$t['connected_at'] ?? null];
    }
    mg_out(['ok'=>true, 'count'=>count($out), 'accounts'=>$out]);
}

if ($action === 'list') {
    if (!mg_commander_ok($input['pin'] ?? '')) mg_out(['ok'=>false,'error'=>'commander pin required'], 401);
    $email = $input['email'] ?? '';
    if (!$email) mg_out(['ok'=>false,'error'=>'email required'], 400);
    $tok = mg_access_token($email);
    if (!$tok) mg_out(['ok'=>false,'error'=>'account not authorized'], 401);
    $q = $input['q'] ?? '';
    $max = (int)($input['max'] ?? 15);
    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages?maxResults=' . $max;
    if ($q) $url .= '&q=' . urlencode($q);
    $r = mg_curl($url, [CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$tok]]);
    if (!$r['ok']) mg_out(['ok'=>false,'status'=>$r['status']], 500);
    $j = json_decode($r['body'], true);
    $msgs = [];
    foreach (array_slice($j['messages'] ?? [], 0, $max) as $m) {
        $mr = mg_curl('https://gmail.googleapis.com/gmail/v1/users/me/messages/'.$m['id'].'?format=metadata&metadataHeaders=Subject&metadataHeaders=From&metadataHeaders=Date', [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$tok],
        ]);
        if (!$mr['ok']) continue;
        $md = json_decode($mr['body'], true);
        $h = [];
        foreach (($md['payload']['headers'] ?? []) as $hh) $h[strtolower($hh['name'])] = $hh['value'];
        $msgs[] = ['id'=>$m['id'], 'thread_id'=>$md['threadId'], 'snippet'=>$md['snippet'] ?? '', 'subject'=>$h['subject'] ?? '', 'from'=>$h['from'] ?? '', 'date'=>$h['date'] ?? ''];
    }
    mg_out(['ok'=>true, 'email'=>$email, 'count'=>count($msgs), 'messages'=>$msgs]);
}

if ($action === 'read') {
    if (!mg_commander_ok($input['pin'] ?? '')) mg_out(['ok'=>false,'error'=>'commander pin required'], 401);
    $email = $input['email'] ?? '';
    $msg_id = $input['msg_id'] ?? '';
    if (!$email || !$msg_id) mg_out(['ok'=>false,'error'=>'email + msg_id required'], 400);
    $tok = mg_access_token($email);
    if (!$tok) mg_out(['ok'=>false,'error'=>'account not authorized'], 401);
    $r = mg_curl('https://gmail.googleapis.com/gmail/v1/users/me/messages/'.$msg_id.'?format=full', [CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$tok]]);
    if (!$r['ok']) mg_out(['ok'=>false,'status'=>$r['status']], 500);
    $j = json_decode($r['body'], true);
    $body_text = '';
    $walk = function($part) use (&$walk, &$body_text) {
        if (($part['mimeType'] ?? '') === 'text/plain' && !empty($part['body']['data'])) $body_text .= base64_decode(strtr($part['body']['data'], '-_', '+/'));
        foreach (($part['parts'] ?? []) as $p) $walk($p);
    };
    if (isset($j['payload'])) $walk($j['payload']);
    $headers = [];
    foreach (($j['payload']['headers'] ?? []) as $hh) $headers[strtolower($hh['name'])] = $hh['value'];
    mg_out(['ok'=>true, 'id'=>$msg_id, 'thread_id'=>$j['threadId'], 'headers'=>$headers, 'snippet'=>$j['snippet'] ?? '', 'body'=>$body_text]);
}

if ($action === 'draft') {
    if (!mg_commander_ok($input['pin'] ?? '')) mg_out(['ok'=>false,'error'=>'commander pin required'], 401);
    $email = $input['email'] ?? '';
    $to = $input['to'] ?? '';
    $subject = $input['subject'] ?? '';
    $body = $input['body'] ?? '';
    $thread_id = $input['thread_id'] ?? null;
    if (!$email || !$to || !$body) mg_out(['ok'=>false,'error'=>'email + to + body required'], 400);
    $tok = mg_access_token($email);
    if (!$tok) mg_out(['ok'=>false,'error'=>'account not authorized'], 401);
    $raw_msg = "To: $to\r\nSubject: $subject\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n$body";
    $raw_b64 = rtrim(strtr(base64_encode($raw_msg), '+/', '-_'), '=');
    $payload = ['message' => ['raw' => $raw_b64]];
    if ($thread_id) $payload['message']['threadId'] = $thread_id;
    $r = mg_curl('https://gmail.googleapis.com/gmail/v1/users/me/drafts', [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$tok, 'Content-Type: application/json'],
    ]);
    if (!$r['ok']) mg_out(['ok'=>false,'status'=>$r['status'],'body'=>substr($r['body'],0,500)], 500);
    $j = json_decode($r['body'], true);
    mg_out(['ok'=>true, 'draft_id'=>$j['id'] ?? null, 'message_id'=>$j['message']['id'] ?? null, 'note'=>'Draft saved. Review in Gmail Drafts. Mo sends manually.']);
}

if ($action === 'digest') {
    if (!mg_commander_ok($input['pin'] ?? '')) mg_out(['ok'=>false,'error'=>'commander pin required'], 401);
    $email = $input['email'] ?? '';
    if (!$email) mg_out(['ok'=>false,'error'=>'email required'], 400);
    $tok = mg_access_token($email);
    if (!$tok) mg_out(['ok'=>false,'error'=>'account not authorized'], 401);
    $r = mg_curl('https://gmail.googleapis.com/gmail/v1/users/me/messages?q=' . urlencode('is:unread newer_than:1d') . '&maxResults=20', [CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$tok]]);
    if (!$r['ok']) mg_out(['ok'=>false,'status'=>$r['status']], 500);
    $j = json_decode($r['body'], true);
    $summary = [];
    foreach (array_slice($j['messages'] ?? [], 0, 20) as $m) {
        $mr = mg_curl('https://gmail.googleapis.com/gmail/v1/users/me/messages/'.$m['id'].'?format=metadata&metadataHeaders=Subject&metadataHeaders=From', [CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$tok]]);
        if (!$mr['ok']) continue;
        $md = json_decode($mr['body'], true);
        $h = [];
        foreach (($md['payload']['headers'] ?? []) as $hh) $h[strtolower($hh['name'])] = $hh['value'];
        $summary[] = ['from'=>$h['from'] ?? '', 'subject'=>$h['subject'] ?? '', 'snippet'=>$md['snippet'] ?? ''];
    }
    mg_out(['ok'=>true, 'email'=>$email, 'unread_24h'=>count($summary), 'messages'=>$summary]);
}

if ($action === 'unread_count') {
    // Lightweight bell-badge poll. No PIN required (just a count, no content leak).
    // Sums unread newer-than-1d across all connected accounts.
    global $TOKEN_DIR;
    $total = 0; $by_account = [];
    foreach (glob($TOKEN_DIR . '/*.json') as $f) {
        $tdat = json_decode(file_get_contents($f), true);
        $email_addr = $tdat['email'] ?? basename($f, '.json');
        $tok = mg_access_token($email_addr);
        if (!$tok) { $by_account[$email_addr] = ['ok'=>false, 'count'=>0]; continue; }
        $r = mg_curl('https://gmail.googleapis.com/gmail/v1/users/me/messages?q=' . urlencode('is:unread newer_than:1d') . '&maxResults=50', [CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$tok]]);
        if (!$r['ok']) { $by_account[$email_addr] = ['ok'=>false, 'count'=>0]; continue; }
        $j = json_decode($r['body'], true);
        $c = isset($j['resultSizeEstimate']) ? (int)$j['resultSizeEstimate'] : count($j['messages'] ?? []);
        $by_account[$email_addr] = ['ok'=>true, 'count'=>$c];
        $total += $c;
    }
    mg_out(['ok'=>true, 'unread_24h'=>$total, 'accounts'=>$by_account, 'ts'=>date('c')]);
}

if ($action === 'brief') {
    // Take selected msg_ids (or fall back to digest) → pull bodies → Maya brain → 3-bullet brief.
    if (!mg_commander_ok($input['pin'] ?? '')) mg_out(['ok'=>false,'error'=>'commander pin required'], 401);
    $email = $input['email'] ?? '';
    if (!$email) mg_out(['ok'=>false,'error'=>'email required'], 400);
    $tok = mg_access_token($email);
    if (!$tok) mg_out(['ok'=>false,'error'=>'account not authorized'], 401);
    // Phase B · emit "start" event so campus shows Maya thinking
    $brief_event_id = '';
    $ev_curl = curl_init('http://127.0.0.1/api/maya_event');
    curl_setopt_array($ev_curl, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Host: iamsuperio.cloud'],
        CURLOPT_POSTFIELDS => json_encode(['actor'=>'maya','action'=>'brief','target'=>$email,'status'=>'start','room'=>'maya_brain']),
    ]);
    $ev_resp = curl_exec($ev_curl);
    curl_close($ev_curl);
    $ej = json_decode((string)$ev_resp, true);
    if (is_array($ej) && !empty($ej['event']['id'])) $brief_event_id = $ej['event']['id'];

    $msg_ids = is_array($input['msg_ids'] ?? null) ? $input['msg_ids'] : [];
    // Mo 2026-05-15: brief default = gemini (richer summary · 1M context)
    // Mo can override per-call via input.engine
    $engine  = $input['engine'] ?? 'gemini';

    // If no IDs supplied, fall back to "unread newer than 1d" - Maya picks for Mo
    if (empty($msg_ids)) {
        $r = mg_curl('https://gmail.googleapis.com/gmail/v1/users/me/messages?q=' . urlencode('is:unread newer_than:1d') . '&maxResults=15', [CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$tok]]);
        if (!$r['ok']) mg_out(['ok'=>false,'error'=>'inbox query failed','status'=>$r['status']], 500);
        $j = json_decode($r['body'], true);
        foreach (($j['messages'] ?? []) as $m) $msg_ids[] = $m['id'];
    }
    if (empty($msg_ids)) mg_out(['ok'=>true, 'brief'=>'No unread emails in the last 24 hours, brat moj. Inbox is clean.', 'count'=>0]);

    // Pull each message body (full format · text/plain only, snippet fallback)
    $bundle = []; $titles = [];
    foreach (array_slice($msg_ids, 0, 15) as $mid) {
        $mr = mg_curl('https://gmail.googleapis.com/gmail/v1/users/me/messages/'.$mid.'?format=full', [CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$tok]]);
        if (!$mr['ok']) continue;
        $md = json_decode($mr['body'], true);
        $h = [];
        foreach (($md['payload']['headers'] ?? []) as $hh) $h[strtolower($hh['name'])] = $hh['value'];
        $body_text = '';
        $walk = function($part) use (&$walk, &$body_text) {
            if (($part['mimeType'] ?? '') === 'text/plain' && !empty($part['body']['data'])) $body_text .= base64_decode(strtr($part['body']['data'], '-_', '+/'));
            foreach (($part['parts'] ?? []) as $p) $walk($p);
        };
        if (isset($md['payload'])) $walk($md['payload']);
        if (!$body_text) $body_text = $md['snippet'] ?? '';
        // Truncate per email so total prompt stays sane
        $body_text = substr(trim($body_text), 0, 1500);
        $titles[] = ($h['from'] ?? '?') . ' · ' . ($h['subject'] ?? '(no subject)');
        $bundle[] = sprintf("FROM: %s\nSUBJECT: %s\nDATE: %s\nBODY:\n%s\n---", $h['from'] ?? '?', $h['subject'] ?? '?', $h['date'] ?? '?', $body_text);
    }

    if (empty($bundle)) mg_out(['ok'=>false, 'error'=>'could not read any of the requested messages'], 500);

    $prompt = "You are Maya, Mo's executive briefing assistant. Below are " . count($bundle) . " emails Mo wants briefed.\n\n"
        . "Output a tight 3-section brief in plain spoken English (NO markdown, NO bullet symbols — just clear sentences):\n"
        . "1. WHAT MATTERS · the 1-2 emails that need Mo's attention today, with sender, subject, and the ONE action expected from Mo.\n"
        . "2. INFO ONLY · the rest, summarized in one breath each (sender + subject + 5-word gist).\n"
        . "3. SUGGESTED REPLY · for the most-urgent email only, draft a 2-sentence reply Mo can send as-is.\n\n"
        . "Speak the way you would to a brother. No 'Dear Sir.' No bullet emoji. Mo is going to listen to this with his ears — make it sound natural read aloud.\n\n"
        . "EMAILS:\n\n" . implode("\n", $bundle);

    // Call Maya brain via local loopback (no SSL, lower latency)
    $brain_resp = mg_curl('http://127.0.0.1/api/index', [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Host: iamsuperio.cloud'],
        CURLOPT_POSTFIELDS => json_encode(['action'=>'ask', 'question'=>$prompt, 'engine'=>$engine]),
        CURLOPT_TIMEOUT => 90,
    ]);
    if (!$brain_resp['ok']) mg_out(['ok'=>false, 'error'=>'maya brain call failed', 'status'=>$brain_resp['status'], 'body'=>substr($brain_resp['body'] ?? '', 0, 400)], 502);
    $bj = json_decode($brain_resp['body'], true);
    $brief_text = $bj['answer'] ?? $bj['response'] ?? $bj['text'] ?? $bj['content'] ?? '';
    if (!$brief_text) mg_out(['ok'=>false, 'error'=>'brain returned no answer field', 'sample'=>substr($brain_resp['body'], 0, 400)], 502);

    // Phase B · emit "done" event so the campus packet completes
    $ev_curl2 = curl_init('http://127.0.0.1/api/maya_event');
    curl_setopt_array($ev_curl2, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Host: iamsuperio.cloud'],
        CURLOPT_POSTFIELDS => json_encode(['actor'=>'maya','action'=>'brief','target'=>$email,'status'=>'done',
            'room'=>'maya_brain','parent_event_id'=>$brief_event_id,
            'result'=>'briefed ' . count($bundle) . ' email · ' . ($bj['engine'] ?? $engine)]),
    ]);
    curl_exec($ev_curl2);
    curl_close($ev_curl2);
    mg_out([
        'ok'        => true,
        'count'     => count($bundle),
        'titles'    => $titles,
        'brief'     => $brief_text,
        'engine'    => $bj['engine'] ?? $engine,
        'voice_url' => null,  // Client calls /api/maya_voice separately if Mo wants it spoken
        'event_id'  => $brief_event_id,
        'ts'        => date('c'),
    ]);
}

if ($action === 'logout') {
    if (!mg_commander_ok($input['pin'] ?? '')) mg_out(['ok'=>false,'error'=>'commander pin required'], 401);
    $email = $input['email'] ?? '';
    if (!$email) mg_out(['ok'=>false,'error'=>'email required'], 400);
    $p = mg_token_path($email);
    if (file_exists($p)) @unlink($p);
    mg_out(['ok'=>true, 'revoked'=>$email]);
}

mg_out(['ok'=>false, 'error'=>'unknown action', 'supported'=>['status','connect','callback','accounts','list','read','draft','digest','unread_count','brief','logout']], 400);
