<?php
require_once 'auth_session.php';
$pdo = get_db_auth();

// Admin credentials
$email = 'a.yusuf.jarir2021@gmail.com';
$password = 'Jarir13245!';
$hash = password_hash($password, PASSWORD_DEFAULT);

// 1. Ensure user exists or update
$stmt = $pdo->prepare("SELECT id FROM qa_users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo "Updating password for existing user: $email\n";
    $upd = $pdo->prepare("UPDATE qa_users SET password_hash = ?, role = 'admin', is_active = 1 WHERE email = ?");
    $upd->execute([$hash, $email]);
    echo "Password updated successfully.\n";
} else {
    echo "Creating new admin user: $email\n";
    $ins = $pdo->prepare("INSERT INTO qa_users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)");
    $ins->execute(['Admin User', $email, $hash, 'admin', 1]);
    echo "User created successfully.\n";
}
?>