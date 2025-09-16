// ایجاد فایل progress_tracker.php
<?php
include 'header.php';
include 'config.php';

// دریافت کتاب‌ها و پیشرفت آنها
try {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               (SELECT COUNT(*) FROM book_chapters bc WHERE bc.book_id = b.id) as total_chapters,
               (SELECT COUNT(*) FROM book_chapters bc WHERE bc.book_id = b.id AND bc.completed = 1) as completed_chapters
        FROM books b 
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("خطا در دریافت کتاب‌ها: " . $e->getMessage());
}

// دریافت دوره‌ها و پیشرفت آنها
try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM course_chapters cc WHERE cc.course_id = c.id) as total_chapters,
               (SELECT COUNT(*) FROM course_chapters cc WHERE cc.course_id = c.id AND cc.completed = 1) as completed_chapters
        FROM courses c 
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("خطا در دریافت دوره‌ها: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">پیگیری پیشرفت</h1>

    <!-- کتاب‌ها -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">کتاب‌ها</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($books as $book): 
                    $progress = $book['total_chapters'] > 0 ? 
                        round(($book['completed_chapters'] / $book['total_chapters']) * 100) : 0;
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($book['author']); ?></p>
                            
                            <div class="progress mb-3">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $progress; ?>%" 
                                     aria-valuenow="<?php echo $progress; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?php echo $progress; ?>%
                                </div>
                            </div>
                            
                            <p class="card-text">
                                <small class="text-muted">
                                    <?php echo $book['completed_chapters']; ?> از <?php echo $book['total_chapters']; ?> فصل مطالعه شده
                                </small>
                            </p>
                            
                            <a href="book_progress.php?id=<?php echo $book['id']; ?>" class="btn btn-primary btn-sm">
                                مدیریت فصل‌ها
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- دوره‌ها -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">دوره‌های آموزشی</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($courses as $course): 
                    $progress = $course['total_chapters'] > 0 ? 
                        round(($course['completed_chapters'] / $course['total_chapters']) * 100) : 0;
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($course['instructor']); ?></p>
                            
                            <div class="progress mb-3">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $progress; ?>%" 
                                     aria-valuenow="<?php echo $progress; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?php echo $progress; ?>%
                                </div>
                            </div>
                            
                            <p class="card-text">
                                <small class="text-muted">
                                    <?php echo $course['completed_chapters']; ?> از <?php echo $course['total_chapters']; ?> بخش تکمیل شده
                                </small>
                            </p>
                            
                            <a href="course_progress.php?id=<?php echo $course['id']; ?>" class="btn btn-success btn-sm">
                                مدیریت بخش‌ها
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>