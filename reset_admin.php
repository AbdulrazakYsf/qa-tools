<?php
require_once 'auth_session.php';
$pdo = get_db_auth();

echo "<pre>";
echo "<h3>Database Diagnostic & Reset</h3>";

// 1. Check/Add Column
try {
    echo "Checking schema...\n";
    $cols = $pdo->query("SHOW COLUMNS FROM qa_users LIKE 'password_hash'")->fetchAll();
    if (count($cols) == 0) {
        echo "Column 'password_hash' missing. Adding it...\n";
        $pdo->exec("ALTER TABLE qa_users ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT '' AFTER email");
        echo "Column added.\n";
    } else {
        echo "Column 'password_hash' exists.\n";
    }
} catch (Exception $e) {
    die("Schema Error: " . $e->getMessage());
}

// 2. Reset Admin
$email = 'a.yusuf.jarir2021@gmail.com';
$password = 'Jarir13245!';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Resetting user: $email\n";

$stmt = $pdo->prepare("SELECT id FROM qa_users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $upd = $pdo->prepare("UPDATE qa_users SET password_hash = ?, role = 'admin', is_active = 1 WHERE id = ?");
    $upd->execute([$hash, $user['id']]);
    echo "User updated. ID: " . $user['id'] . "\n";
} else {
    $ins = $pdo->prepare("INSERT INTO qa_users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)");
    $ins->execute(['Admin User', $email, $hash, 'admin', 1]);
    echo "User created. ID: " . $pdo->lastInsertId() . "\n";
}

echo "\n<b>Done. Try logging in now.</b>";
echo "</pre>";
?>