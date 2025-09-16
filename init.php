<?php
/**
 * Initialization File - Must be included at the beginning of every page
 */

// Include required files
require_once 'config.php';
require_once 'auth.php';
require_once 'jdate.php';

// Check database connection
if (!isset($pdo) || $pdo === null) {
    die("خطا در اتصال به پایگاه داده. لطفاً بعداً تلاش کنید.");
}

// Test database connection
try {
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    die("خطا در ارتباط با پایگاه داده: " . htmlspecialchars($e->getMessage()));
}

// Check login for pages that require authentication
$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['login.php', 'register.php', 'index.php', 'forgot-password.php'];
$login_required_pages = ['dashboard.php', 'tasks.php', 'books.php', 'courses.php', 
                        'profile.php', 'users.php', 'calendar.php', 'reports.php'];

if (!in_array($current_page, $public_pages) && in_array($current_page, $login_required_pages)) {
    require_login();
}

// Check admin access for admin pages
$admin_pages = ['users.php', 'reports.php'];
if (in_array($current_page, $admin_pages) && !is_admin()) {
    $_SESSION['error'] = "شما دسترسی لازم برای مشاهده این صفحه را ندارید";
    header("Location: dashboard.php");
    exit;
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
$user_id = get_user_id();

// Set timezone
date_default_timezone_set(TIMEZONE);

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Handle pre-flight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit;
}

?>