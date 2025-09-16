<?php
/**
 * Configuration File - Planner Application
 * Includes database connection and basic security functions
 */

// تنظیمات ثابت‌ها
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'meir123_planner_db');
    define('DB_USER', 'meir123_planner_user');
    define('DB_PASS', 'vmLover@97');
}

if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://meir123.com/planner/');
    define('APP_NAME', 'Planner Application');
    define('TIMEZONE', 'Asia/Tehran');
}

// تنظیم منطقه زمانی
date_default_timezone_set(TIMEZONE);

// تنظیمات خطا
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

// مدیریت سشن
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// تابع sanitize_input
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        if (is_array($data)) {
            return array_map('sanitize_input', $data);
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

// تابع اتصال به دیتابیس
if (!function_exists('connect_db')) {
    function connect_db() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'خطا در اتصال به پایگاه داده']);
                exit;
            }
            die("خطا در اتصال به پایگاه داده. لطفاً بعداً تلاش کنید.");
        }
    }
}

// توابع CSRF
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// تابع redirect
if (!function_exists('redirect')) {
    function redirect($url) {
        header('Location: ' . $url);
        exit();
    }
}

// ایجاد اتصال به دیتابیس
$pdo = connect_db();

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Auto-sanitize POST and GET data
if (!empty($_POST)) {
    $_POST = array_map('sanitize_input', $_POST);
}
if (!empty($_GET)) {
    $_GET = array_map('sanitize_input', $_GET);
}
?>