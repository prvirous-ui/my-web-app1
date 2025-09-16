<?php 
require_once 'init.php';
include 'header.php';
include 'jdate.php'; // اضافه کردن فایل توابع تاریخ شمسی

// تولید و ذخیره توکن CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// مدیریت درخواست‌های AJAX برای تکمیل کار
if (isset($_POST['action']) && $_POST['action'] === 'complete_task' && isset($_POST['task_id'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'توکن CSRF نامعتبر است']);
        exit;
    }

    try {
        $user_id = get_user_id();
        $task_id = filter_var($_POST['task_id'], FILTER_SANITIZE_NUMBER_INT);
        $user_column = 'user_id';
        $stmt = $pdo->prepare("UPDATE tasks SET completed = 1, completed_at = NOW() WHERE id = ? AND $user_column = ?");
        $stmt->execute([$task_id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'کار با موفقیت تکمیل شد']);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'خطا در تکمیل کار: ' . $e->getMessage()]);
        exit;
    }
}

// دریافت آمار از دیتابیس
try {
    if (!isset($pdo)) {
        throw new Exception("اتصال به دیتابیس برقرار نیست");
    }
    
    $user_id = get_user_id();
    
    $user_columns = [];
    $tables_to_check = ['tasks', 'books', 'courses', 'book_chapters', 'course_chapters'];
    
    foreach ($tables_to_check as $table) {
        try {
            $stmt = $pdo->prepare("DESCRIBE $table");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $user_column = '';
            foreach ($columns as $col) {
                if (strpos(strtolower($col), 'user') !== false) {
                    $user_column = $col;
                    break;
                }
            }
            
            $user_columns[$table] = $user_column ?: 'user_id';
            
        } catch (PDOException $e) {
            $user_columns[$table] = 'user_id';
        }
    }
    
    $task_count = 0;
    $urgent_count = 0;
    $recent_tasks = [];
    
    $user_column = $user_columns['tasks'];
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE $user_column = ? AND completed = 0");
    $stmt->execute([$user_id]);
    $task_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE $user_column = ? AND priority = 'high' AND completed = 0");
    $stmt->execute([$user_id]);
    $urgent_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE $user_column = ? AND completed = 0 ORDER BY due_date ASC LIMIT 4");
    $stmt->execute([$user_id]);
    $recent_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $book_count = 0;
    $recent_books = [];
    
    $user_column = $user_columns['books'];
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM books WHERE $user_column = ? AND progress = 100");
    $stmt->execute([$user_id]);
    $book_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->prepare("SELECT * FROM books WHERE $user_column = ? ORDER BY id DESC LIMIT 4");
    $stmt->execute([$user_id]);
    $recent_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $course_count = 0;
    $recent_courses = [];
    
    $user_column = $user_columns['courses'];
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM courses WHERE $user_column = ? AND status = 'در حال گذراندن'");
    $stmt->execute([$user_id]);
    $course_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE $user_column = ? ORDER BY id DESC LIMIT 4");
    $stmt->execute([$user_id]);
    $recent_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $weekly_study_count = 0;
    $weekly_completed_chapters = [];
    
    try {
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        
        $user_column = $user_columns['book_chapters'];
        $stmt = $pdo->prepare("SELECT bc.*, b.title as book_title 
                              FROM book_chapters bc 
                              JOIN books b ON bc.book_id = b.id 
                              WHERE bc.completed_date BETWEEN ? AND ? 
                              AND b.$user_column = ?");
        $stmt->execute([$week_start, $week_end, $user_id]);
        $weekly_book_chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $user_column = $user_columns['course_chapters'];
        $stmt = $pdo->prepare("SELECT cc.*, c.title as course_title 
                              FROM course_chapters cc 
                              JOIN courses c ON cc.course_id = c.id 
                              WHERE cc.completed_date BETWEEN ? AND ? 
                              AND c.$user_column = ?");
        $stmt->execute([$week_start, $week_end, $user_id]);
        $weekly_course_chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $weekly_study_count = count($weekly_book_chapters) + count($weekly_course_chapters);
        $weekly_completed_chapters = array_merge($weekly_book_chapters, $weekly_course_chapters);
        
    } catch(PDOException $e) {
        error_log("خطا در محاسبه آمار مطالعه هفتگی: " . $e->getMessage());
    }

    $total_study_hours = 0;
    try {
        $user_column = $user_columns['book_chapters'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM book_chapters bc 
                              JOIN books b ON bc.book_id = b.id 
                              WHERE bc.completed_date BETWEEN ? AND ? 
                              AND b.$user_column = ?");
        $stmt->execute([$week_start, $week_end, $user_id]);
        $book_chapters_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $user_column = $user_columns['course_chapters'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM course_chapters cc 
                              JOIN courses c ON cc.course_id = c.id 
                              WHERE cc.completed_date BETWEEN ? AND ? 
                              AND c.$user_column = ?");
        $stmt->execute([$week_start, $week_end, $user_id]);
        $course_chapters_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $total_study_hours = ($book_chapters_count * 1.5) + ($course_chapters_count * 2);
    } catch(PDOException $e) {
        error_log("خطا در محاسبه ساعت مطالعه: " . $e->getMessage());
    }

    $daily_study_stats = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $timestamp = strtotime($date);
        $day_name = jdate('l', $timestamp);
        
        $daily_count = 0;
        
        try {
            $user_column = $user_columns['book_chapters'];
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM book_chapters bc 
                                  JOIN books b ON bc.book_id = b.id 
                                  WHERE bc.completed_date = ? AND b.$user_column = ?");
            $stmt->execute([$date, $user_id]);
            $book_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $user_column = $user_columns['course_chapters'];
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM course_chapters cc 
                                  JOIN courses c ON cc.course_id = c.id 
                                  WHERE cc.completed_date = ? AND c.$user_column = ?");
            $stmt->execute([$date, $user_id]);
            $course_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $daily_count = $book_count + $course_count;
        } catch(PDOException $e) {
            error_log("خطا در محاسبه آمار روزانه: " . $e->getMessage());
        }
        
        $daily_study_stats[] = [
            'date' => $date,
            'day_name' => $day_name,
            'count' => $daily_count
        ];
    }

    $satisfaction_percentage = min(100, round(($weekly_study_count / max(1, $task_count + $course_count)) * 100));

} catch(PDOException $e) {
    if (!function_exists('log_error')) {
        function log_error($message) {
            error_log($message);
        }
    }
    
    log_error("خطا در دریافت آمار داشبورد: " . $e->getMessage());
    $task_count = $book_count = $course_count = $urgent_count = $weekly_study_count = 0;
    $recent_tasks = $recent_books = $recent_courses = $weekly_completed_chapters = $daily_study_stats = [];
    echo "<div class='alert alert-danger m-3'>خطا در ارتباط با دیتابیس: " . htmlspecialchars($e->getMessage()) . "</div>";
} catch(Exception $e) {
    if (!function_exists('log_error')) {
        function log_error($message) {
            error_log($message);
        }
    }
    
    log_error("خطای عمومی: " . $e->getMessage());
    $task_count = $book_count = $course_count = $urgent_count = $weekly_study_count = 0;
    $recent_tasks = $recent_books = $recent_courses = $weekly_completed_chapters = $daily_study_stats = [];
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - اتوماسیون برنامه‌ریزی شخصی</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-light: #7a9ff7;
            --primary-dark: #2a4b8d;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
            --gradient-primary: linear-gradient(180deg, var(--primary) 10%, var(--primary-dark) 100%);
            --gradient-success: linear-gradient(180deg, var(--success) 10%, #0f9d6e 100%);
            --gradient-info: linear-gradient(180deg, var(--info) 10%, #2a96a8 100%);
            --gradient-warning: linear-gradient(180deg, var(--warning) 10%, #dda20a 100%);
            --shadow-sm: 0 0.15rem 0.75rem rgba(0, 0, 0, 0.1);
            --shadow-md: 0 0.3rem 1rem rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(180deg, var(--light) 0%, #e8ecf6 100%);
            color: var(--dark);
            min-height: 100vh;
            margin: 0;
        }

        .dashboard-header {
            background: var(--gradient-primary);
            color: white;
            border-radius: 0 0 1.5rem 1.5rem;
            box-shadow: var(--shadow-md);
            padding: 2rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 10% 20%, rgba(255, 255, 255, 0.2) 0%, transparent 50%);
            opacity: 0.5;
        }

        .stat-card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--shadow-sm);
            background: white;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 5px;
            background: var(--gradient-primary);
        }

        .stat-card.success::before { background: var(--gradient-success); }
        .stat-card.info::before { background: var(--gradient-info); }
        .stat-card.warning::before { background: var(--gradient-warning); }

        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.2;
            position: absolute;
            bottom: 0.5rem;
            left: 0.5rem;
            color: var(--primary);
            transition: transform 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .stat-card.success .stat-number { color: var(--success); }
        .stat-card.info .stat-number { color: var(--info); }
        .stat-card.warning .stat-number { color: var(--warning); }

        .stat-title {
            font-size: 0.9rem;
            color: var(--dark);
            text-transform: uppercase;
            font-weight: 600;
        }

        .stat-desc {
            font-size: 0.75rem;
            color: var(--gray-600);
        }

        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .content-card:hover {
            box-shadow: var(--shadow-md);
        }

        .content-card .card-header {
            background: transparent;
            border-bottom: 1px solid var(--light);
            font-weight: 700;
            color: var(--dark);
            padding: 1rem 1.5rem;
            border-radius: 1rem 1rem 0 0;
        }

        .content-card .card-body {
            padding: 1.5rem;
        }

        .scroll-container {
            max-height: 300px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) transparent;
        }

        .scroll-container::-webkit-scrollbar {
            width: 6px;
        }

        .scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .task-item, .book-item, .course-item, .recent-study-item {
            background: var(--light);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid var(--gray-300);
            transition: all 0.3s ease;
        }

        .task-item:hover, .book-item:hover, .course-item:hover, .recent-study-item:hover {
            background: white;
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }

        .priority-high { border-left-color: var(--danger); }
        .priority-medium { border-left-color: var(--warning); }
        .priority-low { border-left-color: var(--success); }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(180deg, var(--primary-light) 10%, var(--primary) 100%);
            transform: translateY(-2px);
        }

        .badge-primary { background: var(--gradient-primary); }
        .badge-success { background: var(--gradient-success); }
        .badge-info { background: var(--gradient-info); }
        .badge-warning { background: var(--gradient-warning); }

        .progress {
            height: 6px;
            border-radius: 3px;
            background-color: var(--light);
        }

        .progress-bar {
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        .empty-state {
            padding: 2rem;
            text-align: center;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.4;
        }

        .summary-card {
            background: white;
            border-radius: 1rem;
            padding: 1.25rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .summary-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            transition: transform 0.3s ease;
        }

        .summary-card:hover .summary-icon {
            transform: rotate(10deg);
        }

        .study-chart {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .chart-bar {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .chart-bar-label {
            width: 70px;
            font-size: 0.8rem;
            color: var(--dark);
        }

        .chart-bar-track {
            flex-grow: 1;
            height: 15px;
            background: var(--light);
            border-radius: 7px;
            overflow: hidden;
            margin: 0 0.75rem;
        }

        .chart-bar-fill {
            height: 100%;
            background: var(--gradient-primary);
            border-radius: 7px;
            transition: width 0.5s ease;
        }

        .chart-bar-value {
            width: 25px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--dark);
        }

        .toast {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1050;
            min-width: 200px;
            background: white;
            border-radius: 0.5rem;
            box-shadow: var(--shadow-md);
            padding: 1rem;
            display: none;
        }

        .toast.success { border-left: 4px solid var(--success); }
        .toast.error { border-left: 4px solid var(--danger); }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Toast برای نمایش پیام‌ها -->
    <div id="toast" class="toast">
        <span id="toast-message"></span>
    </div>

    <div class="dashboard-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="h3 mb-2">اتوماسیون برنامه‌ریزی شخصی</h1>
                    <p class="mb-0">مدیریت هوشمند کارها و مطالعات</p>
                </div>
                <div class="col-lg-4 text-lg-start">
                    <span class="badge bg-light text-dark px-3 py-2">
                        <i class="bi bi-calendar-event me-2"></i>
                        <?php echo get_current_jalali_date(); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- کارت‌های آمار -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="card-body">
                        <i class="bi bi-list-check stat-icon"></i>
                        <div class="stat-number"><?php echo $task_count; ?></div>
                        <div class="stat-title">کارهای فعال</div>
                        <div class="stat-desc">در حال انجام</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card success">
                    <div class="card-body">
                        <i class="bi bi-book stat-icon"></i>
                        <div class="stat-number"><?php echo $book_count; ?></div>
                        <div class="stat-title">کتاب‌های کامل</div>
                        <div class="stat-desc">تکمیل شده</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card info">
                    <div class="card-body">
                        <i class="bi bi-mortarboard stat-icon"></i>
                        <div class="stat-number"><?php echo $course_count; ?></div>
                        <div class="stat-title">دوره‌های فعال</div>
                        <div class="stat-desc">در حال گذراندن</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card warning">
                    <div class="card-body">
                        <i class="bi bi-calendar-check stat-icon"></i>
                        <div class="stat-number"><?php echo $weekly_study_count; ?></div>
                        <div class="stat-title">فصل‌های مطالعه شده</div>
                        <div class="stat-desc">این هفته</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- محتوای اصلی -->
        <div class="row g-4">
            <!-- کارهای اخیر -->
            <div class="col-lg-4">
                <div class="content-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-task me-2"></i>کارهای اخیر</span>
                        <a href="tasks.php" class="btn btn-sm btn-primary">مشاهده همه</a>
                    </div>
                    <div class="card-body">
                        <div class="scroll-container">
                            <?php if (!empty($recent_tasks)): ?>
                                <?php foreach ($recent_tasks as $task): 
                                    $priority = $task['priority'] ?? 'low';
                                    if ($priority == 'high') {
                                        $priority_class = 'priority-high';
                                        $priority_text = 'فوری';
                                        $badge_class = 'bg-danger';
                                    } elseif ($priority == 'medium') {
                                        $priority_class = 'priority-medium';
                                        $priority_text = 'متوسط';
                                        $badge_class = 'bg-warning';
                                    } else {
                                        $priority_class = 'priority-low';
                                        $priority_text = 'عادی';
                                        $badge_class = 'bg-success';
                                    }
                                    
                                    $due_date = function_exists('format_jalali_date') && isset($task['due_date']) ? 
                                        format_jalali_date($task['due_date']) : 
                                        ($task['due_date'] ?? 'تعیین نشده');
                                ?>
                                <div class="task-item <?php echo $priority_class; ?>" data-task-id="<?php echo $task['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 text-dark text-small"><?php echo htmlspecialchars($task['title'] ?? 'بدون عنوان'); ?></h6>
                                        <span class="badge <?php echo $badge_class; ?> text-xsmall"><?php echo $priority_text; ?></span>
                                    </div>
                                    <p class="mb-2 text-muted text-xsmall"><?php echo htmlspecialchars($task['description'] ?? 'بدون توضیح'); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted text-xsmall"><i class="bi bi-clock me-1"></i> <?php echo $due_date; ?></small>
                                        <?php if (isset($task['id'])): ?>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-success complete-task" data-task-id="<?php echo $task['id']; ?>" data-csrf-token="<?php echo $csrf_token; ?>"><i class="bi bi-check"></i></button>
                                            <a href="tasks.php?action=delete&id=<?php echo $task['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این کار مطمئن هستید؟')"><i class="bi bi-trash"></i></a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-check-circle text-success"></i>
                                <p class="mb-2">هیچ کاری برای نمایش وجود ندارد</p>
                                <a href="tasks.php" class="btn btn-sm btn-primary">افزودن کار جدید</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- کتاب‌های اخیر -->
            <div class="col-lg-4">
                <div class="content-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-book me-2"></i>کتاب‌های اخیر</span>
                        <a href="books.php" class="btn btn-sm btn-primary">مشاهده همه</a>
                    </div>
                    <div class="card-body">
                        <div class="scroll-container">
                            <?php if (!empty($recent_books)): ?>
                                <?php foreach ($recent_books as $book): 
                                    $progress = $book['progress'] ?? 0;
                                    if ($progress == 100) {
                                        $progress_color = 'bg-success';
                                        $status_icon = 'bi-check-circle';
                                    } elseif ($progress > 50) {
                                        $progress_color = 'bg-primary';
                                        $status_icon = 'bi-book';
                                    } else {
                                        $progress_color = 'bg-warning';
                                        $status_icon = 'bi-bookmark';
                                    }
                                ?>
                                <div class="book-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <i class="bi <?php echo $status_icon; ?> fs-5 text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1 text-dark text-small"><?php echo htmlspecialchars($book['title'] ?? 'بدون عنوان'); ?></h6>
                                            <p class="mb-1 text-muted text-xsmall"><i class="bi bi-person me-1"></i> <?php echo htmlspecialchars($book['author'] ?? 'ناشناس'); ?></p>
                                            <div class="progress mb-1">
                                                <div class="progress-bar <?php echo $progress_color; ?>" role='progressbar' style='width: <?php echo $progress; ?>%;'></div>
                                            </div>
                                            <small class="text-muted text-xsmall"><?php echo $progress; ?>% خوانده شده</small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-book text-info"></i>
                                <p class="mb-2">هیچ کتابی برای نمایش وجود ندارد</p>
                                <a href="books.php" class="btn btn-sm btn-primary">افزودن کتاب جدید</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- دوره‌های اخیر -->
            <div class="col-lg-4">
                <div class="content-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-mortarboard me-2"></i>دوره‌های اخیر</span>
                        <a href="courses.php" class="btn btn-sm btn-primary">مشاهده همه</a>
                    </div>
                    <div class="card-body">
                        <div class="scroll-container">
                            <?php if (!empty($recent_courses)): ?>
                                <?php foreach ($recent_courses as $course): 
                                    $status = $course['status'] ?? 'در حال گذراندن';
                                    if ($status == 'تکمیل شده') {
                                        $status_class = 'badge-success';
                                        $status_icon = 'bi-check-circle';
                                    } elseif ($status == 'در حال گذراندن') {
                                        $status_class = 'badge-primary';
                                        $status_icon = 'bi-play-circle';
                                    } else {
                                        $status_class = 'badge-secondary';
                                        $status_icon = 'bi-pause-circle';
                                    }
                                    
                                    $progress = $course['progress'] ?? 0;
                                ?>
                                <div class="course-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <i class="bi <?php echo $status_icon; ?> fs-5 text-info"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1 text-dark text-small"><?php echo htmlspecialchars($course['title'] ?? 'بدون عنوان'); ?></h6>
                                            <p class="mb-1 text-muted text-xsmall"><i class="bi bi-person me-1"></i> <?php echo htmlspecialchars($course['instructor'] ?? 'نامشخص'); ?></p>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="badge <?php echo $status_class; ?> text-xsmall"><?php echo $status; ?></span>
                                                <small class="text-muted text-xsmall"><?php echo $progress; ?>% پیشرفت</small>
                                            </div>
                                            <?php if ($progress > 0 && $progress < 100): ?>
                                            <div class="progress" style="height: 4px;">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $progress; ?>%;"></div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-mortarboard text-info"></i>
                                <p class="mb-2">هیچ دوره‌ای برای نمایش وجود ندارد</p>
                                <a href="courses.php" class="btn btn-sm btn-primary">افزودن دوره جدید</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- خلاصه عملکرد و آمار مطالعه -->
        <div class="row mt-4">
            <div class="col-lg-8 mb-4">
                <div class="content-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>آمار مطالعه هفتگی</h5>
                    </div>
                    <div class="card-body">
                        <div class="study-chart">
                            <?php if (!empty($daily_study_stats)): ?>
                                <?php 
                                $max_count = max(array_column($daily_study_stats, 'count'));
                                $max_count = $max_count > 0 ? $max_count : 1;
                                ?>
                                <?php foreach ($daily_study_stats as $stat): ?>
                                    <div class="chart-bar">
                                        <div class="chart-bar-label"><?php echo $stat['day_name']; ?></div>
                                        <div class="chart-bar-track">
                                            <div class="chart-bar-fill" style="width: <?php echo ($stat['count'] / $max_count) * 100; ?>%;"></div>
                                        </div>
                                        <div class="chart-bar-value"><?php echo $stat['count']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-bar-chart text-info"></i>
                                    <p class="mb-2">هیچ داده‌ای برای نمایش وجود ندارد</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="content-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>مطالعات اخیر</h5>
                    </div>
                    <div class="card-body">
                        <div class="scroll-container">
                            <?php if (!empty($weekly_completed_chapters)): ?>
                                <?php foreach ($weekly_completed_chapters as $chapter): 
                                    $is_book = isset($chapter['book_title']);
                                    $title = $is_book ? $chapter['book_title'] : $chapter['course_title'];
                                    $chapter_title = $chapter['title'];
                                    $completed_date = !empty($chapter['completed_date']) ? format_jalali_date($chapter['completed_date']) : 'امروز';
                                ?>
                                <div class="recent-study-item">
                                    <div class="recent-study-icon">
                                        <i class="bi <?php echo $is_book ? 'bi-book' : 'bi-mortarboard'; ?>"></i>
                                    </div>
                                    <div class="recent-study-content">
                                        <div class="recent-study-title"><?php echo htmlspecialchars($chapter_title); ?></div>
                                        <div class="recent-study-desc"><?php echo htmlspecialchars($title); ?></div>
                                        <div class="recent-study-date"><?php echo $completed_date; ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-check-circle text-success"></i>
                                    <p class="mb-2">هنوز مطالعه‌ای ثبت نشده است</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- خلاصه عملکرد -->
        <div class="row">
            <div class="col-12">
                <div class="content-card">
                    <div class="card-header text-center">
                        <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>خلاصه عملکرد هفتگی</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <div class="summary-card">
                                    <i class="bi bi-check-circle-fill text-success summary-icon"></i>
                                    <div class="summary-number"><?php echo $weekly_study_count; ?></div>
                                    <div class="summary-text">فصل‌های مطالعه شده</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="summary-card">
                                    <i class="bi bi-book-fill text-info summary-icon"></i>
                                    <div class="summary-number"><?php echo $task_count; ?></div>
                                    <div class="summary-text">کارهای فعال</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="summary-card">
                                    <i class="bi bi-clock-fill text-warning summary-icon"></i>
                                    <div class="summary-number"><?php echo round($total_study_hours); ?>h</div>
                                    <div class="summary-text">ساعت مطالعه</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="summary-card">
                                    <i class="bi bi-star-fill text-primary summary-icon"></i>
                                    <div class="summary-number"><?php echo $satisfaction_percentage; ?>%</div>
                                    <div class="summary-text">رضایت از عملکرد</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // انیمیشن برای کارت‌ها
            const cards = document.querySelectorAll('.stat-card, .content-card, .summary-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // مدیریت تکمیل کارها با AJAX
            document.querySelectorAll('.complete-task').forEach(button => {
                button.addEventListener('click', function() {
                    const taskId = this.getAttribute('data-task-id');
                    const csrfToken = this.getAttribute('data-csrf-token');
                    const taskItem = this.closest('.task-item');

                    fetch('dashboard.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=complete_task&task_id=${taskId}&csrf_token=${csrfToken}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        const toast = document.getElementById('toast');
                        const toastMessage = document.getElementById('toast-message');
                        toast.style.display = 'block';
                        
                        if (data.success) {
                            toast.className = 'toast success';
                            toastMessage.textContent = data.message || 'کار با موفقیت تکمیل شد!';
                            taskItem.style.transition = 'opacity 0.5s ease';
                            taskItem.style.opacity = '0';
                            setTimeout(() => {
                                taskItem.remove();
                            }, 500);
                        } else {
                            toast.className = 'toast error';
                            toastMessage.textContent = data.error || 'خطا در تکمیل کار!';
                        }

                        setTimeout(() => {
                            toast.style.display = 'none';
                        }, 3000);
                    })
                    .catch(error => {
                        const toast = document.getElementById('toast');
                        const toastMessage = document.getElementById('toast-message');
                        toast.className = 'toast error';
                        toastMessage.textContent = 'خطا در ارتباط با سرور!';
                        toast.style.display = 'block';
                        setTimeout(() => {
                            toast.style.display = 'none';
                        }, 3000);
                    });
                });
            });

            // انیمیشن برای نمودار
            const chartBars = document.querySelectorAll('.chart-bar-fill');
            chartBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.transition = 'width 0.5s ease';
                    bar.style.width = width;
                }, 500);
            });
        });
    </script>
</body>
</html>

<?php include 'footer.php'; ?>