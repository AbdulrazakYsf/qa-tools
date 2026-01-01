<?php
require_once 'auth_session.php';
require_login();
$currentUser = current_user();

// Handle Save Logic via POST (API Endpoint)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start output buffering to catch any stray warnings/notices
    ob_start();
    header('Content-Type: application/json');

    try {
        require_role(['admin', 'tester']);

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        if (!$input) {
            throw new Exception("Invalid JSON received");
        }

        if (!isset($input['action']) || $input['action'] !== 'save_tool') {
             throw new Exception("Invalid Action");
        }
        
        $name = $input['name'] ?? '';
        if (!$name) throw new Exception("Tool Name is required");

        $code = strtolower(str_replace(' ', '_', $name));
        $steps = $input['steps'] ?? [];

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
        
        // 2. Save Steps
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
    </script>
</div>
";
        file_put_contents(__DIR__ . "/tools/{$code}.html", $htmlContent);

        // Clear any previous output (e.g. notices)
        ob_end_clean();
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        // Clear any previous output
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tool Studio - Network Inspector</title>
    <style>
        :root { --border-color: #e0e0e0; --bg-color: #f5f5f5; --header-bg: #f8f9fa; --row-hover: #f1f3f4; --selected-row: #e8f0fe; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Header */
        header { background: #2c3e50; color: white; padding: 0 15px; height: 48px; display: flex; align-items: center; justify-content: space-between; font-size: 14px; }
        .brand { font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .recording-dot { width: 10px; height: 10px; background: #ff5252; border-radius: 50%; display: none; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

        /* Toolbar */
        .toolbar { background: #fff; border-bottom: 1px solid var(--border-color); padding: 8px 12px; display: flex; gap: 8px; align-items: center; }
        .url-bar { flex: 1; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; }
        .btn { padding: 6px 12px; border: 1px solid transparent; border-radius: 4px; font-size: 12px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background: #1a73e8; color: white; }
        .btn-danger { background: #d93025; color: white; }
        .btn-light { background: #fff; border-color: #ccc; color: #333; }
        .btn:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.5; cursor: default; }

        /* Main Layout */
        .main-container { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .split-view { flex: 1; display: flex; overflow: hidden; }
        
        .browser-pane { flex: 1; display: flex; flex-direction: column; border-right: 1px solid var(--border-color); background: #eee; }
        iframe { flex: 1; border: none; background: white; }

        .inspector-pane { width: 50%; display: flex; flex-direction: column; background: white; min-width: 300px; }
        
        /* Network Table */
        .network-grid { flex: 1; overflow: auto; position: relative; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 11px; }
        thead { position: sticky; top: 0; background: var(--header-bg); z-index: 1; box-shadow: 0 1px 0 var(--border-color); }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--border-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        th { font-weight: 600; color: #5f6368; user-select: none; }
        tr.network-row { cursor: pointer; }
        tr.network-row:hover { background: var(--row-hover); }
        tr.network-row.selected { background: var(--selected-row); }
        
        /* Status Colors */
        .status-2xx { color: #188038; }
        .status-4xx, .status-5xx { color: #d93025; }
        .status-0 { color: #888; font-style: italic; }

        /* Details Pane */
        .details-pane { height: 300px; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; background: #fff; font-size: 12px; }
        .details-tabs { display: flex; background: #f1f3f4; border-bottom: 1px solid var(--border-color); }
        .tab { padding: 8px 16px; cursor: pointer; color: #666; border-bottom: 2px solid transparent; }
        .tab.active { color: #1a73e8; border-bottom-color: #1a73e8; font-weight: 500; }
        .details-content { flex: 1; overflow: auto; padding: 10px; font-family: monospace; white-space: pre-wrap; word-break: break-all; }
        .json-view { color: #24292e; }

        /* Resizer (Optional implementation later) */
        
    </style>
</head>
<body>

<header>
    <div class="brand">
        <span class="recording-dot" id="rec-dot"></span>
        Tool Studio <span style="opacity:0.6; font-weight:normal; font-size:12px;">| Network Inspector</span>
    </div>
    <div>
        <button class="btn btn-light" onclick="window.location.href='dashboard.php'">Exit</button>
    </div>
</header>

<div class="toolbar">
    <button class="btn btn-danger" id="btn-record" onclick="toggleRecord()">⏺ Record</button>
    <button class="btn btn-light" onclick="clearLog()">⃠ Clear</button>
    
    <div style="width:1px; height:20px; background:#ddd; margin:0 5px;"></div>
    
    <input type="text" id="url-input" class="url-bar" value="https://www.jarir.com" placeholder="Enter URL...">
    <button class="btn btn-primary" onclick="browse()">Go</button>
    
    <div style="flex:1;"></div>
    
    <button class="btn btn-light" onclick="exportHAR()">📥 Export HAR</button>
    <button class="btn btn-success" id="btn-save" disabled onclick="saveTool()">💾 Save Tool</button>
</div>

<div class="main-container">
    <div class="split-view">
        <!-- Left: Browser -->
        <div class="browser-pane">
            <iframe id="browser-frame" src="about:blank"></iframe>
        </div>

        <!-- Right: Inspector -->
        <div class="inspector-pane">
            <div class="network-grid">
                <table id="network-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Status</th>
                            <th style="width: 60px;">Method</th>
                            <th>Name</th>
                            <th style="width: 70px;">Type</th>
                            <th style="width: 60px;">Size</th>
                            <th style="width: 60px;">Time</th>
                        </tr>
                    </thead>
                    <tbody id="network-tbody">
                        <!-- Rows -->
                    </tbody>
                </table>
            </div>
            
            <div class="details-pane" id="details-pane">
                <div class="details-tabs">
                    <div class="tab active" onclick="switchTab('headers')">Headers</div>
                    <div class="tab" onclick="switchTab('payload')">Payload</div>
                    <div class="tab" onclick="switchTab('response')">Response</div>
                </div>
                <div id="tab-content-headers" class="details-content">Select a request to view details.</div>
                <div id="tab-content-payload" class="details-content" style="display:none;"></div>
                <div id="tab-content-response" class="details-content" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<script>
    let isRecording = false;
    let recordedEvents = []; // Full raw events
    let selectedRequestIndex = -1;
    const browser = document.getElementById('browser-frame');
    const urlInput = document.getElementById('url-input');

    // Init
    // browse();

    function browse() {
        const url = urlInput.value;
        if (!url) return;
        browser.src = 'proxy.php?url=' + encodeURIComponent(url);
    }

    function toggleRecord() {
        isRecording = !isRecording;
        document.getElementById('rec-dot').style.display = isRecording ? 'block' : 'none';
        document.getElementById('btn-record').textContent = isRecording ? '⏹ Stop' : '⏺ Record';
        // Only enable save if we have items
        updateSaveButton();
    }
    
    function clearLog() {
        recordedEvents = [];
        document.getElementById('network-tbody').innerHTML = '';
        document.getElementById('tab-content-headers').textContent = '';
        document.getElementById('tab-content-payload').textContent = '';
        document.getElementById('tab-content-response').textContent = '';
        selectedRequestIndex = -1;
        updateSaveButton();
    }
    
    function updateSaveButton() {
        // We only save API calls for the "Tool", not images/resources
        const apiCount = recordedEvents.filter(e => e.type === 'fetch' || e.type === 'xhr').length;
        // Allows saving if at least 1 API call exists
        const btn = document.getElementById('btn-save');
        btn.disabled = !(isRecording === false && apiCount > 0);
    }

    // Message Listener
    window.addEventListener('message', function(e) {
        const msg = e.data;
        if (!msg || msg.source !== 'tool-studio-recorder') return;

        if (msg.type === 'recorder-ready') {
            console.log("Recorder Connected", msg.payload.url);
        }
        else if (msg.type === 'api-call') {
            addRequest(msg.payload);
        }
        else if (msg.type === 'debug') {
            // Optional: Log to console
            // console.debug("[Recorder]", msg.payload.msg, msg.payload.url);
        }
    });

    function addRequest(req) {
        recordedEvents.push(req);
        const index = recordedEvents.length - 1;
        
        const tr = document.createElement('tr');
        tr.className = 'network-row';
        tr.onclick = function() { selectRow(index, tr); };
        
        // Format Data
        const urlObj = new URL(req.url);
        const name = urlObj.pathname.split('/').pop() || urlObj.pathname;
        const shortName = name.length > 30 ? name.substring(0, 30)+'...' : name;
        
        const statusClass = req.status >= 500 ? 'status-5xx' : (req.status >= 400 ? 'status-4xx' : (req.status >= 200 ? 'status-2xx' : 'status-0'));
        const size = req.transferSize ? (req.transferSize / 1024).toFixed(1) + ' KB' : (req.responseBody ? (req.responseBody.length/1024).toFixed(1) + ' KB' : '-');
        const time = req.duration ? Math.round(req.duration) + ' ms' : '-';
        
        tr.innerHTML = `
            <td class="${statusClass}">${req.status || 'Pending'}</td>
            <td>${req.method}</td>
            <td title="${req.url}">${shortName}</td>
            <td>${req.type}</td>
            <td>${size}</td>
            <td>${time}</td>
        `;

        document.getElementById('network-tbody').appendChild(tr);
        tr.scrollIntoView({ behavior: 'smooth', block: 'end' });
        updateSaveButton();
    }

    function selectRow(index, trElement) {
        selectedRequestIndex = index;
        
        // Highlight UI
        const rows = document.querySelectorAll('.network-row');
        rows.forEach(r => r.classList.remove('selected'));
        trElement.classList.add('selected');
        
        const req = recordedEvents[index];
        renderDetails(req);
    }
    
    /* Details Pane Logic */
    function switchTab(tabName) {
        ['headers', 'payload', 'response'].forEach(t => {
            document.getElementById('tab-content-' + t).style.display = (t === tabName) ? 'block' : 'none';
        });
        document.querySelectorAll('.tab').forEach(t => {
            t.classList.toggle('active', t.innerText.toLowerCase() === tabName);
        });
    }

    function renderDetails(req) {
        // Headers
        const headersHtml = `
<strong>General</strong>
Request URL: ${req.url}
Request Method: ${req.method}
Status Code: ${req.status}

<strong>Response Headers</strong>
${req.responseHeaders ? JSON.stringify(req.responseHeaders, null, 2) : '(Not Captured)'}

<strong>Request Headers</strong>
${JSON.stringify(req.requestHeaders || {}, null, 2)}
`;
        document.getElementById('tab-content-headers').innerHTML = headersHtml.trim().replace(/\n/g, '<br>');

        // Payload
        const body = req.requestBody;
        let bodyStr = '';
        if (typeof body === 'string') {
            try { bodyStr = JSON.stringify(JSON.parse(body), null, 2); } catch(e) { bodyStr = body; }
        } else if (typeof body === 'object') {
            bodyStr = JSON.stringify(body, null, 2);
        }
        document.getElementById('tab-content-payload').textContent = bodyStr || '(No Payload)';

        // Response
        let resStr = '';
        const res = req.responseBody;
        if (typeof res === 'object') resStr = JSON.stringify(res, null, 2);
        else if (typeof res === 'string') {
            try { resStr = JSON.stringify(JSON.parse(res), null, 2); } catch(e) { resStr = res; }
        } else {
             resStr = String(res);
        }
        
        if (resStr.length > 50000) resStr = resStr.substring(0, 50000) + '... (Truncated)';
        document.getElementById('tab-content-response').textContent = resStr;
    }

    /* Save Tool Logic */
    function saveTool() {
        const name = prompt("Enter a name for this new tool:");
        if (!name) return;
        
        // Filter just the API calls
        const steps = recordedEvents.filter(e => e.type === 'fetch' || e.type === 'xhr');
        
        const btn = document.getElementById('btn-save');
        btn.textContent = 'Saving...';
        btn.disabled = true;

        fetch('tool_studio.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'save_tool',
                name: name,
                steps: steps
            })
        }).then(r => r.json()).then(data => {
            if (data.status === 'success') {
                alert("Tool Saved!");
                window.location.href = 'tool_studio.php';
            } else {
                alert("Error: " + data.error);
            }
        }).catch(e => alert(e)).finally(() => {
            btn.textContent = '💾 Save Tool';
            btn.disabled = false;
        });
    }

    /* Export HAR */
    function exportHAR() {
        if (recordedEvents.length === 0) return alert("Nothing to export");
        
        const har = {
            log: {
                version: "1.2",
                creator: { name: "Tool Studio", version: "1.0" },
                entries: recordedEvents.map(req => ({
                    startedDateTime: new Date().toISOString(), // Appr.
                    time: req.duration || 0,
                    request: {
                        method: req.method,
                        url: req.url,
                        headers: [], // Convert object to array if needed
                        postData: { mimeType: "application/json", text: JSON.stringify(req.requestBody) }
                    },
                    response: {
                        status: req.status || 0,
                        statusText: "",
                        headers: [],
                        content: {
                            mimeType: "app/json",
                            text: typeof req.responseBody === 'string' ? req.responseBody : JSON.stringify(req.responseBody)
                        }
                    }
                }))
            }
        };
        
        const blob = new Blob([JSON.stringify(har, null, 2)], {type: "application/json"});
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "tool_studio_log.har";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>
