<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = sanitize_input($_POST['email']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $email]);
        
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['username'] = $username;
        header("Location: dashboard.php");
        exit;
    } catch(PDOException $e) {
        $error = "خطا در ثبت‌نام: نام کاربری ممکن است قبلاً استفاده شده باشد";
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام در سیستم برنامه‌ریزی</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fc; font-family: 'Vazir', Tahoma; height: 100vh; display: flex; align-items: center; }
        .register-container { max-width: 400px; margin: 0 auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="register-container">
        <h2 class="text-center mb-4">ثبت‌نام در سیستم</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label>نام کاربری</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>ایمیل</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>رمز عبور</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">ثبت‌نام</button>
        </form>
        <div class="text-center mt-3">
            <a href="login.php">قبلاً ثبت‌نام کرده‌اید؟ وارد شوید</a>
        </div>
    </div>
</body>
</html>