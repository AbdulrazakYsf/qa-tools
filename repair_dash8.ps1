$ErrorActionPreference = "Stop"
$outFile = "qa-dash8.php"
$toolsDir = "tools"
$dash7 = "qa-dash7.php"

Write-Host "Starting repair..."

$header = @"
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'auth_session.php';
require_login();
$currentUser = current_user();

`$TOOLS_HTML = [
"@

# Start with header (UTF8 encoding)
Set-Content -Path $outFile -Value $header -Encoding UTF8

# Tools
$files = Get-ChildItem -Path $toolsDir -Filter *.html
foreach ($f in $files) {
    $key = $f.BaseName
    Write-Host "Adding tool: $key"
    $content = Get-Content -Path $f.FullName -Raw
    
    $marker = "tool_content_$key"
    
    Add-Content -Path $outFile -Value "    '$key' => <<<'${marker}'" -Encoding UTF8
    # Append content. Note: Add-Content will add a newline at the end of the value.
    # To avoid double newlines if content has one, we trim end?
    # No, heredoc body must be exact.
    # But Add-Content adds a line break appearing *after* the content string.
    
    # We want the content to be exactly as in file.
    # If we use -NoNewline it might be safer, but then we need to manually handle the marker line.
    
    Add-Content -Path $outFile -Value $content -NoNewline -Encoding UTF8
    
    # Ensure newline before marker
    if (-not $content.EndsWith("`n")) {
        Add-Content -Path $outFile -Value "`n" -NoNewline -Encoding UTF8
    }
    # Ensure marker is on its own line
    Add-Content -Path $outFile -Value "`n${marker}," -Encoding UTF8
    Add-Content -Path $outFile -Value "" -Encoding UTF8
}

Add-Content -Path $outFile -Value "];" -Encoding UTF8
Add-Content -Path $outFile -Value "" -Encoding UTF8

# Logic from dash7
Write-Host "Reading $dash7..."
$lines = Get-Content -Path $dash7
$startLine = -1
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "/\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*\*" -and $lines[$i+1] -match "\* 1. DATABASE") {
        $startLine = $i
        break
    }
}

if ($startLine -eq -1) {
    Write-Error "Could not find start of logic in $dash7"
}

Write-Host "Found logic start at line $($startLine+1)"
# Extract and append
$lines | Select-Object -Skip $startLine | Add-Content -Path $outFile -Encoding UTF8

Write-Host "Success! $outFile has been repaired."
