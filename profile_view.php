<?php
include 'header.php';
include 'config.php';
include 'auth.php';

check_csrf_token();
$user_id = get_user_id();

// بررسی آیا کاربر مدیر است
function is_admin_user() {
    global $pdo, $user_id;
    
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && $user['role'] == 'admin';
    } catch(PDOException $e) {
        error_log("Admin check failed: " . $e->getMessage());
        return false;
    }
}

if (!is_admin_user()) {
    die('دسترسی غیرمجاز - فقط مدیران می‌توانند به این صفحه دسترسی داشته باشند');
}

// دریافت اطلاعات کاربر
$view_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$view_user_id]);
    $view_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$view_user) {
        die('کاربر یافت نشد');
    }
} catch(PDOException $e) {
    die("خطا در دریافت اطلاعات کاربر: " . $e->getMessage());
}

// دریافت آمار کاربر
try {
    // تعداد کارها
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE user_id = ?");
    $stmt->execute([$view_user_id]);
    $tasks_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // تعداد کتاب‌ها
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM books WHERE user_id = ?");
    $stmt->execute([$view_user_id]);
    $books_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // تعداد دوره‌ها
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM courses WHERE user_id = ?");
    $stmt->execute([$view_user_id]);
    $courses_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch(PDOException $e) {
    $tasks_count = $books_count = $courses_count = 0;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">مشاهده پروفایل کاربر</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="users.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت به لیست کاربران
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">اطلاعات کاربری</h6>
                </div>
                <div class="card-body text-center">
                    <img src="<?php echo !empty($view_user['profile_image']) ? $view_user['profile_image'] : 'https://via.placeholder.com/150/4e73df/ffffff?text=' . substr($view_user['username'], 0, 1); ?>" 
                         class="img-fluid rounded-circle mb-3" alt="تصویر پروفایل" style="width: 150px; height: 150px; object-fit: cover;">
                    <h5 class="card-title"><?php echo !empty($view_user['fullname']) ? htmlspecialchars($view_user['fullname']) : htmlspecialchars($view_user['username']); ?></h5>
                    <p class="text-muted">@<?php echo htmlspecialchars($view_user['username']); ?></p>
                    
                    <p class="text-muted">
                        <i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($view_user['email']); ?>
                    </p>
                    
                    <p class="text-muted">
                        <i class="bi bi-person-badge me-2"></i>
                        نقش: 
                        <span class="badge bg-<?php echo $view_user['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                            <?php echo $view_user['role'] == 'admin' ? 'مدیر' : 'کاربر'; ?>
                        </span>
                    </p>
                    
                    <p class="text-muted">
                        <i class="bi bi-calendar me-2"></i>
                        عضو شده از: <?php echo (new DateTime($view_user['created_at']))->format('Y/m/d'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">تعداد کارها</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $tasks_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-list-task fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">تعداد کتاب‌ها</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $books_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-book fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">تعداد دوره‌ها</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $courses_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-mortarboard fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">بیوگرافی</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($view_user['bio'])): ?>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($view_user['bio'])); ?></p>
                    <?php else: ?>
                        <p class="text-muted">هیچ بیوگرافی ثبت نشده است.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>