<?php
// functions.php
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function check_csrf_token() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
}

function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function get_user_id() {
    // این تابع باید بر اساس سیستم احراز هویت شما پیاده‌سازی شود
    // در این مثال یک مقدار ثابت برمی‌گردانیم
    return 1;
}

function check_user_ownership($pdo, $table, $id, $user_id) {
    $stmt = $pdo->prepare("SELECT user_id FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    return $item && $item['user_id'] == $user_id;
}
?>