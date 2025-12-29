<?php
// proxy.php - A simple rewriting proxy for Tool Studio
require_once 'auth_session.php';
require_login();

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

// 1. Forward Request Headers
$reqHeaders = [];
foreach (getallheaders() as $key => $value) {
    // Skip Host (let curl set it), Skip Content-Length (let curl recalc)
    // Cookie is important
    if (stripos($key, 'Host') !== false || stripos($key, 'Content-Length') !== false) continue;
    $reqHeaders[] = "$key: $value";
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);

// 2. Forward Body (POST/PUT)
if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE' || $method === 'PATCH') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $input = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
}

// 3. User Agent Override (Consistency)
// curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ToolStudio/1.0');

// Execute
$response = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Set Response Headers
http_response_code($httpCode);
header("Content-Type: $contentType");
// Important: Allow everything
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
// Strip X-Frame/CSP
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
    <script src='recorder.js'></script>
    ";

    // Insert after <head>
    $response = str_ireplace('<head>', '<head>' . $baseTag, $response);
    
    // Insert before </body>
    if (strpos($response, '</body>') !== false) {
        $response = str_ireplace('</body>', $injection . '</body>', $response);
    } else {
        $response .= $injection;
    }
    
    echo $response;

} else {
    // JSON, JS, CSS, Images: Just echo
    echo $response;
}
?>
