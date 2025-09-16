<?php
include 'config.php';
include 'jdate.php';

if (isset($_GET['material_id'])) {
    $material_id = (int)$_GET['material_id'];
    
    try {
        // تشخیص نوع محتوا (کتاب یا دوره)
        $stmt = $pdo->prepare("SELECT type FROM study_materials WHERE id = ?");
        $stmt->execute([$material_id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($material) {
            if ($material['type'] === 'book') {
                $table = 'book_chapters';
                $foreign_key = 'book_id';
            } else {
                $table = 'course_chapters';
                $foreign_key = 'course_id';
            }
            
            // دریافت فصل‌ها
            $stmt = $pdo->prepare("SELECT id, title FROM $table WHERE $foreign_key = ? ORDER BY id");
            $stmt->execute([$material_id]);
            $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode($chapters);
        }
    } catch(PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'خطا در دریافت فصل‌ها']);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'پارامترهای نامعتبر']);
}
?>