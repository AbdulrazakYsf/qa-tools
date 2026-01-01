<?php
// proxy.php - A simple rewriting proxy for Tool Studio
require_once 'auth_session.php';
require_login();
session_write_close(); // Unlock session to allow parallel proxy requests

// Disable timeout for large assets
set_time_limit(0);

// Helper to resolve relative URLs
function rel2abs($rel, $base) {
    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
    if ($rel[0] == '#' || $rel[0] == '?') return $base . $rel;
    return $rel; 
}

$url = $_GET['url'] ?? '';

if (!$url) {
    die("No URL specified.");
}

// Basic validation and protocol enforcing
if (strpos($url, 'http') !== 0) {
    $url = 'https://' . $url;
}

$method = $_SERVER['REQUEST_METHOD'];

// Init cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_ENCODING, ''); // Auto-decode gzip/deflate/br
curl_setopt($ch, CURLOPT_HEADER, false); // We get headers separately or curl_getinfo? Usually cleaner to not include header in body output

// Check toggle: Default is ON (1)
$useCorsFix = isset($_GET['cors']) ? $_GET['cors'] === '1' : true;

// 1. Forward Request Headers
$reqHeaders = [];

if ($useCorsFix) {
    // --- SPOOFING LGOIC ---
    $parsedUrl = parse_url($url);
    $targetOrigin = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? '');

    foreach (getallheaders() as $key => $value) {
        if (stripos($key, 'Host') !== false || stripos($key, 'Content-Length') !== false) continue;
        if (stripos($key, 'Origin') !== false) continue;
        if (stripos($key, 'Referer') !== false) continue;
        $reqHeaders[] = "$key: $value";
    }
    $reqHeaders[] = "Origin: $targetOrigin";
    $reqHeaders[] = "Referer: $targetOrigin/";
} else {
    // --- PASSTHROUGH LOGIC (Original) ---
    foreach (getallheaders() as $key => $value) {
        if (stripos($key, 'Host') !== false || stripos($key, 'Content-Length') !== false) continue;
        $reqHeaders[] = "$key: $value";
    }
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);

// ... (Body forwarding is same) ...

// 4. Capture and Forward Response Headers
$responseHeaders = [];

if ($useCorsFix) {
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
        $len = strlen($header);
        $header = trim($header);
        if (empty($header)) return $len; 

        $lower = strtolower($header);
        
        // Critical Header Forwarding with Domain Strip
        if (strpos($lower, 'set-cookie:') === 0 || 
            strpos($lower, 'content-type:') === 0 ||
            strpos($lower, 'location:') === 0 ||
            strpos($lower, 'etag:') === 0 ||
            strpos($lower, 'cache-control:') === 0 ||
            strpos($lower, 'last-modified:') === 0) {
            
            if (strpos($lower, 'set-cookie:') === 0) {
                // Strip Domain to allow localhost acceptance
                $header = preg_replace('/;\s*Domain=[^;]+/', '', $header);
            }
            header($header, false);
        }
        
        if (strpos($lower, 'content-type:') === 0) {
            $responseHeaders['content-type'] = substr($header, 13);
        }
        
        return $len;
    });
} else {
    // Basic Header Handling (Let PHP handle output primarily, just capture Content-Type)
    // Actually, if we disable fix, we should revert to simple content-type capture?
    // The original code used curl_getinfo for content-type.
    // So we don't set a HEADERFUNCTION here.
}

// Execute
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = $responseHeaders['content-type'] ?? curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

// Set Response Code
http_response_code($httpCode);
// Content-Type is already forwarded by the loop above, but we kept it in $contentType for logic below.

// Robust CORS Handling
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");    // cache for 1 day

// Access-Control-Allow-Methods
if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH");
} else {
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH");
}

// Access-Control-Allow-Headers
if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
    header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
} else {
    header("Access-Control-Allow-Headers: *");
}

// Handle Pre-flight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Strip X-Frame/CSP to allow iframe embedding
header_remove("X-Frame-Options");
header_remove("Content-Security-Policy");

// Logic: If HTML, rewrite links and Inject Recorder
if (stripos($contentType, 'text/html') !== false) {
    
    // 1. Inject Base Tag
    $baseTag = '<base href="' . $finalUrl . '">';
    
    // 2. Injection Script
    // We also hook clicks to force navigation via proxy (Simple <a> tags)
    // Complex JS navigation (History API) will still update URL bar, 
    // but fetches are now hooked via recorder.js!
    
    $recorderCode = file_get_contents(__DIR__ . '/recorder.js');

    $injection = "
    <script>
    (function() {
        // Intercept Clicks to keep in proxy
        document.addEventListener('click', function(e) {
            let target = e.target.closest('a');
            if (target && target.href) {
                // Ignore if it's a hash or js:
                if (target.getAttribute('href').startsWith('#') || target.getAttribute('href').startsWith('javascript:')) return;
                
                e.preventDefault();
                // We use the Absolute href (thanks to base tag, target.href is absolute)
                // Redirect parent? No, iframe window.
                window.location.href = 'proxy.php?url=' + encodeURIComponent(target.href);
            }
        });
    })();
    </script>
    <script>{$recorderCode}</script>
    ";

    // Insert after <head> to run BEFORE other scripts
    $response = str_ireplace('<head>', '<head>' . $baseTag . $injection, $response);
    
    echo $response;

} else {
    // JSON, JS, CSS, Images: Just echo
    echo $response;
}
?>
