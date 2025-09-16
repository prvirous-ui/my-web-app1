<?php
// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

// شروع سشن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// لاگ کردن وضعیت سشن برای دیباگ
error_log("Session status in login.php: " . session_status());
error_log("Session data in login.php before processing: " . (isset($_SESSION) ? print_r($_SESSION, true) : "No session data"));

// اگر کاربر قبلاً لاگین کرده، به dashboard هدایت شود
if (isset($_SESSION['user_id'])) {
    error_log("User already logged in, redirecting to dashboard.php");
    header("Location: dashboard.php");
    exit;
}

// بررسی وجود فایل‌های ضروری
if (!file_exists('config.php') || !file_exists('auth.php')) {
    die("فایل‌های ضروری سیستم یافت نشدند. لطفاً با مدیر سیستم تماس بگیرید.");
}

require_once 'config.php';
require_once 'auth.php';

// تعریف توابع اگر وجود ندارند
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        return htmlspecialchars(stripslashes(trim($data)));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
    }
}

// پردازش فرم لاگین
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data received: " . print_r($_POST, true));
    
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // اعتبارسنجی فیلدها
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "لطفاً تمام فیلدها را پر کنید";
        error_log("Login failed: Empty fields");
    } else {
        try {
            // بررسی اتصال به دیتابیس
            if (!isset($pdo)) {
                throw new PDOException("اتصال به دیتابیس برقرار نیست");
            }
            
            $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                error_log("User found: " . print_r($user, true));
                
                // بررسی پسورد
                if (verify_password($password, $user['password'])) {
                    // لاگین موفقیت‌آمیز
                    login_user($user['id'], $user['username'], $user['role']);
                    error_log("User logged in successfully: user_id=" . $user['id'] . ", username=" . $user['username']);
                    
                    // هدایت به داشبورد
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $_SESSION['error'] = "نام کاربری یا رمز عبور اشتباه است";
                    error_log("Login failed: Password verification failed");
                }
            } else {
                $_SESSION['error'] = "نام کاربری یا رمز عبور اشتباه است";
                error_log("Login failed: User not found");
            }
        } catch(PDOException $e) {
            $_SESSION['error'] = "خطا در ارتباط با دیتابیس: " . $e->getMessage();
            error_log("Database error during login: " . $e->getMessage());
        } catch(Exception $e) {
            $_SESSION['error'] = "خطای سیستمی: " . $e->getMessage();
            error_log("System error during login: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم - برنامه‌ریز شخصی</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-light: #7a9ff7;
            --primary-dark: #2a4b8d;
            --success: #1cc88a;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: pulse 3s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .login-header h3 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .login-header p {
            opacity: 0.9;
            margin-bottom: 0;
            position: relative;
            z-index: 2;
        }
        
        .login-body {
            padding: 2.5rem 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control {
            border-radius: 12px;
            padding: 15px 20px;
            border: 2px solid #e3e6f0;
            font-size: 16px;
            transition: all 0.3s ease;
            background: var(--light);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.3rem rgba(78, 115, 223, 0.15);
            background: white;
            transform: translateY(-2px);
        }
        
        .form-control::placeholder {
            color: #aab7c5;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 12px;
            padding: 15px 20px;
            font-weight: 700;
            font-size: 16px;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(78, 115, 223, 0.4);
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:active {
            transform: translateY(-1px);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ffe6e6 0%, #ffcccc 100%);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e3e6f0;
        }
        
        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .register-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aab7c5;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        .input-group {
            position: relative;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h3><i class="bi bi-calendar-check"></i> برنامه‌ریز شخصی</h3>
                <p class="mb-0">لطفاً وارد حساب کاربری خود شوید</p>
            </div>
            
            <div class="login-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php 
                        echo htmlspecialchars($_SESSION['error']); 
                        unset($_SESSION['error']); 
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="bi bi-person me-1"></i> نام کاربری
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="username" 
                            name="username" 
                            required 
                            autofocus
                            placeholder="نام کاربری خود را وارد کنید"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock me-1"></i> رمز عبور
                        </label>
                        <div class="input-group">
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password" 
                                required
                                placeholder="رمز عبور خود را وارد کنید"
                            >
                            <span class="password-toggle" onclick="togglePassword()">
                                <i class="bi bi-eye"></i>
                            </span>
                        </div>
                    </div>
                    
                    <?php echo csrf_field(); ?>
                    
                    <button type="submit" class="btn btn-login" id="loginButton">
                        <span class="loading-spinner" id="loadingSpinner"></span>
                        <span id="buttonText">
                            <i class="bi bi-box-arrow-in-right me-2"></i> ورود به سیستم
                        </span>
                    </button>
                </form>
                
                <div class="register-link">
                    <small class="text-muted">حساب کاربری ندارید؟ 
                        <a href="register.php">ثبت‌نام کنید</a>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                eyeIcon.className = 'bi bi-eye';
            }
        }
        
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const button = document.getElementById('loginButton');
            const spinner = document.getElementById('loadingSpinner');
            const buttonText = document.getElementById('buttonText');
            
            // نمایش اسپینر و غیرفعال کردن دکمه
            spinner.style.display = 'inline-block';
            buttonText.textContent = 'در حال ورود...';
            button.disabled = true;
            
            // اجازه دهید فرم ارسال شود
        });
        
        // فوکوس خودکار روی فیلد نام کاربری
        document.getElementById('username').focus();
        
        // مدیریت کلید Enter
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const focused = document.activeElement;
                if (focused.tagName === 'INPUT') {
                    document.getElementById('loginForm').requestSubmit();
                }
            }
        });
    </script>
</body>
</html>