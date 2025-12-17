<?php
$file = 'dashboard.php';
$lines = file($file);
$startLine = -1;
$endLine = -1;

foreach ($lines as $i => $line) {
    if (strpos($line, '$TOOLS_HTML = [') !== false) {
        $startLine = $i;
    }
    if (strpos($line, 'tool_content_sub_category') !== false) {
        // The ]; is close after
        for ($j = $i; $j < count($lines); $j++) {
            if (trim($lines[$j]) === '];') {
                $endLine = $j;
                break 2;
            }
        }
    }
}

if ($startLine > -1 && $endLine > -1) {
    $newContent = [];
    // 0 to startLine - 1
    for ($k = 0; $k < $startLine; $k++)
        $newContent[] = $lines[$k];

    // Insert new logic
    $newContent[] = '$TOOLS_HTML = [];' . PHP_EOL;
    $newContent[] = '$toolsDir = __DIR__ . \'/tools/\';' . PHP_EOL;
    $newContent[] = 'if (is_dir($toolsDir)) {' . PHP_EOL;
    $newContent[] = '    foreach (glob($toolsDir . \'*.html\') as $file) {' . PHP_EOL;
    $newContent[] = '        $code = basename($file, \'.html\');' . PHP_EOL;
    $newContent[] = '        $TOOLS_HTML[$code] = file_get_contents($file);' . PHP_EOL;
    $newContent[] = '    }' . PHP_EOL;
    $newContent[] = '}' . PHP_EOL;

    // endLine + 1 to end
    for ($k = $endLine + 1; $k < count($lines); $k++)
        $newContent[] = $lines[$k];

    file_put_contents($file, implode('', $newContent));
    echo "Files patched successfully. Removed lines $startLine to $endLine.\n";
} else {
    echo "Markers not found. Start: $startLine, End: $endLine\n";
    exit(1);
}
?>