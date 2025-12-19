<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'auth_session.php';
require_once 'tool_runners.php';
require_login();
$currentUser = current_user();

/********************
 * 1. API HANDLING (Must be before any HTML)
 ********************/
if (isset($_GET['api'])) {
  // Clean buffer just in case
  while (ob_get_level())
    ob_end_clean();
  header('Content-Type: application/json; charset=utf-8');

  $action = $_GET['api'];
  $db = get_db_auth();

  // Handle File Upload separately (Multipart)
  if ($action === 'upload-avatar') {
    if (!isset($_FILES['avatar'])) {
      http_response_code(400);
      echo json_encode(['error' => 'No file uploaded']);
      exit;
    }

    $file = $_FILES['avatar'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid file type']);
      exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
      http_response_code(400);
      echo json_encode(['error' => 'File too large (Max 5MB)']);
      exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'user_' . current_user()['id'] . '_' . time() . '.' . $ext;
    $targetDir = 'uploads/';
    if (!is_dir($targetDir))
      mkdir($targetDir, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
      echo json_encode(['ok' => true, 'url' => $targetDir . $filename]);
    } else {
      http_response_code(500);
      echo json_encode(['error' => 'Failed to move uploaded file']);
    }
    exit;
  }

  $input = json_decode(file_get_contents('php://input'), true) ?? [];

  try {
    switch ($action) {
      /* Configs */
      case 'list-configs':
        $user = current_user();
        $uid = $user['id'];
        $role = $user['role'];

        $sql = "SELECT c.*, u.name as user_name 
                FROM qa_tool_configs c 
                LEFT JOIN qa_users u ON c.user_id = u.id ";

        $params = [];
        if ($role !== 'admin') {
          // Testers see: their own configs OR global configs
          $sql .= " WHERE c.user_id=? OR c.user_id IS NULL ";
          $params[] = $uid;
        }
        // Admin sees ALL (no WHERE clause needed implies all)

        // Order: Own config first (if owner), then Global, then others. 
        // For admin, maybe just Date DESC? Or maybe group by Tool? 
        // Let's stick to created_at for Admin, but for tester prioritize their own.
        if ($role !== 'admin') {
          $sql .= " ORDER BY (c.user_id IS NOT NULL) DESC, c.created_at DESC";
        } else {
          $sql .= " ORDER BY c.tool_code ASC, c.created_at DESC";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

      case 'save-config':
        require_role(['admin', 'tester']);
        $id = $input['id'] ?? null;
        $tool_code = $input['tool_code'] ?? '';
        $config_name = $input['config_name'] ?? '';
        $cfg = $input['config'] ?? [];
        $is_enabled = !empty($input['is_enabled']) ? 1 : 0;
        $currentUser = current_user();
        $currUid = $currentUser['id'];

        if (!$tool_code || !$config_name) {
          http_response_code(400);
          echo json_encode(['error' => 'Missing tool_code or config_name']);
          break;
        }
        $cfgJson = json_encode($cfg, JSON_UNESCAPED_UNICODE);

        // Determine Owner
        // Default: Current User
        $ownerId = $currUid;

        // If Admin, check if they provided an owner_id (null for Global, or specific ID)
        if ($currentUser['role'] === 'admin') {
          if (array_key_exists('owner_id', $input)) {
            $ownerId = $input['owner_id']; // Can be null (Global) or numeric
          }
        }

        if ($id) {
          // Fetch existing to check permissions
          $existing = $db->prepare("SELECT user_id FROM qa_tool_configs WHERE id=?");
          $existing->execute([$id]);
          $row = $existing->fetch();

          if (!$row) {
            echo json_encode(['error' => 'Config not found']);
            break;
          }

          $prevOwner = $row['user_id'];

          // Permission Check:
          // Admin can edit anything.
          // Tester can ONLY edit their own.
          if ($currentUser['role'] !== 'admin' && $prevOwner != $currUid) {
            http_response_code(403);
            echo json_encode(['error' => 'Access Denied: You can only edit your own configurations.']);
            break;
          }

          // If Tester is saving, FORCE owner to remain themselves (cannot steal ownership or make global)
          if ($currentUser['role'] !== 'admin') {
            $ownerId = $prevOwner; // Keep original owner (which we verified is them)
          }

          $stmt = $db->prepare("UPDATE qa_tool_configs SET tool_code=?, config_name=?, config_json=?, is_enabled=?, user_id=? WHERE id=?");
          $stmt->execute([$tool_code, $config_name, $cfgJson, $is_enabled, $ownerId, $id]);

        } else {
          // INSERT
          $stmt = $db->prepare("INSERT INTO qa_tool_configs (tool_code, config_name, config_json, is_enabled, user_id) VALUES (?,?,?,?,?)");
          $stmt->execute([$tool_code, $config_name, $cfgJson, $is_enabled, $ownerId]);
          $id = $db->lastInsertId();
        }
        echo json_encode(['ok' => true, 'id' => $id]);
        break;

      case 'delete-config':
        require_role(['admin', 'tester']);
        if (!empty($input['id'])) {
          $id = $input['id'];
          // Check Perms
          $user = current_user();
          if ($user['role'] !== 'admin') {
            $chk = $db->prepare("SELECT user_id FROM qa_tool_configs WHERE id=?");
            $chk->execute([$id]);
            $c = $chk->fetch();
            if (!$c || $c['user_id'] != $user['id']) {
              http_response_code(403);
              echo json_encode(['error' => 'Access Denied']);
              break;
            }
          }
          $stmt = $db->prepare("DELETE FROM qa_tool_configs WHERE id=?");
          $stmt->execute([$id]);
        }
        echo json_encode(['ok' => true]);
        break;

      /* Users */
      case 'list-users':
        $stmt = $db->query("SELECT * FROM qa_users ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll());
        break;

      case 'save-user':
        require_role(['admin']);
        $id = $input['id'] ?? null;
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'tester';
        $is_active = !empty($input['is_active']) ? 1 : 0;

        if (!$name || !$email) {
          http_response_code(400);
          echo json_encode(['error' => 'Missing name or email']);
          break;
        }
        if ($id) {
          if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE qa_users SET name=?, email=?, password_hash=?, role=?, is_active=? WHERE id=?");
            $stmt->execute([$name, $email, $hash, $role, $is_active, $id]);
          } else {
            $stmt = $db->prepare("UPDATE qa_users SET name=?, email=?, role=?, is_active=? WHERE id=?");
            $stmt->execute([$name, $email, $role, $is_active, $id]);
          }
        } else {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $stmt = $db->prepare("INSERT INTO qa_users (name, email, password_hash, role, is_active) VALUES (?,?,?,?,?)");
          $stmt->execute([$name, $email, $hash, $role, $is_active]);
          $id = $db->lastInsertId();
        }
        echo json_encode(['ok' => true, 'id' => $id]);
        break;

      case 'delete-user':
        require_role(['admin']);
        if (!empty($input['id'])) {
          $stmt = $db->prepare("DELETE FROM qa_users WHERE id=?");
          $stmt->execute([$input['id']]);
        }
        echo json_encode(['ok' => true]);
        break;

      /* Profile */
      case 'update-profile':
        $user = current_user();
        $id = $user['id'];
        if (!$id) {
          http_response_code(401);
          echo json_encode(['error' => 'Not logged in']);
          break;
        }

        $name = $input['name'] ?? '';
        $password = $input['password'] ?? '';
        $avatar = $input['avatar_url'] ?? '';

        if (!$name) {
          http_response_code(400);
          echo json_encode(['error' => 'Name is required']);
          break;
        }

        if ($password) {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $stmt = $db->prepare("UPDATE qa_users SET name=?, avatar_url=?, password_hash=? WHERE id=?");
          $stmt->execute([$name, $avatar, $hash, $id]);
        } else {
          $stmt = $db->prepare("UPDATE qa_users SET name=?, avatar_url=? WHERE id=?");
          $stmt->execute([$name, $avatar, $id]);
        }

        $_SESSION['user_name'] = $name;
        echo json_encode(['ok' => true]);
        break;

      case 'assign-global-config':
        require_role(['admin']);
        $configId = $input['config_id'] ?? null;
        if (!$configId) {
          echo json_encode(['error' => 'Missing config ID']);
          break;
        }

        // Verify config exists
        $stmt = $db->prepare("SELECT id, user_id FROM qa_tool_configs WHERE id=?");
        $stmt->execute([$configId]);
        $cfg = $stmt->fetch();
        if (!$cfg) {
          echo json_encode(['error' => 'Config not found']);
          break;
        }

        // If it's a User config (user_id != NULL), we probably shouldn't mess with it unless we want to "take over"
        // But the requirement says "Global Configuration". Global implies user_id IS NULL.
        // Let's assume Global means user_id IS NULL.
        // Or if the admin wants to load *any* config. The prompt says "Load Configuration... for their test runs".
        // Use admin_user_id to store this temporary "selection".

        // Logic: Set admin_user_id = current admin ID for this config.
        // AND potentially clear admin_user_id from other configs of the SAME tool?
        // Let's assume multi-config loading is allowed per tool.
        // Actually, user says "load global configuration for their test runs".
        // Usually, you run one config per tool.
        // Let's just set the link.

        $adminId = current_user()['id'];

        // Optional: Clear previous selection for this tool? 
        // We don't have tool_code here easily without another query, but let's just update this one.
        $stmt = $db->prepare("UPDATE qa_tool_configs SET admin_user_id=? WHERE id=?");
        $stmt->execute([$adminId, $configId]);
        echo json_encode(['ok' => true]);
        break;

      /* Support System */
      case 'save-support':
        $uid = current_user()['id'];
        $sub = $input['subject'] ?? 'No Subject';
        $msg = $input['message'] ?? '';
        $prio = $input['priority'] ?? 'low';
        if (!$msg) {
          echo json_encode(['error' => 'Message empty']);
          break;
        }

        $stmt = $db->prepare("INSERT INTO qa_support_messages (user_id, subject, message, priority) VALUES (?, ?, ?, ?)");
        $stmt->execute([$uid, $sub, $msg, $prio]);
        echo json_encode(['ok' => true]);
        break;

      case 'update-support-priority':
        require_role(['admin']);
        $id = $input['id'] ?? null;
        $prio = $input['priority'] ?? 'low';

        if (!$id) {
          echo json_encode(['error' => 'Missing ID']);
          break;
        }

        $stmt = $db->prepare("UPDATE qa_support_messages SET priority=? WHERE id=?");
        $stmt->execute([$prio, $id]);
        echo json_encode(['ok' => true]);
        break;

      case 'list-support':
        require_role(['admin']);
        $stmt = $db->query("
            SELECT m.*, u.name as user_name, u.email as user_email 
            FROM qa_support_messages m 
            JOIN qa_users u ON m.user_id = u.id 
            ORDER BY m.created_at DESC LIMIT 50
        ");
        echo json_encode($stmt->fetchAll());
        break;

      case 'mark-support-read':
        require_role(['admin', 'tester']);
        $id = $input['id'] ?? null;
        if (!$id) {
          echo json_encode(['error' => 'Missing ID']);
          break;
        }

        $user = current_user();
        if ($user['role'] === 'admin') {
          // Admin marks 'Admin Unread' (0) as Read (2)
          $stmt = $db->prepare("UPDATE qa_support_messages SET is_read=2 WHERE id=? AND is_read=0");
          $stmt->execute([$id]);
        } else {
          // User marks 'User Unread' (1) as Read (2)
          $user_id = $user['id'];
          // Verify ownership implicitly via user_id check
          $stmt = $db->prepare("UPDATE qa_support_messages SET is_read=2 WHERE id=? AND user_id=? AND is_read=1");
          $stmt->execute([$id, $user_id]);
        }
        echo json_encode(['ok' => true]);
        break;

      case 'get-unread-support':
        require_role(['admin', 'tester']);
        $user = current_user();
        if ($user['role'] === 'admin') {
          $stmt = $db->query("SELECT COUNT(*) as count FROM qa_support_messages WHERE is_read=0");
          echo json_encode($stmt->fetch());
        } else {
          $stmt = $db->prepare("SELECT COUNT(*) as count FROM qa_support_messages WHERE user_id=? AND is_read=1");
          $stmt->execute([$user['id']]);
          echo json_encode($stmt->fetch());
        }
        break;

      case 'reply-support':
        require_role(['admin', 'tester']); // Allow users to reply too
        $current = current_user();
        $id = $input['id'] ?? null;
        $reply = $input['reply'] ?? '';

        if (!$id || !$reply) {
          echo json_encode(['error' => 'Missing ID or Reply']);
          break;
        }

        // Check ownership if tester
        if ($current['role'] !== 'admin') {
          $chk = $db->prepare("SELECT user_id FROM qa_support_messages WHERE id=?");
          $chk->execute([$id]);
          $row = $chk->fetch();
          if (!$row || $row['user_id'] != $current['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Access Denied']);
            break;
          }
        }

        $senderName = ($current['role'] === 'admin') ? 'Admin' : 'User';
        $separator = "\n\n--- $senderName Reply ---\n";

        // If admin replies, is_read=1 (User sees it as new). If user replies, is_read=0 (Admin sees it as new).
        $isReadVal = ($current['role'] === 'admin') ? 1 : 0;

        $stmt = $db->prepare("UPDATE qa_support_messages SET admin_reply = CONCAT(IFNULL(admin_reply,''), ?, ?), reply_at=NOW(), is_read=? WHERE id=?");
        $stmt->execute([$separator, $reply, $isReadVal, $id]);
        echo json_encode(['ok' => true]);
        break;

      case 'my-support-history':
        require_login();
        $uid = current_user()['id'];
        // Join with qa_users to get the name, just like list-support
        $stmt = $db->prepare("
            SELECT m.*, u.name as user_name 
            FROM qa_support_messages m 
            LEFT JOIN qa_users u ON m.user_id = u.id 
            WHERE m.user_id=? 
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$uid]);
        echo json_encode($stmt->fetchAll());
        break;

      case 'get-profile':
        $user = current_user();
        if ($user['id']) {
          $stmt = $db->prepare("SELECT id, name, email, role, avatar_url, created_at FROM qa_users WHERE id=?");
          $stmt->execute([$user['id']]);
          echo json_encode($stmt->fetch() ?: []);
        } else {
          echo json_encode([]);
        }
        break;

      /* Runs */
      case 'list-runs':
        require_login();
        $user = current_user();
        $isTester = ($user['role'] === 'tester');
        $sql = "
            SELECT r.*, u.name as user_name 
            FROM qa_test_runs r 
            LEFT JOIN qa_users u ON r.user_id = u.id 
            WHERE 1=1
        ";
        $params = [];
        if ($isTester) {
          $sql .= " AND r.user_id = ?";
          $params[] = $user['id'];
        }
        $sql .= " ORDER BY r.run_date DESC LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

      case 'run-details':
        $rid = (int) ($input['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM qa_run_results WHERE run_id=?");
        $stmt->execute([$rid]);
        echo json_encode($stmt->fetchAll());
        break;

      case 'save-run':
        require_role(['admin', 'tester']);
        $status = $input['status'] ?? 'unknown';
        $total = $input['total_tests'] ?? 0;
        $passed = $input['passed'] ?? 0;
        $failed = $input['failed'] ?? 0;
        $open = $input['open_issues'] ?? 0;
        $notes = $input['notes'] ?? '';
        $results = $input['results'] ?? [];
        $user = current_user();
        $userId = $user['id'] ?? null;

        if (!$userId) {
          http_response_code(401);
          echo json_encode(['error' => 'Not logged in']);
          break;
        }

        $stmt = $db->prepare("INSERT INTO qa_test_runs (user_id, status, total_tests, passed, failed, open_issues, notes) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$userId, $status, $total, $passed, $failed, $open, $notes]);
        $runId = $db->lastInsertId();

        if ($runId && !empty($results)) {
          $sqlVal = [];
          $sqlBind = [];
          foreach ($results as $row) {
            $toolCode = $row['tool_code'] ?? 'unknown';
            $st = $row['status'] ?? 'unknown';
            $url = $row['url'] ?? '';
            $par = $row['parent'] ?? '';
            $raw = isset($row['payload']) ? json_encode($row['payload']) : json_encode($row);

            $sqlVal[] = "(?,?,?,?,?,?)";
            $sqlBind[] = $runId;
            $sqlBind[] = $toolCode;
            $sqlBind[] = $st;
            $sqlBind[] = $url;
            $sqlBind[] = $par;
            $sqlBind[] = $raw;
          }

          if ($sqlVal) {
            $chunkSize = 50; // Insert in chunks
            $chunks = array_chunk($sqlVal, $chunkSize);
            $bindChunks = array_chunk($sqlBind, $chunkSize * 6); // 6 params per row

            for ($i = 0; $i < count($chunks); $i++) {
              $q = "INSERT INTO qa_run_results (run_id, tool_code, status, url, parent, payload) VALUES " . implode(',', $chunks[$i]);
              $stmt = $db->prepare($q);
              $stmt->execute($bindChunks[$i]);
            }
          }
        }
        echo json_encode(['id' => $runId]);
        break;

      case 'delete-run':
        require_role(['admin', 'tester']);
        $ids = [];
        if (!empty($input['ids']) && is_array($input['ids'])) {
          $ids = $input['ids'];
        } elseif (!empty($input['id'])) {
          $ids[] = $input['id'];
        }

        if (!empty($ids)) {
          // Use a loop or IN clause. Loop is simpler for prepared statements with varying count
          foreach ($ids as $id) {
            // Optional: Check ownership if tester (though frontend filters it, backend should verify)
            // For now, assuming testers can delete what they see (which is only their own runs)
            $db->prepare("DELETE FROM qa_run_results WHERE run_id=?")->execute([$id]);
            $db->prepare("DELETE FROM qa_test_runs WHERE id=?")->execute([$id]);
          }
        }
        echo json_encode(['ok' => true]);
        break;

      case 'stats':
        $user = current_user();
        $targetUid = null;

        // If Admin/Viewer, allow filtering by specific user if provided
        if (in_array($user['role'], ['admin', 'viewer'])) {
          if (!empty($input['user_id'])) {
            $targetUid = $input['user_id'];
          }
        } else {
          // Testers always restricted to self
          $targetUid = $user['id'];
        }

        $where = "WHERE 1=1";
        $params = [];
        if ($targetUid) {
          $where .= " AND user_id = ?";
          $params[] = $targetUid;
        }

        // Filters
        $statusFilter = $input['status'] ?? null;
        $toolFilter = $input['tool'] ?? null;

        if ($statusFilter) {
          $where .= " AND status = ?";
          $params[] = $statusFilter;
        }
        if ($toolFilter) {
          // tools column is comma separated e.g. "cms,login"
          $where .= " AND tools LIKE ?";
          $params[] = "%$toolFilter%";
        }

        // Helper for stats
        $getStat = function ($sql, $p) use ($db) {
          $s = $db->prepare($sql);
          $s->execute($p);
          return (int) $s->fetch()['c'];
        };

        $total = $getStat("SELECT COUNT(*) AS c FROM qa_test_runs $where", $params);
        $passed = $getStat("SELECT COUNT(*) AS c FROM qa_test_runs $where AND status='passed'", $params);
        $failed = $getStat("SELECT COUNT(*) AS c FROM qa_test_runs $where AND status='failed'", $params);

        // Open Issues Sum
        $s = $db->prepare("SELECT COALESCE(SUM(open_issues),0) AS s FROM qa_test_runs $where");
        $s->execute($params);
        $open = (int) $s->fetch()['s'];

        // METRICS ENHANCEMENT

        // 1. Pass Rate
        $passRate = 0;
        if ($total > 0) {
          $passRate = round(($passed / $total) * 100, 1);
        }

        // 2. Utilized Tools (Distinct tool_code from results linked to these runs)
        $sqlTools = "
            SELECT COUNT(DISTINCT res.tool_code) as c 
            FROM qa_run_results res
            INNER JOIN qa_test_runs tr ON res.run_id = tr.id
            $where
        ";
        $tQuery = $db->prepare($sqlTools);
        $tQuery->execute($params);
        $utilized = (int) $tQuery->fetch()['c'];

        // 3. Tool Breakdown (Pass vs Fail)
        $sqlBreakdown = "
            SELECT 
                res.tool_code,
                SUM(CASE WHEN UPPER(res.status) IN ('OK','VALID','SUCCESS','IN STOCK') THEN 1 ELSE 0 END) as p,
                SUM(CASE WHEN UPPER(res.status) NOT IN ('OK','VALID','SUCCESS','IN STOCK') THEN 1 ELSE 0 END) as f
            FROM qa_run_results res
            INNER JOIN qa_test_runs tr ON res.run_id = tr.id
            $where
            GROUP BY res.tool_code
        ";
        $bQuery = $db->prepare($sqlBreakdown);
        $bQuery->execute($params);
        $toolStats = $bQuery->fetchAll(PDO::FETCH_ASSOC);

        // 4. STATS EXPANSION: Total Configs & Tickets
        // Use $targetUid to filter if set (works for both User Filter select and Tester role restriction)
        $pMeta = [];
        $whereMeta = "";
        if ($targetUid) {
          $whereMeta = "WHERE user_id = ?";
          $pMeta[] = $targetUid;
        }

        // Configs
        $stmtCfg = $db->prepare("SELECT COUNT(*) as c FROM qa_tool_configs $whereMeta");
        $stmtCfg->execute($pMeta);
        $totalConfigs = $stmtCfg->fetch()['c'];

        // Tickets
        $stmtTix = $db->prepare("SELECT COUNT(*) as c FROM qa_support_messages $whereMeta");
        $stmtTix->execute($pMeta);
        $totalTickets = $stmtTix->fetch()['c'];

        echo json_encode([
          'total_runs' => $total,
          'passed' => $passed,
          'failed' => $failed,
          'open_issues' => $open,
          'pass_rate' => $passRate,
          'utilized_tools' => $utilized,
          'tool_stats' => $toolStats,
          'total_configs' => $totalConfigs,
          'total_tickets' => $totalTickets
        ]);
        break;

      /* Tool Execution */
      case 'run-tool':
        $code = $input['code'] ?? '';
        $toolInput = $input['input'] ?? [];

        if (!$code) {
          echo json_encode(['error' => 'No tool code provided']);
          break;
        }

        $res = [];
        // Switch based on tool code
        switch ($code) {
          case 'add_to_cart':
            $res = ToolRunner::run_add_to_cart($toolInput);
            break;
          case 'brand':
            $res = ToolRunner::run_brand($toolInput);
            break;
          case 'cms':
            $res = ToolRunner::run_cms($toolInput);
            break;
          case 'products':
            $res = ToolRunner::run_products($toolInput);
            break;
          case 'category':
            $res = ToolRunner::run_category($toolInput);
            break;
          case 'sku':
            $res = ToolRunner::run_sku($toolInput);
            break;
          case 'stock':
            $res = ToolRunner::run_stock($toolInput);
            break;

          /* --- Newly Migrated Tools --- */
          case 'headers_check':
            $res = ToolRunner::run_headers_check($toolInput);
            break;
          case 'speed_test':
            $res = ToolRunner::run_speed_test($toolInput);
            break;
          case 'json_validator':
            $res = ToolRunner::run_json_validator($toolInput);
            break;
          case 'asset_count':
            $res = ToolRunner::run_asset_count($toolInput);
            break;
          case 'images':
            $res = ToolRunner::run_images($toolInput);
            break;
          case 'link_extractor':
            $res = ToolRunner::run_link_extractor($toolInput);
            break;
          case 'get_categories':
            $res = ToolRunner::run_get_categories($toolInput);
            break;
          case 'sub_category':
            $res = ToolRunner::run_sub_category($toolInput);
            break;
          case 'category_filter':
            $res = ToolRunner::run_category_filter($toolInput);
            break;

          default:
            // Temporary fallback for tools not yet migrated (will return empty or error)
            echo json_encode(['error' => "Tool '$code' not yet migrated to API"]);
            exit;
        }

        echo json_encode(['rows' => $res]);
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
<script>
  const CURRENT_USER_ROLE = '<?php echo htmlspecialchars($currentUser['role'] ?? 'viewer'); ?>';
</script>
<?php

$TOOLS_HTML = [];
$toolsDir = __DIR__ . '/tools/';
if (is_dir($toolsDir)) {
  foreach (glob($toolsDir . '*.html') as $file) {
    $code = basename($file, '.html');
    $TOOLS_HTML[$code] = file_get_contents($file);
  }
}

/*********************************
 * 1. DATABASE (MySQL, auto-init)
 *********************************/

const QA_DB_HOST = 'sql309.infinityfree.com';
const QA_DB_PORT = 3306;
const QA_DB_NAME = 'if0_40372489_init_db';
const QA_DB_USER = 'if0_40372489';
const QA_DB_PASS = 'KmUb1Azwzo';

function qa_db(): PDO
{
  static $pdo = null;
  if ($pdo !== null)
    return $pdo;

  $dsn = 'mysql:host=' . QA_DB_HOST . ';port=' . QA_DB_PORT . ';dbname=' . QA_DB_NAME . ';charset=utf8mb4';
  $pdo = new PDO($dsn, QA_DB_USER, QA_DB_PASS);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  // Ensure tables exist (idempotent)
  $pdo->exec("
        CREATE TABLE IF NOT EXISTS qa_tool_configs (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          tool_code VARCHAR(64) NOT NULL,
          config_name VARCHAR(191) NOT NULL,
          config_json MEDIUMTEXT NOT NULL,
          is_enabled TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

  $pdo->exec("
        CREATE TABLE IF NOT EXISTS qa_test_runs (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          run_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          status VARCHAR(32) NOT NULL,
          total_tests INT NOT NULL DEFAULT 0,
          passed INT NOT NULL DEFAULT 0,
          failed INT NOT NULL DEFAULT 0,
          open_issues INT NOT NULL DEFAULT 0,
          notes TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

  $pdo->exec("
        CREATE TABLE IF NOT EXISTS qa_run_results (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          run_id INT UNSIGNED NOT NULL,
          tool_code VARCHAR(64) NOT NULL,
          status VARCHAR(32) NOT NULL,
          url TEXT,
          parent TEXT,
          payload MEDIUMTEXT,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_run_tool (run_id, tool_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

  $pdo->exec("
        CREATE TABLE IF NOT EXISTS qa_users (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(191) NOT NULL,
          email VARCHAR(191) NOT NULL,
          password_hash VARCHAR(255) NOT NULL DEFAULT '',
          role VARCHAR(32) NOT NULL DEFAULT 'tester',
          avatar_url TEXT,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

  $pdo->exec("
        CREATE TABLE IF NOT EXISTS qa_support_messages (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id INT UNSIGNED NOT NULL,
          subject VARCHAR(191),
          message TEXT,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_support_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

  // Dynamic Migrations
  try {
    $cols = $pdo->query("SHOW COLUMNS FROM qa_users LIKE 'avatar_url'")->fetchAll();
    if (count($cols) == 0)
      $pdo->exec("ALTER TABLE qa_users ADD COLUMN avatar_url TEXT AFTER role");
  } catch (Exception $e) {
  }

  try {
    $cols = $pdo->query("SHOW COLUMNS FROM qa_users LIKE 'password_hash'")->fetchAll();
    if (count($cols) == 0)
      $pdo->exec("ALTER TABLE qa_users ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT '' AFTER email");
  } catch (Exception $e) {
  }

  try {
    $cols = $pdo->query("SHOW COLUMNS FROM qa_support_messages LIKE 'is_read'")->fetchAll();
    if (count($cols) == 0)
      $pdo->exec("ALTER TABLE qa_support_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER message");
  } catch (Exception $e) {
  }

  return $pdo;
}



/********************
 * 3. PAGE RENDERING
 ********************/

$TOOL_DEFS = [
  ['code' => 'brand', 'name' => 'Brand Links'],
  ['code' => 'cms', 'name' => 'CMS Blocks'],
  ['code' => 'category', 'name' => 'Category Links'],
  ['code' => 'category_filter', 'name' => 'Filtered Category'],
  ['code' => 'getcategories', 'name' => 'Get Categories'],
  ['code' => 'images', 'name' => 'Images'],
  ['code' => 'login', 'name' => 'Login'],
  ['code' => 'price_checker', 'name' => 'Price Checker'],
  ['code' => 'products', 'name' => 'Products'],
  ['code' => 'sku', 'name' => 'SKU Lookup'],
  ['code' => 'stock', 'name' => 'Stock / Availability'],
  ['code' => 'sub_category', 'name' => 'Subcategories'],
  ['code' => 'add_to_cart', 'name' => 'Add to Cart'],
  ['code' => 'speed_test', 'name' => 'Speed Test'],
  ['code' => 'link_extractor', 'name' => 'Link Extractor'],
  ['code' => 'asset_count', 'name' => 'Asset Counter'],
  ['code' => 'json_validator', 'name' => 'JSON Validator'],
  ['code' => 'headers_check', 'name' => 'Headers Inspector'],
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>QA Automation Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    /* Support Layout Centering */
    .support-layout {
      display: flex;
      height: 600px;
      background: #fff;
      border: 1px solid #dfe5eb;
      border-radius: 10px;
      overflow: hidden;
      max-width: 1000px;
      /* Match other sections */
      margin: 0 auto;
      /* Center it */
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .support-sidebar {
      width: 300px;
      background: #f8fafc;
      border-right: 1px solid #dfe5eb;
      display: flex;
      flex-direction: column;
    }

    .support-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      position: relative;
      background: #fff;
    }

    /* Pre-wrap for messages to support multi-line replies */
    .msg-bubble {
      white-space: pre-wrap;
      max-width: 80%;
      margin-bottom: 10px;
      padding: 10px 14px;
      border-radius: 12px;
      font-size: 13px;
      line-height: 1.4;
      position: relative;
      clear: both;
    }

    .msg-user {
      background: #fff;
      color: #333;
      float: left;
      border: 1px solid #e0e6ed;
      border-bottom-left-radius: 2px;
    }

    .msg-agent {
      background: #2962ff;
      /* Blue */
      color: #fff;
      float: right;
      border-bottom-right-radius: 2px;
    }

    .msg-meta {
      display: block;
      margin-top: 4px;
      font-size: 10px;
      opacity: 0.7;
      text-align: right;
    }

    :root {
      --bg: #f4f7fa;
      --card: #fff;
      --radius: 12px;
      --shadow: 0 4px 12px rgba(0, 0, 0, .08);
      --blue: #1E88E5;
      --green: #43A047;
      --red: #E53935;
      --amber: #FB8C00;
      --muted: #607D8B;
      --border: #dde3ec;
    }

    * {
      box-sizing: border-box;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    body {
      margin: 0;
      background: var(--bg);
      color: #263238;
    }

    .app-shell {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px 16px 40px;
    }

    .app-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 18px;
    }

    .app-header h1 {
      font-size: 24px;
      margin: 0;
      color: #1a3a57;
    }

    .app-header small {
      color: var(--muted);
    }

    /* SUPPORT SCREEN REDESIGN */
    .support-layout {
      display: flex;
      height: calc(100vh - 140px);
      /* Adjust based on header/tabs */
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      overflow: hidden;
    }

    .support-sidebar {
      width: 320px;
      background: #f8f9fa;
      border-right: 1px solid #ddd;
      display: flex;
      flex-direction: column;
    }

    .support-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      background: #fff;
      position: relative;
    }

    /* Sidebar Components */
    .sidebar-header {
      padding: 16px;
      border-bottom: 1px solid #eee;
    }

    .user-profile-sm {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
    }

    .user-avatar-sm {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #ccc;
      object-fit: cover;
    }

    .search-box {
      position: relative;
    }

    .search-box input {
      width: 100%;
      padding: 8px 12px 8px 32px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 13px;
    }

    .search-icon {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
    }

    .filter-tags {
      display: flex;
      gap: 8px;
      padding: 0 16px 16px;
      border-bottom: 1px solid #eee;
    }

    .filter-tag {
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 12px;
      background: #eee;
      color: #555;
      cursor: pointer;
      border: none;
    }

    .filter-tag.active {
      background: #e3f2fd;
      color: #1976d2;
      font-weight: 600;
    }

    .ticket-list {
      flex: 1;
      overflow-y: auto;
      padding: 10px;
    }

    .ticket-card {
      background: #fff;
      border: 1px solid #eee;
      border-left: 4px solid transparent;
      /* Status color */
      border-radius: 6px;
      padding: 12px;
      margin-bottom: 10px;
      cursor: pointer;
      transition: box-shadow 0.2s;
    }

    .ticket-card:hover {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .ticket-card.active {
      background: #f0f8ff;
      border-color: #bbdefb;
    }

    .ticket-card.status-urgent {
      border-left-color: #ff5252;
    }

    .ticket-card.status-medium {
      border-left-color: #ffca28;
    }

    .ticket-card.status-low {
      border-left-color: #66bb6a;
    }

    .ticket-card.status-high {
      border-left-color: #42a5f5;
    }

    .t-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 4px;
    }

    .t-title {
      font-weight: 600;
      font-size: 13px;
      color: #333;
    }

    .t-badge {
      font-size: 10px;
      padding: 2px 6px;
      border-radius: 4px;
    }

    .badge-urgent {
      background: #ffebee;
      color: #c62828;
    }

    .badge-medium {
      background: #fff8e1;
      color: #f57f17;
    }

    .badge-low {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .badge-high {
      background: #e3f2fd;
      color: #1565c0;
    }

    .t-meta {
      font-size: 11px;
      color: #777;
      margin-top: 4px;
    }

    .t-user {
      display: block;
      margin-bottom: 2px;
    }

    /* Main Chat Area */
    .chat-header {
      padding: 16px;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #fff;
    }

    .chat-title h3 {
      margin: 0;
      font-size: 16px;
    }

    .chat-title span {
      font-size: 12px;
      color: #777;
      margin-left: 8px;
    }

    .chat-messages {
      flex: 1;
      padding: 20px;
      overflow-y: auto;
      background: #fafafa;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .msg-bubble {
      max-width: 70%;
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 13px;
      line-height: 1.5;
      position: relative;
    }

    .msg-user {
      align-self: flex-start;
      background: #fff;
      border: 1px solid #eee;
      border-top-left-radius: 0;
    }

    .msg-agent {
      align-self: flex-end;
      background: #2962ff;
      color: white;
      border-top-right-radius: 0;
    }

    .msg-meta {
      font-size: 10px;
      margin-top: 4px;
      display: block;
      opacity: 0.7;
    }

    .chat-input-area {
      padding: 16px;
      border-top: 1px solid #eee;
      background: #fff;
    }

    .chat-actions {
      display: flex;
      gap: 8px;
      margin-bottom: 8px;
    }

    .action-chip {
      font-size: 11px;
      padding: 4px 10px;
      background: #f5f5f5;
      border-radius: 12px;
      cursor: pointer;
      border: 1px solid #eee;
    }

    .action-chip:hover {
      background: #eee;
    }

    .input-row {
      display: flex;
      gap: 10px;
    }

    .input-row textarea {
      flex: 1;
      border: 1px solid #ddd;
      border-radius: 6px;
      padding: 10px;
      font-size: 13px;
      resize: none;
      height: 60px;
    }

    .send-btn {
      width: 40px;
      height: 40px;
      background: #2962ff;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Switch View Fab */
    .view-switch {
      position: absolute;
      bottom: 20px;
      right: 20px;
      background: #263238;
      color: white;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 12px;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
      z-index: 100;
    }

    .tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 16px;
    }

    .tab-btn {
      padding: 8px 14px;
      border-radius: 999px;
      border: 1px solid transparent;
      background: transparent;
      cursor: pointer;
      font-size: 14px;
      color: var(--muted);
    }

    .tab-btn.active {
      background: #e3f2fd;
      border-color: #90caf9;
      color: #0d47a1;
    }

    .card-row {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
      margin-bottom: 18px;
    }

    .charts-section {
      margin-bottom: 18px;
    }

    .charts-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
    }

    .chart-card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 10px 12px;
    }

    .chart-card h3 {
      margin: 0 0 6px;
      font-size: 13px;
      color: var(--muted);
    }

    .chart-card canvas {
      width: 100%;
      max-height: 180px;
    }

    @media(max-width:900px) {
      .charts-grid {
        grid-template-columns: repeat(1, minmax(0, 1fr));
      }
    }


    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 16px;
      margin-bottom: 20px;
    }

    @media(max-width: 900px) {
      .stats-row {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media(max-width: 480px) {
      .stats-row {
        grid-template-columns: 1fr;
      }
    }

    .stat-card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 12px 14px;
      border-top: 4px solid transparent;
    }

    .stat-card h3 {
      margin: 0 0 8px;
      font-size: 14px;
      color: var(--muted);
    }

    .stat-value {
      font-size: 26px;
      font-weight: 700;
    }

    .stat-meta {
      font-size: 12px;
      color: var(--muted);
    }

    .stat-total {
      border-top-color: var(--blue);
    }

    .stat-pass {
      border-top-color: var(--green);
    }

    .stat-fail {
      border-top-color: var(--red);
    }

    .stat-open {
      border-top-color: var(--amber);
    }

    .dashboard-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 16px;
      margin-bottom: 22px;
    }

    .section-card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 14px 16px 16px;
    }

    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 10px;
    }

    .section-header h2 {
      margin: 0;
      font-size: 16px;
    }

    .section-header small {
      color: var(--muted);
    }

    .modules-grid {
      display: flex;
      gap: 10px;
      overflow-x: auto;
      padding-bottom: 6px;
      scroll-snap-type: x mandatory;
    }

    .modules-grid .module-tile {
      flex: 0 0 220px;
      scroll-snap-align: start;
    }

    .module-tile {
      border-radius: 10px;
      padding: 12px 12px 10px;
      background: #e3f2fd;
      cursor: pointer;
      position: relative;
      border: 1px solid rgba(0, 0, 0, .04);
      transition: .2s;
    }

    .module-tile:nth-child(3n) {
      background: #e8f5e9;
    }

    .module-tile:nth-child(4n) {
      background: #fff3e0;
    }

    .module-tile:nth-child(5n) {
      background: #fce4ec;
    }

    .module-tile.active {
      transform: translateY(-1px);
      box-shadow: 0 3px 10px rgba(0, 0, 0, .12);
      border-color: #90caf9;
    }

    .module-title {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .module-meta {
      font-size: 12px;
      color: var(--muted);
    }

    .tool-runner {
      display: flex;
      flex-direction: column;
      height: 100%;
    }

    .tool-runner-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .tool-runner-controls button {
      border-radius: 999px;
      border: none;
      padding: 8px 14px;
      font-size: 13px;
      cursor: pointer;
    }

    /* Report Modal Styles */
    .report-header {
      background: linear-gradient(135deg, #1a3a57 0%, #1E88E5 100%);
      color: #fff;
      padding: 24px 32px;
    }

    .report-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-top: 16px;
    }

    .meta-item {
      background: rgba(255, 255, 255, 0.15);
      border-radius: 8px;
      padding: 8px 16px;
    }

    .meta-item label {
      display: block;
      font-size: 10px;
      text-transform: uppercase;
      opacity: 0.8;
      margin-bottom: 2px;
      color: white !important;
    }

    .meta-item .value {
      font-size: 16px;
      font-weight: 700;
      color: white;
    }

    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 12px;
      margin-bottom: 24px;
    }

    .summary-card {
      background: #f8fafc;
      border-radius: 8px;
      padding: 16px;
      text-align: center;
      border: 1px solid #e2e8f0;
    }

    .summary-card h3 {
      margin: 0 0 4px;
      font-size: 11px;
      text-transform: uppercase;
      color: #607D8B;
    }

    .summary-card .value {
      font-size: 24px;
      font-weight: 700;
      color: #37474f;
    }

    .summary-card.passed .value {
      color: var(--success);
    }

    .summary-card.failed .value {
      color: var(--danger);
    }

    .summary-card.open .value {
      color: #FB8C00;
    }

    .summary-card.rate .value {
      color: var(--secondary);
    }

    .tool-section {
      margin-bottom: 20px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      overflow: hidden;
    }

    .tool-header {
      background: #f1f5f9;
      padding: 10px 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .tool-header h3 {
      margin: 0;
      font-size: 14px;
      font-weight: 600;
      color: #37474f;
    }

    .tool-stats {
      display: flex;
      gap: 8px;
      font-size: 11px;
    }

    .tool-stats span {
      padding: 2px 8px;
      border-radius: 10px;
    }

    .tool-stats .passed {
      background: #e8f5e9;
      color: var(--success);
    }

    .tool-stats .failed {
      background: #ffebee;
      color: var(--danger);
    }

    .tool-stats .warn {
      background: #fff3e0;
      color: #FB8C00;
    }

    .detail-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
    }

    .detail-table th,
    .detail-table td {
      padding: 8px 12px;
      text-align: left;
      border-bottom: 1px solid #e2e8f0;
    }

    .detail-table th {
      background: #f8fafc;
      font-weight: 600;
      color: #546e7a;
    }

    .detail-table .mini-badge {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 600;
      color: white;
    }

    .mini-badge.ok {
      background: var(--success);
    }

    .mini-badge.warn {
      background: #FB8C00;
    }

    .mini-badge.fail {
      background: var(--danger);
    }

    .url-cell {
      max-width: 300px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-family: monospace;
    }

    @media print {
      .modal-content {
        position: static;
        width: 100%;
        height: auto;
        border: none;
        box-shadow: none;
      }

      .modal-overlay {
        position: static;
        background: white;
        padding: 0;
      }

      .close-modal,
      .print-btn-hide {
        display: none !important;
      }

      body>*:not(#modal-report) {
        display: none;
      }

      #modal-report {
        display: block !important;
        position: static;
        z-index: 9999;
      }
    }

    .btn-primary {
      background: var(--blue);
      color: #fff;
    }

    .btn-ghost {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--muted);
    }

    .btn-primary:disabled {
      opacity: .6;
      cursor: default;
    }

    .tool-container {
      border-radius: 10px;
      border: 1px solid var(--border);
      padding: 0;
      min-height: 350px;
      background: #fafbff;
      overflow: hidden;
    }

    .tool-iframe {
      width: 100%;
      height: 420px;
      border: none;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .table th,
    .table td {
      padding: 8px;
      border-bottom: 1px solid #e0e6f0;
    }

    .table th {
      text-align: left;
      color: var(--muted);
      font-weight: 500;
    }

    .badge {
      padding: 4px 8px;
      border-radius: 999px;
      font-size: 11px;
    }

    .badge-pass {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .badge-fail {
      background: #ffebee;
      color: #c62828;
    }

    .badge-run {
      background: #e3f2fd;
      color: #1565c0;
    }

    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
      margin-top: 10px;
    }

    .form-field label {
      display: block;
      font-size: 13px;
      margin-bottom: 4px;
      color: #455a64;
    }

    .form-field input,
    .form-field select,
    .form-field textarea {
      width: 100%;
      padding: 7px 9px;
      border-radius: 6px;
      border: 1px solid #cfd8dc;
      font-size: 13px;
    }

    .form-field textarea {
      min-height: 90px;
      resize: vertical;
    }

    .checkbox-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      font-size: 13px;
    }

    .checkbox-row label {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .actions-row {
      margin-top: 10px;
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .table-actions button {
      border: none;
      background: transparent;
      color: #1E88E5;
      font-size: 12px;
      cursor: pointer;
      padding: 0 4px;
    }

    .run-console {
      background: #0d1117;
      color: #c9d1d9;
      font-family: 'Consolas', monospace;
      font-size: 13px;
      padding: 12px;
      border-radius: 6px;
      height: 100%;
      overflow-y: auto;
      border: 1px solid #30363d;
    }

    .run-console .log-line {
      margin-bottom: 4px;
      border-bottom: 1px solid #21262d;
      padding-bottom: 2px;
      white-space: pre-wrap;
    }

    .run-console .log-error {
      color: #ff7b72;
      font-weight: bold;
    }

    .run-console .log-success {
      color: #7ee787;
      font-weight: bold;
    }

    .run-console .log-info {
      color: #a5d6ff;
    }

    .run-console .log-warn {
      color: #d29922;
    }

    /* Modal */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.6);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal-card {
      background: #fff;
      width: 90%;
      max-width: 1000px;
      height: 90%;
      max-height: 800px;
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
      padding: 14px 20px;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #f8fafc;
    }

    .modal-header h3 {
      margin: 0;
      font-size: 18px;
      color: #1a3a57;
    }

    .modal-body {
      flex: 1;
      padding: 0;
      background: #fafbff;
      position: relative;
    }

    .modal-iframe {
      width: 100%;
      height: 100%;
      border: none;
    }

    .modal-close {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #666;
    }

    /* Fullscreen Console Mode */
    /* Fullscreen Console Mode */
    .console-mode .app-shell {
      display: none !important;
    }

    #console-overlay {
      display: none;
    }

    .console-mode #console-overlay {
      display: block;
      height: 100vh;
      padding: 20px;
      box-sizing: border-box;
    }

    .console-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 14px;
    }

    .console-header h2 {
      margin: 0;
      color: #1a3a57;
    }

    /* Profile Dropdown */
    .profile-dropdown {
      display: none;
      position: absolute;
      top: 100%;
      right: 0;
      width: 180px;
      background: #fff;
      border: 1px solid #e1e4e8;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      z-index: 1001;
      overflow: hidden;
      margin-top: 5px;
    }

    .profile-dropdown.active {
      display: block;
    }

    .dropdown-item {
      padding: 10px 16px;
      font-size: 14px;
      color: #333;
      cursor: pointer;
      border-bottom: 1px solid #f0f0f0;
      transition: background 0.1s;
    }

    .dropdown-item:last-child {
      border-bottom: none;
    }

    .dropdown-item:hover {
      background: #f8f9fa;
    }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
  <div class="app-shell">
    <header class="app-header">
      <div>
        <h1>QA Automation Dashboard</h1>
        <small>All tools & dashboard in a single PHP file</small>
      </div>
      <div class="user-profile" id="profile-trigger"
        style="display:flex; align-items:center; gap:10px; cursor:pointer;position:relative;">
        <div style="text-align:right;">
          <div style="font-weight:600; font-size:14px;" id="header-username">
            <?php echo htmlspecialchars($currentUser['name'] ?? 'Guest'); ?>
          </div>
          <div style="font-size:11px; color:var(--muted); text-transform:uppercase;">
            <?php echo htmlspecialchars($currentUser['role'] ?? 'Viewer'); ?>
          </div>
        </div>
        <img id="header-avatar"
          src="<?php echo htmlspecialchars($currentUser['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['name'] ?? 'User')); ?>"
          style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid #fff; box-shadow:0 2px 5px rgba(0,0,0,0.1);">

        <!-- Dropdown Menu -->
        <div class="profile-dropdown" id="profile-dropdown">
          <div class="dropdown-item" id="menu-edit-profile">
            <span>Edit Profile</span>
          </div>
          <div class="dropdown-item" onclick="location.href='logout.php'" style="color:#c62828;">
            <span>Logout</span>
          </div>
        </div>
      </div>
    </header>

    <div class="tabs">
      <button class="tab-btn active" data-tab="dashboard">Dashboard</button>
      <button class="tab-btn" data-tab="configs">Configurations</button>
      <button class="tab-btn" data-tab="users">Users</button>
      <button class="tab-btn" data-tab="support">Support Center</button>
    </div>

    <!-- DASHBOARD TAB -->
    <section id="tab-dashboard" class="tab-content active">

      <div class="section-card charts-section">
        <div class="section-header">
          <div>
            <h2>Run Insights</h2>
            <small>Overview of all saved test runs</small>
          </div>
          <!-- User Filter (Global) -->
          <div class="filter-group" id="filter-user-container" style="display:none; margin-bottom:0;">
            <select id="filter-user" class="form-control" style="width:160px;">
              <option value="">All Users</option>
            </select>
          </div>
        </div>
        <div class="charts-grid">
          <div class="chart-card">
            <h3>Pass vs Fail (Tests)</h3>
            <canvas id="chart-pass-fail"></canvas>
          </div>
          <div class="chart-card">
            <h3>Runs by Status</h3>
            <canvas id="chart-run-status"></canvas>
          </div>
          <div class="chart-card">
            <h3>Recent Pass Rate</h3>
            <canvas id="chart-pass-trend"></canvas>
          </div>
        </div>
        <div class="text-muted" style="margin-top:8px;">
          Charts are aggregated from the data stored in your <strong>Test Runs</strong> table.
        </div>
      </div>

      <div class="stats-row">
        <!-- Total -->
        <div class="stat-card stat-total" style="border-top: 4px solid #1E88E5; cursor:pointer;"
          onclick="setRunFilter('')">
          <h3>Total Test Runs</h3>
          <div class="stat-value" id="stat-total">0</div>
          <div class="stat-meta">All time</div>
        </div>
        <!-- Passed -->
        <div class="stat-card stat-pass" style="border-top: 4px solid #43A047; cursor:pointer;"
          onclick="setRunFilter('passed')">
          <h3>Passed</h3>
          <div class="stat-value" id="stat-passed">0</div>
          <div class="stat-meta">Runs marked as passed</div>
        </div>
        <!-- Failed -->
        <div class="stat-card stat-fail" style="border-top: 4px solid #E53935; cursor:pointer;"
          onclick="setRunFilter('failed')">
          <h3>Failed</h3>
          <div class="stat-value" id="stat-failed">0</div>
          <div class="stat-meta">Runs marked as failed</div>
        </div>
        <!-- Open Issues -->
        <div class="stat-card stat-open" style="border-top: 4px solid #FB8C00; cursor:pointer;"
          onclick="triggerOpenIssuesReport()">
          <h3>Open Issues</h3>
          <div class="stat-value" id="stat-open">0</div>
          <div class="stat-meta">Total open issues across runs</div>
        </div>
        <!-- Pass Rate -->
        <div class="stat-card" style="border-top: 4px solid #8e24aa;">
          <h3>Pass Rate</h3>
          <div class="stat-value"><span id="stat-rate">0</span>%</div>
          <div class="stat-meta">Percentage of passed runs</div>
        </div>
        <!-- Utilized Tools -->
        <div class="stat-card" style="border-top: 4px solid #00acc1;">
          <h3>Utilized Tools</h3>
          <div class="stat-value" id="stat-utilized">0</div>
          <div class="stat-meta">Distinct tools ever run</div>
        </div>
        <!-- Total Configs -->
        <div class="stat-card" style="border-top: 4px solid #7cb342;">
          <h3>Total Configs</h3>
          <div class="stat-value" id="stat-configs">0</div>
          <div class="stat-meta">Available tool configurations</div>
        </div>
        <!-- Support Tickets -->
        <div class="stat-card" style="border-top: 4px solid #d81b60;">
          <h3>Support Tickets</h3>
          <div class="stat-value" id="stat-tickets">0</div>
          <div class="stat-meta">Total tickets in system</div>
        </div>
      </div>

      <div class="dashboard-grid">
        <div class="section-card">
          <div class="section-header">
            <h2>Test Modules Overview</h2>
            <small>Click a module to open the tool</small>
          </div>
          <div style="padding: 0 16px 8px; display:flex; justify-content:space-between; align-items:center;">
            <button class="btn-small btn-secondary" id="btn-select-all-modules">Select All / None</button>
            <button class="btn-primary" id="btn-run-all">Run All Tests</button>
          </div>
          <div class="modules-grid" id="modules-grid"></div>
          <div class="text-muted" style="margin-top:8px;">
            Selected tools will run in sequence when you click "Run All Tests". Click a tile to run individually.
          </div>
        </div>
      </div>



      <!-- Manual logging of test runs -->
      <div class="section-card">
        <div class="section-header">
          <h2>Recent Test Runs</h2>
          <small>History of automated execution</small>
        </div>
        <div class="actions-row"
          style="margin-bottom:14px; background:#f8fafc; padding:12px; border-radius:8px; display:flex; gap:16px;">
          <div class="filter-group">
            <label>Filter by Status</label>
            <select id="filter-status" class="form-control" style="width:140px;">
              <option value="">All Statuses</option>
              <option value="passed">Passed</option>
              <option value="failed">Failed</option>
            </select>
          </div>
          <div class="filter-group">
            <label>Filter by Tool</label>
            <select id="filter-tool" class="form-control" style="width:140px;">
              <option value="">All Tools</option>
              <!-- Populated by JS -->
            </select>
          </div>

          <div style="flex:1;"></div>
          <div style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:8px;">
            <div style="font-size:13px; color:#555; font-weight:600; margin-right:8px;">Total Runs: <span
                id="filtered-total">0</span></div>
            <button id="btn-bulk-delete" class="btn-small" style="background:#d32f2f; display:none;"
              onclick="bulkDeleteRuns()">Delete Selected (<span id="selected-count">0</span>)</button>
            <button class="btn-small btn-secondary" onclick="downloadRunsCSV()">Export CSV</button>
          </div>
        </div>

        <table class="table" id="runs-table">
          <thead>
            <tr>
              <th width="30"><input type="checkbox" id="select-all-runs" onclick="toggleSelectAll(this)"></th>
              <th width="40">#</th>
              <th>Date</th>
              <th>User</th>
              <th width="80">Status</th>
              <th>Total</th>
              <th>Passed</th>
              <th>Failed</th>
              <th>Open</th>
              <th>Notes</th>
              <th width="120">Actions</th>
              <th width="80">Report</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </section>

    <!-- JS Logic for Batch Delete -->
    <script>
      function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('.run-checkbox');
        checkboxes.forEach(cb => {
          // Only select visible rows
          if (cb.closest('tr').style.display !== 'none') {
            cb.checked = source.checked;
          }
        });
        updateBulkAction();
      }

      function updateBulkAction() {
        const checked = document.querySelectorAll('.run-checkbox:checked').length;
        const btn = document.getElementById('btn-bulk-delete');
        const count = document.getElementById('selected-count');
        if (btn && count) {
          count.textContent = checked;
          btn.style.display = checked > 0 ? 'inline-block' : 'none';
        }
      }

      async function bulkDeleteRuns() {
        const checked = document.querySelectorAll('.run-checkbox:checked');
        if (checked.length === 0) return;

        if (!confirm('Are you sure you want to delete ' + checked.length + ' runs? This cannot be undone.')) return;

        const ids = Array.from(checked).map(cb => parseInt(cb.value));

        try {
          await api('delete-run', { ids: ids });
          // refresh
          await loadRuns();
          // reset header checkbox
          document.getElementById('select-all-runs').checked = false;
          updateBulkAction();
        } catch (e) {
          alert('Error deleting runs: ' + e.message);
        }
      }
    </script>

    <!-- CONFIGURATION TAB -->
    <section id="tab-configs" class="tab-content">
      <div class="section-card">
        <div class="section-header">
          <h2>Create / Edit Configuration</h2>
          <small>Inputs are stored only; tools read them manually.</small>
        </div>
        <form id="config-form">
          <input type="hidden" id="cfg-id">
          <div class="form-grid">
            <div class="form-field">
              <label>Configuration Name</label>
              <input type="text" id="cfg-name" placeholder="e.g., Daily Brand Link Check">
            </div>
            <div class="form-field">
              <label>Tool</label>
              <select id="cfg-tool-code">
                <?php foreach ($TOOL_DEFS as $t): ?>
                      <option value="<?php echo htmlspecialchars($t['code'], ENT_QUOTES); ?>">
                        <?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>
                      </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Owner Selection (Admin Only - Hidden by default) -->
          <div class="form-grid" id="cfg-owner-wrapper" style="margin-top:14px; display:none;">
            <div class="form-field">
              <label>Owner (Admin Only)</label>
              <select id="cfg-owner">
                <option value="">Global (Everyone)</option>
                <!-- Populated via JS -->
              </select>
            </div>
          </div>

          <div class="form-grid" style="margin-top:14px;">
            <div class="form-field" style="grid-column:1 / -1;">
              <label>Target URLs / JSON / Inputs</label>
              <textarea id="cfg-inputs" placeholder="Paste any inputs required for the selected tool."></textarea>
            </div>
          </div>

          <div class="actions-row">
            <label style="font-size:13px;display:flex;align-items:center;gap:4px;">
              <input type="checkbox" id="cfg-enabled" checked> Enable this configuration
            </label>
            <button type="button" class="btn-primary" id="cfg-save-btn">Save Configuration</button>
            <button type="button" class="btn-ghost" id="cfg-reset-btn">Reset</button>
          </div>
        </form>
      </div>

      <div class="section-card" style="margin-top:16px;">
        <div class="section-header" style="display:flex; justify-content:space-between; align-items:center;">
          <div>
            <h2>Existing Configurations</h2>
            <small>Reference only – open tool and copy values manually.</small>
          </div>
          <div>
            <select id="filter-config-owner" class="form-control" style="font-size:12px; padding:4px 8px; width:140px;"
              onchange="renderConfigsTable()">
              <option value="">All Owners</option>
            </select>
          </div>
        </div>
        <table class="table" id="configs-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Owner</th>
              <th>Tool</th>
              <th>Enabled</th>
              <th>Snippet</th>
              <th></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </section>

    <!-- USERS TAB -->
    <section id="tab-users" class="tab-content">
      <div class="section-card">
        <div class="section-header">
          <h2>User Management</h2>
          <small>Metadata only (no auth).</small>
        </div>
        <form id="user-form">
          <input type="hidden" id="user-id">
          <div class="form-grid">
            <div class="form-field">
              <label>Name</label>
              <input type="text" id="user-name" placeholder="Tester name">
            </div>
            <div class="form-field">
              <label>Email</label>
              <input type="email" id="user-email" placeholder="tester@jarir.com">
            </div>
            <div class="form-field">
              <label>Password (Leave blank to keep current)</label>
              <input type="password" id="user-password" placeholder="New Password">
            </div>
            <div class="form-field">
              <label>Role</label>
              <select id="user-role">
                <option value="tester">Tester</option>
                <option value="admin">Admin</option>
                <option value="viewer">Viewer</option>
              </select>
            </div>
            <div class="form-field">
              <label>Status</label>
              <div class="checkbox-row">
                <label><input type="checkbox" id="user-active" checked> Active</label>
              </div>
            </div>
          </div>
          <div class="actions-row">
            <button type="button" class="btn-primary" id="user-save-btn">Save User</button>
            <button type="button" class="btn-ghost" id="user-reset-btn">Reset</button>
          </div>
        </form>
      </div>

      <div class="section-card" style="margin-top:16px;">
        <div class="section-header">
          <h2>Existing Users</h2>
          <small>Assign testers to runs manually in notes.</small>
        </div>
        <table class="table" id="users-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </section>
  </div>

  <!-- CONSOLE OVERLAY (Focus Mode) -->
  <div id="console-overlay">
    <div class="section-card" style="height:100%; display:flex; flex-direction:column;">
      <div class="console-header">
        <h2>Test Execution Log</h2>
        <button class="btn-ghost" onclick="exitConsoleMode()">Close Console</button>
      </div>
      <div id="dash-console" class="run-console"></div>
    </div>
  </div>

  <!-- SUPPORT TAB -->
  <section id="tab-support" class="tab-content">
    <div class="support-layout">
      <!-- Sidebar -->
      <div class="support-sidebar">
        <!-- Header / Profile -->
        <div class="sidebar-header">
          <div class="user-profile-sm">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($currentUser['name']); ?>&background=random"
              class="user-avatar-sm" id="supp-my-avatar">
            <div>
              <div style="font-weight:600; font-size:13px;"><?php echo htmlspecialchars($currentUser['name']); ?></div>
              <div style="font-size:11px; color:#777;" id="supp-role-label">Support Interface</div>
            </div>
          </div>
          <div class="search-box">
            <span class="search-icon">&#128269;</span>
            <input type="text" placeholder="Search tickets..." id="supp-search">
          </div>
        </div>

        <!-- Filters (Admin Only usually, but showing for design) -->
        <div class="filter-tags" id="supp-filters">
          <button class="filter-tag active" onclick="filterSupport('all', this)">All</button>
          <button class="filter-tag" onclick="filterSupport('open', this)">Open</button>
          <button class="filter-tag" onclick="filterSupport('pending', this)">Pending</button>
        </div>

        <!-- Ticket List -->
        <div class="ticket-list" id="supp-ticket-list">
          <!-- Loaded via JS -->
          <div style="text-align:center; padding:20px; color:#999;">Loading...</div>
        </div>

        <!-- User View: New Ticket Button -->
        <div style="padding:10px; border-top:1px solid #eee; display:none;" id="supp-user-actions">
          <button class="btn-primary" style="width:100%;" onclick="openNewTicketModal()">+ New Support Ticket</button>
        </div>
      </div>

      <!-- Main Chat Area -->
      <div class="support-main">
        <div id="supp-chat-view" style="display:flex; flex-direction:column; height:100%;">
          <div class="chat-header">
            <div class="chat-title">
              <h3 id="chat-ticket-subject">Select a ticket</h3>
              <span id="chat-ticket-id"></span>
              <span class="t-badge badge-high" id="chat-ticket-status"></span>
            </div>
            <div style="display:flex; gap:10px;">
              <!-- Admin Actions -->
              <button class="btn-small btn-ghost" onclick="escalateTicket()">Escalate</button>
              <button class="btn-small btn-ghost" onclick="closeTicket()">Close Ticket</button>
            </div>
          </div>

          <div class="chat-messages" id="chat-messages-area">
            <div style="text-align:center; margin-top:40px; color:#ccc;">
              Select a ticket from the sidebar to view details.
            </div>
          </div>

          <div class="chat-input-area">
            <div class="chat-actions">
              <div class="action-chip" onclick="insertQuickReply('Hello! How can I help you today?')">Quick Reply</div>
              <div class="action-chip" onclick="insertQuickReply('I am checking your account status...')">Check Status
              </div>
            </div>
            <div class="input-row">
              <textarea id="chat-reply-input" placeholder="Type your response..."></textarea>
              <button class="send-btn" onclick="sendChatMessage()">&#10148;</button>
            </div>
          </div>
        </div>

        <!-- Floating Switcher Removed -->
      </div>
    </div>
  </section>

  <!-- Report Modal (Iframe) -->
  <div id="modal-report" class="modal-overlay">
    <div class="modal-content"
      style="width:95%; max-width:1200px; height:90vh; padding:0; display:flex; flex-direction:column; background:#fff;">
      <div
        style="padding:10px 15px; background:#f4f7fa; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
        <h2 style="margin:0; font-size:18px; color:#333;">Detailed Report</h2>
        <button onclick="closeReportModal()"
          style="background:transparent; border:none; font-size:24px; color:#555; cursor:pointer;">&times;</button>
      </div>
      <iframe id="report-iframe" src="about:blank" style="flex:1; border:none; width:100%; height:100%;"></iframe>
    </div>
  </div>

  <!-- PROFILE MODAL -->
  <div class="modal-overlay" id="profile-modal">
    <div class="modal-card" style="max-width:400px; height:auto; max-height:90%;">
      <div class="modal-header">
        <h3>Edit Profile</h3>
        <button class="modal-close" onclick="closeProfileModal()">&times;</button>
      </div>
      <div class="modal-body" style="padding:20px;">
        <form id="profile-form">
          <div style="text-align:center; margin-bottom:20px;">
            <img id="profile-preview" src=""
              style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:1px solid #ddd;">
            <div style="margin-top:10px;">
              <input type="file" id="prof-file" accept="image/*" style="font-size:12px;">
            </div>
          </div>
          <div class="form-group" style="margin-bottom:15px;">
            <label style="display:block; font-size:13px; margin-bottom:5px;">Name</label>
            <input type="text" id="prof-name"
              style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px;">
          </div>
          <div class="form-group" style="margin-bottom:15px;">
            <label style="display:block; font-size:13px; margin-bottom:5px;">Avatar URL</label>
            <input type="text" id="prof-avatar" placeholder="https://..."
              style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px;">
          </div>
          <div class="form-group" style="margin-bottom:20px;">
            <label style="display:block; font-size:13px; margin-bottom:5px;">New Password (Optional)</label>
            <input type="password" id="prof-password" placeholder="Leave blank to keep current"
              style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px;">
          </div>
          <div style="display:flex; gap:10px;">
            <button type="button" class="btn-primary" id="save-profile-btn" style="flex:1;">Save Changes</button>
            <button type="button" class="btn-ghost" onclick="location.href='logout.php'"
              style="flex:1; border-color:#ffcdd2; color:#c62828;">Logout</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL FOR TOOLS -->
  <div class="modal-overlay" id="tool-modal">
    <div class="modal-card">
      <div class="modal-header">
        <h3 id="modal-title">Tool Name</h3>
        <button class="modal-close" onclick="closeToolModal()">&times;</button>
      </div>
      <div class="modal-body">
        <iframe id="tool-iframe" class="modal-iframe"></iframe>
      </div>
    </div>
  </div>

  <!-- RUN SUMMARY MODAL -->
  <div class="modal-overlay" id="run-summary-modal">
    <div class="modal-card"
      style="max-width:400px; width:100%; border-radius:12px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.2);">
      <div class="modal-header" style="background:#f8f9fa; border-bottom:1px solid #eee; padding:12px 20px;">
        <h3 id="summary-title" style="margin:0; font-size:18px; color:#333;">Run Complete</h3>
        <button class="btn-ghost" onclick="closeSummaryModal()"
          style="font-size:24px; padding:0; width:30px; height:30px; line-height:30px; border:none;">&times;</button>
      </div>
      <div class="modal-body" style="padding:24px 20px;">

        <!-- Icon -->
        <div style="text-align:center; margin-bottom:20px;">
          <div id="summary-icon" style="font-size:42px;"></div>
        </div>

        <!-- Stats Grid (2x2) -->
        <div class="stat-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:20px;">
          <div class="stat-card" style="background:#f5f7fa; padding:12px; border-radius:8px; text-align:center;">
            <div class="stat-value" id="sum-total" style="font-size:20px; font-weight:bold; color:#333;">0</div>
            <div class="stat-label" style="font-size:12px; color:#666; text-transform:uppercase; letter-spacing:0.5px;">
              Total</div>
          </div>
          <div class="stat-card" style="background:#e8f5e9; padding:12px; border-radius:8px; text-align:center;">
            <div class="stat-value" id="sum-passed" style="font-size:20px; font-weight:bold; color:#2e7d32;">0</div>
            <div class="stat-label"
              style="font-size:12px; color:#1b5e20; text-transform:uppercase; letter-spacing:0.5px;">Passed</div>
          </div>
          <div class="stat-card" style="background:#ffebee; padding:12px; border-radius:8px; text-align:center;">
            <div class="stat-value" id="sum-failed" style="font-size:20px; font-weight:bold; color:#c62828;">0</div>
            <div class="stat-label"
              style="font-size:12px; color:#b71c1c; text-transform:uppercase; letter-spacing:0.5px;">Failed</div>
          </div>
          <div class="stat-card" style="background:#fff3e0; padding:12px; border-radius:8px; text-align:center;">
            <div class="stat-value" id="sum-open" style="font-size:20px; font-weight:bold; color:#ef6c00;">0</div>
            <div class="stat-label"
              style="font-size:12px; color:#e65100; text-transform:uppercase; letter-spacing:0.5px;">Open Issues</div>
          </div>
        </div>

        <!-- Action -->
        <div style="text-align:center;">
          <button class="btn-primary" onclick="closeSummaryModal()"
            style="width:100%; border-radius:6px; padding:10px;">Close Summary</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const TOOL_DEFS = <?php echo json_encode($TOOL_DEFS, JSON_UNESCAPED_UNICODE); ?>;
    const TOOL_HTML = <?php echo json_encode($TOOLS_HTML, JSON_UNESCAPED_UNICODE); ?>;

    let ACTIVE_TOOL = null;
    let CONFIGS = [];
    let USERS = [];
    let RUNS = [];

    let chartPassFail = null;
    let chartRunStatus = null;
    let chartPassTrend = null;

    function computeRunAggregates(list) {
      const target = list || RUNS;
      const agg = {
        totalTests: 0,
        passed: 0,
        failed: 0,
        open: 0,
        statusCounts: {}
      };
      target.forEach(r => {
        const total = Number(r.total_tests || 0);
        const passed = Number(r.passed || 0);
        const failed = Number(r.failed || 0);
        const open = Number(r.open_issues || 0);
        agg.totalTests += total;
        agg.passed += passed;
        agg.failed += failed;
        agg.open += open;
        const key = ((r.status || 'unknown') + '').toLowerCase();
        agg.statusCounts[key] = (agg.statusCounts[key] || 0) + 1;
      });
      return agg;
    }

    function updateChartsFromRuns(runList = null) {
      if (typeof Chart === 'undefined') return;
      const listToUse = runList || RUNS;
      const agg = computeRunAggregates(listToUse);

      /* Old Pass/Fail Pie Chart Removed - Now Handled by loadStats with server data */
      const passFailCanvas = document.getElementById('chart-pass-fail');
      // We clear it here just in case, but actual rendering is now in loadStats to use tool breakdown
      // Actually, if we don't clear it, the old logic might overwrite? 
      // We are removing the logic, so it won't overwrite.
      // But we should ensure the canvas exists.


      const statusCanvas = document.getElementById('chart-run-status');
      if (statusCanvas) {
        const labels = Object.keys(agg.statusCounts);
        const values = labels.map(k => agg.statusCounts[k]);
        if (chartRunStatus) chartRunStatus.destroy();
        chartRunStatus = new Chart(statusCanvas.getContext('2d'), {
          type: 'pie',
          data: {
            labels,
            datasets: [{ data: values.length ? values : [0] }]
          },
          options: {
            plugins: { legend: { display: true, position: 'bottom' } },
            maintainAspectRatio: false
          }
        });
      }

      const trendCanvas = document.getElementById('chart-pass-trend');
      if (trendCanvas) {
        const sorted = listToUse.slice().sort((a, b) => {
          const da = (a.run_date || '').toString();
          const db = (b.run_date || '').toString();
          return da.localeCompare(db);
        });
        const recent = sorted.slice(-10);
        const labels = recent.map(r => r.run_date);
        const values = recent.map(r => {
          const total = Number(r.total_tests || 0);
          const passed = Number(r.passed || 0);
          if (!total) return 0;
          return Math.round((passed / total) * 100);
        });
        if (chartPassTrend) chartPassTrend.destroy();
        chartPassTrend = new Chart(trendCanvas.getContext('2d'), {
          type: 'line',
          data: {
            labels,
            datasets: [{ label: 'Pass rate %', data: values, tension: 0.2 }]
          },
          options: {
            scales: { y: { beginAtZero: true, max: 100 } },
            plugins: { legend: { display: false } },
            maintainAspectRatio: false
          }
        });
      }
    }

    async function api(action, payload) {
      const res = await fetch('?api=' + encodeURIComponent(action), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload || {})
      });
      if (!res.ok) throw new Error('API ' + action + ' failed');
      return res.json();
    }

    /* Tabs */
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
      });
    });

    /* Modules grid */
    const modulesGrid = document.getElementById('modules-grid');
    TOOL_DEFS.forEach((t) => {
      const div = document.createElement('div');
      div.className = 'module-tile';
      div.dataset.code = t.code;
      div.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;">
      <div>
        <div class="module-title">${t.name}</div>
        <div class="module-meta">Tool code: ${t.code}</div>
      </div>
      <label style="display:flex;align-items:center;gap:4px;font-size:11px;color:#607D8B;">
        <input type="checkbox" class="module-run-checkbox" data-code="${t.code}">
        <span>Run</span>
      </label>
    </div>`;
      div.addEventListener('click', (ev) => {
        if (ev.target.closest('input[type="checkbox"]')) return;
        selectModule(t.code);
      });
      modulesGrid.appendChild(div);
    });

    const iframe = document.getElementById('tool-iframe');


    function parseConfigObject(cfg) {
      if (!cfg || !cfg.config_json) return {};
      try {
        const obj = JSON.parse(cfg.config_json || '{}');
        return obj && typeof obj === 'object' ? obj : {};
      } catch (e) {
        console.error('Invalid config_json for', cfg.tool_code, e);
        return {};
      }
    }

    function applyConfigToTool(doc, code, cfgObj) {
      const inputs = (cfgObj.inputs || '').toString();

      switch (code) {
        case 'brand':
        case 'category':
        case 'cms':
        case 'sku': {
          const el = doc.getElementById('urlInput');
          if (el) el.value = inputs;
          break;
        }
        case 'stock':
        case 'getcategories':
        case 'images':
        case 'products':
        case 'sub_category':
        case 'category_filter': {
          const ta = doc.getElementById('urls');
          if (ta) ta.value = inputs;
          break;
        }
        case 'price_checker': {
          const ta = doc.getElementById('cmsInput');
          if (ta) ta.value = inputs;
          break;
        }
        case 'login': {
          const ta = doc.getElementById('bulk');
          if (ta) ta.value = inputs;
          break;
        }
        case 'add_to_cart': {
          const skuTa = doc.getElementById('skus');
          if (skuTa) skuTa.value = inputs;
          if (cfgObj.qty && doc.getElementById('qty')) {
            doc.getElementById('qty').value = cfgObj.qty;
          }
          break;
        }
        default: {
          const ta = doc.querySelector('textarea');
          if (ta) ta.value = inputs;
          break;
        }
      }
    }

    async function runToolWithConfig(code, cfg) {
      return new Promise((resolve) => {
        const cfgObj = parseConfigObject(cfg);

        function onLoad() {
          iframe.removeEventListener('load', onLoad);

          try {
            const w = iframe.contentWindow;
            const doc = iframe.contentDocument || w.document;

            applyConfigToTool(doc, code, cfgObj);

            let runFn = w.run || w.Run || w.start || w.execute;
            if (!runFn) {
              if (code === 'cms' && typeof w.startCrawling === 'function') {
                runFn = w.startCrawling;
              }
            }
            if (typeof runFn !== 'function') {
              console.warn('No runnable function for tool', code);
              resolve({ tests: 0, passed: 0, failed: 1, open: 1, rows: [] });
              return;
            }

            let finished = false;

            function collectResults() {
              if (finished) return;
              finished = true;

              let tests = 0, passed = 0, failed = 0, open = 0;
              const rowsOut = [];

              if (Array.isArray(w.rows)) {
                w.rows.forEach(r => {
                  if (!r) return;
                  const status = (r.status || '').toString();
                  const url = r.link || r.url || r.href || r.cms || r.endpoint || '';
                  const parent = r.parent || r.source || r.origin || '';
                  const row = { status, url, parent };
                  try {
                    row.payload = JSON.stringify(r);
                  } catch (e) {
                    row.payload = null;
                  }
                  rowsOut.push(row);

                  const s = status.toUpperCase();
                  if (!s) return;
                  tests++;
                  if (s === 'OK' || s === 'VALID' || s === 'SUCCESS' || s === 'IN STOCK') passed++;
                  else { failed++; open++; }
                });
              } else {
                const els = doc.querySelectorAll('[data-status]');
                els.forEach(el => {
                  const status = (el.getAttribute('data-status') || '').toString();
                  const s = status.toUpperCase();
                  let url = el.getAttribute('data-url') || '';
                  if (!url) {
                    const a = el.querySelector('a');
                    if (a && a.href) url = a.href;
                  }
                  const parent = el.getAttribute('data-parent') || '';
                  const row = { status, url, parent, payload: null };
                  rowsOut.push(row);

                  if (!s) return;
                  tests++;
                  if (s === 'OK' || s === 'VALID' || s === 'SUCCESS' || s === 'IN STOCK') passed++;
                  else { failed++; open++; }
                });
              }

              resolve({ tests, passed, failed, open, rows: rowsOut });
            }

            (async () => {
              try {
                const res = runFn();
                if (res && typeof res.then === 'function') {
                  await res;
                }

                const loadingEl = doc.getElementById('loading');
                if (loadingEl) {
                  const checkDone = () => {
                    const style = getComputedStyle(loadingEl);
                    if (style.display === 'none' || style.display === '') {
                      setTimeout(collectResults, 300);
                    } else {
                      setTimeout(checkDone, 400);
                    }
                  };
                  checkDone();
                } else {
                  setTimeout(collectResults, 2000);
                }
              } catch (e) {
                console.error(e);
                resolve({ tests: 0, passed: 0, failed: 1, open: 1, rows: [] });
              }
            })();
          } catch (e) {
            console.error(e);
            resolve({ tests: 0, passed: 0, failed: 1, open: 1, rows: [] });
          }
        }

        iframe.addEventListener('load', onLoad);
        loadToolIntoIframe(code);
      });
    }

    function loadToolIntoIframe(code) {
      const html = TOOL_HTML[code];
      if (!html) {
        iframe.srcdoc = `<html><body style="font-family:sans-serif;padding:12px;">
      <p>No HTML embedded for tool: <b>${code}</b>.</p>
    </body></html>`;
        return;
      }
      iframe.srcdoc = html;
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€ UI Controls â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function openToolModal(code) {
      const def = TOOL_DEFS.find(t => t.code === code);
      document.getElementById('modal-title').innerText = def ? def.name : code;
      const modal = document.getElementById('tool-modal');
      modal.classList.add('active');
      loadToolIntoIframe(code);
    }

    function closeToolModal() {
      document.getElementById('tool-modal').classList.remove('active');
      iframe.srcdoc = ''; // clear
    }

    function selectModule(code) {
      openToolModal(code);
    }

    /* Open all tools quickly (no automation, just navigation) */
    document.getElementById('btn-select-all-modules').addEventListener('click', () => {
      const boxes = document.querySelectorAll('.module-run-checkbox');
      const allChecked = [...boxes].every(b => b.checked);
      boxes.forEach(b => b.checked = !allChecked);
    });

    function enterConsoleMode() {
      document.body.classList.add('console-mode');
      const c = document.getElementById('dash-console');
      c.innerHTML = '';
      logToConsole('Console Mode Active. Initializing...', 'info');
    }

    function exitConsoleMode() {
      document.body.classList.remove('console-mode');
    }
    window.exitConsoleMode = exitConsoleMode;
    window.closeToolModal = closeToolModal;

    function logToConsole(msg, type = 'info') {
      const c = document.getElementById('dash-console');
      // c.style.display = 'block'; // Always block in focus mode
      const div = document.createElement('div');
      div.className = 'log-line log-' + type;
      div.innerText = `[${new Date().toLocaleTimeString()}] ${msg}`;
      c.appendChild(div);
      c.scrollTop = c.scrollHeight;
    }

    function closeSummaryModal() {
      document.getElementById('run-summary-modal').classList.remove('active');
    }
    window.closeSummaryModal = closeSummaryModal;

    document.getElementById('btn-run-all').addEventListener('click', async () => {
      // Collect selected tools from checkboxes
      const selectedCodes = [...document.querySelectorAll('.module-run-checkbox:checked')].map(cb => cb.dataset.code);
      if (!selectedCodes.length) {
        alert('Please select at least one module to run using the checkboxes.');
        return;
      }

      // ENTER FOCUS MODE
      enterConsoleMode();
      logToConsole(`Starting Run All... Selected tools: ${selectedCodes.length}`);

      const btn = document.getElementById('btn-run-all');
      btn.disabled = true;

      let totalTests = 0, totalPassed = 0, totalFailed = 0, totalOpen = 0;
      const allDetails = [];

      // Get current user for config prioritization (Admin loaded vs Personal vs Global)
      const curUserInfo = await api('get-profile');
      const curUserId = curUserInfo.id;
      const isAdminRun = (curUserInfo.role === 'admin');

      for (const code of selectedCodes) {
        logToConsole(`Preparing ${code}...`, 'info');

        // Find best config
        // Filter enabled configs for this tool
        const toolConfigs = CONFIGS.filter(c => c.tool_code === code && c.is_enabled == 1);

        let cfg = null;
        if (isAdminRun) {
          // 1. Admin Loaded
          cfg = toolConfigs.find(c => c.admin_user_id == curUserId);
          // 2. Personal (if any)
          if (!cfg) cfg = toolConfigs.find(c => c.user_id == curUserId);
          // 3. Global
          if (!cfg) cfg = toolConfigs.find(c => !c.user_id);
          // 4. Any (Fallback)
          if (!cfg) cfg = toolConfigs[0];
        } else {
          // Tester: Own first (guaranteed by list-configs order usually, but let's be explicit)
          cfg = toolConfigs.find(c => c.user_id == curUserId);
          if (!cfg) cfg = toolConfigs.find(c => !c.user_id); // Global
          if (!cfg) cfg = toolConfigs[0];
        }

        // Validate Input existence
        let inputsValid = false;
        if (cfg) {
          try {
            const parsed = JSON.parse(cfg.config_json || '{}');
            const inp = (parsed.inputs || '').trim();
            if (inp.length > 0) inputsValid = true;
          } catch (e) { }
        }

        if (!cfg || !inputsValid) {
          logToConsole(`SKIPPED ${code}: Missing or empty configuration inputs.`, 'error');
          totalFailed++;
          totalOpen++;
          continue;
        }

        try {
          if (cfg) {
             const parsed = JSON.parse(cfg.config_json || '{}');
             logToConsole(`Inputs for ${code}:\n${parsed.inputs || '(none)'}`, 'info');
          }
          logToConsole(`Running ${code}...`, 'info');
          const result = await runToolWithConfig(code, cfg);
          totalTests += result.tests || 0;
          totalPassed += result.passed || 0;
          totalFailed += result.failed || 0;
          totalOpen += result.open || 0;
          allDetails.push({
            tool_code: code,
            rows: result.rows || []
          });

          // LOG DETAILS
          if (result.rows && result.rows.length) {
            result.rows.forEach(r => {
              const s = r.status.toUpperCase();
              const isFail = ['FAILED', 'ERROR', 'OUT OF STOCK'].includes(s);
              const type = isFail ? 'warn' : 'success';
              const icon = isFail ? 'âŒ' : 'âœ…';
              logToConsole(`  -> ${icon} [${s}] ${r.url}`, type);
            });
          }

          if (result.failed > 0) {
            logToConsole(`${code} Finished: ${result.passed} Pass, ${result.failed} Fail`, 'error');
          } else {
            logToConsole(`${code} Finished: ${result.passed} Pass, ${result.failed} Fail`, 'success');
          }
          
          logToConsole(`Result: ${JSON.stringify(result, null, 2)}`, 'info');

        } catch (e) {
          console.error('Error running tool', code, e);
          logToConsole(`Error running ${code}: ${e.message}`, 'error');
          totalFailed += 1;
          totalOpen += 1;
        }
      }

      const status = totalFailed > 0 ? 'failed' : 'passed';
      const notes = 'Run All Tests via dashboard (tools: ' + selectedCodes.join(', ') + ')';

      try {
        logToConsole('Saving Run Report...', 'info');
        // Flatten results for backend
        const flatResults = [];
        allDetails.forEach(d => {
          if (d.rows && d.rows.length) {
            d.rows.forEach(r => {
              flatResults.push({
                tool_code: d.tool_code,
                status: r.status,
                url: r.url,
                parent: r.parent,
                payload: r
              });
            });
          }
        });

        await api('save-run', {
          status: status,
          total_tests: totalTests,
          passed: totalPassed,
          failed: totalFailed,
          open_issues: totalOpen,
          notes: notes,
          results: flatResults
        });
        await Promise.all([loadRuns(), loadStats()]);
        logToConsole('Run Saved Successfully.', 'success');
        logToConsole('Run All Tests completed.', 'info');

        // Auto-close console and show summary
        setTimeout(() => {
          exitConsoleMode();

          // Show Summary Modal
          document.getElementById('sum-total').innerText = totalTests;
          document.getElementById('sum-passed').innerText = totalPassed;
          document.getElementById('sum-failed').innerText = totalFailed;
          document.getElementById('sum-open').innerText = totalOpen;

          const title = document.getElementById('summary-title');
          const icon = document.getElementById('summary-icon');
          if (totalFailed > 0) {
            title.innerText = 'Run Completed with Errors';
            title.style.color = '#d32f2f';
            icon.innerHTML = '&#9888;&#65039;'; // Warning Emoji
          } else {
            title.innerText = 'Run Passed Successfully';
            title.style.color = '#2e7d32';
            icon.innerHTML = '&#9989;'; // Check Mark Emoji
          }

          document.getElementById('run-summary-modal').classList.add('active');

        }, 1500); // Short delay to let user see "Saved" message

      } catch (e) {
        console.error(e);
        logToConsole('Error Saving Run: ' + e.message, 'error');
        alert('Error saving run: ' + e.message);
      } finally {
        btn.disabled = false;
      }
    });

    /* Test runs */
    function renderRunsTable(list) {
      const tbody = document.querySelector('#runs-table tbody');
      tbody.innerHTML = '';
      list.forEach(r => {
        const tr = document.createElement('tr');
        tr.dataset.id = r.id;
        // Show tools mini badges
        let toolBadges = '';
        if (r.tools) {
          const tCodes = r.tools.split(',');
          if (tCodes.length > 3) toolBadges = `<span class="badge" style="background:#eee;color:#555">${tCodes.length} tools</span>`;
          else toolBadges = tCodes.map(c => `<span class="badge" style="background:#eef;color:#444">${c}</span>`).join(' ');
        }
        // User badge
        const userName = r.user_name || 'Unknown';
        const userHtml = `<div style="font-weight:bold; font-size:12px; color:#455a64;">${userName}</div>`;

        tr.innerHTML = `
      <td><input type="checkbox" class="run-checkbox" value="${r.id}" onclick="updateBulkAction()"></td>
      <td>${r.id}</td>
      <td>
        <div>${r.run_date}</div>
        <div style="margin-top:2px;">${toolBadges}</div>
      </td>
      <td>${userHtml}</td>
      <td><span class="badge badge-${r.status === 'passed' ? 'pass' : 'fail'}">${r.status}</span></td>
      <td>${r.total_tests}</td>
      <td>${r.passed}</td>
      <td>${r.failed}</td>
      <td>${r.open_issues}</td>
      <td>${r.notes ? r.notes : ''}</td>
      <td class="table-actions"><button data-action="delete">Delete</button></td>
      <td><button class="btn-small btn-secondary" onclick="openReportModal(${r.id})">Report</button></td>`;
        tbody.appendChild(tr);
      });
      // Reset bulk selection UI when re-rendering
      updateBulkAction();
      const selectAll = document.getElementById('select-all-runs');
      if (selectAll) selectAll.checked = false;
    }

    function applyRunFilters() {
      const st = document.getElementById('filter-status').value.toLowerCase();
      const tc = document.getElementById('filter-tool').value;

      const uid = document.getElementById('filter-user').value;

      // Update Database Stats (Cards) based on selected User
      // Update Database Stats (Cards) AND Tool Chart based on selected filters
      loadStats(uid, st, tc);

      const filtered = RUNS.filter(r => {
        // Status filter
        if (st && (r.status || '').toLowerCase() !== st) return false;
        // Tool filter
        if (tc) {
          const runTools = (r.tools || '').split(',').map(t => t.trim());
          if (!runTools.includes(tc)) return false;
        }
        // User Filter (Client side for the loaded batch)
        if (uid) {
          if (r.user_id != uid) return false;
        }
        return true;
      });
      document.getElementById('filtered-total').textContent = filtered.length;
      renderRunsTable(filtered);

      // Update Charts based on the VISIBLE set (Filtered)
      updateChartsFromRuns(filtered);
    }

    function downloadRunsCSV() {
      const st = document.getElementById('filter-status').value.toLowerCase();
      const tc = document.getElementById('filter-tool').value;
      const uid = document.getElementById('filter-user').value;

      // Re-filter to ensure we export what is seen (or we could store filtered state)
      const filtered = RUNS.filter(r => {
        if (st && (r.status || '').toLowerCase() !== st) return false;
        if (tc) {
          const runTools = (r.tools || '').split(',');
          if (!runTools.includes(tc)) return false;
        }
        if (uid && r.user_id != uid) return false;
        return true;
      });

      if (!filtered.length) {
        alert('No runs to export');
        return;
      }

      // Header
      const headers = ['ID', 'Date', 'User', 'Status', 'Tools', 'Total Tests', 'Passed', 'Failed', 'Open Issues', 'Notes'];
      const csvRows = [headers.join(',')];

      filtered.forEach(r => {
        const tools = (r.tools || '').replace(/,/g, ';'); // escape commas
        const note = (r.notes || '').replace(/"/g, '""');
        const row = [
          r.id,
          r.run_date,
          '"' + (r.user_name || '') + '"',
          r.status,
          `"${tools}"`,
          r.total_tests,
          r.passed,
          r.failed,
          r.open_issues,
          `"${note}"`
        ];
        csvRows.push(row.join(','));
      });

      const blob = new Blob(['\uFEFF' + csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.setAttribute('hidden', '');
      a.setAttribute('href', url);
      const dateStr = new Date().toISOString().slice(0, 10);
      a.setAttribute('download', `QA_Test_Runs_Summary_${dateStr}.csv`);
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }

    function setRunFilter(status) {
      const sel = document.getElementById('filter-status');
      if (sel) {
        sel.value = status;
        applyRunFilters();
        // Scroll to table
        document.getElementById('runs-table').scrollIntoView({ behavior: 'smooth' });
      }
    }

    /* Test runs */
    async function loadRuns() {
      RUNS = await api('list-runs');
      updateChartsFromRuns();

      // Populate Tool Filter Dropdown if empty
      const tf = document.getElementById('filter-tool');
      if (tf && tf.options.length <= 1) {
        TOOL_DEFS.forEach(t => {
          const opt = document.createElement('option');
          opt.value = t.code;
          opt.innerText = t.name;
          tf.appendChild(opt);
        });
      }

      // Populate User Filter if visible (Admins/Viewers)
      const uf = document.getElementById('filter-user');
      if (uf && uf.offsetParent !== null && uf.options.length <= 1) {
        const allUsers = await api('list-users');
        allUsers.forEach(u => {
          const opt = document.createElement('option');
          opt.value = u.id;
          opt.innerText = u.name;
          uf.appendChild(opt);
        });
      }

      applyRunFilters();
    }

    document.getElementById('filter-status').addEventListener('change', applyRunFilters);
    document.getElementById('filter-tool').addEventListener('change', applyRunFilters);
    document.getElementById('filter-user').addEventListener('change', applyRunFilters);

    async function showRunDetails(runId) {
      const panel = document.getElementById('run-details-panel');
      const container = document.getElementById('run-details-content');
      if (!panel || !container) return;

      container.innerHTML = '<p class="text-muted">Loading run details...</p>';
      panel.style.display = 'block';

      try {
        const rows = await api('run-details', { id: runId });
        if (!rows || !rows.length) {
          container.innerHTML = '<p class="text-muted">No detailed link data stored for this run.</p>';
          return;
        }
        const header = '<table class="run-details-table"><thead><tr><th>Tool</th><th>Status</th><th>URL</th><th>Parent</th></tr></thead><tbody>';
        const body = rows.map(r => {
          const status = (r.status || '').toString();
          let badgeClass = 'run-details-badge';
          const upper = status.toUpperCase();
          if (upper === 'OK') badgeClass += ' ok';
          else badgeClass += ' fail';
          const safeTool = (r.tool_code || '').toString();
          const safeUrl = (r.url || '').toString();
          const safeParent = (r.parent || '').toString();
          return `<tr>
        <td>${safeTool}</td>
        <td><span class="${badgeClass}">${status}</span></td>
        <td>${safeUrl}</td>
        <td>${safeParent}</td>
      </tr>`;
        }).join('');
        container.innerHTML = header + body + '</tbody></table>';
      } catch (e) {
        console.error(e);
        container.innerHTML = '<p class="text-danger">Failed to load run details: ' + e.message + '</p>';
      }
    }

    document.querySelector('#runs-table').addEventListener('click', async (e) => {
      const btn = e.target.closest('button');
      if (btn && btn.dataset.action === 'delete') {
        const tr = btn.closest('tr'); const id = parseInt(tr.dataset.id, 10);
        if (!confirm('Delete this test run?')) return;
        await api('delete-run', { id: id });
        await Promise.all([loadRuns(), loadStats()]);
        const panel = document.getElementById('run-details-panel');
        if (panel) panel.style.display = 'none';
        return;
      }

      const tr = e.target.closest('tr');
      if (!tr || !tr.dataset.id) return;
      const runId = parseInt(tr.dataset.id, 10);
      if (!runId) return;
      await showRunDetails(runId);
    });



    /* Configs */
    /* Configs */
    async function loadConfigs() {
      CONFIGS = await api('list-configs');

      // Populate Filter Dropdown (extract unique owners)
      const filterSel = document.getElementById('filter-config-owner');
      if (filterSel) {
        const currentVal = filterSel.value;
        // specific owners
        const owners = new Map(); // id -> name
        CONFIGS.forEach(c => {
          if (c.user_id) owners.set(c.user_id, c.user_name || 'User ' + c.user_id);
          else owners.set('global', 'Global');
        });

        // Clear except first
        while (filterSel.options.length > 1) filterSel.remove(1);

        // Add Global first if exists
        if (owners.has('global')) {
          const opt = document.createElement('option');
          opt.value = 'global';
          opt.innerText = 'Global';
          filterSel.appendChild(opt);
          owners.delete('global');
        }

        // Add others
        owners.forEach((name, id) => {
          const opt = document.createElement('option');
          opt.value = id;
          opt.innerText = name;
          filterSel.appendChild(opt);
        });


        filterSel.value = currentVal; // restore selection
      }

      await renderConfigsTable();
    }

    async function renderConfigsTable() {
      const curUser = await api('get-profile');
      const curUid = curUser.id;
      const isAdmin = (curUser.role === 'admin');

      // Admin Editor Dropdown Logic
      const ownerSel = document.getElementById('cfg-owner');
      const ownerWrap = document.getElementById('cfg-owner-wrapper');
      if (isAdmin && ownerSel && ownerSel.options.length <= 1) {
        const allUsers = await api('list-users');
        allUsers.forEach(u => {
          const opt = document.createElement('option');
          opt.value = u.id;
          opt.innerText = u.name + ' (User)';
          ownerSel.appendChild(opt);
        });
        ownerWrap.style.display = 'block';
      }

      // Filter Logic
      const filterVal = document.getElementById('filter-config-owner') ? document.getElementById('filter-config-owner').value : '';

      const tbody = document.querySelector('#configs-table tbody');
      tbody.innerHTML = '';

      CONFIGS.forEach((cfg, i) => {
        // Filter Check
        if (filterVal) {
          if (filterVal === 'global') {
            if (cfg.user_id) return;
          } else {
            if (cfg.user_id != filterVal) return;
          }
        }

        let snippet = '';
        try {
          const json = JSON.parse(cfg.config_json || '{}');
          snippet = (json.inputs || '').toString().slice(0, 60).replace(/\s+/g, ' ');
        } catch (e) { }

        let ownerBadge = '';
        if (!cfg.user_id) {
          ownerBadge = '<span class="badge" style="background:#2196f3; color:white;">Global</span>';
        } else if (cfg.user_id == curUid) {
          ownerBadge = '<span class="badge" style="background:#4caf50; color:white;">You</span>';
        } else {
          ownerBadge = `<span class="badge" style="background:#eee; color:#555;">${cfg.user_name || 'User ' + cfg.user_id}</span>`;
        }

        // Actions Visibility
        // Admin: All
        // Tester: Only if Own
        let actions = '';
        // Load Button Logic (Admin Only)
        // Visible only if filter is NOT 'All Users' (empty)
        // And accessible for Global (user_id=null) or User configs (if desired)
        // Request: "visible for Global and specific Users selections, hidden for All Users"
        let loadBtn = '';
        if (isAdmin && filterVal !== '') {
          // Check if already loaded? (admin_user_id == curUid)
          // We don't have admin_user_id in the JS object yet, need to ensuring list-configs returns it.
          // Backend `SELECT c.*` returns it.
          const isLoaded = (cfg.admin_user_id == curUid);
          const btnText = isLoaded ? 'Loaded' : 'Load';
          const btnClass = isLoaded ? 'btn-success' : 'btn-secondary'; // Helper classes needed or inline style
          const style = isLoaded ? 'background:#28a745;color:white' : 'background:#6c757d;color:white';

          loadBtn = `<button data-action="load-config" style="${style}">${btnText}</button>`;
        }

        if (isAdmin || cfg.user_id == curUid) {
          actions = `
             ${loadBtn}
             <button data-action="edit">Edit</button>
             <button data-action="delete" class="text-danger">Delete</button>
           `;
        } else {
          actions = `
            ${loadBtn}
            <span class="text-muted" style="font-size:11px;">Read-only</span>
          `;
        }

        const tr = document.createElement('tr');
        tr.dataset.id = cfg.id;
        tr.innerHTML = `
      <td>${i + 1}</td>
      <td><strong>${cfg.config_name}</strong></td>
      <td>${ownerBadge}</td>
      <td>${cfg.tool_code}</td>
      <td>${cfg.is_enabled ? 'Yes' : 'No'}</td>
      <td class="text-muted" style="font-family:monospace; font-size:12px;">${snippet}</td>
      <td class="table-actions">
        ${actions}
      </td>`;
        tbody.appendChild(tr);
      });
    }

    document.querySelector('#configs-table').addEventListener('click', async (e) => {
      const btn = e.target.closest('button'); if (!btn) return;
      const tr = btn.closest('tr'); const id = parseInt(tr.dataset.id, 10);
      const cfg = CONFIGS.find(c => c.id == id); if (!cfg) return;

      if (btn.dataset.action === 'edit') {
        document.getElementById('cfg-id').value = cfg.id;
        document.getElementById('cfg-name').value = cfg.config_name;
        document.getElementById('cfg-tool-code').value = cfg.tool_code;
        document.getElementById('cfg-enabled').checked = !!cfg.is_enabled;
        let inputs = '';
        try {
          const json = JSON.parse(cfg.config_json || '{}');
          inputs = json.inputs || '';
        } catch (e) { }
        document.getElementById('cfg-inputs').value = inputs;

        // Populate Owner if visible
        const ownerSel = document.getElementById('cfg-owner');
        if (ownerSel) {
          // If user_id is null/undefined -> Global ("")
          // Else -> user_id
          ownerSel.value = cfg.user_id ? cfg.user_id : "";
        }
      } else if (btn.dataset.action === 'delete') {
        if (!confirm('Delete configuration "' + cfg.config_name + '"?')) return;
        await api('delete-config', { id: cfg.id });
        await loadConfigs();
      }
      else if (btn.dataset.action === 'load-config') {
        await api('assign-global-config', { config_id: cfg.id });
        // Refresh to show status
        await loadConfigs();
      }
    });

    document.getElementById('cfg-save-btn').addEventListener('click', async () => {
      const id = document.getElementById('cfg-id').value || null;
      const name = document.getElementById('cfg-name').value.trim();
      const tool = document.getElementById('cfg-tool-code').value;
      const inputs = document.getElementById('cfg-inputs').value.trim();
      const enabled = document.getElementById('cfg-enabled').checked;

      if (!name || !tool) {
        alert('Configuration name and tool are required.');
        return;
      }

      const ownerSel = document.getElementById('cfg-owner');
      let ownerId = undefined;
      if (ownerSel && ownerSel.offsetParent !== null) {
        ownerId = ownerSel.value === "" ? null : parseInt(ownerSel.value);
      }

      const payload = {
        id: id, tool_code: tool, config_name: name,
        config: { inputs: inputs }, is_enabled: enabled ? 1 : 0
      };
      if (ownerId !== undefined) {
        payload.owner_id = ownerId;
      }

      await api('save-config', payload);

      document.getElementById('config-form').reset();
      document.getElementById('cfg-id').value = '';
      document.getElementById('cfg-enabled').checked = true;
      await loadConfigs();
    });

    document.getElementById('cfg-reset-btn').addEventListener('click', () => {
      document.getElementById('config-form').reset();
      document.getElementById('cfg-id').value = '';
      document.getElementById('cfg-enabled').checked = true;
    });

    /* Users */
    async function loadUsers() {
      USERS = await api('list-users');
      const tbody = document.querySelector('#users-table tbody');
      tbody.innerHTML = '';
      USERS.forEach((u, i) => {
        const tr = document.createElement('tr');
        tr.dataset.id = u.id;
        tr.innerHTML = `
      <td>${i + 1}</td>
      <td>${u.name}</td>
      <td>${u.email}</td>
      <td>${u.role}</td>
      <td>${u.is_active ? 'Active' : 'Inactive'}</td>
      <td class="table-actions">
        <button data-action="edit">Edit</button>
        <button data-action="delete">Delete</button>
      </td>`;
        tbody.appendChild(tr);
      });
    }

    document.querySelector('#users-table').addEventListener('click', async (e) => {
      const btn = e.target.closest('button'); if (!btn) return;
      const tr = btn.closest('tr'); const id = parseInt(tr.dataset.id, 10);
      const u = USERS.find(x => x.id == id); if (!u) return;

      if (btn.dataset.action === 'edit') {
        document.getElementById('user-id').value = u.id;
        document.getElementById('user-name').value = u.name;
        document.getElementById('user-email').value = u.email;
        document.getElementById('user-role').value = u.role;
        document.getElementById('user-active').checked = !!u.is_active;
      } else if (btn.dataset.action === 'delete') {
        if (!confirm('Delete user \"' + u.name + '\"?')) return;
        await api('delete-user', { id: u.id });
        await loadUsers();
      }
    });

    document.getElementById('user-save-btn').addEventListener('click', async () => {
      const id = document.getElementById('user-id').value || null;
      const name = document.getElementById('user-name').value.trim();
      const email = document.getElementById('user-email').value.trim();
      const password = document.getElementById('user-password').value.trim();
      const role = document.getElementById('user-role').value;
      const active = document.getElementById('user-active').checked;

      if (!name || !email) {
        alert('Name and email are required.');
        return;
      }

      await api('save-user', { id: id, name: name, email: email, password: password, role: role, is_active: active ? 1 : 0 });

      document.getElementById('user-form').reset();
      document.getElementById('user-id').value = '';
      document.getElementById('user-active').checked = true;
      await loadUsers();
    });

    document.getElementById('user-reset-btn').addEventListener('click', () => {
      document.getElementById('user-form').reset();
      document.getElementById('user-id').value = '';
      document.getElementById('user-active').checked = true;
    });

    /* Stats */
    async function loadStats(userId = null, status = null, tool = null) {
      try {
        const payload = { user_id: userId, status: status, tool: tool };
        const s = await api('stats', payload);
        document.getElementById('stat-total').textContent = s.total_runs;
        document.getElementById('stat-passed').textContent = s.passed;
        document.getElementById('stat-failed').textContent = s.failed;
        document.getElementById('stat-open').textContent = s.open_issues;

        // New Metric Fields
        const rateEl = document.getElementById('stat-rate');
        if (rateEl) rateEl.textContent = s.pass_rate;

        const utilEl = document.getElementById('stat-utilized');
        if (utilEl) utilEl.textContent = s.utilized_tools;

        const cfgEl = document.getElementById('stat-configs');
        if (cfgEl) cfgEl.textContent = s.total_configs;

        const tixEl = document.getElementById('stat-tickets');
        if (tixEl) tixEl.textContent = s.total_tickets;

        // RENDER CLUSTERED BAR CHART (Tool vs Pass/Fail)
        if (typeof Chart !== 'undefined' && s.tool_stats) {
          const ctx = document.getElementById('chart-pass-fail');
          if (ctx) {
            const labels = s.tool_stats.map(t => t.tool_code); // Tool Codes on X-Axis
            const passData = s.tool_stats.map(t => t.p);
            const failData = s.tool_stats.map(t => t.f);

            if (chartPassFail) chartPassFail.destroy();

            chartPassFail = new Chart(ctx.getContext('2d'), {
              type: 'bar',
              data: {
                labels: labels,
                datasets: [
                  {
                    label: 'Passed',
                    data: passData,
                    backgroundColor: '#43A047'
                  },
                  {
                    label: 'Failed',
                    data: failData,
                    backgroundColor: '#E53935'
                  }
                ]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                  x: { stacked: true },
                  y: { stacked: true, beginAtZero: true }
                },
                plugins: {
                  legend: { position: 'bottom' }
                }
              }
            });
          }
        }

      } catch (e) {
        console.error('Stats error:', e);
      }
    }
    async function enforceRoleUI() {
      if (typeof CURRENT_USER_ROLE === 'undefined') return;
      const role = CURRENT_USER_ROLE.toLowerCase();

      const tabConfigs = document.querySelector('button[data-tab="configs"]');
      const tabUsers = document.querySelector('button[data-tab="users"]');
      const tabSupport = document.querySelector('button[data-tab="support"]');
      const suppAdmin = document.getElementById('support-admin-view');
      const contactForm = document.getElementById('contact-support-form');
      const myTickets = document.getElementById('my-support-tickets');

      const userFilter = document.getElementById('filter-user-container');

      // Default text
      if (tabSupport) tabSupport.textContent = 'Support';

      // User Filter Visibility
      if (role === 'admin' || role === 'viewer') {
        if (userFilter) userFilter.style.display = 'block';
      } else {
        if (userFilter) userFilter.style.display = 'none';
      }

      if (role === 'viewer') {
        if (tabConfigs) tabConfigs.style.display = 'none';
        if (tabUsers) tabUsers.style.display = 'none';
      } else if (role === 'tester') {
        if (tabUsers) tabUsers.style.display = 'none';
      }

      if (role === 'admin') {
        if (contactForm) contactForm.style.display = 'none';
        if (myTickets) myTickets.style.display = 'none'; // Admin doesn't need personal history here
        if (suppAdmin) {
          suppAdmin.style.display = 'block';
          loadSupport();
        }
        if (tabSupport) {
          tabSupport.textContent = 'Support Center';
          updateSupportBadge();
        }
      } else {
        // Non-admin (User)
        if (myTickets) {
          myTickets.style.display = 'block';
          loadMySupport();
        }
        if (tabSupport) {
          updateSupportBadge();
        }
      }
    }

    async function updateSupportBadge() {
      const tabSupport = document.querySelector('button[data-tab="support"]');
      if (!tabSupport) return;
      try {
        const c = await api('get-unread-support');
        // Remove old badge
        const old = tabSupport.querySelector('span');
        if (old) old.remove();

        if (c && c.count > 0) {
          tabSupport.innerHTML += ` <span id="supp-badge-count" style="background:red; color:white; padding:2px 6px; border-radius:10px; font-size:11px;">${c.count}</span>`;
        }
      } catch (e) { }
    }

    enforceRoleUI();

    /* Support Logic (Redesign) */

    // MOCK STATE REMOVED - Using Real Role
    let SUPP_ROLE = (typeof CURRENT_USER_ROLE !== 'undefined') ? CURRENT_USER_ROLE : 'viewer';
    // Map tester->user for internal logic consistency if needed, but 'tester' is fine.
    if (SUPP_ROLE === 'tester') SUPP_ROLE = 'user';

    let ACTIVE_TICKET = null;
    let TICKETS_CACHE = [];

    // Init
    document.addEventListener('DOMContentLoaded', () => {
      updateSupportUI();
      // Auto load if support tab is active (or just load it anyway)
      loadSupportData();
    });

    /* Toggle Removed */

    function updateSupportUI() {
      // Label
      const label = document.getElementById('supp-role-label');
      if (label) label.textContent = (SUPP_ROLE === 'admin') ? 'Support Agent (Admin)' : 'Support Interface';

      // Filters - Admin only
      const filters = document.getElementById('supp-filters');
      if (filters) filters.style.display = (SUPP_ROLE === 'admin') ? 'flex' : 'none';

      // New Ticket Button - User only
      const userActions = document.getElementById('supp-user-actions');
      if (userActions) userActions.style.display = (SUPP_ROLE === 'admin') ? 'none' : 'block';

      // Chat Actions (Quick Reply) - Admin only
      const chatActions = document.querySelector('.chat-actions');
      if (chatActions) chatActions.style.display = (SUPP_ROLE === 'admin') ? 'flex' : 'none';
    }

    // Load Tickets (Unified function for both roles for now, filtering handled by API/Mock)
    async function loadSupportData() {
      const listDiv = document.getElementById('supp-ticket-list');
      if (!listDiv) return;
      listDiv.innerHTML = '<div style="text-align:center; padding:20px; color:#999;">Loading...</div>';

      // In a real app, 'admin' calls list-support, 'user' calls my-support-history
      // Here we will fetch and manually enhance with mock data for the design
      let rawData = [];
      try {
        if (SUPP_ROLE === 'admin') {
          rawData = await api('list-support');
        } else {
          rawData = await api('my-support-history');
        }
      } catch (e) {
        console.error(e);
        listDiv.innerHTML = '<div style="text-align:center; color:red;">Error loading tickets</div>';
        return;
      }

      if (rawData.length === 0) {
        listDiv.innerHTML = '<div style="text-align:center; padding:20px; color:#888;">No tickets found.</div>';
        TICKETS_CACHE = [];
        return;
      }

      // ENHANCE DATA (Mocking missing fields for design)
      TICKETS_CACHE = rawData.map(t => {
        // Mock status/priority if not present
        if (!t.status) {
          // Infer from reply
          if (t.admin_reply) t.status = 'closed';
          else t.status = 'open';
        }
        // Priority now comes from DB (default 'low' if null)
        if (!t.priority) t.priority = 'low';
        return t;
      });

      renderTicketList(TICKETS_CACHE);
    }

    // Alias for existing calls
    window.loadSupport = loadSupportData;
    window.loadMySupport = loadSupportData;

    function renderTicketList(tickets) {
      const listDiv = document.getElementById('supp-ticket-list');
      listDiv.innerHTML = '';

      tickets.forEach(t => {
        const card = document.createElement('div');
        card.className = `ticket-card status-${t.priority || 'low'}`;
        card.onclick = () => selectTicket(t.id);
        if (ACTIVE_TICKET && ACTIVE_TICKET.id == t.id) card.classList.add('active');

        // Format Date
        const dateStr = t.created_at || 'Just now';

        // Status Badge logic
        let badgeClass = 'badge-low';
        if (t.priority === 'urgent') badgeClass = 'badge-urgent';
        if (t.priority === 'high') badgeClass = 'badge-high';
        if (t.priority === 'medium') badgeClass = 'badge-medium';

        // Unread Logic
        // Admin: is_read=0 is unread
        // User: is_read=1 is unread (replied by admin)
        const isUnread = (SUPP_ROLE === 'admin' && t.is_read == 0) || (SUPP_ROLE !== 'admin' && t.is_read == 1);
        const fontWeight = isUnread ? '700' : '400';
        const dot = isUnread ? '<span style="color:red; font-size:20px; line-height:0; position:absolute; top:10px; right:10px;">&bull;</span>' : '';

        card.innerHTML = `
               ${dot}
               <div class="t-header">
                  <span class="t-title" style="font-weight:${fontWeight}">Ticket #${t.id}</span>
                  <span class="t-badge ${badgeClass}">${t.priority || 'Normal'}</span>
               </div>
               <div class="t-user" style="font-size:12px; font-weight:${fontWeight};">${t.subject}</div>
               <div class="t-meta">${t.user_name || 'User'} &bull; ${dateStr}</div>
            `;
        listDiv.appendChild(card);
      });
    }

    async function selectTicket(id) {
      const t = TICKETS_CACHE.find(x => x.id == id);
      if (!t) return;
      ACTIVE_TICKET = t;

      // Mark as read logic
      // Admin reading (0->2), User reading (1->2)
      const isUnread = (SUPP_ROLE === 'admin' && t.is_read == 0) || (SUPP_ROLE !== 'admin' && t.is_read == 1);

      if (isUnread) {
        // Call API
        api('mark-support-read', { id: t.id });

        // Optimistic update
        t.is_read = 2;
        renderTicketList(TICKETS_CACHE); // Remove dot/bold

        // Decrement badge
        const badge = document.getElementById('supp-badge-count');
        if (badge) {
          let count = parseInt(badge.textContent || '0');
          if (count > 0) {
            count--;
            badge.textContent = count;
            if (count === 0) badge.remove();
          }
        }
      }

      // Update Sidebar Active State
      document.querySelectorAll('.ticket-card').forEach(c => c.classList.remove('active'));
      // Re-render list to show active state properly (or just toggle class if cached dom elements)
      renderTicketList(TICKETS_CACHE);

      // Update Chat Header
      document.getElementById('chat-ticket-subject').innerText = t.subject;
      document.getElementById('chat-ticket-id').innerText = `Ticket #${t.id} • ${t.user_name || 'User'}`;
      const statusBadge = document.getElementById('chat-ticket-status');
      if (SUPP_ROLE === 'admin') {
        const p = t.priority || 'low';
        statusBadge.className = '';
        statusBadge.innerHTML = `
            <select onchange="updateTicketPriority(${t.id}, this.value)" style="font-size:11px; padding:2px; border-radius:4px; border:1px solid #ccc;">
                <option value="low" ${p === 'low' ? 'selected' : ''}>LOW</option>
                <option value="medium" ${p === 'medium' ? 'selected' : ''}>MEDIUM</option>
                <option value="high" ${p === 'high' ? 'selected' : ''}>HIGH</option>
                <option value="urgent" ${p === 'urgent' ? 'selected' : ''}>URGENT</option>
            </select>
          `;
      } else {
        statusBadge.className = `t-badge badge-${t.priority || 'low'}`;
        statusBadge.innerText = (t.priority || 'Normal').toUpperCase();
      }

      // Render Messages
      const chatArea = document.getElementById('chat-messages-area');
      chatArea.innerHTML = '';

      // 1. Initial User Message
      const userMsg = document.createElement('div');
      userMsg.className = 'msg-bubble msg-user';
      userMsg.innerHTML = `
          ${t.message}
          <span class="msg-meta">${t.created_at}</span>
      `;
      chatArea.appendChild(userMsg);

      // 2. Parse Replies
      if (t.admin_reply) {
        // Regex to find "--- Role Reply ---" lines
        // We split by the separator pattern.
        // Pattern: \n\n--- (.*?) Reply ---\n
        // Note: The logic below is a simple parser.

        let fullText = t.admin_reply;
        // Split by the separator but keep the delimiter to know who sent it
        // actually standard split might suck here. Let's use matchAll or logical loop.

        // Better approach:
        const parts = fullText.split(/\n\n--- (.*?) Reply ---\n/g);
        // parts[0] might be empty or text before first separator?
        // If the reply starts with the separator, parts[0] is empty. 
        // parts[1] is Role (captured group), parts[2] is Message. parts[3] Role, parts[4] msg...

        // Example: "\n\n--- Admin Reply ---\nhello" -> ["", "Admin", "hello"]


        for (let i = 1; i < parts.length; i += 2) {
          const role = parts[i].trim(); // 'Admin' or 'User'
          const rawMsg = parts[i + 1];
          if (!rawMsg) continue;

          const msgDiv = document.createElement('div');
          // Explicit check
          const isAgent = (role === 'Admin');

          msgDiv.className = isAgent ? 'msg-bubble msg-agent' : 'msg-bubble msg-user';
          msgDiv.innerHTML = `
                ${rawMsg.trim()}
                <span class="msg-meta">${role}</span>
             `;
          chatArea.appendChild(msgDiv);
        }
      }

      // Auto scroll
      chatArea.scrollTop = chatArea.scrollHeight;
    }

    async function sendChatMessage() {
      if (!ACTIVE_TICKET) return alert('Select a ticket first');
      const input = document.getElementById('chat-reply-input');
      const text = input.value.trim();
      if (!text) return;

      // Admin -> reply-support
      // Current backend `reply-support` is only for admin to reply to a specific user ticket.
      // Users cannot "reply" to a ticket in this simple DB schema (it's 1 q, 1 a).

      // Unified reply logic
      const res = await api('reply-support', { id: ACTIVE_TICKET.id, reply: text });
      if (res.ok) {
        // Optimistic UI updates
        const chatArea = document.getElementById('chat-messages-area');
        const msg = document.createElement('div');
        msg.className = 'msg-bubble msg-agent'; // Agent style for self (or user style, doesn't matter much for self-view)
        // If user, maybe style differently? But maintaining one style for "Me" is fine.

        // Divider visual (mock)
        const roleName = (SUPP_ROLE === 'admin') ? 'Admin' : 'User';
        const fullText = `\n\n--- ${roleName} Reply ---\n${text}`;

        msg.innerHTML = `${fullText}<span class="msg-meta">Just now</span>`;
        chatArea.appendChild(msg);
        input.value = '';

        // Reload to get server timestamp/format eventually
        // loadSupportData(); 
      } else {
        alert('Error sending reply: ' + (res.error || 'Unknown'));
      }
    }

    function insertQuickReply(text) {
      const input = document.getElementById('chat-reply-input');
      input.value = text;
      input.focus();
    }

    function updateTicketPriority(id, prio) {
      if (!confirm('Change priority to ' + prio + '?')) return;
      api('update-support-priority', { id: id, priority: prio }).then(res => {
        if (res.ok) {
          // Update cache
          const t = TICKETS_CACHE.find(x => x.id == id);
          if (t) t.priority = prio;
          loadSupportData(); // Refresh list to update colors
        }
      });
    }

    function openNewTicketModal() {
      // Simple prompt for now or show the old form in a modal
      const subject = prompt("Ticket Subject:");
      if (!subject) return;
      const message = prompt("Message Details:");
      if (!message) return;

      api('save-support', { subject, message }).then(res => {
        if (res.ok) {
          alert('Ticket created');
          loadSupportData();
        } else {
          alert('Error creating ticket');
        }
      });
    }

    function filterSupport(type, btn) {
      document.querySelectorAll('.filter-tag').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      // Implement client-side filter
      if (type === 'all') renderTicketList(TICKETS_CACHE);
      if (type === 'open') renderTicketList(TICKETS_CACHE.filter(t => !t.admin_reply));
      if (type === 'pending') renderTicketList(TICKETS_CACHE.filter(t => !t.admin_reply)); // same as open for now
    }

    function escalateTicket() {
      alert('Escalated to Tier 2 (Mock)');
      if (ACTIVE_TICKET) {
        ACTIVE_TICKET.priority = 'urgent';
        renderTicketList(TICKETS_CACHE);
        selectTicket(ACTIVE_TICKET.id);
      }
    }

    function closeTicket() {
      if (!confirm('Close this ticket?')) return;
      // Mock close
      alert('Ticket closed');
    }
    /* Profile Logic */
    const profileModal = document.getElementById('profile-modal');
    const profileDropdown = document.getElementById('profile-dropdown');

    // Toggle Dropdown
    document.getElementById('profile-trigger').addEventListener('click', (e) => {
      // Only toggle if not clicking inside the dropdown
      if (e.target.closest('.profile-dropdown')) return;
      profileDropdown.classList.toggle('active');
      e.stopPropagation();
    });

    // Close Dropdown when clicking outside
    document.addEventListener('click', () => {
      profileDropdown.classList.remove('active');
    });

    // Edit Profile Click
    document.getElementById('menu-edit-profile').addEventListener('click', async (e) => {
      e.stopPropagation(); // Prevent bubbling to header toggle
      profileDropdown.classList.remove('active'); // Close menu

      const u = await api('get-profile');
      if (u && u.id) {
        document.getElementById('prof-name').value = u.name;
        document.getElementById('prof-avatar').value = u.avatar_url || '';
        document.getElementById('profile-preview').src = u.avatar_url || `https://ui-avatars.com/api/?name=${u.name}`;
        profileModal.classList.add('active'); // Open Modal
      }
    });

    function closeProfileModal() {
      profileModal.classList.remove('active');
    }

    document.getElementById('save-profile-btn').addEventListener('click', async () => {
      const name = document.getElementById('prof-name').value;
      let avatar = document.getElementById('prof-avatar').value;
      const pass = document.getElementById('prof-password').value;
      const fileInput = document.getElementById('prof-file');

      // Handle File Upload
      if (fileInput.files.length > 0) {
        const fd = new FormData();
        fd.append('avatar', fileInput.files[0]);
        try {
          const upRes = await fetch('?api=upload-avatar', { method: 'POST', body: fd });
          const upJson = await upRes.json();
          if (upJson.ok) {
            avatar = upJson.url;
          } else {
            alert('Upload failed: ' + upJson.error); return;
          }
        } catch (e) { alert('Upload failed'); return; }
      }

      const res = await api('update-profile', { name, avatar_url: avatar, password: pass });
      if (res.ok) {
        alert('Profile updated!');
        location.reload();
      } else {
        alert('Error: ' + (res.error || 'Unknown'));
      }
    });

    /* Report Modal Logic (Iframe) */
    function openReportModal(target) {
      const modal = document.getElementById('modal-report');
      const iframe = document.getElementById('report-iframe');
      if (!modal || !iframe) return;

      let url = '';
      if (typeof target === 'number' || (typeof target === 'string' && /^\d+$/.test(target))) {
        // It's a Run ID
        url = `qa_run_report.php?run_id=${target}`;
      } else if (typeof target === 'string' && target.startsWith('http')) {
        url = target;
      } else if (typeof target === 'string') {
        // Assume relative URL
        url = target;
      }

      if (url) {
        iframe.src = url;
        modal.classList.add('active');
      }
    }

    function closeReportModal() {
      const modal = document.getElementById('modal-report');
      const iframe = document.getElementById('report-iframe');
      if (modal) modal.classList.remove('active');
      if (iframe) iframe.src = 'about:blank';
    }

    window.triggerOpenIssuesReport = function () {
      const userFilter = document.getElementById('filter-user');
      let url = 'qa_run_report.php?status=failed';
      if (userFilter && userFilter.value) {
        url += '&user_id=' + encodeURIComponent(userFilter.value);
      }
      openReportModal(url);
    };

    /* Initial */
    Promise.all([loadConfigs(), loadUsers(), loadRuns(), loadStats()])
      .catch(console.error);
  </script>
</body>

</html>