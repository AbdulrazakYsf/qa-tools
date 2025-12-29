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
    // ... basic resolution ...
    // For now, let's rely on base tag injection or proxy-rewriting middleware
    // Actually, rewriting everything is safer for keeping user in proxy.
    return $rel; 
}

$url = $_GET['url'] ?? '';

if (!$url) {
    die("No URL specified.");
}

// Basic validation
if (strpos($url, 'http') !== 0) {
    $url = 'https://' . $url;
}

// Fetch Content
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ToolStudio/1.0');
// Forward Cookies?
if (isset($_SERVER['HTTP_COOKIE'])) {
   curl_setopt($ch, CURLOPT_COOKIE, $_SERVER['HTTP_COOKIE']);
}
// Ignore SSL (dev)
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

$response = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

// Set Headers
header("Content-Type: $contentType");

// Logic: If HTML, rewrite links and Inject Recorder
if (stripos($contentType, 'text/html') !== false) {
    
    // 1. Inject Base Tag (Corrects relative assets like images/css)
    // We use the FINAL fetched URL as base
    $baseTag = '<base href="' . $finalUrl . '">';
    
    // BUT, we want links (<a href>) to go through proxy.
    // So we need a mixed approach: 
    // Assets -> Load directly from source (via Base Tag)
    // Navigation -> Go through Proxy (requires rewriting)
    
    // Let's rely on Base Tag for assets, and use JS to hook clicks for navigation?
    // Or simpler: Inject recorder, and let recorder ALSO Hook Clicks.
    // Let's try minimal intervention first: Just Base Tag + Recorder.
    
    // Only issue: If user clicks a link, they leave the proxy and go to real site.
    // Solution: Inject JS to intercept clicks.
    
    $injection = "
    <script>
    (function() {
        // Intercept Clicks to keep in proxy
        document.addEventListener('click', function(e) {
            let target = e.target.closest('a');
            if (target && target.href) {
                e.preventDefault();
                const realUrl = target.href; // filtered by base tag
                window.location.href = 'proxy.php?url=' + encodeURIComponent(realUrl);
            }
        });
    })();
    </script>
    <script src='recorder.js'></script>
    ";

    // Insert after <head>
    $response = str_ireplace('<head>', '<head>' . $baseTag, $response);
    
    // Insert before </body>
    $response = str_ireplace('</body>', $injection . '</body>', $response);
    
    echo $response;

} else {
    // CSS/JS/Images: Just echo
    echo $response;
}
?>
