// ایجاد فایل interactive_calendar.php
<?php
include 'header.php';
include 'config.php';
include 'date_manager.php';

// دریافت تاریخ فعلی
$current_date = isset($_GET['date']) ? $_GET['date'] : DateManager::getCurrentJalali();
$current_gregorian = DateManager::convertToGregorian($current_date);

// دریافت کارهای تاریخ selected
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               CASE 
                 WHEN t.completed = 1 THEN 'completed' 
                 WHEN t.due_date < CURDATE() THEN 'overdue' 
                 ELSE 'pending' 
               END as status
        FROM tasks t 
        WHERE t.user_id = ? AND DATE(t.due_date) = ?
        ORDER BY t.priority DESC, t.created_at DESC
    ");
    $stmt->execute([$user_id, $current_gregorian]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("خطا در دریافت کارها: " . $e->getMessage());
}

// پردازش تیک زدن کارها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_task'])) {
    $task_id = (int)$_POST['task_id'];
    $completed = isset($_POST['completed']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE tasks SET completed = ?, completed_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$completed, $task_id, $user_id]);
        
        $_SESSION['success'] = "وضعیت کار به‌روزرسانی شد";
        header("Location: interactive_calendar.php?date=" . urlencode($current_date));
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "خطا در به‌روزرسانی کار: " . $e->getMessage();
        header("Location: interactive_calendar.php?date=" . urlencode($current_date));
        exit;
    }
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">تقویم تعاملی</h1>

    <!-- ناوبری تاریخ -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <a href="interactive_calendar.php?date=<?php echo DateManager::addDaysToJalali($current_date, -1); ?>" 
                   class="btn btn-outline-primary">
                   <i class="bi bi-chevron-right"></i> روز قبل
                </a>
                
                <h4 class="mb-0"><?php echo $current_date; ?></h4>
                
                <a href="interactive_calendar.php?date=<?php echo DateManager::addDaysToJalali($current_date, 1); ?>" 
                   class="btn btn-outline-primary">
                   روز بعد <i class="bi bi-chevron-left"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- لیست کارها -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">کارهای روز</h5>
        </div>
        <div class="card-body">
            <?php if (empty($tasks)): ?>
                <p class="text-muted">هیچ کاری برای این تاریخ برنامه‌ریزی نشده است.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50px">وضعیت</th>
                                <th>عنوان کار</th>
                                <th>اولویت</th>
                                <th>توضیحات</th>
                                <th width="100px">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                            <tr class="<?php echo $task['status'] == 'completed' ? 'table-success' : ($task['status'] == 'overdue' ? 'table-danger' : ''); ?>">
                                <td>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <input type="hidden" name="completed" value="<?php echo $task['completed'] ? '0' : '1'; ?>">
                                        <button type="submit" name="toggle_task" class="btn btn-sm <?php echo $task['completed'] ? 'btn-success' : 'btn-outline-secondary'; ?>">
                                            <i class="bi bi-<?php echo $task['completed'] ? 'check-circle-fill' : 'circle'; ?>"></i>
                                        </button>
                                    </form>
                                </td>
                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $task['priority'] == 'high' ? 'danger' : 
                                             ($task['priority'] == 'medium' ? 'warning' : 'success'); 
                                    ?>">
                                        <?php echo $task['priority'] == 'high' ? 'بالا' : 
                                               ($task['priority'] == 'medium' ? 'متوسط' : 'پایین'); ?>
                                    </span>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars($task['description'])); ?></td>
                                <td>
                                    <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('آیا مطمئن هستید؟')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>