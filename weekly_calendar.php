<?php
include 'header.php';
include 'config.php';

// تاریخ شروع و پایان هفته
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

// دریافت فعالیت‌های هفتگی
try {
    $weekly_activities = get_weekly_activities($pdo, $user_id, $current_week_start, $current_week_end);
} catch(PDOException $e) {
    die("خطا در دریافت فعالیت‌های هفتگی: " . $e->getMessage());
}

// گروه‌بندی فعالیت‌ها بر اساس تاریخ
$grouped_activities = [];
foreach ($weekly_activities as $activity) {
    $date = $activity['date'];
    if (!isset($grouped_activities[$date])) {
        $grouped_activities[$date] = [];
    }
    $grouped_activities[$date][] = $activity;
}

// روزهای هفته
$days_of_week = [
    'monday' => 'دوشنبه',
    'tuesday' => 'سه‌شنبه',
    'wednesday' => 'چهارشنبه',
    'thursday' => 'پنجشنبه',
    'friday' => 'جمعه',
    'saturday' => 'شنبه',
    'sunday' => 'یکشنبه'
];
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">تقویم هفتگی و فعالیت‌ها</h1>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    در این صفحه می‌توانید فعالیت‌های برنامه‌ریزی شده برای هر روز از هفته را مشاهده و مدیریت کنید.
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">هفته: <?php echo format_jalali_date($current_week_start) . ' - ' . format_jalali_date($current_week_end); ?></h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>روز</th>
                        <th>تاریخ</th>
                        <th>فعالیت‌ها</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($days_of_week as $english_day => $persian_day) {
                        $current_date = date('Y-m-d', strtotime($english_day . ' this week'));
                        $jalali_date = format_jalali_date($current_date);
                        
                        echo "<tr>";
                        echo "<td><strong>{$persian_day}</strong></td>";
                        echo "<td>{$jalali_date}</td>";
                        echo "<td>";
                        
                        if (isset($grouped_activities[$current_date]) && !empty($grouped_activities[$current_date])) {
                            echo "<div class='activities-list'>";
                            foreach ($grouped_activities[$current_date] as $activity) {
                                if ($activity['type'] == 'course_chapter') {
                                    echo "<div class='activity-item mb-2 p-2 border rounded'>";
                                    echo "<span class='badge bg-info me-2'>دوره</span>";
                                    echo "<strong>{$activity['course_name']}</strong> - {$activity['title']}";
                                    echo "</div>";
                                } else {
                                    echo "<div class='activity-item mb-2 p-2 border rounded'>";
                                    echo "<span class='badge bg-primary me-2'>کار</span>";
                                    echo $activity['title'];
                                    echo "</div>";
                                }
                            }
                            echo "</div>";
                        } else {
                            echo "<span class='text-muted'>هیچ فعالیتی برنامه‌ریزی نشده است</span>";
                        }
                        
                        echo "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between">
    <a href="weekly_calendar.php?week=prev" class="btn btn-outline-primary">هفته قبل</a>
    <a href="weekly_calendar.php?week=today" class="btn btn-primary">هفته جاری</a>
    <a href="weekly_calendar.php?week=next" class="btn btn-outline-primary">هفته بعد</a>
</div>

<style>
.activities-list {
    max-height: 200px;
    overflow-y: auto;
}
.activity-item {
    background-color: #f8f9fa;
}
.activity-item:hover {
    background-color: #e9ecef;
}
</style>

<?php
include 'footer.php';
?>