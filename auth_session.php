<?php
session_start();

// Database configuration (Shared with dash)
const QA_DB_HOST_AUTH = 'sql309.infinityfree.com';
const QA_DB_PORT_AUTH = 3306;
const QA_DB_NAME_AUTH = 'if0_40372489_init_db';
const QA_DB_USER_AUTH = 'if0_40372489';
const QA_DB_PASS_AUTH = 'KmUb1Azwzo';

function get_db_auth()
{
    static $pdo = null;
    if ($pdo)
        return $pdo;
    $dsn = 'mysql:host=' . QA_DB_HOST_AUTH . ';port=' . QA_DB_PORT_AUTH . ';dbname=' . QA_DB_NAME_AUTH . ';charset=utf8mb4';
    $pdo = new PDO($dsn, QA_DB_USER_AUTH, QA_DB_PASS_AUTH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Ensure tables exist (Shared Schema Definition)
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
          is_read TINYINT(1) NOT NULL DEFAULT 0,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_support_user (user_id)
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
          notes TEXT,
          duration INT DEFAULT 0,
          input_data LONGTEXT DEFAULT NULL,
          output_data LONGTEXT DEFAULT NULL,
          user_id INT UNSIGNED DEFAULT NULL,
          INDEX idx_run_user (user_id)
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

    // Dynamic Migrations (Idempotent)
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

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM qa_support_messages LIKE 'admin_reply'")->fetchAll();
        if (count($cols) == 0) {
            $pdo->exec("ALTER TABLE qa_support_messages ADD COLUMN admin_reply TEXT AFTER is_read");
            $pdo->exec("ALTER TABLE qa_support_messages ADD COLUMN reply_at TIMESTAMP NULL AFTER admin_reply");
        }
    } catch (Exception $e) {
    }

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM qa_support_messages LIKE 'priority'")->fetchAll();
        if (count($cols) == 0) {
            $pdo->exec("ALTER TABLE qa_support_messages ADD COLUMN priority VARCHAR(20) DEFAULT 'low' AFTER is_read");
        }
    } catch (Exception $e) {
    }

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM qa_test_runs LIKE 'user_id'")->fetchAll();
        if (count($cols) == 0) {
            $pdo->exec("ALTER TABLE qa_test_runs ADD COLUMN user_id INT UNSIGNED DEFAULT NULL AFTER id");
            $pdo->exec("CREATE INDEX idx_run_user ON qa_test_runs(user_id)");
        }
    } catch (Exception $e) {
    }

    // Migration: Add input_data and output_data columns (v2.3.1)
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM qa_test_runs LIKE 'input_data'")->fetchAll();
        if (count($cols) == 0)
            $pdo->exec("ALTER TABLE qa_test_runs ADD COLUMN input_data LONGTEXT DEFAULT NULL");
    } catch (Exception $e) {
    }

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM qa_test_runs LIKE 'output_data'")->fetchAll();
        if (count($cols) == 0)
            $pdo->exec("ALTER TABLE qa_test_runs ADD COLUMN output_data LONGTEXT DEFAULT NULL");
    } catch (Exception $e) {
    }

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM qa_test_runs LIKE 'duration'")->fetchAll();
        if (count($cols) == 0)
            $pdo->exec("ALTER TABLE qa_test_runs ADD COLUMN duration INT DEFAULT 0");
    } catch (Exception $e) {
    }

    // Ensure qa_tool_configs table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qa_tool_configs (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          tool_code VARCHAR(50) NOT NULL,
          config_name VARCHAR(100),
          config_json TEXT,
          is_enabled TINYINT(1) DEFAULT 1,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM qa_tool_configs LIKE 'user_id'")->fetchAll();
        if (count($cols) == 0) {
            $pdo->exec("ALTER TABLE qa_tool_configs ADD COLUMN user_id INT UNSIGNED DEFAULT NULL AFTER id");
            $pdo->exec("CREATE INDEX idx_config_user ON qa_tool_configs(user_id)");
            $pdo->exec("CREATE INDEX idx_config_tool ON qa_tool_configs(tool_code)");
        }
    } catch (Exception $e) {
    }

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM qa_tool_configs LIKE 'admin_user_id'")->fetchAll();
        if (count($cols) == 0) {
            $pdo->exec("ALTER TABLE qa_tool_configs ADD COLUMN admin_user_id INT UNSIGNED DEFAULT NULL AFTER user_id");
            $pdo->exec("CREATE INDEX idx_config_admin ON qa_tool_configs(admin_user_id)");
        }
    } catch (Exception $e) {
    }

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM qa_users LIKE 'api_key'")->fetchAll();
        if (count($cols) == 0) {
            $pdo->exec("ALTER TABLE qa_users ADD COLUMN api_key VARCHAR(64) DEFAULT NULL UNIQUE AFTER password_hash");
            $pdo->exec("CREATE INDEX idx_user_api_key ON qa_users(api_key)");
        }
    } catch (Exception $e) {
    }

    return $pdo;
}

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        if (isset($_GET['api']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Session expired. Please login again.']);
            exit;
        }
        header('Location: index.php');
        exit;
    }
}

function require_role($allowed_roles)
{
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
        if (isset($_GET['api']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Access Denied: Permission level ' . ($_SESSION['user_role'] ?? 'none') . ' is insufficient.']);
            exit;
        }
        die("Access Denied: You do not have the required permissions.");
    }
}

function current_user()
{
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'role' => $_SESSION['user_role'] ?? 'viewer'
    ];
}
?>