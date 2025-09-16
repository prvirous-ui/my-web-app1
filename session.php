<?php
// تنظیمات session
session_set_cookie_params([
    'lifetime' => 86400, // یک روز
    'path' => '/',
    'domain' => '',
    'secure' => false, // اگر از HTTPS استفاده می‌کنید true کنید
    'httponly' => true,
    'samesite' => 'Lax'
]);

// شروع session
session_start();

// بررسی timeout برای session
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    // آخرین فعالیت بیش از 30 دقیقه پیش بوده
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}

// به روزرسانی زمان آخرین فعالیت
$_SESSION['LAST_ACTIVITY'] = time();

// regenerate session ID برای امنیت بیشتر
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} else if (time() - $_SESSION['CREATED'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
}
?>