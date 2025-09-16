// ایجاد فایل advanced_reports.php
<?php
include 'header.php';
include 'config.php';

// پارامترهای فیلتر
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : DateManager::addDaysToJalali(DateManager::getCurrentJalali(), -30);
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : DateManager::getCurrentJalali();
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// تبدیل تاریخ‌ها به میلادی برای query
$start_date_gregorian = DateManager::convertToGregorian($start_date);
$end_date_gregorian = DateManager::convertToGregorian($end_date);

// دریافت آمار کلی
try {
    // آمار کارها
    $task_query = "SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority_tasks,
        AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_completion_time
    FROM tasks 
    WHERE due_date BETWEEN ? AND ?";
    
    $task_params = [$start_date_gregorian, $end_date_gregorian];
    
    if ($user_filter > 0) {
        $task_query .= " AND user_id = ?";
        $task_params[] = $user_filter;
    }
    
    $stmt = $pdo->prepare($task_query);
    $stmt->execute($task_params);
    $task_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // آمار مطالعه
    $study_query = "SELECT 
        COUNT(*) as total_study_sessions,
        SUM(duration_minutes) as total_study_time,
        AVG(duration_minutes) as avg_study_time
    FROM study_sessions 
    WHERE session_date BETWEEN ? AND ?";
    
    $study_params = [$start_date_gregorian, $end_date_gregorian];
    
    if ($user_filter > 0) {
        $study_query .= " AND user_id = ?";
        $study_params[] = $user_filter;
    }
    
    $stmt = $pdo->prepare($study_query);
    $stmt->execute($study_params);
    $study_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("خطا در دریافت آمار: " . $e->getMessage());
}

// دریافت کاربران برای فیلتر (فقط برای ادمین)
$users = [];
if (is_admin()) {
    try {
        $stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // ignore error
    }
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">گزارش‌گیری پیشرفته</h1>

    <!-- فیلترها -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">فیلترها</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">از تاریخ</label>
                        <input type="text" class="form-control j-datepicker" id="start_date" name="start_date" 
                               value="<?php echo htmlspecialchars($start_date); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">تا تاریخ</label>
                        <input type="text" class="form-control j-datepicker" id="end_date" name="end_date" 
                               value="<?php echo htmlspecialchars($end_date); ?>" required>
                    </div>
                    <?php if (is_admin() && !empty($users)): ?>
                    <div class="col-md-3">
                        <label for="user_id" class="form-label">کاربر</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <option value="0">همه کاربران</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                        <a href="advanced_reports.php" class="btn btn-secondary me-2">حذف فیلترها</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- آمار کلی -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">کل کارها</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $task_stats['total_tasks']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-list-task fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">کارهای تکمیل شده</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $task_stats['completed_tasks']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">میانگین زمان تکمیل (ساعت)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo round($task_stats['avg_completion_time'], 1); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">جلسات مطالعه</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $study_stats['total_study_sessions']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- نمودارها و جداول اضافی -->
    <!-- ... -->
</div>

<?php include 'footer.php'; ?>