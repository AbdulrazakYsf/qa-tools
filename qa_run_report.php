<?php
/**
 * QA Run Report Generator
 * 
 * Generates a detailed PDF-style report for a specific test run
 * 
 * @version 2.0.0
 */

// Auth & DB
require_once 'auth_session.php';
require_login();

// Roles allowed: Admin, Tester, Viewer (All logged in users essentially)
// No role check needed beyond login unless we want to restrict Viewers? 
// User said "Testers, Admin and Viewers have access", so require_login is sufficient.

function qa_db()
{
    return get_db_auth();
}

// Get run ID or Filters
$runId = isset($_GET['run_id']) ? (int) $_GET['run_id'] : 0;
$filterUser = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : ''; // passed, failed, or empty for all

if (!$runId && !$filterUser && !$filterStatus) {
    die('Run ID or Filters (user_id/status) required.');
}

try {
    $db = qa_db();
    $reportTitle = "QA Run Report";
    $reportSubtitle = "Details";
    $run = [];
    $details = [];

    if ($runId) {
        // SINGLE RUN MODE
        $stmt = $db->prepare("SELECT * FROM qa_test_runs WHERE id = ?");
        $stmt->execute([$runId]);
        $run = $stmt->fetch();

        if (!$run)
            die('Run not found.');

        $reportTitle = "QA Test Run Report #$runId";
        $reportSubtitle = date('M j, Y H:i', strtotime($run['run_date']));

        $stmt = $db->prepare("SELECT * FROM qa_run_results WHERE run_id = ? ORDER BY tool_code, status, url");
        $stmt->execute([$runId]);
        $details = $stmt->fetchAll();

    } else {
        // AGGREGATE MODE
        // 1. Build Run Query
        $sqlRuns = "SELECT * FROM qa_test_runs WHERE 1=1";
        $paramsRuns = [];

        if ($filterUser) {
            $sqlRuns .= " AND user_id = ?";
            $paramsRuns[] = $filterUser;
        }

        if ($filterStatus === 'failed') {
            $sqlRuns .= " AND status = 'failed'";
        } elseif ($filterStatus === 'passed') {
            $sqlRuns .= " AND status = 'passed'";
        }

        $stmt = $db->prepare($sqlRuns);
        $stmt->execute($paramsRuns);
        $runs = $stmt->fetchAll();

        if (empty($runs)) {
            $run = [
                'total_tests' => 0,
                'passed' => 0,
                'failed' => 0,
                'open_issues' => 0,
                'status' => 'aggregated',
                'run_date' => date('Y-m-d H:i:s'),
                'notes' => 'No runs found matching filters.'
            ];
        } else {
            // Aggregation Calculation
            $agg = ['total_tests' => 0, 'passed' => 0, 'failed' => 0, 'open_issues' => 0];
            $runIds = [];
            foreach ($runs as $r) {
                $agg['total_tests'] += $r['total_tests'];
                $agg['passed'] += $r['passed'];
                $agg['failed'] += $r['failed'];
                $agg['open_issues'] += $r['open_issues'];
                $runIds[] = $r['id'];
            }
            $run = array_merge($agg, [
                'status' => 'aggregated',
                'run_date' => date('Y-m-d H:i:s'),
                'notes' => 'Aggregated report for ' . count($runIds) . ' run(s).'
            ]);

            // 2. Fetch Details (Only if we have runs)
            if (!empty($runIds)) {
                $inQuery = implode(',', array_fill(0, count($runIds), '?'));
                $sqlDetails = "SELECT * FROM qa_run_results WHERE run_id IN ($inQuery)";

                // If filtering by failed status, show only critical items
                if ($filterStatus === 'failed') {
                    $sqlDetails .= " AND status NOT IN ('OK','PASS','PASSED','VALID','SUCCESS','IN STOCK')";
                } elseif ($filterStatus === 'passed') {
                    $sqlDetails .= " AND status IN ('OK','PASS','PASSED','VALID','SUCCESS','IN STOCK')";
                }

                $sqlDetails .= " ORDER BY tool_code, status, url";
                $stmt = $db->prepare($sqlDetails);
                $stmt->execute($runIds);
                $details = $stmt->fetchAll();
            }
        }

        $reportTitle = "Aggregated QA Report";
        $reportSubtitle = $filterUser ? "User Filter Active" : "All Users";
        if ($filterStatus)
            $reportSubtitle .= " | " . ucfirst($filterStatus);
    }

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
    <title><?php echo htmlspecialchars($reportTitle); ?></title>
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
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
            background: rgba(255, 255, 255, 0.15);
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

        .summary-card.passed .value {
            color: var(--green);
        }

        .summary-card.failed .value {
            color: var(--red);
        }

        .summary-card.open .value {
            color: var(--amber);
        }

        .summary-card.rate .value {
            color: var(--blue);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-badge.passed {
            background: #e8f5e9;
            color: var(--green);
        }

        .status-badge.failed {
            background: #ffebee;
            color: var(--red);
        }

        .status-badge.partial {
            background: #fff3e0;
            color: var(--amber);
        }

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

        .tool-stats .passed {
            background: #e8f5e9;
            color: var(--green);
        }

        .tool-stats .failed {
            background: #ffebee;
            color: var(--red);
        }

        .tool-stats .warn {
            background: #fff3e0;
            color: var(--amber);
        }

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

        .mini-badge.ok {
            background: var(--green);
            color: #fff;
        }

        .mini-badge.warn {
            background: var(--amber);
            color: #fff;
        }

        .mini-badge.fail {
            background: var(--red);
            color: #fff;
        }

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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .print-btn:hover {
            background: #1565c0;
        }

        @media print {

            .print-btn,
            .controls-bar {
                display: none;
            }

            body {
                padding: 0;
                background: #fff;
            }

            .report {
                box-shadow: none;
            }
        }

        .controls-bar {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px;
            background: #f1f5f9;
            border-radius: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #455a64;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            background: #fff;
            min-width: 140px;
        }

        .btn-export {
            margin-left: auto;
            background: #43A047;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-export:hover {
            background: #2e7d32;
        }
    </style>
</head>

<body>

    <button class="print-btn" onclick="window.print()">Print Report</button>

    <div class="report">
        <div class="report-header">
            <h1><?php echo htmlspecialchars($reportTitle); ?></h1>
            <div class="subtitle"><?php echo htmlspecialchars($reportSubtitle); ?></div>

            <div class="report-meta">
                <?php if ($runId): ?>
                    <div class="meta-item">
                        <label>Run ID</label>
                        <div class="value">#<?php echo $runId; ?></div>
                    </div>
                <?php endif; ?>
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
                    <div class="controls-bar">
                        <div class="filter-group">
                            <label>Filter by Tool:</label>
                            <select id="toolFilter" onchange="applyFilters()">
                                <option value="all">All Tools</option>
                                <?php foreach (array_keys($byTool) as $code): ?>
                                    <option value="<?php echo htmlspecialchars($code); ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $code))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Filter by Status:</label>
                            <select id="statusFilter" onchange="applyFilters()">
                                <option value="all">All Statuses</option>
                                <option value="passed">Passed</option>
                                <option value="failed">Failed</option>
                                <option value="warning">Warning</option>
                            </select>
                        </div>
                        <button class="btn-export" onclick="exportToCsv()">
                            <span>Download CSV</span>
                        </button>
                    </div>

                    <h2>Results by Tool</h2>

                    <?php foreach ($byTool as $toolCode => $toolData): ?>
                        <div class="tool-section" data-tool="<?php echo htmlspecialchars($toolCode); ?>">
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
                                            $status = strtoupper(trim($row['status']));
                                            $badgeClass = 'fail';
                                            if ($status === 'OK' || $status === 'PASS' || $status === 'PASSED' || $status === 'VALID' || $status === 'SUCCESS' || $status === 'IN STOCK')
                                                $badgeClass = 'ok';
                                            elseif ($status === 'WARN' || $status === 'WARNING')
                                                $badgeClass = 'warn';
                                            ?>
                                            <tr>
                                                <td class="status-cell">
                                                    <span
                                                        class="mini-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
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

    <script>
        function applyFilters() {
            const tool = document.getElementById('toolFilter').value;
            const status = document.getElementById('statusFilter').value;

            document.querySelectorAll('.tool-section').forEach(section => {
                const sectionTool = section.dataset.tool;
                const toolMatch = (tool === 'all' || tool === sectionTool);

                let visibleRows = 0;

                // Filter rows within the section
                section.querySelectorAll('tbody tr').forEach(row => {
                    const badge = row.querySelector('.mini-badge');
                    let rowStatus = 'all';

                    if (badge.classList.contains('ok')) rowStatus = 'passed';
                    else if (badge.classList.contains('fail')) rowStatus = 'failed';
                    else if (badge.classList.contains('warn')) rowStatus = 'warning';

                    // Match logic
                    const statusMatch = (status === 'all' || status === rowStatus);

                    if (statusMatch) {
                        row.style.display = '';
                        visibleRows++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Show section only if tool matches AND it has visible rows
                if (toolMatch && visibleRows > 0) {
                    section.style.display = '';
                } else {
                    section.style.display = 'none';
                }
            });
        }

        function exportToCsv() {
            const runId = "<?php echo $runId; ?>";
            let csv = [];
            csv.push(['Tool', 'Status', 'URL / Item', 'Parent / Source', 'Date']);

            document.querySelectorAll('.tool-section').forEach(section => {
                if (section.style.display === 'none') return; // Skip hidden sections

                const toolName = section.querySelector('h3').innerText;

                section.querySelectorAll('tbody tr').forEach(row => {
                    if (row.style.display === 'none') return; // Skip hidden rows

                    const status = row.querySelector('.mini-badge').innerText;
                    const url = row.querySelector('.url-cell').innerText;
                    const parent = row.querySelectorAll('td')[2].innerText;
                    const date = "<?php echo date('Y-m-d H:i', strtotime($run['run_date'])); ?>";

                    // CSV Escape
                    const rowData = [toolName, status, url, parent, date].map(v => {
                        const q = v.replace(/"/g, '""');
                        return `"${q}"`;
                    });
                    csv.push(rowData.join(','));
                });
            });

            const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `Run_${runId}_Report.csv`;
            link.click();
        }
    </script>
    </div>

</body>

</html>