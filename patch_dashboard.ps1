$file = "dashboard.php"
$lines = Get-Content $file
$startLine = -1
$endLine = -1
$rowCount = $lines.Count

for ($i = 0; $i -lt $rowCount; $i++) {
    if ($lines[$i].Contains('$TOOLS_HTML = [')) {
        $startLine = $i
    }
    if ($lines[$i] -like '*tool_content_sub_category*') {
        for ($j = $i; $j -lt $rowCount; $j++) {
            if ($lines[$j].Trim() -eq "];") {
                $endLine = $j
                break
            }
        }
    }
}

if ($startLine -gt -1 -and $endLine -gt -1) {
    $newContent = $lines[0..($startLine - 1)]
    $newContent += '$TOOLS_HTML = [];'
    $newContent += '$toolsDir = __DIR__ . ''/tools/'';'
    $newContent += 'if (is_dir($toolsDir)) {'
    $newContent += '    foreach (glob($toolsDir . ''*.html'') as $file) {'
    $newContent += '        $code = basename($file, ''.html'');'
    $newContent += '        $TOOLS_HTML[$code] = file_get_contents($file);'
    $newContent += '    }'
    $newContent += '}'
    $newContent += $lines[($endLine + 1)..($rowCount - 1)]
    
    $newContent | Set-Content $file -Encoding UTF8
    Write-Host "Patched lines $startLine to $endLine."
}
else {
    Write-Host "Markers not found. Start: $startLine End: $endLine"
}
