<?php
include 'config.php';

// شروع session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chapter_id = (int)$_POST['chapter_id'];
    $chapter_type = $_POST['chapter_type'];
    $new_date = $_POST['new_date'];
    
    try {
        if ($chapter_type === 'book') {
            $table = 'book_chapters';
        } elseif ($chapter_type === 'course') {
            $table = 'course_chapters';
        } else {
            throw new Exception("نوع فصل نامعتبر است");
        }
        
        $stmt = $pdo->prepare("UPDATE $table SET study_date = ? WHERE id = ?");
        $stmt->execute([$new_date, $chapter_id]);
        
        echo json_encode(['success' => true, 'message' => 'تاریخ مطالعه با موفقیت تغییر کرد']);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر']);
}
?>