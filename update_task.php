<?php
// شروع session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیم هدر برای بازگشت JSON
header('Content-Type: application/json');

// بررسی درخواست POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // شبیه‌سازی عملیات بر روی تسک‌ها
    if ($task_id > 0 && in_array($action, ['complete', 'delete'])) {
        // در یک سیستم واقعی، اینجا کوئری به دیتابیس اجرا می‌شد
        
        if ($action === 'complete') {
            // علامت گذاری تسک به عنوان کامل
            echo json_encode(['success' => true, 'message' => 'تسک با موفقیت کامل标记 شد']);
        } elseif ($action === 'delete') {
            // حذف تسک
            echo json_encode(['success' => true, 'message' => 'تسک با موفقیت حذف شد']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'پارامترهای نامعتبر']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر']);
}
exit();
?>