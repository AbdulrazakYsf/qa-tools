<?php
/**
 * QA Automation Dashboard
 * 
 * A modular, scalable QA testing system for Jarir.com
 * 
 * Features:
 * - 13 embedded testing tools
 * - Run All Tests with proper async handling
 * - Configuration management
 * - Test run logging and reporting
 * - User management
 * 
 * @version 2.1.0 - Fixed multi-run support
 */

/*********************************
 * CONFIGURATION
 *********************************/

// Database Configuration
const QA_DB_HOST = 'sql309.infinityfree.com';
const QA_DB_PORT = 3306;
const QA_DB_NAME = 'if0_40372489_init_db';
const QA_DB_USER = 'if0_40372489';
const QA_DB_PASS = 'KmUb1Azwzo';

// Tool Definitions
$TOOL_DEFS = [
    ['code' => 'brand',           'name' => 'Brand Links'],
    ['code' => 'cms',             'name' => 'CMS Blocks'],
    ['code' => 'category',        'name' => 'Category Links'],
    ['code' => 'category_filter', 'name' => 'Filtered Category'],
    ['code' => 'getcategories',   'name' => 'Get Categories'],
    ['code' => 'images',          'name' => 'Images'],
    ['code' => 'login',           'name' => 'Login'],
    ['code' => 'price_checker',   'name' => 'Price Checker'],
    ['code' => 'products',        'name' => 'Products'],
    ['code' => 'sku',             'name' => 'SKU Lookup'],
    ['code' => 'stock',           'name' => 'Stock / Availability'],
    ['code' => 'sub_category',    'name' => 'Subcategories'],
    ['code' => 'add_to_cart',     'name' => 'Add to Cart'],
];

/*********************************
 * LOAD TOOL HTML FILES
 *********************************/

$TOOLS_HTML = [];
$toolsDir = __DIR__ . '/tools/';

foreach ($TOOL_DEFS as $tool) {
    $code = $tool['code'];
    $filePath = $toolsDir . $code . '.html';
    
    if (file_exists($filePath)) {
        $TOOLS_HTML[$code] = file_get_contents($filePath);
    } else {
        $TOOLS_HTML[$code] = '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:20px;text-align:center;">
            <h2>Tool Not Found</h2>
            <p>The tool file <strong>' . htmlspecialchars($code) . '.html</strong> was not found.</p>
        </body></html>';
    }
}

/*********************************
 * DATABASE (MySQL, auto-init)
 *********************************/

function qa_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = 'mysql:host=' . QA_DB_HOST . ';port=' . QA_DB_PORT . ';dbname=' . QA_DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, QA_DB_USER, QA_DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("CREATE TABLE IF NOT EXISTS qa_tool_configs (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          tool_code VARCHAR(64) NOT NULL,
          config_name VARCHAR(191) NOT NULL,
          config_json MEDIUMTEXT NOT NULL,
          is_enabled TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS qa_test_runs (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          run_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          status VARCHAR(32) NOT NULL,
          total_tests INT NOT NULL DEFAULT 0,
          passed INT NOT NULL DEFAULT 0,
          failed INT NOT NULL DEFAULT 0,
          open_issues INT NOT NULL DEFAULT 0,
          notes TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS qa_run_results (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          run_id INT UNSIGNED NOT NULL,
          tool_code VARCHAR(64) NOT NULL,
          status VARCHAR(32) NOT NULL,
          url TEXT,
          parent TEXT,
          payload MEDIUMTEXT,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_run_tool (run_id, tool_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS qa_users (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(191) NOT NULL,
          email VARCHAR(191) NOT NULL,
          role VARCHAR(32) NOT NULL DEFAULT 'tester',
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    return $pdo;
}

/********************
 * SIMPLE JSON API
 ********************/

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['api'];
    $db = qa_db();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    try {
        switch ($action) {
            case 'list-configs':
                $stmt = $db->query("SELECT * FROM qa_tool_configs ORDER BY created_at DESC");
                echo json_encode($stmt->fetchAll());
                break;

            case 'save-config':
                $id = $input['id'] ?? null;
                $tool_code = $input['tool_code'] ?? '';
                $config_name = $input['config_name'] ?? '';
                $cfg = $input['config'] ?? [];
                $is_enabled = !empty($input['is_enabled']) ? 1 : 0;
                if (!$tool_code || !$config_name) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing tool_code or config_name']);
                    break;
                }
                $cfgJson = json_encode($cfg, JSON_UNESCAPED_UNICODE);
                if ($id) {
                    $stmt = $db->prepare("UPDATE qa_tool_configs SET tool_code=?, config_name=?, config_json=?, is_enabled=? WHERE id=?");
                    $stmt->execute([$tool_code, $config_name, $cfgJson, $is_enabled, $id]);
                } else {
                    $stmt = $db->prepare("INSERT INTO qa_tool_configs (tool_code, config_name, config_json, is_enabled) VALUES (?,?,?,?)");
                    $stmt->execute([$tool_code, $config_name, $cfgJson, $is_enabled]);
                    $id = $db->lastInsertId();
                }
                echo json_encode(['ok' => true, 'id' => $id]);
                break;

            case 'delete-config':
                if (!empty($input['id'])) {
                    $stmt = $db->prepare("DELETE FROM qa_tool_configs WHERE id=?");
                    $stmt->execute([$input['id']]);
                }
                echo json_encode(['ok' => true]);
                break;

            case 'list-users':
                $stmt = $db->query("SELECT * FROM qa_users ORDER BY created_at DESC");
                echo json_encode($stmt->fetchAll());
                break;

            case 'save-user':
                $id = $input['id'] ?? null;
                $name = $input['name'] ?? '';
                $email = $input['email'] ?? '';
                $role = $input['role'] ?? 'tester';
                $is_active = !empty($input['is_active']) ? 1 : 0;
                if (!$name || !$email) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing name or email']);
                    break;
                }
                if ($id) {
                    $stmt = $db->prepare("UPDATE qa_users SET name=?, email=?, role=?, is_active=? WHERE id=?");
                    $stmt->execute([$name, $email, $role, $is_active, $id]);
                } else {
                    $stmt = $db->prepare("INSERT INTO qa_users (name, email, role, is_active) VALUES (?,?,?,?)");
                    $stmt->execute([$name, $email, $role, $is_active]);
                    $id = $db->lastInsertId();
                }
                echo json_encode(['ok' => true, 'id' => $id]);
                break;

            case 'delete-user':
                if (!empty($input['id'])) {
                    $stmt = $db->prepare("DELETE FROM qa_users WHERE id=?");
                    $stmt->execute([$input['id']]);
                }
                echo json_encode(['ok' => true]);
                break;

            case 'list-runs':
                $stmt = $db->query("SELECT * FROM qa_test_runs ORDER BY run_date DESC LIMIT 50");
                echo json_encode($stmt->fetchAll());
                break;

            case 'run-details':
                $runId = $input['id'] ?? null;
                if (!$runId) { echo json_encode([]); break; }
                $stmt = $db->prepare("SELECT tool_code, status, url, parent, payload, created_at FROM qa_run_results WHERE run_id=? ORDER BY tool_code, status, url");
                $stmt->execute([$runId]);
                echo json_encode($stmt->fetchAll());
                break;

            case 'save-run':
                $id = $input['id'] ?? null;
                $status = $input['status'] ?? 'completed';
                $total = (int)($input['total_tests'] ?? 0);
                $passed = (int)($input['passed'] ?? 0);
                $failed = (int)($input['failed'] ?? 0);
                $open = (int)($input['open_issues'] ?? 0);
                $notes = $input['notes'] ?? '';
                $details = $input['details'] ?? null;

                if ($id) {
                    $stmt = $db->prepare("UPDATE qa_test_runs SET status=?, total_tests=?, passed=?, failed=?, open_issues=?, notes=? WHERE id=?");
                    $stmt->execute([$status, $total, $passed, $failed, $open, $notes, $id]);
                    if (is_array($details)) {
                        $del = $db->prepare("DELETE FROM qa_run_results WHERE run_id=?");
                        $del->execute([$id]);
                    }
                } else {
                    $stmt = $db->prepare("INSERT INTO qa_test_runs (status, total_tests, passed, failed, open_issues, notes) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$status, $total, $passed, $failed, $open, $notes]);
                    $id = $db->lastInsertId();
                }

                if (is_array($details)) {
                    $ins = $db->prepare("INSERT INTO qa_run_results (run_id, tool_code, status, url, parent, payload) VALUES (?,?,?,?,?,?)");
                    foreach ($details as $toolBlock) {
                        if (empty($toolBlock['tool_code']) || empty($toolBlock['rows']) || !is_array($toolBlock['rows'])) continue;
                        $toolCode = $toolBlock['tool_code'];
                        foreach ($toolBlock['rows'] as $row) {
                            $st = $row['status'] ?? '';
                            $url = $row['url'] ?? '';
                            $par = $row['parent'] ?? '';
                            $raw = isset($row['payload']) ? $row['payload'] : json_encode($row);
                            $ins->execute([$id, $toolCode, $st, $url, $par, $raw]);
                        }
                    }
                }
                echo json_encode(['ok' => true, 'id' => $id]);
                break;

            case 'delete-run':
                if (!empty($input['id'])) {
                    $stmt = $db->prepare("DELETE FROM qa_run_results WHERE run_id=?");
                    $stmt->execute([$input['id']]);
                    $stmt = $db->prepare("DELETE FROM qa_test_runs WHERE id=?");
                    $stmt->execute([$input['id']]);
                }
                echo json_encode(['ok' => true]);
                break;

            case 'stats':
                $total = (int)$db->query("SELECT COUNT(*) AS c FROM qa_test_runs")->fetch()['c'];
                $passed = (int)$db->query("SELECT COUNT(*) AS c FROM qa_test_runs WHERE status='passed'")->fetch()['c'];
                $failed = (int)$db->query("SELECT COUNT(*) AS c FROM qa_test_runs WHERE status='failed'")->fetch()['c'];
                $open = (int)$db->query("SELECT COALESCE(SUM(open_issues),0) AS s FROM qa_test_runs")->fetch()['s'];
                echo json_encode(['total_runs' => $total, 'passed_runs' => $passed, 'failed_runs' => $failed, 'open_issues' => $open]);
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Unknown api']);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>QA Automation Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{--bg:#f4f7fa;--card:#fff;--radius:12px;--shadow:0 4px 12px rgba(0,0,0,.08);--blue:#1E88E5;--green:#43A047;--red:#E53935;--amber:#FB8C00;--muted:#607D8B;--border:#dde3ec;}
*{box-sizing:border-box;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
body{margin:0;background:var(--bg);color:#263238;}
.app-shell{max-width:1200px;margin:0 auto;padding:20px 16px 40px;}
.app-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;}
.app-header h1{font-size:24px;margin:0;color:#1a3a57;}
.app-header small{color:var(--muted);}
.tabs{display:flex;gap:8px;margin-bottom:16px;}
.tab-btn{padding:8px 14px;border-radius:999px;border:1px solid transparent;background:transparent;cursor:pointer;font-size:14px;color:var(--muted);}
.tab-btn.active{background:#e3f2fd;border-color:#90caf9;color:#0d47a1;}
.tab-content{display:none;}
.tab-content.active{display:block;}
.section-card{background:var(--card);border-radius:var(--radius);padding:20px 22px;box-shadow:var(--shadow);margin-bottom:16px;}
.section-header{margin-bottom:14px;}
.section-header h2{margin:0;font-size:18px;color:#37474f;}
.section-header small{color:var(--muted);font-size:13px;}
.card-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:16px;}
.stat-card{background:var(--card);border-radius:var(--radius);padding:16px;box-shadow:var(--shadow);text-align:center;}
.stat-card h3{margin:0 0 6px;font-size:13px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
.stat-value{font-size:32px;font-weight:700;margin-bottom:4px;}
.stat-meta{font-size:12px;color:var(--muted);}
.stat-total .stat-value{color:var(--blue);}
.stat-pass .stat-value{color:var(--green);}
.stat-fail .stat-value{color:var(--red);}
.stat-open .stat-value{color:var(--amber);}
.dashboard-grid{display:grid;grid-template-columns:1fr 2fr;gap:16px;}
@media(max-width:900px){.dashboard-grid{grid-template-columns:1fr;}}
.modules-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;}
.module-tile{background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:12px;cursor:pointer;transition:.2s;}
.module-tile:hover{border-color:var(--blue);background:#e3f2fd;}
.module-tile.active{border-color:var(--blue);background:#e3f2fd;}
.module-title{font-weight:600;font-size:14px;color:#37474f;}
.module-meta{font-size:11px;color:var(--muted);margin-top:2px;}
.tool-runner{display:flex;flex-direction:column;height:100%;}
.tool-runner-controls{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:#f8fafc;border-radius:8px 8px 0 0;border:1px solid var(--border);border-bottom:none;}
.tool-container{flex:1;min-height:500px;border:1px solid var(--border);border-radius:0 0 8px 8px;overflow:hidden;}
.tool-iframe{width:100%;height:100%;border:none;min-height:500px;}
.btn-primary{background:var(--blue);color:#fff;border:none;padding:8px 14px;border-radius:6px;cursor:pointer;font-size:13px;}
.btn-primary:hover{background:#1565c0;}
.btn-primary:disabled{background:#90caf9;cursor:not-allowed;}
.btn-ghost{background:transparent;border:1px solid var(--border);padding:8px 14px;border-radius:6px;cursor:pointer;font-size:13px;color:#455a64;}
.btn-ghost:hover{background:#eceff1;}
.btn-ghost:disabled{opacity:0.5;cursor:not-allowed;}
.table{width:100%;border-collapse:collapse;font-size:13px;}
.table th,.table td{padding:10px 12px;text-align:left;border-bottom:1px solid var(--border);}
.table th{background:#f8fafc;font-weight:600;color:#455a64;}
.table-actions{white-space:nowrap;}
.table-actions button{margin-right:6px;padding:4px 8px;font-size:11px;border-radius:4px;cursor:pointer;border:1px solid #ddd;background:#fff;}
.table-actions button:hover{background:#f0f0f0;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;}
.form-field label{display:block;font-size:13px;font-weight:600;color:#455a64;margin-bottom:4px;}
.form-field input,.form-field select,.form-field textarea{width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:14px;}
.form-field textarea{min-height:100px;resize:vertical;}
.actions-row{display:flex;align-items:center;gap:12px;margin-top:16px;}
.log-form{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;}
.log-form .form-field{flex:0 0 auto;}
.log-form .form-field input,.log-form .form-field select{width:100px;}
.charts-section{margin-bottom:16px;}
.charts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;}
.chart-card{background:#f8fafc;border-radius:8px;padding:14px;border:1px solid var(--border);}
.chart-card h3{margin:0 0 10px;font-size:14px;color:#455a64;}
.chart-card canvas{max-height:180px;}
.text-muted{color:var(--muted);font-size:13px;}
.checkbox-row{display:flex;gap:16px;}
.checkbox-row label{display:flex;align-items:center;gap:6px;font-size:13px;}
.run-status-indicator{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;}
.run-status-indicator.passed{background:var(--green);}
.run-status-indicator.failed{background:var(--red);}
.run-status-indicator.partial{background:var(--amber);}
.run-progress{margin-top:10px;padding:10px;background:#f8fafc;border-radius:6px;border:1px solid var(--border);}
.run-progress-text{font-size:13px;color:#455a64;}
.run-progress-bar{height:6px;background:#e0e0e0;border-radius:3px;margin-top:6px;overflow:hidden;}
.run-progress-fill{height:100%;background:var(--blue);transition:width 0.3s;}
#run-details-panel{display:none;margin-top:16px;padding:14px;background:#f8fafc;border-radius:8px;border:1px solid var(--border);}
#run-details-panel h3{margin:0 0 10px;font-size:14px;}
#run-details-content{max-height:300px;overflow:auto;}
</style>
</head>
<body>
<div class="app-shell">
  <header class="app-header">
    <div><h1>QA Automation Dashboard</h1><small>Jarir.com Testing Suite v2.1</small></div>
  </header>
  <div class="tabs">
    <button class="tab-btn active" data-tab="dashboard">Dashboard</button>
    <button class="tab-btn" data-tab="configs">Configurations</button>
    <button class="tab-btn" data-tab="users">Users</button>
  </div>
  <section id="tab-dashboard" class="tab-content active">
    <div class="section-card charts-section">
      <div class="section-header"><h2>Run Insights</h2><small>Overview of all saved test runs</small></div>
      <div class="charts-grid">
        <div class="chart-card"><h3>Pass vs Fail (Tests)</h3><canvas id="chart-pass-fail"></canvas></div>
        <div class="chart-card"><h3>Runs by Status</h3><canvas id="chart-run-status"></canvas></div>
        <div class="chart-card"><h3>Recent Pass Rate</h3><canvas id="chart-pass-trend"></canvas></div>
      </div>
    </div>
    <div class="card-row">
      <div class="stat-card stat-total"><h3>Total Test Runs</h3><div class="stat-value" id="stat-total">0</div><div class="stat-meta">All time</div></div>
      <div class="stat-card stat-pass"><h3>Passed</h3><div class="stat-value" id="stat-passed">0</div><div class="stat-meta">Runs marked as passed</div></div>
      <div class="stat-card stat-fail"><h3>Failed</h3><div class="stat-value" id="stat-failed">0</div><div class="stat-meta">Runs marked as failed</div></div>
      <div class="stat-card stat-open"><h3>Open Issues</h3><div class="stat-value" id="stat-open">0</div><div class="stat-meta">Total open issues</div></div>
    </div>
    <div class="dashboard-grid">
      <div class="section-card">
        <div class="section-header"><h2>Test Modules</h2><small>Click to open, check to include in Run All</small></div>
        <div class="modules-grid" id="modules-grid"></div>
      </div>
      <div class="section-card">
        <div class="tool-runner">
          <div class="tool-runner-controls">
            <div><strong id="active-tool-name">No tool selected</strong><div class="text-muted" id="active-tool-config-info">Choose a module on the left.</div></div>
            <div style="display:flex;gap:8px;">
              <button class="btn-ghost" id="btn-run-selected">Run Selected Tool</button>
              <button class="btn-primary" id="btn-run-all">Run All Tests</button>
            </div>
          </div>
          <div id="run-progress" class="run-progress" style="display:none;">
            <div class="run-progress-text" id="run-progress-text">Initializing...</div>
            <div class="run-progress-bar"><div class="run-progress-fill" id="run-progress-fill" style="width:0%"></div></div>
          </div>
          <div class="tool-container"><iframe id="tool-iframe" class="tool-iframe"></iframe></div>
        </div>
      </div>
    </div>
    <div class="section-card">
      <div class="section-header"><h2>Log Test Run</h2><small>Manual summary after running tools</small></div>
      <div class="log-form">
        <div class="form-field"><label>Status</label><select id="run-status"><option value="passed">Passed</option><option value="failed">Failed</option><option value="partial">Partial</option></select></div>
        <div class="form-field"><label>Total</label><input type="number" id="run-total" value="0"></div>
        <div class="form-field"><label>Passed</label><input type="number" id="run-passed" value="0"></div>
        <div class="form-field"><label>Failed</label><input type="number" id="run-failed" value="0"></div>
        <div class="form-field"><label>Open</label><input type="number" id="run-open" value="0"></div>
        <div class="form-field"><label>Notes</label><input type="text" id="run-notes" placeholder="Optional notes"></div>
        <button class="btn-primary" id="run-save-btn">Save Run</button>
      </div>
      <table class="table" id="runs-table" style="margin-top:16px;"><thead><tr><th>#</th><th>Date</th><th>Status</th><th>Total</th><th>Passed</th><th>Failed</th><th>Open</th><th>Notes</th><th>Actions</th><th>Report</th></tr></thead><tbody></tbody></table>
      <div id="run-details-panel"><div style="display:flex;justify-content:space-between;align-items:center;"><h3>Run Details</h3><button class="btn-ghost" id="run-details-close">Close</button></div><div id="run-details-content"></div></div>
    </div>
  </section>
  <section id="tab-configs" class="tab-content">
    <div class="section-card">
      <div class="section-header"><h2>Create / Edit Configuration</h2><small>Configurations are applied when using "Run All Tests"</small></div>
      <form id="config-form">
        <input type="hidden" id="cfg-id">
        <div class="form-grid">
          <div class="form-field"><label>Configuration Name</label><input type="text" id="cfg-name" placeholder="e.g., Daily Brand Link Check"></div>
          <div class="form-field"><label>Tool</label><select id="cfg-tool-code"><?php foreach ($TOOL_DEFS as $t): ?><option value="<?php echo htmlspecialchars($t['code'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-grid" style="margin-top:14px;"><div class="form-field" style="grid-column:1 / -1;"><label>Target URLs / JSON / Inputs</label><textarea id="cfg-inputs" placeholder="Paste any inputs required for the selected tool"></textarea></div></div>
        <div class="actions-row">
          <label style="font-size:13px;display:flex;align-items:center;gap:4px;"><input type="checkbox" id="cfg-enabled" checked> Enable this configuration</label>
          <button type="button" class="btn-primary" id="cfg-save-btn">Save Configuration</button>
          <button type="button" class="btn-ghost" id="cfg-reset-btn">Reset</button>
        </div>
      </form>
    </div>
    <div class="section-card" style="margin-top:16px;">
      <div class="section-header"><h2>Existing Configurations</h2></div>
      <table class="table" id="configs-table"><thead><tr><th>#</th><th>Name</th><th>Tool</th><th>Enabled</th><th>Snippet</th><th>Actions</th></tr></thead><tbody></tbody></table>
    </div>
  </section>
  <section id="tab-users" class="tab-content">
    <div class="section-card">
      <div class="section-header"><h2>User Management</h2><small>Metadata only</small></div>
      <form id="user-form">
        <input type="hidden" id="user-id">
        <div class="form-grid">
          <div class="form-field"><label>Name</label><input type="text" id="user-name" placeholder="Tester name"></div>
          <div class="form-field"><label>Email</label><input type="email" id="user-email" placeholder="tester@jarir.com"></div>
          <div class="form-field"><label>Role</label><select id="user-role"><option value="tester">Tester</option><option value="admin">Admin</option><option value="viewer">Viewer</option></select></div>
          <div class="form-field"><label>Status</label><div class="checkbox-row"><label><input type="checkbox" id="user-active" checked> Active</label></div></div>
        </div>
        <div class="actions-row"><button type="button" class="btn-primary" id="user-save-btn">Save User</button><button type="button" class="btn-ghost" id="user-reset-btn">Reset</button></div>
      </form>
    </div>
    <div class="section-card" style="margin-top:16px;"><div class="section-header"><h2>Existing Users</h2></div><table class="table" id="users-table"><thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead><tbody></tbody></table></div>
  </section>
</div>

<script>
const TOOL_DEFS = <?php echo json_encode($TOOL_DEFS, JSON_UNESCAPED_UNICODE); ?>;
const TOOL_HTML = <?php echo json_encode($TOOLS_HTML, JSON_UNESCAPED_UNICODE); ?>;

let ACTIVE_TOOL = null;
let CONFIGS = [];
let USERS = [];
let RUNS = [];
let isRunningAll = false;
let currentLoadHandler = null;
let chartPassFail = null, chartRunStatus = null, chartPassTrend = null;

async function api(action, payload) {
  const res = await fetch('?api=' + encodeURIComponent(action), {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload || {})});
  if (!res.ok) throw new Error('API ' + action + ' failed');
  return res.json();
}

document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
  });
});

function updateChartsFromRuns() {
  let totalPassed = 0, totalFailed = 0, runsPassed = 0, runsFailed = 0;
  RUNS.forEach(r => {
    totalPassed += parseInt(r.passed) || 0;
    totalFailed += parseInt(r.failed) || 0;
    if ((r.status || '').toLowerCase() === 'passed') runsPassed++; else runsFailed++;
  });
  const pf = document.getElementById('chart-pass-fail');
  if (pf) {
    if (chartPassFail) chartPassFail.destroy();
    chartPassFail = new Chart(pf.getContext('2d'), {type: 'doughnut', data: {labels: ['Passed', 'Failed'], datasets: [{data: [totalPassed, totalFailed], backgroundColor: ['#43A047', '#E53935']}]}, options: {maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}}});
  }
  const rs = document.getElementById('chart-run-status');
  if (rs) {
    if (chartRunStatus) chartRunStatus.destroy();
    chartRunStatus = new Chart(rs.getContext('2d'), {type: 'pie', data: {labels: ['Passed Runs', 'Failed Runs'], datasets: [{data: [runsPassed, runsFailed], backgroundColor: ['#43A047', '#E53935']}]}, options: {maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}}});
  }
  const pt = document.getElementById('chart-pass-trend');
  if (pt) {
    if (chartPassTrend) chartPassTrend.destroy();
    const recent = RUNS.slice(0, 10).reverse();
    const labels = recent.map((r, i) => 'Run ' + (i + 1));
    const rates = recent.map(r => {const t = (parseInt(r.passed) || 0) + (parseInt(r.failed) || 0); return t > 0 ? Math.round((parseInt(r.passed) || 0) / t * 100) : 0;});
    chartPassTrend = new Chart(pt.getContext('2d'), {type: 'line', data: {labels: labels, datasets: [{label: 'Pass Rate %', data: rates, borderColor: '#1E88E5', tension: 0.3, fill: false}]}, options: {maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {min: 0, max: 100}}}});
  }
}

const modulesGrid = document.getElementById('modules-grid');
const iframe = document.getElementById('tool-iframe');

TOOL_DEFS.forEach(t => {
  const div = document.createElement('div');
  div.className = 'module-tile';
  div.dataset.code = t.code;
  div.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center;gap:6px;"><div><div class="module-title">${t.name}</div><div class="module-meta">Tool: ${t.code}</div></div><label style="display:flex;align-items:center;gap:4px;font-size:11px;color:#607D8B;" onclick="event.stopPropagation();"><input type="checkbox" class="module-run-checkbox" data-code="${t.code}"><span>Run</span></label></div>`;
  div.addEventListener('click', (ev) => { if (ev.target.closest('input[type="checkbox"]')) return; selectModule(t.code); });
  modulesGrid.appendChild(div);
});

function loadToolIntoIframe(code, onLoadCallback) {
  // CRITICAL: Remove any existing load handler first to prevent duplicate calls
  if (currentLoadHandler) {
    iframe.removeEventListener('load', currentLoadHandler);
    currentLoadHandler = null;
  }
  
  const html = TOOL_HTML[code];
  if (!html) {
    iframe.srcdoc = `<html><body style="font-family:sans-serif;padding:20px;text-align:center;"><h2>Tool Not Found</h2><p>No HTML for tool: <strong>${code}</strong></p></body></html>`;
    if (onLoadCallback) setTimeout(() => onLoadCallback(null), 100);
    return;
  }
  
  if (onLoadCallback) {
    currentLoadHandler = function loadHandler() {
      iframe.removeEventListener('load', loadHandler);
      currentLoadHandler = null;
      onLoadCallback(iframe);
    };
    iframe.addEventListener('load', currentLoadHandler);
  }
  
  iframe.srcdoc = html;
}

function selectModule(code) {
  ACTIVE_TOOL = code;
  document.querySelectorAll('.module-tile').forEach(m => m.classList.toggle('active', m.dataset.code === code));
  const def = TOOL_DEFS.find(t => t.code === code);
  document.getElementById('active-tool-name').textContent = def ? def.name : code;
  const cfg = CONFIGS.find(c => c.tool_code === code && c.is_enabled == 1) || CONFIGS.find(c => c.tool_code === code);
  document.getElementById('active-tool-config-info').textContent = cfg ? `Using config: ${cfg.config_name}` : 'No saved config for this tool.';
  loadToolIntoIframe(code);
}

function parseConfigObject(cfg) {
  if (!cfg || !cfg.config_json) return {};
  try { const obj = JSON.parse(cfg.config_json || '{}'); return obj && typeof obj === 'object' ? obj : {}; }
  catch (e) { console.error('Invalid config_json', e); return {}; }
}

function applyConfigToTool(doc, code, cfgObj) {
  const inputs = (cfgObj.inputs || '').toString();
  const mappings = {'brand':'urlInput','cms':'urlInput','category':'urlInput','sku':'urlInput','stock':'urlInput','getcategories':'urls','images':'urls','products':'urls','sub_category':'urls','category_filter':'urls','price_checker':'cmsInput','login':'bulk','add_to_cart':'skus'};
  const inputId = mappings[code];
  if (inputId) { const el = doc.getElementById(inputId); if (el) el.value = inputs; }
  if (code === 'add_to_cart' && cfgObj.qty) { const qtyEl = doc.getElementById('qty'); if (qtyEl) qtyEl.value = cfgObj.qty; }
}

function resetToolState(w) {
  try {
    if (typeof w.clearSession === 'function') w.clearSession();
    else if (typeof w.clearResults === 'function') w.clearResults();
    if (Array.isArray(w.rows)) w.rows.length = 0;
    if (w.processed instanceof Set) w.processed.clear();
    if (w.generated instanceof Set) w.generated.clear();
    const resultsEl = w.document.getElementById('results'); if (resultsEl) resultsEl.innerHTML = '';
    const loadingEl = w.document.getElementById('loading'); if (loadingEl) loadingEl.style.display = 'none';
  } catch (e) { console.warn('Could not reset tool state:', e); }
}

function runToolWithConfig(code, cfg) {
  return new Promise((resolve) => {
    const cfgObj = parseConfigObject(cfg);
    const timeout = 120000;
    let finished = false;
    let timeoutId = null;
    let pollIntervalId = null;

    function cleanup() {
      finished = true;
      if (timeoutId) { clearTimeout(timeoutId); timeoutId = null; }
      if (pollIntervalId) { clearInterval(pollIntervalId); pollIntervalId = null; }
    }

    function finishWithResult(result) {
      if (finished) return;
      cleanup();
      resolve(result);
    }

    timeoutId = setTimeout(() => {
      finishWithResult({tests: 1, passed: 0, failed: 1, open: 1, rows: [{status: 'TIMEOUT', url: '', parent: code, message: 'Tool timed out'}]});
    }, timeout);

    loadToolIntoIframe(code, function(iframeEl) {
      if (finished) return;
      if (!iframeEl) {
        finishWithResult({tests: 0, passed: 0, failed: 1, open: 1, rows: [{status: 'FAIL', url: '', parent: code, message: 'Tool not found'}]});
        return;
      }

      try {
        const w = iframeEl.contentWindow;
        const doc = iframeEl.contentDocument || w.document;
        
        resetToolState(w);
        applyConfigToTool(doc, code, cfgObj);

        let runFn = w.run || w.Run || w.start || w.execute;
        if (!runFn && code === 'cms' && typeof w.startCrawling === 'function') runFn = w.startCrawling;
        
        if (typeof runFn !== 'function') {
          finishWithResult({tests: 0, passed: 0, failed: 1, open: 1, rows: [{status: 'FAIL', url: '', parent: code, message: 'No run function found'}]});
          return;
        }

        let validationFailed = false;
        w.alert = (msg) => {
          console.log(`Tool ${code} alert:`, msg);
          if (msg && (msg.includes('Please enter at least one') || msg.includes('Please select') || msg.includes('Please paste'))) {
            validationFailed = true;
            finishWithResult({tests: 0, passed: 0, failed: 1, open: 1, rows: [{status: 'FAIL', url: '', parent: code, message: `Validation: ${msg}`}]});
          }
        };

        let pollCount = 0;
        const maxPolls = 120;

        function checkResults() {
          if (finished || validationFailed) return;
          pollCount++;

          try {
            const loadingEl = doc.getElementById('loading');
            const isLoading = loadingEl && loadingEl.style.display !== 'none';
            const resultsEl = doc.getElementById('results');
            const items = resultsEl ? resultsEl.querySelectorAll('li') : [];
            const hasResults = items.length > 0;
            const loadingDone = !isLoading || !loadingEl;

            if (loadingDone && (hasResults || pollCount > 15)) {
              let tests = 0, passed = 0, failed = 0, open = 0;
              const rows = [];

              items.forEach(li => {
                tests++;
                const statusChip = li.querySelector('.chip, .status-badge');
                let status = 'UNKNOWN';
                if (statusChip) {
                  const text = statusChip.textContent.toUpperCase();
                  const classes = statusChip.className;
                  if (classes.includes('ok') || text.includes('OK') || text.includes('PASS')) { status = 'OK'; passed++; }
                  else if (classes.includes('warn') || text.includes('WARN')) { status = 'WARN'; open++; }
                  else { status = 'FAIL'; failed++; }
                }
                const linkEl = li.querySelector('a');
                const url = linkEl ? linkEl.href : (li.textContent || '').substring(0, 200).trim();
                rows.push({status, url, parent: code});
              });

              if (tests === 0) { tests = 1; passed = 1; rows.push({status: 'OK', url: 'No items to test', parent: code}); }
              finishWithResult({tests, passed, failed, open, rows});
              return;
            }

            if (pollCount >= maxPolls) {
              finishWithResult({tests: 1, passed: 0, failed: 1, open: 1, rows: [{status: 'TIMEOUT', url: '', parent: code, message: 'Polling timeout'}]});
            }
          } catch (e) { console.error('Error checking results:', e); }
        }

        pollIntervalId = setInterval(checkResults, 1000);

        try {
          console.log(`Executing run for ${code}`);
          const result = runFn.call(w);
          if (result && typeof result.then === 'function') {
            result.then(() => console.log(`Tool ${code} promise resolved`)).catch(e => console.error(`Tool ${code} error:`, e));
          }
        } catch (e) { console.error('Error calling run:', e); }

      } catch (e) {
        console.error('Error in tool setup:', e);
        finishWithResult({tests: 0, passed: 0, failed: 1, open: 1, rows: [{status: 'FAIL', url: '', parent: code, message: `Setup error: ${e.message}`}]});
      }
    });
  });
}

document.getElementById('btn-run-selected').addEventListener('click', async () => {
  if (!ACTIVE_TOOL) { alert('Please select a tool first.'); return; }
  const cfg = CONFIGS.find(c => c.tool_code === ACTIVE_TOOL && c.is_enabled == 1) || CONFIGS.find(c => c.tool_code === ACTIVE_TOOL);
  if (!cfg) { alert('No configuration found. Please add one or enter inputs manually.'); return; }
  const btn = document.getElementById('btn-run-selected');
  btn.disabled = true; btn.textContent = 'Running...';
  try {
    const result = await runToolWithConfig(ACTIVE_TOOL, cfg);
    alert(`Tool "${ACTIVE_TOOL}" completed:\nTests: ${result.tests}\nPassed: ${result.passed}\nFailed: ${result.failed}\nOpen: ${result.open}`);
  } catch (e) { console.error(e); alert('Error: ' + e.message); }
  finally { btn.disabled = false; btn.textContent = 'Run Selected Tool'; }
});

document.getElementById('btn-run-all').addEventListener('click', async () => {
  if (isRunningAll) { alert('Tests are already running.'); return; }
  await loadConfigs();
  const selectedCodes = [...document.querySelectorAll('.module-run-checkbox:checked')].map(cb => cb.dataset.code);
  if (!selectedCodes.length) { alert('Please select at least one module using the checkboxes.'); return; }
  
  const missing = [], plan = [];
  selectedCodes.forEach(code => {
    let cfg = CONFIGS.find(c => c.tool_code === code && c.is_enabled == 1) || CONFIGS.find(c => c.tool_code === code);
    if (!cfg) missing.push(code); else plan.push({code, cfg});
  });
  if (missing.length) { alert('Missing configuration for: ' + missing.join(', ')); return; }

  isRunningAll = true;
  const btn = document.getElementById('btn-run-all');
  btn.disabled = true; btn.textContent = 'Running...';
  const progressDiv = document.getElementById('run-progress');
  const progressText = document.getElementById('run-progress-text');
  const progressFill = document.getElementById('run-progress-fill');
  progressDiv.style.display = 'block'; progressFill.style.width = '0%';

  let totalTests = 0, totalPassed = 0, totalFailed = 0, totalOpen = 0;
  const allDetails = [];

  for (let i = 0; i < plan.length; i++) {
    const item = plan[i];
    progressFill.style.width = Math.round((i / plan.length) * 100) + '%';
    progressText.textContent = `Running ${item.code} (${i + 1}/${plan.length})...`;

    try {
      console.log(`=== Starting: ${item.code} ===`);
      const result = await runToolWithConfig(item.code, item.cfg);
      console.log(`=== Completed: ${item.code} ===`, result);
      totalTests += result.tests || 0;
      totalPassed += result.passed || 0;
      totalFailed += result.failed || 0;
      totalOpen += result.open || 0;
      allDetails.push({tool_code: item.code, rows: result.rows || []});
      await new Promise(r => setTimeout(r, 1000));
    } catch (e) {
      console.error('Error running', item.code, e);
      totalFailed += 1; totalOpen += 1;
      allDetails.push({tool_code: item.code, rows: [{status: 'FAIL', url: '', parent: item.code, message: `Error: ${e.message}`}]});
    }
  }

  progressFill.style.width = '100%'; progressText.textContent = 'Saving results...';
  const status = totalFailed > 0 ? 'failed' : 'passed';

  try {
    await api('save-run', {status, total_tests: totalTests, passed: totalPassed, failed: totalFailed, open_issues: totalOpen, notes: 'Run All: ' + selectedCodes.join(', '), details: allDetails});
    await Promise.all([loadRuns(), loadStats()]);
    alert(`Run All completed!\nTotal: ${totalTests}\nPassed: ${totalPassed}\nFailed: ${totalFailed}\nOpen: ${totalOpen}`);
  } catch (e) { console.error(e); alert('Run completed but saving failed: ' + e.message); }
  finally {
    isRunningAll = false; btn.disabled = false; btn.textContent = 'Run All Tests';
    progressDiv.style.display = 'none'; progressFill.style.width = '0%';
  }
});

async function loadConfigs() {
  CONFIGS = await api('list-configs');
  const tbody = document.querySelector('#configs-table tbody'); tbody.innerHTML = '';
  CONFIGS.forEach((c, idx) => {
    const obj = parseConfigObject(c);
    const snippet = (obj.inputs || '').toString().split('\n')[0].slice(0, 80);
    const tr = document.createElement('tr'); tr.dataset.id = c.id;
    tr.innerHTML = `<td>${idx + 1}</td><td>${c.config_name}</td><td>${c.tool_code}</td><td>${c.is_enabled ? 'Yes' : 'No'}</td><td>${snippet}</td><td class="table-actions"><button data-action="edit">Edit</button><button data-action="delete">Delete</button></td>`;
    tbody.appendChild(tr);
  });
}

async function loadRuns() {
  RUNS = await api('list-runs');
  updateChartsFromRuns();
  const tbody = document.querySelector('#runs-table tbody'); tbody.innerHTML = '';
  RUNS.forEach(r => {
    const tr = document.createElement('tr'); tr.dataset.id = r.id;
    tr.innerHTML = `<td>${r.id}</td><td>${r.run_date}</td><td><span class="run-status-indicator ${r.status}"></span>${r.status}</td><td>${r.total_tests}</td><td>${r.passed}</td><td>${r.failed}</td><td>${r.open_issues}</td><td>${r.notes || ''}</td><td class="table-actions"><button data-action="details">Details</button><button data-action="delete">Delete</button></td><td><a href="qa_run_report.php?run_id=${r.id}" target="_blank" class="btn-ghost" style="padding:4px 8px;font-size:11px;">Report</a></td>`;
    tbody.appendChild(tr);
  });
}

async function loadUsers() {
  USERS = await api('list-users');
  const tbody = document.querySelector('#users-table tbody'); tbody.innerHTML = '';
  USERS.forEach((u, idx) => {
    const tr = document.createElement('tr'); tr.dataset.id = u.id;
    tr.innerHTML = `<td>${idx + 1}</td><td>${u.name}</td><td>${u.email}</td><td>${u.role}</td><td>${u.is_active ? 'Active' : 'Inactive'}</td><td class="table-actions"><button data-action="edit">Edit</button><button data-action="delete">Delete</button></td>`;
    tbody.appendChild(tr);
  });
}

async function loadStats() {
  const s = await api('stats');
  document.getElementById('stat-total').textContent = s.total_runs || 0;
  document.getElementById('stat-passed').textContent = s.passed_runs || 0;
  document.getElementById('stat-failed').textContent = s.failed_runs || 0;
  document.getElementById('stat-open').textContent = s.open_issues || 0;
}

document.getElementById('cfg-save-btn').addEventListener('click', async () => {
  const id = document.getElementById('cfg-id').value || null;
  const name = document.getElementById('cfg-name').value.trim();
  const tool = document.getElementById('cfg-tool-code').value;
  const inputs = document.getElementById('cfg-inputs').value;
  const enabled = document.getElementById('cfg-enabled').checked;
  if (!name) { alert('Please enter a configuration name.'); return; }
  try {
    await api('save-config', {id: id ? parseInt(id) : null, config_name: name, tool_code: tool, config: {inputs}, is_enabled: enabled});
    await loadConfigs();
    document.getElementById('cfg-id').value = '';
    document.getElementById('cfg-name').value = '';
    document.getElementById('cfg-inputs').value = '';
    document.getElementById('cfg-enabled').checked = true;
    alert('Configuration saved!');
  } catch (e) { alert('Error: ' + e.message); }
});

document.getElementById('cfg-reset-btn').addEventListener('click', () => {
  document.getElementById('cfg-id').value = '';
  document.getElementById('cfg-name').value = '';
  document.getElementById('cfg-inputs').value = '';
  document.getElementById('cfg-enabled').checked = true;
});

document.querySelector('#configs-table tbody').addEventListener('click', async (e) => {
  const btn = e.target.closest('button'); if (!btn) return;
  const tr = btn.closest('tr'); const id = tr.dataset.id; const action = btn.dataset.action;
  if (action === 'edit') {
    const cfg = CONFIGS.find(c => c.id == id);
    if (cfg) {
      const obj = parseConfigObject(cfg);
      document.getElementById('cfg-id').value = cfg.id;
      document.getElementById('cfg-name').value = cfg.config_name;
      document.getElementById('cfg-tool-code').value = cfg.tool_code;
      document.getElementById('cfg-inputs').value = obj.inputs || '';
      document.getElementById('cfg-enabled').checked = cfg.is_enabled == 1;
    }
  } else if (action === 'delete') {
    if (confirm('Delete this configuration?')) { await api('delete-config', {id: parseInt(id)}); await loadConfigs(); }
  }
});

document.getElementById('user-save-btn').addEventListener('click', async () => {
  const id = document.getElementById('user-id').value || null;
  const name = document.getElementById('user-name').value.trim();
  const email = document.getElementById('user-email').value.trim();
  const role = document.getElementById('user-role').value;
  const active = document.getElementById('user-active').checked;
  if (!name || !email) { alert('Please enter name and email.'); return; }
  try {
    await api('save-user', {id: id ? parseInt(id) : null, name, email, role, is_active: active});
    await loadUsers();
    document.getElementById('user-id').value = '';
    document.getElementById('user-name').value = '';
    document.getElementById('user-email').value = '';
    document.getElementById('user-active').checked = true;
    alert('User saved!');
  } catch (e) { alert('Error: ' + e.message); }
});

document.getElementById('user-reset-btn').addEventListener('click', () => {
  document.getElementById('user-id').value = '';
  document.getElementById('user-name').value = '';
  document.getElementById('user-email').value = '';
  document.getElementById('user-active').checked = true;
});

document.querySelector('#users-table tbody').addEventListener('click', async (e) => {
  const btn = e.target.closest('button'); if (!btn) return;
  const tr = btn.closest('tr'); const id = tr.dataset.id; const action = btn.dataset.action;
  if (action === 'edit') {
    const user = USERS.find(u => u.id == id);
    if (user) {
      document.getElementById('user-id').value = user.id;
      document.getElementById('user-name').value = user.name;
      document.getElementById('user-email').value = user.email;
      document.getElementById('user-role').value = user.role;
      document.getElementById('user-active').checked = user.is_active == 1;
    }
  } else if (action === 'delete') {
    if (confirm('Delete this user?')) { await api('delete-user', {id: parseInt(id)}); await loadUsers(); }
  }
});

document.getElementById('run-save-btn').addEventListener('click', async () => {
  const status = document.getElementById('run-status').value;
  const total = parseInt(document.getElementById('run-total').value) || 0;
  const passed = parseInt(document.getElementById('run-passed').value) || 0;
  const failed = parseInt(document.getElementById('run-failed').value) || 0;
  const open = parseInt(document.getElementById('run-open').value) || 0;
  const notes = document.getElementById('run-notes').value;
  try {
    await api('save-run', {status, total_tests: total, passed, failed, open_issues: open, notes});
    await Promise.all([loadRuns(), loadStats()]);
    document.getElementById('run-total').value = '0';
    document.getElementById('run-passed').value = '0';
    document.getElementById('run-failed').value = '0';
    document.getElementById('run-open').value = '0';
    document.getElementById('run-notes').value = '';
    alert('Run saved!');
  } catch (e) { alert('Error: ' + e.message); }
});

document.querySelector('#runs-table tbody').addEventListener('click', async (e) => {
  const btn = e.target.closest('button'); if (!btn) return;
  const tr = btn.closest('tr'); const id = tr.dataset.id; const action = btn.dataset.action;
  if (action === 'details') { await showRunDetails(id); }
  else if (action === 'delete') {
    if (confirm('Delete this run?')) { await api('delete-run', {id: parseInt(id)}); await Promise.all([loadRuns(), loadStats()]); }
  }
});

async function showRunDetails(runId) {
  const panel = document.getElementById('run-details-panel');
  const container = document.getElementById('run-details-content');
  container.innerHTML = '<p class="text-muted">Loading...</p>';
  panel.style.display = 'block';
  try {
    const rows = await api('run-details', {id: parseInt(runId)});
    if (!Array.isArray(rows) || !rows.length) { container.innerHTML = '<p class="text-muted">No details.</p>'; return; }
    let html = '<table class="table"><thead><tr><th>Tool</th><th>Status</th><th>URL</th><th>Parent</th></tr></thead><tbody>';
    rows.forEach(r => { html += `<tr><td>${r.tool_code || ''}</td><td>${r.status || ''}</td><td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;">${r.url || ''}</td><td>${r.parent || ''}</td></tr>`; });
    html += '</tbody></table>';
    container.innerHTML = html;
  } catch (e) { container.innerHTML = '<p class="text-muted">Error: ' + e.message + '</p>'; }
}

document.getElementById('run-details-close').addEventListener('click', () => { document.getElementById('run-details-panel').style.display = 'none'; });

(async function init() {
  try {
    await Promise.all([loadConfigs(), loadRuns(), loadUsers(), loadStats()]);
    if (TOOL_DEFS.length) selectModule(TOOL_DEFS[0].code);
  } catch (e) { console.error('Init error:', e); }
})();
</script>
</body>
</html>
