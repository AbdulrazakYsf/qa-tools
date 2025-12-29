<?php
require_once 'auth_session.php';
require_login();
$currentUser = current_user();

// Handle Save Logic via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_tool') {
    header('Content-Type: application/json');
    try {
        require_role(['admin', 'tester']);
        $input = json_decode(file_get_contents('php://input'), true);
        
        $name = $input['name'];
        $code = strtolower(str_replace(' ', '_', $name));
        $steps = $input['steps'];

        // Save to DB
        $db = get_db_auth();
        
        // 1. Create/Update Tool Entry
        // Check if exists
        $chk = $db->prepare("SELECT id FROM qa_tools WHERE code = ?");
        $chk->execute([$code]);
        if (!$chk->fetch()) {
             $stmt = $db->prepare("INSERT INTO qa_tools (code, name, api_enabled) VALUES (?, ?, 1)");
             $stmt->execute([$code, $name]);
        }
        
        // 2. Save Steps (For now, we might need a new table `qa_tool_steps` or just save as JSON in `qa_tools`?)
        // The user didn't specify DB schema changes, but we need to store the "Tool Sequence".
        // Let's add a `configuration` or `script` column to `qa_tools`.
        // Or create a file in `tools/custom_tools/`.
        // Let's use a File-based approach for the "Runner" since `dashboard.php` loads PHP/HTML files.
        // We will generate a `.json` file for the steps, and a `.html` wrapper.
        
        if (!is_dir(__DIR__ . '/tools/custom')) {
            mkdir(__DIR__ . '/tools/custom', 0755, true);
        }
        
        // Save Sequence
        file_put_contents(__DIR__ . "/tools/custom/{$code}.json", json_encode($steps, JSON_PRETTY_PRINT));
        
        // Create HTML Wrapper for Dashboard
        $htmlContent = "
<div id=\"custom-tool-runner\" data-tool-code=\"{$code}\">
    <div class=\"loading\" style=\"display:none;\">Running {$name}...</div>
    <ul id=\"results\" class=\"results\"></ul>
    <script>
        // Start Execution
        window.startCustomTool = function() {
            // Call generic runner
            runCustomSequence('{$code}');
        };
        // Auto-run if requested?
        // window.startCustomTool();
    </script>
</div>
";
        file_put_contents(__DIR__ . "/tools/{$code}.html", $htmlContent);

        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tool Studio - QA Automation</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        header { background: #1a3a57; color: white; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; height: 50px; }
        .toolbar { background: #f1f5f9; padding: 8px 15px; display: flex; gap: 10px; border-bottom: 1px solid #ddd; align-items: center; }
        .main { flex: 1; display: flex; overflow: hidden; }
        .sidebar { width: 350px; background: #fff; border-right: 1px solid #ddd; display: flex; flex-direction: column; }
        .browser-container { flex: 1; background: #e2e8f0; position: relative; }
        iframe { width: 100%; height: 100%; border: none; background: white; }
        
        .url-bar { flex: 1; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; }
        .btn { padding: 6px 14px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .log-header { padding: 10px; background: #f8fafc; border-bottom: 1px solid #ddd; font-weight: bold; font-size: 12px; color: #64748b; }
        .log-list { flex: 1; overflow-y: auto; list-style: none; padding: 0; margin: 0; }
        .log-item { padding: 10px; border-bottom: 1px solid #eee; font-size: 12px; cursor: pointer; }
        .log-item:hover { background: #f1f5f9; }
        .log-method { font-weight: bold; margin-right: 5px; }
        .method-GET { color: #007bff; }
        .method-POST { color: #28a745; }
        .log-url { color: #333; word-break: break-all; }
        .recording-indicator { width: 12px; height: 12px; background: red; border-radius: 50%; display: inline-block; animation: blink 1s infinite; margin-right: 5px; display: none; }
        @keyframes blink { 50% { opacity: 0.4; } }
    </style>
</head>
<body>

<header>
    <div style="display:flex; align-items:center;">
        <span class="recording-indicator" id="rec-dot"></span>
        <span style="font-weight:bold;">Tool Studio</span>
    </div>
    <div>
        <button class="btn" style="background:rgba(255,255,255,0.2); color:white;" onclick="window.location.href='dashboard.php'">Exit to Dashboard</button>
    </div>
</header>

<div class="toolbar">
    <button class="btn btn-danger" id="btn-record" onclick="toggleRecord()">⏺ Record</button>
    <div style="width: 1px; height: 20px; background: #ccc; margin: 0 10px;"></div>
    <input type="text" id="url-input" class="url-bar" value="https://www.jarir.com" placeholder="Enter URL...">
    <button class="btn btn-primary" onclick="browse()">Go</button>
    <div style="flex:1;"></div>
    <button class="btn btn-success" id="btn-save" disabled onclick="saveTool()">Save Tool</button>
</div>

<div class="main">
    <div class="sidebar">
        <div class="log-header">RECORDED STEPS (<span id="step-count">0</span>)</div>
        <ul class="log-list" id="log-list">
            <!-- Steps go here -->
        </ul>
        <div style="padding:10px; border-top:1px solid #ddd; background:#f9f9f9; display:none;" id="save-panel">
            <input type="text" id="new-tool-name" placeholder="Tool Name (e.g. Checkout Flow)" style="width:100%; padding:8px; margin-bottom:5px; border:1px solid #ddd; border-radius:4px;">
            <button class="btn btn-success" style="width:100%;" onclick="confirmSave()">Confirm Save</button>
        </div>
        
        <!-- Debug Console -->
        <div style="margin-top:auto; border-top:1px solid #ddd;">
            <div onclick="toggleDebug()" style="padding:8px; background:#ddd; cursor:pointer; font-size:11px; font-weight:bold;">
                â–¶ DEBUG CONSOLE (<span id="debug-status">Waiting...</span>)
            </div>
            <div id="debug-panel" style="height:150px; overflow-y:auto; background:#333; color:#0f0; padding:5px; font-family:monospace; font-size:10px; display:none;">
                <div>> Initializing...</div>
            </div>
        </div>
    </div>
    <div class="browser-container">
        <iframe id="browser-frame" src="about:blank"></iframe>
    </div>
</div>

<script>
    let isRecording = false;
    let recordedSteps = [];
    const browser = document.getElementById('browser-frame');
    const urlInput = document.getElementById('url-input');
    const debugPanel = document.getElementById('debug-panel');

    function logDebug(msg) {
        const d = document.createElement('div');
        d.textContent = '> ' + msg;
        debugPanel.appendChild(d);
        debugPanel.scrollTop = debugPanel.scrollHeight;
    }
    
    function toggleDebug() {
        const p = document.getElementById('debug-panel');
        p.style.display = p.style.display === 'none' ? 'block' : 'none';
    }

    function browse() {
        const url = urlInput.value;
        if (!url) return;
        browser.src = 'proxy.php?url=' + encodeURIComponent(url);
    }

    function toggleRecord() {
        isRecording = !isRecording;
        document.getElementById('rec-dot').style.display = isRecording ? 'inline-block' : 'none';
        document.getElementById('btn-record').textContent = isRecording ? 'â¹ Stop' : 'âº Record';
        // document.getElementById('btn-record').classList.toggle('btn-danger');
        if (!isRecording) {
            document.getElementById('btn-save').disabled = recordedSteps.length === 0;
        }
    }

    // Listen for Recorder Events
    window.addEventListener('message', function(e) {
        const msg = e.data;
        if (msg.source !== 'tool-studio-recorder') return;

        if (msg.type === 'recorder-ready') {
            document.getElementById('debug-status').textContent = 'Connected';
            document.getElementById('debug-status').style.color = 'green';
            logDebug("Recorder Injected Successfully: " + msg.payload.url);
        }

        if (msg.type === 'debug') {
            logDebug((msg.payload.msg || '') + ' ' + (msg.payload.url || '') + ' ' + (msg.payload.error || ''));
        }

        if (msg.type === 'api-call') {
            logDebug("Captured API: " + msg.payload.url);
            if (!isRecording) return;
            addStep(msg.payload);
        }
    });

    function addStep(data) {
        recordedSteps.push(data);
        document.getElementById('step-count').textContent = recordedSteps.length;
        
        const li = document.createElement('li');
        li.className = 'log-item';
        
        // Shorten URL
        let displayUrl = data.url;
        try { displayUrl = new URL(data.url).pathname; } catch(e) {}
        if (displayUrl.length > 40) displayUrl = displayUrl.substring(0, 37) + '...';

        li.innerHTML = `
            <span class="log-method method-${data.method}">${data.method}</span>
            <span class="log-url" title="${data.url}">${displayUrl}</span>
            <div style="font-size:10px; color:#999; margin-top:2px;">${data.status} • ${data.duration}ms</div>
        `;
        document.getElementById('log-list').appendChild(li);
    }

    function saveTool() {
        document.getElementById('save-panel').style.display = 'block';
    }

    async function confirmSave() {
        const name = document.getElementById('new-tool-name').value;
        if (!name) return alert("Enter a tool name");

        const btn = document.querySelector('#save-panel button');
        const oldText = btn.textContent;
        btn.textContent = 'Saving...';
        btn.disabled = true;

        try {
            const res = await fetch('tool_studio.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'save_tool',
                    name: name,
                    steps: recordedSteps
                })
            });
            const data = await res.json();
            if (data.status === 'success') {
                alert("Tool Saved Successfully!");
                window.location.reload(); 
            } else {
                alert("Error: " + data.error);
            }
        } catch (e) {
            alert("Save failed: " + e);
        }
        
        btn.textContent = oldText;
        btn.disabled = false;
    }

    // Init
    // browse(); // Don't auto-browse, let user choose
</script>

</body>
</html>
