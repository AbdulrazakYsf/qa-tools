<?php
/**
 * Tool Studio v2 (Single File)
 * - Safe allowlisted proxy for jarir.com pages (no CORS)
 * - API proxy to capture request/response and replay later
 * - Recorder injection for HTML pages: click/navigate + fetch->proxy
 *
 * IMPORTANT:
 * This is an internal QA tool. Do NOT expose publicly.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

// --- Optional: hook into your existing auth layer if present ---
if (file_exists(__DIR__ . '/auth_session.php')) {
    require_once __DIR__ . '/auth_session.php';
    if (function_exists('require_login')) {
        require_login();
    }
}

// ---------- Security: allowlist + SSRF protection ----------
const ALLOW_HOST_SUFFIXES = ['jarir.com']; // allows jarir.com and *.jarir.com
const MAX_BODY_BYTES = 2_000_000;          // 2MB capture limit (safety)
const CONNECT_TIMEOUT = 15;
const TOTAL_TIMEOUT = 45;

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function is_private_ip(string $ip): bool {
    // IPv4 private ranges
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($ip);
        $ranges = [
            ['0.0.0.0',   '0.255.255.255'],
            ['10.0.0.0',  '10.255.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0','169.254.255.255'],
            ['172.16.0.0','172.31.255.255'],
            ['192.168.0.0','192.168.255.255'],
        ];
        foreach ($ranges as [$s,$e]) {
            if ($long >= ip2long($s) && $long <= ip2long($e)) return true;
        }
        return false;
    }
    // IPv6 private/loopback/link-local
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return (
            str_starts_with($ip, '::1') ||
            str_starts_with($ip, 'fc') ||
            str_starts_with($ip, 'fd') ||
            str_starts_with($ip, 'fe80')
        );
    }
    return true; // unknown treated as unsafe
}

function host_allowed(string $host): bool {
    $host = strtolower($host);
    foreach (ALLOW_HOST_SUFFIXES as $suffix) {
        $suffix = strtolower($suffix);
        if ($host === $suffix) return true;
        if (str_ends_with($host, '.' . $suffix)) return true;
    }
    return false;
}

function validate_target_url(string $url): array {
    $url = trim($url);
    if ($url === '') return [false, 'Empty url', null];

    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return [false, 'Invalid url', null];

    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) return [false, 'Only http/https allowed', null];

    $host = strtolower($parts['host']);
    if (!host_allowed($host)) return [false, 'Host not allowlisted', null];

    // Resolve DNS and block private IPs
    $ips = @gethostbynamel($host);
    if (!$ips || count($ips) === 0) return [false, 'DNS resolution failed', null];
    foreach ($ips as $ip) {
        if (is_private_ip($ip)) return [false, 'Blocked private IP resolution', null];
    }

    // Optional: restrict ports
    if (isset($parts['port']) && !in_array((int)$parts['port'], [80, 443], true)) {
        return [false, 'Port not allowed', null];
    }

    return [true, 'ok', $url];
}

function get_cookie_jar_path(): string {
    $dir = sys_get_temp_dir() . '/qa_toolstudio_cookies';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $sid = preg_replace('/[^a-zA-Z0-9_\-]/', '', session_id());
    return $dir . '/cookie_' . $sid . '.txt';
}

function curl_fetch(array $req): array {
    $url    = $req['url'];
    $method = strtoupper($req['method'] ?? 'GET');
    $headers= $req['headers'] ?? [];
    $body   = $req['body'] ?? null;

    $ch = curl_init($url);

    $cookieJar = get_cookie_jar_path();

    $outHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT        => TOTAL_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_USERAGENT      => 'Jarir-QA-ToolStudio/2.0 (+internal)',
    ]);

    // Method handling
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    } elseif (in_array($method, ['PUT','PATCH','DELETE'], true)) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    } else {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    // Header allowlist pass-through (avoid dangerous ones)
    $safeHeaders = [];
    foreach ($headers as $k => $v) {
        $kn = strtolower((string)$k);
        if (in_array($kn, ['host','content-length'], true)) continue;
        // Optionally allow Authorization for Jarir APIs
        if ($kn === 'authorization' || $kn === 'content-type' || $kn === 'accept' || $kn === 'x-requested-with') {
            $safeHeaders[] = $k . ': ' . $v;
            continue;
        }
        // keep some common headers
        if (in_array($kn, ['accept-language','cache-control','pragma','referer','origin'], true)) {
            $safeHeaders[] = $k . ': ' . $v;
        }
    }
    if (count($safeHeaders) > 0) curl_setopt($ch, CURLOPT_HTTPHEADER, $safeHeaders);

    $t0 = microtime(true);
    $raw = curl_exec($ch);
    $dt = (int)round((microtime(true) - $t0) * 1000);

    if ($raw === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        curl_close($ch);
        return [
            'ok' => false,
            'error' => "cURL error ($code): $err",
            'status' => 0,
            'headers' => [],
            'body' => '',
            'time_ms' => $dt
        ];
    }

    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headerStr = substr($raw, 0, $headerSize);
    $bodyStr   = substr($raw, $headerSize);

    // Parse headers (last response in redirects chain)
    $lines = preg_split("/\r\n|\n|\r/", trim($headerStr));
    foreach ($lines as $line) {
        if (str_contains($line, ':')) {
            [$hk, $hv] = explode(':', $line, 2);
            $hk = trim($hk);
            $hv = trim($hv);
            if (!isset($outHeaders[$hk])) $outHeaders[$hk] = $hv;
        }
    }

    // Truncate capture (do not blow memory/UI)
    if (strlen($bodyStr) > MAX_BODY_BYTES) {
        $bodyStr = substr($bodyStr, 0, MAX_BODY_BYTES) . "\n/* truncated */";
    }

    return [
        'ok' => true,
        'status' => $status,
        'headers' => $outHeaders,
        'body' => $bodyStr,
        'time_ms' => $dt
    ];
}

// ---------- Persistence (optional DB integration) ----------
function get_pdo_or_null(): ?PDO {
    if (function_exists('get_db_auth')) {
        try { return get_db_auth(); } catch (Throwable $e) { return null; }
    }
    return null;
}

function ensure_custom_tools_table(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qa_custom_tools (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            name VARCHAR(255) NOT NULL,
            tool_code VARCHAR(64) NOT NULL,
            definition_json MEDIUMTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

// ---------- ROUTES ----------
$mode = $_GET['mode'] ?? '';

/**
 * mode=api_proxy : same-origin API proxy (POST JSON)
 * {
 *   url, method, headers (object), body (string)
 * }
 */
if ($mode === 'api_proxy') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) json_out(['ok'=>false,'error'=>'Invalid JSON'], 400);

    [$ok, $msg, $url] = validate_target_url((string)($data['url'] ?? ''));
    if (!$ok) json_out(['ok'=>false,'error'=>$msg], 400);

    $method = strtoupper((string)($data['method'] ?? 'GET'));
    $headers = $data['headers'] ?? [];
    $body = $data['body'] ?? null;

    // Only allow safe methods
    if (!in_array($method, ['GET','POST','PUT','PATCH','DELETE'], true)) {
        json_out(['ok'=>false,'error'=>'Method not allowed'], 400);
    }

    $res = curl_fetch([
        'url' => $url,
        'method' => $method,
        'headers' => is_array($headers) ? $headers : [],
        'body' => is_string($body) ? $body : (is_null($body) ? null : json_encode($body))
    ]);

    // Always allow our own UI to call this endpoint
    header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
    header('Access-Control-Allow-Credentials: true');

    json_out($res, $res['ok'] ? 200 : 502);
}

/**
 * mode=page_proxy : HTML/page proxy for "browser" (GET ?url=...)
 * - strips CSP headers
 * - injects recorder JS
 */
if ($mode === 'page_proxy') {
    $target = (string)($_GET['url'] ?? '');
    [$ok, $msg, $url] = validate_target_url($target);
    if (!$ok) { http_response_code(400); echo "Blocked: " . htmlspecialchars($msg); exit; }

    $res = curl_fetch(['url'=>$url,'method'=>'GET','headers'=>[],'body'=>null]);
    if (!$res['ok']) { http_response_code(502); echo "Proxy error: " . htmlspecialchars($res['error']); exit; }

    $ctype = '';
    foreach ($res['headers'] as $hk => $hv) {
        if (strtolower($hk) === 'content-type') { $ctype = $hv; break; }
    }
    if ($ctype === '') $ctype = 'text/html; charset=utf-8';

    // Remove CSP and other blocking headers by not forwarding them.
    header('Content-Type: ' . $ctype);
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');

    $body = $res['body'];

    // Only inject into HTML
    if (stripos($ctype, 'text/html') !== false) {
        $inject = <<<JS
<script>
(function(){
  // Notify parent that page loaded
  window.parent && window.parent.postMessage({source:'tool-studio-recorder', type:'recorder-ready', payload:{url: location.href}}, '*');

  // Capture clicks as "steps" (navigation intent)
  document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('a');
    if(!a) return;
    var href = a.getAttribute('href');
    if(!href) return;
    // Let parent handle navigation through proxy
    e.preventDefault();
    try {
      var abs = new URL(href, location.href).toString();
      window.parent.postMessage({source:'tool-studio-recorder', type:'navigate', payload:{url: abs}}, '*');
    } catch(err){}
  }, true);

  // Patch fetch so ANY API call goes through same-origin api_proxy (no CORS)
  var _fetch = window.fetch.bind(window);
  window.fetch = async function(input, init){
    init = init || {};
    var method = (init.method || 'GET').toUpperCase();
    var url = (typeof input === 'string') ? input : (input && input.url ? input.url : '');
    try { url = new URL(url, location.href).toString(); } catch(e){}

    // Only proxy jarir.com calls; everything else uses native fetch
    var host = '';
    try { host = new URL(url).hostname.toLowerCase(); } catch(e){}
    var isJarir = (host === 'jarir.com' || host.endsWith('.jarir.com'));

    if(!isJarir) return _fetch(input, init);

    var headersObj = {};
    try {
      var h = init.headers || {};
      if (h instanceof Headers) { h.forEach((v,k)=>headersObj[k]=v); }
      else if (Array.isArray(h)) { h.forEach(([k,v])=>headersObj[k]=v); }
      else { Object.keys(h).forEach(k=>headersObj[k]=h[k]); }
    } catch(e){}

    var body = init.body || null;

    var t0 = performance.now();
    var proxyRes = await _fetch('tool_studio_v2.php?mode=api_proxy', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({url:url, method:method, headers:headersObj, body: body})
    });
    var data = await proxyRes.json();
    var dt = Math.round(performance.now() - t0);

    // send to parent as captured api-call
    try {
      window.parent.postMessage({
        source:'tool-studio-recorder',
        type:'api-call',
        payload:{
          type:'fetch',
          url:url,
          method:method,
          status: data.status || 0,
          duration: data.time_ms || dt,
          requestHeaders: headersObj,
          requestBody: body,
          responseHeaders: data.headers || {},
          responseBody: data.body || ''
        }
      }, '*');
    } catch(e){}

    // emulate Response to page scripts
    var blob = new Blob([data.body || ''], {type: (data.headers && (data.headers['Content-Type']||data.headers['content-type'])) || 'text/plain'});
    var resp = new Response(blob, {status: data.status || 200, statusText: ''});
    return resp;
  };
})();
</script>
JS;

        // Remove CSP meta tags (common blocker)
        $body = preg_replace('/<meta[^>]+http-equiv=["\']Content-Security-Policy["\'][^>]*>/i', '', $body);

        // Inject before </head> if possible
        if (stripos($body, '</head>') !== false) {
            $body = preg_replace('/<\/head>/i', $inject . "\n</head>", $body, 1);
        } else {
            $body = $inject . "\n" . $body;
        }
    }

    echo $body;
    exit;
}

/**
 * POST action save_tool
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) json_out(['status'=>'error','error'=>'Invalid JSON'], 400);

    $action = (string)($data['action'] ?? '');
    if ($action !== 'save_tool') json_out(['status'=>'error','error'=>'Unknown action'], 400);

    $name = trim((string)($data['name'] ?? ''));
    $steps = $data['steps'] ?? null;
    if ($name === '' || !is_array($steps) || count($steps) === 0) {
        json_out(['status'=>'error','error'=>'Name and steps required'], 400);
    }

    // Create a stable "tool_code" from name
    $toolCode = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name));
    $toolCode = trim($toolCode, '_');
    if ($toolCode === '') $toolCode = 'custom_tool_' . time();

    $definition = [
        'version' => 1,
        'type' => 'recorded_api_sequence',
        'name' => $name,
        'tool_code' => $toolCode,
        'created_at' => date('c'),
        'steps' => $steps,
    ];

    $pdo = get_pdo_or_null();
    if ($pdo) {
        ensure_custom_tools_table($pdo);
        $uid = $_SESSION['user_id'] ?? null;

        $stmt = $pdo->prepare("INSERT INTO qa_custom_tools (user_id, name, tool_code, definition_json) VALUES (?, ?, ?, ?)");
        $stmt->execute([$uid, $name, $toolCode, json_encode($definition, JSON_UNESCAPED_UNICODE)]);
        json_out(['status'=>'success','tool_code'=>$toolCode,'saved'=>'db']);
    }

    // Fallback: save to file if no DB available
    $dir = __DIR__ . '/custom_tools';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $path = $dir . '/' . $toolCode . '.json';
    file_put_contents($path, json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    json_out(['status'=>'success','tool_code'=>$toolCode,'saved'=>'file','path'=>basename($path)]);
}

// ---------- UI ----------
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tool Studio v2</title>
<style>
  :root{--bg:#f6f7fb;--card:#fff;--border:#e6e8ef;--muted:#6b7280;--blue:#2563eb;--green:#16a34a;--red:#dc2626;}
  *{box-sizing:border-box;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial}
  body{margin:0;background:var(--bg);color:#111827}
  header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#111827;color:#fff}
  header .title{display:flex;align-items:center;gap:10px;font-weight:700}
  .dot{width:10px;height:10px;border-radius:999px;background:var(--red);display:none}
  .toolbar{display:flex;gap:8px;align-items:center;padding:10px 16px;background:var(--card);border-bottom:1px solid var(--border)}
  .btn{border:1px solid var(--border);background:#fff;padding:8px 10px;border-radius:10px;cursor:pointer;font-size:13px}
  .btn.primary{background:var(--blue);border-color:var(--blue);color:#fff}
  .btn.danger{background:var(--red);border-color:var(--red);color:#fff}
  .btn.success{background:var(--green);border-color:var(--green);color:#fff}
  .btn:disabled{opacity:.5;cursor:not-allowed}
  .url{flex:1;min-width:250px;padding:9px 10px;border:1px solid var(--border);border-radius:10px}
  .wrap{display:flex;gap:12px;padding:12px;min-height:calc(100vh - 110px)}
  .pane{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden}
  .left{flex:1;min-width:380px;display:flex;flex-direction:column}
  .right{width:520px;max-width:45vw;display:flex;flex-direction:column}
  iframe{border:0;width:100%;height:100%}
  .panehead{padding:10px 12px;border-bottom:1px solid var(--border);font-weight:600}
  .tableWrap{flex:1;overflow:auto}
  table{width:100%;border-collapse:collapse;font-size:12px}
  th,td{padding:7px 8px;border-bottom:1px solid var(--border);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  tr:hover{background:#f3f4f6;cursor:pointer}
  .status2{color:var(--green);font-weight:700}
  .status4,.status5{color:var(--red);font-weight:700}
  .details{height:260px;border-top:1px solid var(--border);display:flex;flex-direction:column}
  .tabs{display:flex;border-bottom:1px solid var(--border);background:#f9fafb}
  .tab{padding:8px 12px;font-size:12px;cursor:pointer;color:var(--muted)}
  .tab.active{color:#111827;border-bottom:2px solid var(--blue);font-weight:700}
  pre{margin:0;padding:10px;overflow:auto;font-size:12px;background:#0b1020;color:#e5e7eb;flex:1}
</style>
</head>
<body>

<header>
  <div class="title">
    <span class="dot" id="recDot"></span>
    Tool Studio v2 <span style="opacity:.7;font-weight:500">| Record Jarir API sequences safely</span>
  </div>
  <div>
    <button class="btn" onclick="location.href='dashboard.php'">Exit</button>
  </div>
</header>

<div class="toolbar">
  <button class="btn danger" id="btnRec" onclick="toggleRec()">⏺ Record</button>
  <button class="btn" onclick="clearLog()">Clear</button>
  <input class="url" id="urlInput" value="https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/home" placeholder="Enter jarir.com URL to browse...">
  <button class="btn primary" onclick="go()">Go</button>
  <button class="btn" onclick="exportJson()">Export JSON</button>
  <button class="btn success" id="btnSave" disabled onclick="saveTool()">Save Tool</button>
</div>

<div class="wrap">
  <div class="pane left">
    <div class="panehead">Browser (proxied)</div>
    <div style="flex:1">
      <iframe id="frame" src="about:blank"></iframe>
    </div>
  </div>

  <div class="pane right">
    <div class="panehead">Network (Captured Steps)</div>
    <div class="tableWrap">
      <table>
        <thead>
          <tr>
            <th style="width:70px">Status</th>
            <th style="width:70px">Method</th>
            <th>URL</th>
            <th style="width:80px">Time</th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>

    <div class="details">
      <div class="tabs">
        <div class="tab active" data-tab="req" onclick="switchTab('req')">Request</div>
        <div class="tab" data-tab="res" onclick="switchTab('res')">Response</div>
        <div class="tab" data-tab="meta" onclick="switchTab('meta')">Meta</div>
      </div>
      <pre id="reqPane"></pre>
      <pre id="resPane" style="display:none"></pre>
      <pre id="metaPane" style="display:none"></pre>
    </div>
  </div>
</div>

<script>
let isRec = false;
let events = [];
let selected = -1;

const frame = document.getElementById('frame');
const urlInput = document.getElementById('urlInput');
const tbody = document.getElementById('tbody');
const btnSave = document.getElementById('btnSave');
const recDot = document.getElementById('recDot');
const btnRec = document.getElementById('btnRec');

function go(){
  const url = urlInput.value.trim();
  if(!url) return;
  frame.src = 'tool_studio_v2.php?mode=page_proxy&url=' + encodeURIComponent(url);
  // Log navigation as a step (recorded)
  if(isRec){
    addEvent({
      type:'navigate',
      url:url,
      method:'GET',
      status:0,
      duration:0,
      requestHeaders:{},
      requestBody:null,
      responseHeaders:{},
      responseBody:''
    });
  }
}

function toggleRec(){
  isRec = !isRec;
  recDot.style.display = isRec ? 'inline-block' : 'none';
  btnRec.textContent = isRec ? '⏹ Stop' : '⏺ Record';
  updateSave();
}

function clearLog(){
  events = [];
  selected = -1;
  tbody.innerHTML = '';
  document.getElementById('reqPane').textContent = '';
  document.getElementById('resPane').textContent = '';
  document.getElementById('metaPane').textContent = '';
  updateSave();
}

function updateSave(){
  const apiCount = events.filter(e => e.type === 'fetch' || e.type === 'xhr').length;
  btnSave.disabled = !(isRec === false && apiCount > 0);
}

function addEvent(e){
  if(!isRec && (e.type==='fetch'||e.type==='xhr'||e.type==='navigate')){
    // if not recording, ignore captured events
    return;
  }
  events.push(e);
  const idx = events.length - 1;

  const tr = document.createElement('tr');
  tr.onclick = ()=>selectRow(idx,tr);

  const status = e.status || 0;
  const cls = status >= 500 ? 'status5' : (status >= 400 ? 'status4' : (status >= 200 ? 'status2' : ''));
  tr.innerHTML = `
    <td class="${cls}">${status || '-'}</td>
    <td>${(e.method||'-')}</td>
    <td title="${e.url}">${e.url}</td>
    <td>${(e.duration||0)}ms</td>
  `;
  tbody.appendChild(tr);
  updateSave();
}

function selectRow(i,tr){
  selected = i;
  [...tbody.querySelectorAll('tr')].forEach(x=>x.style.background='');
  tr.style.background = '#eef2ff';
  renderDetails();
}

function renderDetails(){
  if(selected < 0) return;
  const e = events[selected];

  const req = {
    type: e.type,
    url: e.url,
    method: e.method,
    headers: e.requestHeaders || {},
    body: e.requestBody || null,
  };
  const res = {
    status: e.status,
    headers: e.responseHeaders || {},
    body: e.responseBody || '',
  };
  const meta = {
    duration_ms: e.duration || 0,
    captured_at: new Date().toISOString()
  };

  document.getElementById('reqPane').textContent  = JSON.stringify(req, null, 2);
  document.getElementById('resPane').textContent  = JSON.stringify(res, null, 2);
  document.getElementById('metaPane').textContent = JSON.stringify(meta, null, 2);
}

function switchTab(tab){
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  document.querySelector(`.tab[data-tab="${tab}"]`).classList.add('active');
  document.getElementById('reqPane').style.display  = (tab==='req') ? 'block' : 'none';
  document.getElementById('resPane').style.display  = (tab==='res') ? 'block' : 'none';
  document.getElementById('metaPane').style.display = (tab==='meta')? 'block' : 'none';
}

function exportJson(){
  if(events.length===0) return alert('Nothing to export');
  const blob = new Blob([JSON.stringify({events}, null, 2)], {type:'application/json'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'tool_studio_recording.json';
  a.click();
  URL.revokeObjectURL(a.href);
}

async function saveTool(){
  const name = prompt('Tool name? (e.g. CMS Home API Flow)');
  if(!name) return;

  const steps = events.filter(e => e.type==='fetch' || e.type==='xhr' || e.type==='navigate');
  const r = await fetch('tool_studio_v2.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'save_tool', name, steps})
  });
  const data = await r.json();
  if(data.status==='success'){
    alert('Saved! tool_code=' + data.tool_code + ' ('+data.saved+')');
    clearLog();
  } else {
    alert('Save failed: ' + (data.error||'unknown'));
  }
}

// Receive injected recorder messages
window.addEventListener('message', (ev)=>{
  const msg = ev.data;
  if(!msg || msg.source !== 'tool-studio-recorder') return;

  if(msg.type === 'navigate'){
    // user clicked inside proxied page
    urlInput.value = msg.payload.url;
    go();
    return;
  }

  if(msg.type === 'api-call'){
    addEvent(msg.payload);
    // auto-select latest
    const lastRow = tbody.lastElementChild;
    if(lastRow) selectRow(events.length-1, lastRow);
    return;
  }
});
</script>
</body>
</html>
