<?php
/**
 * QA Run Report Generator
 * 
 * Generates a detailed PDF-style report for a specific test run
 * 
 * @version 2.0.0
 */

// Database Configuration (same as dashboard)
const QA_DB_HOST = 'sql309.infinityfree.com';
const QA_DB_PORT = 3306;
const QA_DB_NAME = 'if0_40372489_init_db';
const QA_DB_USER = 'if0_40372489';
const QA_DB_PASS = 'KmUb1Azwzo';

function qa_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = 'mysql:host=' . QA_DB_HOST . ';port=' . QA_DB_PORT . ';dbname=' . QA_DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, QA_DB_USER, QA_DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

// Get run ID from query parameter
$runId = isset($_GET['run_id']) ? (int)$_GET['run_id'] : 0;

if (!$runId) {
    die('Run ID required. Usage: qa_run_report.php?run_id=123');
}

try {
    $db = qa_db();
    
    // Get run summary
    $stmt = $db->prepare("SELECT * FROM qa_test_runs WHERE id = ?");
    $stmt->execute([$runId]);
    $run = $stmt->fetch();
    
    if (!$run) {
        die('Run not found.');
    }
    
    // Get run details
    $stmt = $db->prepare("SELECT * FROM qa_run_results WHERE run_id = ? ORDER BY tool_code, status, url");
    $stmt->execute([$runId]);
    $details = $stmt->fetchAll();
    
    // Group details by tool
    $byTool = [];
    foreach ($details as $row) {
        $code = $row['tool_code'] ?: 'unknown';
        if (!isset($byTool[$code])) {
            $byTool[$code] = ['passed' => 0, 'failed' => 0, 'warn' => 0, 'rows' => []];
        }
        $byTool[$code]['rows'][] = $row;
        $status = strtoupper($row['status']);
        if ($status === 'OK' || $status === 'PASS' || $status === 'PASSED' || $status === 'VALID' || $status === 'SUCCESS' || $status === 'IN STOCK') {
            $byTool[$code]['passed']++;
        } elseif ($status === 'WARN' || $status === 'WARNING') {
            $byTool[$code]['warn']++;
        } else {
            $byTool[$code]['failed']++;
        }
    }
    
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}

// Calculate pass rate
$totalTests = $run['passed'] + $run['failed'];
$passRate = $totalTests > 0 ? round(($run['passed'] / $totalTests) * 100, 1) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QA Test Run Report #<?php echo $runId; ?></title>
<style>
:root {
    --blue: #1E88E5;
    --green: #43A047;
    --red: #E53935;
    --amber: #FB8C00;
    --bg: #f4f7fa;
    --card: #fff;
}
* {
    box-sizing: border-box;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
body {
    margin: 0;
    padding: 20px;
    background: var(--bg);
    color: #263238;
}
.report {
    max-width: 1000px;
    margin: 0 auto;
    background: var(--card);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    overflow: hidden;
}
.report-header {
    background: linear-gradient(135deg, #1a3a57 0%, #1E88E5 100%);
    color: #fff;
    padding: 30px 40px;
}
.report-header h1 {
    margin: 0 0 8px;
    font-size: 28px;
}
.report-header .subtitle {
    opacity: 0.9;
    font-size: 14px;
}
.report-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 20px;
}
.meta-item {
    background: rgba(255,255,255,0.15);
    border-radius: 8px;
    padding: 12px 20px;
}
.meta-item label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.8;
    margin-bottom: 4px;
}
.meta-item .value {
    font-size: 20px;
    font-weight: 700;
}
.report-body {
    padding: 30px 40px;
}
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}
.summary-card {
    background: #f8fafc;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    border: 1px solid #e2e8f0;
}
.summary-card h3 {
    margin: 0 0 8px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #607D8B;
}
.summary-card .value {
    font-size: 32px;
    font-weight: 700;
}
.summary-card.passed .value { color: var(--green); }
.summary-card.failed .value { color: var(--red); }
.summary-card.open .value { color: var(--amber); }
.summary-card.rate .value { color: var(--blue); }
.status-badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    text-transform: capitalize;
}
.status-badge.passed { background: #e8f5e9; color: var(--green); }
.status-badge.failed { background: #ffebee; color: var(--red); }
.status-badge.partial { background: #fff3e0; color: var(--amber); }
.section {
    margin-top: 30px;
}
.section h2 {
    font-size: 18px;
    color: #37474f;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 10px;
    margin: 0 0 16px;
}
.tool-section {
    margin-bottom: 24px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.tool-header {
    background: #e8edf2;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.tool-header h3 {
    margin: 0;
    font-size: 15px;
    color: #37474f;
}
.tool-stats {
    display: flex;
    gap: 12px;
    font-size: 12px;
}
.tool-stats span {
    padding: 3px 8px;
    border-radius: 12px;
}
.tool-stats .passed { background: #e8f5e9; color: var(--green); }
.tool-stats .failed { background: #ffebee; color: var(--red); }
.tool-stats .warn { background: #fff3e0; color: var(--amber); }
.detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.detail-table th,
.detail-table td {
    padding: 10px 16px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}
.detail-table th {
    background: #f1f5f9;
    font-weight: 600;
    color: #455a64;
}
.detail-table tr:last-child td {
    border-bottom: none;
}
.detail-table .status-cell {
    width: 80px;
}
.mini-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}
.mini-badge.ok { background: var(--green); color: #fff; }
.mini-badge.warn { background: var(--amber); color: #fff; }
.mini-badge.fail { background: var(--red); color: #fff; }
.url-cell {
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-family: monospace;
    font-size: 12px;
}
.notes-section {
    background: #fff9c4;
    border-radius: 8px;
    padding: 16px;
    margin-top: 20px;
}
.notes-section h3 {
    margin: 0 0 8px;
    font-size: 14px;
    color: #f57f17;
}
.report-footer {
    background: #f1f5f9;
    padding: 20px 40px;
    text-align: center;
    font-size: 12px;
    color: #607D8B;
}
.print-btn {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--blue);
    color: #fff;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.print-btn:hover {
    background: #1565c0;
}
@media print {
    .print-btn { display: none; }
    body { padding: 0; background: #fff; }
    .report { box-shadow: none; }
}
</style>
</head>
<body>

<button class="print-btn" onclick="window.print()">Print Report</button>

<div class="report">
    <div class="report-header">
        <h1>QA Test Run Report</h1>
        <div class="subtitle">Jarir.com Automated Testing Suite</div>
        
        <div class="report-meta">
            <div class="meta-item">
                <label>Run ID</label>
                <div class="value">#<?php echo $runId; ?></div>
            </div>
            <div class="meta-item">
                <label>Date</label>
                <div class="value"><?php echo date('M j, Y', strtotime($run['run_date'])); ?></div>
            </div>
            <div class="meta-item">
                <label>Time</label>
                <div class="value"><?php echo date('H:i', strtotime($run['run_date'])); ?></div>
            </div>
            <div class="meta-item">
                <label>Status</label>
                <div class="value">
                    <span class="status-badge <?php echo strtolower($run['status']); ?>">
                        <?php echo ucfirst($run['status']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="report-body">
        <div class="summary-grid">
            <div class="summary-card">
                <h3>Total Tests</h3>
                <div class="value"><?php echo $run['total_tests']; ?></div>
            </div>
            <div class="summary-card passed">
                <h3>Passed</h3>
                <div class="value"><?php echo $run['passed']; ?></div>
            </div>
            <div class="summary-card failed">
                <h3>Failed</h3>
                <div class="value"><?php echo $run['failed']; ?></div>
            </div>
            <div class="summary-card open">
                <h3>Open Issues</h3>
                <div class="value"><?php echo $run['open_issues']; ?></div>
            </div>
            <div class="summary-card rate">
                <h3>Pass Rate</h3>
                <div class="value"><?php echo $passRate; ?>%</div>
            </div>
        </div>
        
        <?php if ($run['notes']): ?>
        <div class="notes-section">
            <h3>Run Notes</h3>
            <p><?php echo htmlspecialchars($run['notes']); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($byTool)): ?>
        <div class="section">
            <h2>Results by Tool</h2>
            
            <?php foreach ($byTool as $toolCode => $toolData): ?>
            <div class="tool-section">
                <div class="tool-header">
                    <h3><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $toolCode))); ?></h3>
                    <div class="tool-stats">
                        <?php if ($toolData['passed'] > 0): ?>
                        <span class="passed"><?php echo $toolData['passed']; ?> passed</span>
                        <?php endif; ?>
                        <?php if ($toolData['failed'] > 0): ?>
                        <span class="failed"><?php echo $toolData['failed']; ?> failed</span>
                        <?php endif; ?>
                        <?php if ($toolData['warn'] > 0): ?>
                        <span class="warn"><?php echo $toolData['warn']; ?> warnings</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($toolData['rows'])): ?>
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th class="status-cell">Status</th>
                            <th>URL / Item</th>
                            <th>Parent / Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($toolData['rows'] as $row): 
                            $status = strtoupper($row['status']);
                            $badgeClass = 'fail';
                            if ($status === 'OK' || $status === 'PASS' || $status === 'PASSED' || $status === 'VALID' || $status === 'SUCCESS' || $status === 'IN STOCK') $badgeClass = 'ok';
                            elseif ($status === 'WARN' || $status === 'WARNING') $badgeClass = 'warn';
                        ?>
                        <tr>
                            <td class="status-cell">
                                <span class="mini-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                            </td>
                            <td class="url-cell" title="<?php echo htmlspecialchars($row['url']); ?>">
                                <?php echo htmlspecialchars($row['url'] ?: '-'); ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['parent'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="padding: 16px; color: #607D8B;">No detailed results recorded.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="section">
            <h2>Results</h2>
            <p style="color: #607D8B;">No detailed results were recorded for this run.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="report-footer">
        <p>Generated by QA Automation Dashboard v2.0 | Jarir.com</p>
        <p>Report generated on <?php echo date('F j, Y \a\t H:i:s'); ?></p>
    </div>
</div>

</body>
</html>
