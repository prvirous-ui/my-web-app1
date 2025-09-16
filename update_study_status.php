<?php
include 'config.php';
include 'jdate.php';

// شروع session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chapter_id = (int)$_POST['chapter_id'];
    $chapter_type = $_POST['chapter_type'];
    $action = $_POST['action'];
    $current_date = date('Y-m-d');
    
    try {
        if ($chapter_type === 'book') {
            $table = 'book_chapters';
        } elseif ($chapter_type === 'course') {
            $table = 'course_chapters';
        } else {
            throw new Exception("نوع فصل نامعتبر است");
        }
        
        if ($action === 'complete') {
            $stmt = $pdo->prepare("UPDATE $table SET completed = 1, completed_date = ? WHERE id = ?");
            $stmt->execute([$current_date, $chapter_id]);
            
            echo json_encode(['success' => true, 'message' => 'فصل با موفقیت مطالعه شد']);
        } elseif ($action === 'undo') {
            $stmt = $pdo->prepare("UPDATE $table SET completed = 0, completed_date = NULL WHERE id = ?");
            $stmt->execute([$chapter_id]);
            
            echo json_encode(['success' => true, 'message' => 'وضعیت مطالعه بازگردانی شد']);
        } else {
            throw new Exception("عمل نامعتبر");
        }
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر']);
}
?>