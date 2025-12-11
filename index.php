<?php
session_start();
require_once 'auth_session.php'; // For DB connection

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db = get_db_auth();
        $stmt = $db->prepare("SELECT * FROM qa_users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            // Redirect based on role or default to dashboard
            header('Location: qa-dash8.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Tools Login</title>
    <style>
        :root{--bg:#f4f7fa;--card:#fff;--blue:#1E88E5;--text:#333;}
        body{font-family:system-ui,-apple-system,sans-serif;background:var(--bg);display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
        .login-card{background:var(--card);padding:40px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);width:100%;max-width:400px;text-align:center;}
        .login-card h2{margin-top:0;color:var(--text);}
        .form-group{margin-bottom:20px;text-align:left;}
        .form-group label{display:block;margin-bottom:6px;font-size:14px;font-weight:600;color:#555;}
        input{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;box-sizing:border-box;}
        button{background:var(--blue);color:white;border:none;padding:12px;border-radius:6px;width:100%;font-size:16px;font-weight:600;cursor:pointer;}
        button:hover{opacity:0.9;}
        .error{color:red;margin-bottom:15px;font-size:14px;}
        .footer{margin-top:20px;font-size:13px;color:#888;}
    </style>
</head>
<body>
    <div class="login-card">
        <h2>QA Dashboard Login</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="tester@example.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit">Log In</button>
        </form>
        <div class="footer">
            &copy; <?php echo date('Y'); ?> QA Automation Team
        </div>
    </div>
</body>
</html>
