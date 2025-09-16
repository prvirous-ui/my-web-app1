// ایجاد فایل admin_users.php
<?php
include 'header.php';
include 'config.php';

// بررسی دسترسی ادمین
if (!is_admin()) {
    header("Location: dashboard.php");
    exit;
}

// دریافت لیست کاربران
try {
    $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("خطا در دریافت کاربران: " . $e->getMessage());
}

// دریافت آمار کاربران
$user_stats = [];
foreach ($users as $user) {
    try {
        // تعداد کارها
        $stmt = $pdo->prepare("SELECT COUNT(*) as task_count FROM tasks WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $task_count = $stmt->fetch(PDO::FETCH_ASSOC)['task_count'];
        
        // تعداد کتاب‌ها
        $stmt = $pdo->prepare("SELECT COUNT(*) as book_count FROM books WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $book_count = $stmt->fetch(PDO::FETCH_ASSOC)['book_count'];
        
        // تعداد دوره‌ها
        $stmt = $pdo->prepare("SELECT COUNT(*) as course_count FROM courses WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $course_count = $stmt->fetch(PDO::FETCH_ASSOC)['course_count'];
        
        $user_stats[$user['id']] = [
            'tasks' => $task_count,
            'books' => $book_count,
            'courses' => $course_count
        ];
    } catch(PDOException $e) {
        $user_stats[$user['id']] = ['tasks' => 0, 'books' => 0, 'courses' => 0];
    }
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">مدیریت کاربران</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">لیست کاربران</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="usersTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>نام کاربری</th>
                            <th>ایمیل</th>
                            <th>نقش</th>
                            <th>تعداد کارها</th>
                            <th>تعداد کتاب‌ها</th>
                            <th>تعداد دوره‌ها</th>
                            <th>تاریخ عضویت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                                    <?php echo $user['role'] == 'admin' ? 'مدیر' : 'کاربر'; ?>
                                </span>
                            </td>
                            <td><?php echo $user_stats[$user['id']]['tasks']; ?></td>
                            <td><?php echo $user_stats[$user['id']]['books']; ?></td>
                            <td><?php echo $user_stats[$user['id']]['courses']; ?></td>
                            <td><?php echo DateManager::convertToJalali($user['created_at']); ?></td>
                            <td>
                                <a href="admin_user_activities.php?user_id=<?php echo $user['id']; ?>" 
                                   class="btn btn-sm btn-info" title="مشاهده فعالیت‌ها">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a href="admin_edit_user.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-sm btn-warning" title="ویرایش">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="admin_delete_user.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-sm btn-danger" title="حذف" 
                                   onclick="return confirm('آیا از حذف این کاربر مطمئن هستید؟')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>