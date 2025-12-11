<?php
session_start();

// Database configuration (Shared with dash)
const QA_DB_HOST_AUTH = 'sql309.infinityfree.com';
const QA_DB_PORT_AUTH = 3306;
const QA_DB_NAME_AUTH = 'if0_40372489_init_db';
const QA_DB_USER_AUTH = 'if0_40372489';
const QA_DB_PASS_AUTH = 'KmUb1Azwzo';

function get_db_auth() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . QA_DB_HOST_AUTH . ';port=' . QA_DB_PORT_AUTH . ';dbname=' . QA_DB_NAME_AUTH . ';charset=utf8mb4';
    $pdo = new PDO($dsn, QA_DB_USER_AUTH, QA_DB_PASS_AUTH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

function require_role($allowed_roles) {
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
        die("Access Denied: You do not have the required permissions.");
    }
}

function current_user() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'role' => $_SESSION['user_role'] ?? 'viewer'
    ];
}
?>
