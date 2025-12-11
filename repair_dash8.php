<?php
// repair_dash8.php

error_reporting(E_ALL);

$outFile = 'qa-dash8.php';
$sourceDash7 = 'qa-dash7.php';
$toolsDir = __DIR__ . '/tools';

echo "Start repairing $outFile...\n";

// 1. Prepare Header
$header = <<<'PHP'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'auth_session.php';
require_login();
$currentUser = current_user();

$TOOLS_HTML = [
PHP;

// 2. Build Tools Array
$toolsContent = "";
$files = glob($toolsDir . '/*.html');
foreach ($files as $f) {
    if (!is_file($f))
        continue;
    $key = basename($f, '.html');
    echo "Processing tool: $key\n";

    $content = file_get_contents($f);

    // Use NOWDOC with a unique marker to avoid collisions
    $marker = "tool_content_$key";
    // Ensure content ends with newline to be safe for Nowdoc closing
    if (substr($content, -1) !== "\n") {
        $content .= "\n";
    }

    $toolsContent .= "    '$key' => <<<'{$marker}'\n" . $content . "{$marker},\n\n";
}

$mid = "];\n\n";

// 3. Extract Logic from qa-dash7.php (from Database section onwards)
echo "Reading $sourceDash7...\n";
$dash7Lines = file($sourceDash7);
$startLine = null;

// Find the line index where the Database section starts
foreach ($dash7Lines as $i => $line) {
    if (
        strpos($line, '/*********************************') !== false &&
        strpos($dash7Lines[$i + 1] ?? '', '* 1. DATABASE') !== false
    ) {
        $startLine = $i;
        break;
    }
}

if ($startLine === null) {
    die("Error: Could not find Database section start in $sourceDash7\n");
}

echo "Found logic start at line " . ($startLine + 1) . "\n";

// Slice the array from startLine onto the end
$logicLines = array_slice($dash7Lines, $startLine);
$logicContent = implode("", $logicLines);

// 4. Write Content
$finalContent = $header . "\n" . $toolsContent . $mid . $logicContent;

if (file_put_contents($outFile, $finalContent) === false) {
    die("Error: Failed to write to $outFile\n");
}

echo "Success! $outFile has been repaired.\n";
