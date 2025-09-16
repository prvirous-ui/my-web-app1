<?php 
include 'header.php';
include 'config.php';

// تابع تبدیل تاریخ میلادی به شمسی
function persian_date() {
    $date = date('Y-m-d');
    return format_jalali_date($date);
}

// تابع فرمت‌دهی تاریخ شمسی
function format_jalali_date($date) {
    // اگر کتابخانه‌ای مثل jdf دارید از آن استفاده کنید
    // در غیر این صورت یک تبدیل ساده انجام می‌دهیم
    $timestamp = strtotime($date);
    $jalali_date = date('Y/m/d', $timestamp); // اینجا می‌توانید از کتابخانه کامل‌تری استفاده کنید
    
    // برای نمایش بهتر، می‌توانید از کتابخانه‌های موجود استفاده کنید
    // به عنوان مثال اگر از Composer استفاده می‌کنید، می‌توانید از package "morilog/jalali" استفاده کنید
    return $jalali_date;
}

// دریافت آمار واقعی از دیتابیس
try {
    // آمار کلی کارها
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN priority = 'high' AND completed = 0 THEN 1 ELSE 0 END) as urgent_tasks
        FROM tasks
    ");
    $task_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // آمار کلی کتاب‌ها
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_books,
            SUM(CASE WHEN progress = 100 THEN 1 ELSE 0 END) as completed_books,
            AVG(progress) as avg_progress
        FROM books
    ");
    $book_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // آمار کلی دوره‌ها
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_courses,
            SUM(CASE WHEN progress = 100 THEN 1 ELSE 0 END) as completed_courses,
            AVG(progress) as avg_progress
        FROM courses
    ");
    $course_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // توزیع فعالیت‌ها
    $stmt = $pdo->query("
        SELECT 'tasks' as type, COUNT(*) as count FROM tasks
        UNION ALL
        SELECT 'books' as type, COUNT(*) as count FROM books
        UNION ALL
        SELECT 'courses' as type, COUNT(*) as count FROM courses
    ");
    $activity_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // پیشرفت هفتگی (آخرین ۷ روز)
    $weekly_data = [];
    $weekly_labels = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $jdate = format_jalali_date($date);
        $weekly_labels[] = $jdate;
        
        // کارهای ایجاد شده در این تاریخ
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM tasks 
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date]);
        $weekly_data['tasks'][] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // کتاب‌های ایجاد شده در این تاریخ
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM books 
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date]);
        $weekly_data['books'][] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }

    // کارایی روزانه (آخرین ۷ روز)
    $daily_performance = [];
    $daily_labels = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $jdate = format_jalali_date($date);
        $daily_labels[] = $jdate;
        
        // تعداد کارهای انجام شده در این تاریخ
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM tasks 
            WHERE DATE(created_at) = ? AND completed = 1
        ");
        $stmt->execute([$date]);
        $completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // تخمین ساعت‌های مفید (فرض: هر کار ۲ ساعت)
        $daily_performance[] = $completed * 2;
    }

} catch(PDOException $e) {
    die("خطا در دریافت آمار: " . $e->getMessage());
}

// محاسبه درصدها برای نمودار دایره‌ای
$total_activities = ($task_stats['total_tasks'] ?? 0) + ($book_stats['total_books'] ?? 0) + ($course_stats['total_courses'] ?? 0);
$task_percentage = $total_activities > 0 ? round((($task_stats['total_tasks'] ?? 0) / $total_activities) * 100) : 0;
$book_percentage = $total_activities > 0 ? round((($book_stats['total_books'] ?? 0) / $total_activities) * 100) : 0;
$course_percentage = $total_activities > 0 ? round((($course_stats['total_courses'] ?? 0) / $total_activities) * 100) : 0;

// تاریخ شمسی برای فیلترها
$today_jalali = persian_date();
$week_ago = date('Y-m-d', strtotime('-7 days'));
$week_ago_jalali = format_jalali_date($week_ago);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">گزارش‌گیری و آمار</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="generatePDF()">
            <i class="bi bi-download me-1"></i>
            خروجی PDF
        </button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-primary active" onclick="changeReportRange('weekly')">هفتگی</button>
            <button type="button" class="btn btn-outline-primary" onclick="changeReportRange('monthly')">ماهانه</button>
            <button type="button" class="btn btn-outline-primary" onclick="changeReportRange('yearly')">سالانه</button>
        </div>
    </div>
    <div class="col-md-4">
        <div class="input-group">
            <input type="text" class="form-control" id="startDateFilter" placeholder="<?php echo $week_ago_jalali; ?>" value="<?php echo $week_ago_jalali; ?>">
            <span class="input-group-text">تا</span>
            <input type="text" class="form-control" id="endDateFilter" placeholder="<?php echo $today_jalali; ?>" value="<?php echo $today_jalali; ?>">
            <button class="btn btn-primary" type="button" onclick="applyDateFilters()">
                <i class="bi bi-filter"></i>
            </button>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">فعالیت‌های هفتگی</h6>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="weeklyProgressChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">توزیع فعالیت‌ها</h6>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="activityDistributionChart" height="200"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <span class="mr-2">
                        <i class="bi bi-circle-fill text-primary"></i> کارها (<?php echo $task_percentage; ?>%)
                    </span>
                    <span class="mr-2">
                        <i class="bi bi-circle-fill text-success"></i> کتاب‌ها (<?php echo $book_percentage; ?>%)
                    </span>
                    <span class="mr-2">
                        <i class="bi bi-circle-fill text-info"></i> دوره‌ها (<?php echo $course_percentage; ?>%)
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">آمار کلی</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>نوع فعالیت</th>
                                <th>تعداد کل</th>
                                <th>تکمیل شده</th>
                                <th>درصد پیشرفت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>کارها</td>
                                <td><?php echo $task_stats['total_tasks'] ?? 0; ?></td>
                                <td><?php echo $task_stats['completed_tasks'] ?? 0; ?></td>
                                <td><?php echo ($task_stats['total_tasks'] ?? 0) > 0 ? round((($task_stats['completed_tasks'] ?? 0) / ($task_stats['total_tasks'] ?? 1)) * 100) : 0; ?>%</td>
                            </tr>
                            <tr>
                                <td>کتاب‌ها</td>
                                <td><?php echo $book_stats['total_books'] ?? 0; ?></td>
                                <td><?php echo $book_stats['completed_books'] ?? 0; ?></td>
                                <td><?php echo round($book_stats['avg_progress'] ?? 0); ?>%</td>
                            </tr>
                            <tr>
                                <td>دوره‌ها</td>
                                <td><?php echo $course_stats['total_courses'] ?? 0; ?></td>
                                <td><?php echo $course_stats['completed_courses'] ?? 0; ?></td>
                                <td><?php echo round($course_stats['avg_progress'] ?? 0); ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">کارایی روزانه</h6>
            </div>
            <div class="card-body">
                <div class="chart-bar">
                    <canvas id="dailyPerformanceChart" height="150"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- بخش آمار فوری -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">کل فعالیت‌ها</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_activities; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-grid-1x2 fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">تکمیل شده</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo ($task_stats['completed_tasks'] ?? 0) + ($book_stats['completed_books'] ?? 0) + ($course_stats['completed_courses'] ?? 0); ?>
                        </div>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">میانگین پیشرفت</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                            $task_progress = ($task_stats['total_tasks'] ?? 0) > 0 ? 
                                (($task_stats['completed_tasks'] ?? 0) / ($task_stats['total_tasks'] ?? 1) * 100) : 0;
                            
                            $avg_progress = (
                                $task_progress + 
                                ($book_stats['avg_progress'] ?? 0) + 
                                ($course_stats['avg_progress'] ?? 0)
                            ) / 3;
                            echo round($avg_progress); 
                            ?>%
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-graph-up fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">کارهای فوری</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $task_stats['urgent_tasks'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // نمودار فعالیت‌های هفتگی
    const weeklyProgressCtx = document.getElementById('weeklyProgressChart').getContext('2d');
    const weeklyProgressChart = new Chart(weeklyProgressCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($weekly_labels); ?>,
            datasets: [{
                label: 'کارهای جدید',
                data: <?php echo json_encode($weekly_data['tasks']); ?>,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                fill: true,
                tension: 0.4
            }, {
                label: 'کتاب‌های جدید',
                data: <?php echo json_encode($weekly_data['books']); ?>,
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.05)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'تعداد'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'تاریخ'
                    }
                }
            }
        }
    });

    // نمودار توزیع فعالیت‌ها
    const activityDistributionCtx = document.getElementById('activityDistributionChart').getContext('2d');
    const activityDistributionChart = new Chart(activityDistributionCtx, {
        type: 'doughnut',
        data: {
            labels: ['کارها', 'کتاب‌ها', 'دوره‌ها'],
            datasets: [{
                data: [
                    <?php echo $task_stats['total_tasks'] ?? 0; ?>, 
                    <?php echo $book_stats['total_books'] ?? 0; ?>, 
                    <?php echo $course_stats['total_courses'] ?? 0; ?>
                ],
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc'],
                hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf'],
                hoverBorderColor: 'rgba(234, 236, 244, 1)',
            }],
        },
        options: {
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            let value = context.raw || 0;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        },
    });

    // نمودار کارایی روزانه
    const dailyPerformanceCtx = document.getElementById('dailyPerformanceChart').getContext('2d');
    const dailyPerformanceChart = new Chart(dailyPerformanceCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($daily_labels); ?>,
            datasets: [{
                label: 'ساعات مفید',
                data: <?php echo json_encode($daily_performance); ?>,
                backgroundColor: '#4e73df',
                maxBarThickness: 25,
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'ساعات مفید'
                    },
                    ticks: {
                        callback: function(value) {
                            return value + 'h';
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'تاریخ'
                    }
                }
            }
        }
    });

    // توابع مدیریت فیلترها
    function changeReportRange(range) {
        // تغییر دامنه گزارش
        alert('تغییر دامنه گزارش به: ' + range);
        // اینجا می‌توانید درخواست AJAX برای دریافت داده‌های جدید ارسال کنید
    }

    function applyDateFilters() {
        const startDate = document.getElementById('startDateFilter').value;
        const endDate = document.getElementById('endDateFilter').value;
        
        if (startDate && endDate) {
            alert('اعمال فیلتر تاریخ از ' + startDate + ' تا ' + endDate);
            // اینجا می‌توانید درخواست AJAX برای فیلتر کردن داده‌ها ارسال کنید
        } else {
            alert('لطفاً هر دو تاریخ را انتخاب کنید');
        }
    }

    function generatePDF() {
        alert('در حال تولید گزارش PDF...');
        // اینجا می‌توانید از کتابخانه‌هایی مانند jsPDF برای تولید PDF استفاده کنید
    }
</script>

<?php include 'footer.php'; ?>