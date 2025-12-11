<?php
require_once 'auth_session.php'; // For DB

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if (!$token) {
    die("Invalid or missing token.");
}

$db = get_db_auth();
$stmt = $db->prepare("SELECT * FROM qa_users WHERE verification_token = ? AND token_expires_at > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("This link is invalid or has expired.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['pass1'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';

    if (strlen($pass1) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($pass1 !== $pass2) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        
        // Update user: set password, clear token, activate user
        $update = $db->prepare("UPDATE qa_users SET password_hash = ?, verification_token = NULL, token_expires_at = NULL, is_active = 1 WHERE id = ?");
        $update->execute([$hash, $user['id']]);

        $success = "Password set successfully! <a href='index.php'>Click here to login</a>.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set Password</title>
    <style>
        :root{--bg:#f4f7fa;--card:#fff;--blue:#1E88E5;}
        body{font-family:system-ui,-apple-system,sans-serif;background:var(--bg);display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
        .card{background:var(--card);padding:40px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);width:100%;max-width:400px;}
        h2{margin-top:0;text-align:center;}
        input{width:100%;padding:10px;margin:10px 0;border-radius:6px;border:1px solid #ddd;box-sizing:border-box;}
        button{background:var(--blue);color:white;width:100%;padding:12px;border:none;border-radius:6px;font-weight:bold;cursor:pointer;}
        .msg{padding:10px;border-radius:6px;margin-bottom:15px;text-align:center;}
        .err{background:#ffebee;color:#c62828;}
        .succ{background:#e8f5e9;color:#2e7d32;}
    </style>
</head>
<body>
    <div class="card">
        <h2>Welcome, <?php echo htmlspecialchars($user['name']); ?></h2>
        <p style="text-align:center;color:#666;">Please set your password to continue.</p>
        
        <?php if($error): ?><div class="msg err"><?php echo $error; ?></div><?php endif; ?>
        <?php if($success): ?><div class="msg succ"><?php echo $success; ?></div><?php else: ?>

        <form method="POST">
            <label>New Password</label>
            <input type="password" name="pass1" required>
            <label>Confirm Password</label>
            <input type="password" name="pass2" required>
            <button type="submit">Set Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
