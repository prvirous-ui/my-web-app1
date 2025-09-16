<?php
include 'config.php';

if (isset($_GET['material_id']) && isset($_GET['type'])) {
    $material_id = (int)$_GET['material_id'];
    $type = $_GET['type'];
    
    try {
        if ($type === 'book') {
            $stmt = $pdo->prepare("SELECT id, title FROM book_chapters WHERE book_id = ? AND completed = 0 ORDER BY id");
        } elseif ($type === 'course') {
            $stmt = $pdo->prepare("SELECT id, title FROM course_sections WHERE course_id = ? AND completed = 0 ORDER BY id");
        } else {
            echo json_encode([]);
            exit;
        }
        
        $stmt->execute([$material_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($sections);
    } catch(PDOException $e) {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>