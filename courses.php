<?php
// فعال کردن error logging برای دیباگ
error_reporting(E_ALL);
ini_set('display_errors', 0); // برای محیط production خاموش کن
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Include فایل‌های لازم
require_once 'config.php';
require_once 'auth.php';
require_once 'jdate.php';

// بررسی لاگین
$user_id = get_user_id();
if (!$user_id) {
    redirect("login.php");
}

// توابع کمکی
function check_user_ownership($pdo, $table, $id, $user_id) {
    $stmt = $pdo->prepare("SELECT user_id FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    return $item && $item['user_id'] == $user_id;
}

function validate_jalali_date($date_str) {
    if (empty($date_str)) return null;
    // پشتیبانی از فرمت‌های YYYY/MM/DD و YYYY-M-DD
    if (!preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/', $date_str, $matches)) {
        return false;
    }
    $year = intval($matches[1]);
    $month = intval($matches[2]);
    $day = intval($matches[3]);
    if ($year < 1300 || $year > 1500 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
        return false;
    }
    if ($month > 6 && $day > 30) return false; // ماه‌های دوم سال حداکثر 30 روز
    if ($month == 12 && $day > 29) return false; // اسفنده
    return [$year, $month, $day];
}

// پردازش درخواست‌های AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'توکن CSRF نامعتبر است']);
        exit;
    }
    
    try {
        if ($_POST['action'] == 'add_chapter' || $_POST['action'] == 'edit_chapter') {
            $course_id = intval($_POST['course_id'] ?? 0);
            $title = sanitize_input($_POST['title'] ?? '');
            $study_date_str = sanitize_input($_POST['study_date'] ?? '');
            $study_hours = floatval($_POST['study_hours'] ?? 0);
            
            if (empty($course_id) || empty($title)) {
                echo json_encode(['success' => false, 'error' => 'عنوان و دوره الزامی است']);
                exit;
            }
            
            if (!check_user_ownership($pdo, 'courses', $course_id, $user_id)) {
                echo json_encode(['success' => false, 'error' => 'شما اجازه دسترسی ندارید']);
                exit;
            }
            
            $study_date_mysql = null;
            if (!empty($study_date_str)) {
                $date_parts = validate_jalali_date($study_date_str);
                if ($date_parts === false) {
                    echo json_encode(['success' => false, 'error' => 'فرمت تاریخ نامعتبر است (مثال: 1404/06/23 یا 1404-06-23)']);
                    exit;
                }
                list($year, $month, $day) = $date_parts;
                list($gy, $gm, $gd) = jalali_to_gregorian($year, $month, $day);
                $study_date_mysql = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
            }
            
            if ($_POST['action'] == 'add_chapter') {
                $stmt = $pdo->prepare("INSERT INTO course_chapters (course_id, user_id, title, study_date, study_hours, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$course_id, $user_id, $title, $study_date_mysql, $study_hours]);
                $chapter_id = $pdo->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'message' => 'فصل با موفقیت اضافه شد',
                    'chapter' => [
                        'id' => $chapter_id,
                        'title' => $title,
                        'study_date' => $study_date_mysql ? format_jalali_date($study_date_mysql) : '',
                        'study_hours' => $study_hours,
                        'completed' => false
                    ]
                ]);
            } else {
                $chapter_id = intval($_POST['chapter_id'] ?? 0);
                if (empty($chapter_id)) {
                    echo json_encode(['success' => false, 'error' => 'شناسه فصل نامعتبر است']);
                    exit;
                }
                if (!check_user_ownership($pdo, 'course_chapters', $chapter_id, $user_id)) {
                    echo json_encode(['success' => false, 'error' => 'شما اجازه ویرایش این فصل را ندارید']);
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE course_chapters SET title = ?, study_date = ?, study_hours = ?, updated_at = NOW() WHERE id = ? AND course_id = ?");
                $stmt->execute([$title, $study_date_mysql, $study_hours, $chapter_id, $course_id]);
                echo json_encode([
                    'success' => true,
                    'message' => 'فصل با موفقیت ویرایش شد',
                    'chapter' => [
                        'id' => $chapter_id,
                        'title' => $title,
                        'study_date' => $study_date_mysql ? format_jalali_date($study_date_mysql) : '',
                        'study_hours' => $study_hours
                    ]
                ]);
            }
            
            // بروزرسانی progress
            $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(completed) as completed FROM course_chapters WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $chapters = $stmt->fetch();
            $progress = $chapters['total'] > 0 ? round(($chapters['completed'] / $chapters['total']) * 100) : 0;
            $stmt = $pdo->prepare("UPDATE courses SET progress = ? WHERE id = ?");
            $stmt->execute([$progress, $course_id]);
            
            exit;
        }
        
        if ($_POST['action'] == 'add_course') {
            $title = sanitize_input($_POST['title'] ?? '');
            $instructor = sanitize_input($_POST['instructor'] ?? '');
            $platform = sanitize_input($_POST['platform'] ?? 'نامشخص');
            $status = sanitize_input($_POST['status'] ?? 'در حال مشاهده');
            
            if (empty($title) || empty($instructor)) {
                echo json_encode(['success' => false, 'error' => 'عنوان و مدرس الزامی است']);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO courses (user_id, title, instructor, platform, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $title, $instructor, $platform, $status]);
            $course_id = $pdo->lastInsertId();
            
            $new_chapters = [];
            if (isset($_POST['chapters']) && is_array($_POST['chapters'])) {
                foreach ($_POST['chapters'] as $chapter) {
                    $ch_title = sanitize_input($chapter['title'] ?? '');
                    $study_date_str = sanitize_input($chapter['study_date'] ?? '');
                    $study_hours = floatval($chapter['study_hours'] ?? 0);
                    
                    if (empty($ch_title)) continue;
                    
                    $study_date_mysql = null;
                    if (!empty($study_date_str)) {
                        $date_parts = validate_jalali_date($study_date_str);
                        if ($date_parts === false) continue;
                        list($year, $month, $day) = $date_parts;
                        list($gy, $gm, $gd) = jalali_to_gregorian($year, $month, $day);
                        $study_date_mysql = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO course_chapters (course_id, user_id, title, study_date, study_hours, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$course_id, $user_id, $ch_title, $study_date_mysql, $study_hours]);
                    $chapter_id = $pdo->lastInsertId();
                    $new_chapters[] = [
                        'id' => $chapter_id,
                        'title' => $ch_title,
                        'study_date' => $study_date_mysql ? format_jalali_date($study_date_mysql) : '',
                        'study_hours' => $study_hours,
                        'completed' => false
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'دوره با موفقیت افزوده شد',
                'course' => [
                    'id' => $course_id,
                    'title' => $title,
                    'instructor' => $instructor,
                    'platform' => $platform,
                    'status' => $status,
                    'progress' => 0,
                    'created_at' => date('Y/m/d'),
                    'chapters' => $new_chapters
                ]
            ]);
            exit;
        }
        
        if ($_POST['action'] == 'edit_course') {
            $course_id = intval($_POST['course_id'] ?? 0);
            $title = sanitize_input($_POST['title'] ?? '');
            $instructor = sanitize_input($_POST['instructor'] ?? '');
            $platform = sanitize_input($_POST['platform'] ?? 'نامشخص');
            $status = sanitize_input($_POST['status'] ?? 'در حال مشاهده');
            
            if (empty($course_id) || empty($title) || empty($instructor)) {
                echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص است']);
                exit;
            }
            
            if (!check_user_ownership($pdo, 'courses', $course_id, $user_id)) {
                echo json_encode(['success' => false, 'error' => 'شما اجازه ویرایش این دوره را ندارید']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE courses SET title = ?, instructor = ?, platform = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $instructor, $platform, $status, $course_id]);
            
            echo json_encode(['success' => true, 'message' => 'دوره با موفقیت ویرایش شد']);
            exit;
        }
        
        if ($_POST['action'] == 'delete_course') {
            $course_id = intval($_POST['course_id'] ?? 0);
            if (!check_user_ownership($pdo, 'courses', $course_id, $user_id)) {
                echo json_encode(['success' => false, 'error' => 'شما اجازه حذف این دوره را ندارید']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM course_chapters WHERE course_id = ?");
            $stmt->execute([$course_id]);
            
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            
            echo json_encode(['success' => true, 'message' => 'دوره و فصل‌های مربوطه با موفقیت حذف شدند']);
            exit;
        }
        
        if ($_POST['action'] == 'toggle_chapter') {
            $chapter_id = intval($_POST['chapter_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT course_id, completed FROM course_chapters WHERE id = ?");
            $stmt->execute([$chapter_id]);
            $chapter = $stmt->fetch();
            if (!$chapter || !check_user_ownership($pdo, 'courses', $chapter['course_id'], $user_id)) {
                echo json_encode(['success' => false, 'error' => 'شما اجازه تغییر این فصل را ندارید']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE course_chapters SET completed = NOT completed, completed_date = CASE WHEN completed = 0 THEN CURDATE() ELSE NULL END WHERE id = ?");
            $stmt->execute([$chapter_id]);
            
            $course_id = $chapter['course_id'];
            $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(completed) as completed FROM course_chapters WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $chapters = $stmt->fetch();
            $progress = $chapters['total'] > 0 ? round(($chapters['completed'] / $chapters['total']) * 100) : 0;
            $stmt = $pdo->prepare("UPDATE courses SET progress = ? WHERE id = ?");
            $stmt->execute([$progress, $course_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'وضعیت فصل تغییر کرد',
                'completed' => !$chapter['completed'],
                'progress' => $progress
            ]);
            exit;
        }
        
        if ($_POST['action'] == 'delete_chapter') {
            $chapter_id = intval($_POST['chapter_id'] ?? 0);
            if (!check_user_ownership($pdo, 'course_chapters', $chapter_id, $user_id)) {
                echo json_encode(['success' => false, 'error' => 'شما اجازه حذف این فصل را ندارید']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT course_id FROM course_chapters WHERE id = ?");
            $stmt->execute([$chapter_id]);
            $chapter = $stmt->fetch();
            $course_id = $chapter['course_id'];
            
            $stmt = $pdo->prepare("DELETE FROM course_chapters WHERE id = ?");
            $stmt->execute([$chapter_id]);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(completed) as completed FROM course_chapters WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $chapters = $stmt->fetch();
            $progress = $chapters['total'] > 0 ? round(($chapters['completed'] / $chapters['total']) * 100) : 0;
            $stmt = $pdo->prepare("UPDATE courses SET progress = ? WHERE id = ?");
            $stmt->execute([$progress, $course_id]);
            
            echo json_encode(['success' => true, 'message' => 'فصل با موفقیت حذف شد', 'progress' => $progress]);
            exit;
        }
        
        if ($_POST['action'] == 'get_chapters') {
            $course_id = intval($_POST['course_id'] ?? 0);
            if (!check_user_ownership($pdo, 'courses', $course_id, $user_id)) {
                echo json_encode(['success' => false, 'error' => 'شما اجازه دسترسی ندارید']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM course_chapters WHERE course_id = ? ORDER BY id");
            $stmt->execute([$course_id]);
            $chapters = $stmt->fetchAll();
            $formatted_chapters = [];
            foreach ($chapters as $chapter) {
                $formatted_chapters[] = [
                    'id' => $chapter['id'],
                    'title' => $chapter['title'],
                    'study_date' => $chapter['study_date'] ? format_jalali_date($chapter['study_date']) : '',
                    'study_hours' => $chapter['study_hours'],
                    'completed' => (bool)$chapter['completed']
                ];
            }
            
            $stmt = $pdo->prepare("SELECT progress FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $course = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'chapters' => $formatted_chapters,
                'progress' => $course['progress']
            ]);
            exit;
        }
        
        if ($_POST['action'] == 'get_courses') {
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $courses = $stmt->fetchAll();
            $formatted_courses = [];
            foreach ($courses as $course) {
                $stmt = $pdo->prepare("SELECT * FROM course_chapters WHERE course_id = ? ORDER BY id");
                $stmt->execute([$course['id']]);
                $chapters = $stmt->fetchAll();
                $formatted_chapters = [];
                foreach ($chapters as $chapter) {
                    $formatted_chapters[] = [
                        'id' => $chapter['id'],
                        'title' => $chapter['title'],
                        'study_date' => $chapter['study_date'] ? format_jalali_date($chapter['study_date']) : '',
                        'study_hours' => $chapter['study_hours'],
                        'completed' => (bool)$chapter['completed']
                    ];
                }
                $formatted_courses[] = [
                    'id' => $course['id'],
                    'title' => $course['title'],
                    'instructor' => $course['instructor'],
                    'platform' => $course['platform'],
                    'status' => $course['status'],
                    'progress' => $course['progress'],
                    'created_at' => date('Y/m/d', strtotime($course['created_at'])),
                    'chapters' => $formatted_chapters
                ];
            }
            echo json_encode([
                'success' => true,
                'courses' => $formatted_courses
            ]);
            exit;
        }
        
        echo json_encode(['success' => false, 'error' => 'اقدام نامعتبر']);
        exit;
    } catch(PDOException $e) {
        error_log("خطا در پردازش: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'خطا در سرور: ' . $e->getMessage()]);
        exit;
    }
}

// دریافت دوره‌ها برای نمایش اولیه
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $courses = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("خطا در دریافت دوره‌ها: " . $e->getMessage());
    $_SESSION['error'] = "خطا در دریافت دوره‌ها: " . $e->getMessage();
    $courses = [];
}

include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
        <h1 class="h3 text-gray-800"><i class="bi bi-mortarboard me-2"></i>مدیریت دوره‌ها</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            <i class="bi bi-plus-circle me-2"></i> افزودن دوره جدید
        </button>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">لیست دوره‌ها</h6>
            <span class="badge bg-primary badge-custom"><?php echo count($courses); ?> دوره</span>
        </div>
        <div class="card-body">
            <div class="row" id="courses-list">
                <!-- دوره‌ها به صورت دینامیک با JS پر می‌شوند -->
            </div>
        </div>
    </div>
</div>

<!-- مودال افزودن دوره جدید -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن دوره جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="course-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_course">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="mb-3">
                        <label for="courseTitle" class="form-label">عنوان دوره</label>
                        <input type="text" class="form-control" id="courseTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="courseInstructor" class="form-label">مدرس</label>
                        <input type="text" class="form-control" id="courseInstructor" name="instructor" required>
                    </div>
                    <div class="mb-3">
                        <label for="coursePlatform" class="form-label">پلتفرم</label>
                        <select class="form-select" id="coursePlatform" name="platform" required>
                            <option value="">انتخاب کنید</option>
                            <option value="یوتیوب">یوتیوب</option>
                            <option value="Udemy">Udemy</option>
                            <option value="Coursera">Coursera</option>
                            <option value="فرادرس">فرادرس</option>
                            <option value="مکتب‌خونه">مکتب‌خونه</option>
                            <option value="دیگر">دیگر</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="courseStatus" class="form-label">وضعیت</label>
                        <select class="form-select" id="courseStatus" name="status" required>
                            <option value="در حال مشاهده">در حال مشاهده</option>
                            <option value="تکمیل شده">تکمیل شده</option>
                            <option value="متوقف شده">متوقف شده</option>
                            <option value="برنامه‌ریزی شده">برنامه‌ریزی شده</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">فصل‌ها (اختیاری)</label>
                        <div id="chapters-container">
                            <div class="chapter-fields row mb-2">
                                <div class="col-md-4">
                                    <input type="text" name="chapters[0][title]" class="form-control" placeholder="عنوان فصل 1">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="chapters[0][study_date]" class="form-control j-datepicker" placeholder="1404/06/23">
                                </div>
                                <div class="col-md-3">
                                    <input type="number" step="0.1" min="0" name="chapters[0][study_hours]" class="form-control" placeholder="ساعت (اختیاری)">
                                </div>
                                <div class="col-md-1 d-flex align-items-center">
                                    <i class="bi bi-plus-circle add-chapter-field text-primary fs-5 cursor-pointer"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">
                        ذخیره دوره
                        <span class="spinner-border spinner-border-sm spinner" role="status"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    :root {
        --primary-color: #1cc88a;
        --light-bg: #f8f9fc;
    }
    .card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        margin-bottom: 1.5rem;
        transition: transform 0.3s ease;
    }
    .card:hover {
        transform: translateY(-5px);
    }
    .course-card {
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .course-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
    }
    .chapter-item {
        border: none;
        border-left: 4px solid transparent;
        margin-bottom: 0.5rem;
        border-radius: 5px;
        transition: background-color 0.3s ease;
    }
    .chapter-item.completed {
        border-left-color: var(--primary-color);
        background-color: rgba(28, 200, 138, 0.1);
    }
    .progress {
        height: 0.5rem;
        margin-bottom: 1rem;
        border-radius: 10px;
    }
    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }
    .btn-primary:hover {
        background-color: #0f9d6e;
        border-color: #0f9d6e;
    }
    .badge-custom {
        padding: 0.4em 0.6em;
        border-radius: 0.35rem;
        font-weight: 500;
    }
    .modal-header, .modal-footer {
        border: none;
        border-radius: 15px 15px 0 0;
    }
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(28, 200, 138, 0.25);
    }
    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    .chapter-fields {
        border: 1px solid #e3e6f0;
        border-radius: 5px;
        padding: 10px;
        margin-bottom: 10px;
    }
    .spinner {
        display: none;
        margin-left: 10px;
    }
</style>

<script>
$(document).ready(function() {
    // فعال‌سازی datepicker
    function initDatepicker() {
        $('.j-datepicker').pDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: false,
            autoClose: true,
            observer: true,
            calendarType: 'persian'
        });
    }
    initDatepicker();

    // نمایش/مخفی کردن spinner
    function showSpinner(btn) {
        btn.find('.spinner').show();
        btn.prop('disabled', true);
    }
    function hideSpinner(btn) {
        btn.find('.spinner').hide();
        btn.prop('disabled', false);
    }

    // بروزرسانی لیست دوره‌ها
    function updateCoursesList() {
        $.ajax({
            type: 'POST',
            url: 'courses.php',
            data: {
                action: 'get_courses',
                csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const coursesList = $('#courses-list');
                    coursesList.empty();
                    if (response.courses.length === 0) {
                        coursesList.append(`
                            <div class="text-center py-5">
                                <i class="bi bi-mortarboard fs-1 text-muted d-block mb-3"></i>
                                <p class="text-muted">هیچ دوره‌ای ثبت نشده است.</p>
                                <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                                    <i class="bi bi-plus-circle me-2"></i> افزودن دوره جدید
                                </button>
                            </div>
                        `);
                        return;
                    }
                    response.courses.forEach(course => {
                        const totalChapters = course.chapters.length;
                        const completedChapters = course.chapters.filter(ch => ch.completed).length;
                        const courseHtml = `
                            <div class="col-md-6 col-lg-4 mb-4" data-course-id="${course.id}">
                                <div class="card course-card h-100">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title mb-0 text-truncate">${course.title}</h5>
                                                <p class="text-muted mb-0">${course.instructor}</p>
                                            </div>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-warning edit-course-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editCourseModal${course.id}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-course-btn" 
                                                        data-course-id="${course.id}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="badge bg-secondary badge-custom">${course.platform}</span>
                                            <span class="badge bg-info badge-custom">${course.status}</span>
                                        </div>
                                        <div class="progress-section mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small text-muted">پیشرفت</span>
                                                <span class="small text-muted progress-text">${Math.round(course.progress)}%</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: ${course.progress}%" 
                                                     aria-valuenow="${course.progress}" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mb-3">
                                            <span class="small text-muted chapters-count">${completedChapters} از ${totalChapters} فصل</span>
                                            <span class="small text-muted">${course.created_at}</span>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-sm btn-outline-primary add-chapter-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#addChapterModal${course.id}">
                                                <i class="bi bi-plus-circle me-1"></i> افزودن فصل
                                            </button>
                                        </div>
                                    </div>
                                    ${course.chapters.length > 0 ? `
                                        <div class="card-footer bg-transparent">
                                            <h6 class="mb-2">فصل‌ها:</h6>
                                            <div class="chapters-list">
                                                ${course.chapters.map(chapter => `
                                                    <div class="chapter-item p-2 mb-2 rounded ${chapter.completed ? 'completed' : ''}" 
                                                         data-chapter-id="${chapter.id}">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="chapter-title">${chapter.title}</div>
                                                            <div class="action-buttons">
                                                                <button class="btn btn-sm ${chapter.completed ? 'btn-outline-warning' : 'btn-outline-success'} toggle-chapter-btn"
                                                                        data-chapter-id="${chapter.id}">
                                                                    <i class="bi ${chapter.completed ? 'bi-arrow-counterclockwise' : 'bi-check-lg'}"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-warning edit-chapter-btn" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#editChapterModal${chapter.id}" 
                                                                        data-title="${chapter.title}" 
                                                                        data-study_date="${chapter.study_date}" 
                                                                        data-study_hours="${chapter.study_hours}" 
                                                                        data-chapter_id="${chapter.id}">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger delete-chapter-btn" 
                                                                        data-chapter-id="${chapter.id}">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        ${chapter.study_date ? `
                                                            <div class="small text-muted mt-1">
                                                                <i class="bi bi-calendar me-1"></i>
                                                                تاریخ مطالعه: ${chapter.study_date}
                                                                ${chapter.study_hours > 0 ? `<span class="ms-2"><i class="bi bi-clock me-1"></i>${chapter.study_hours} ساعت</span>` : ''}
                                                            </div>
                                                        ` : ''}
                                                    </div>
                                                    <div class="modal fade" id="editChapterModal${chapter.id}" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">ویرایش فصل: ${chapter.title}</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST" class="chapter-form">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="action" value="edit_chapter">
                                                                        <input type="hidden" name="chapter_id" value="${chapter.id}">
                                                                        <input type="hidden" name="course_id" value="${course.id}">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">عنوان فصل</label>
                                                                            <input type="text" name="title" class="form-control" value="${chapter.title}" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">تاریخ مطالعه (مثال: 1404/06/23)</label>
                                                                            <input type="text" class="form-control j-datepicker" name="study_date" value="${chapter.study_date}">
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">ساعات مطالعه (اختیاری)</label>
                                                                            <input type="number" step="0.1" min="0" name="study_hours" class="form-control" value="${chapter.study_hours}" placeholder="مثال: 2.5">
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                                                        <button type="submit" class="btn btn-primary">
                                                                            ذخیره تغییرات
                                                                            <span class="spinner-border spinner-border-sm spinner" role="status"></span>
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>
                                <div class="modal fade" id="addChapterModal${course.id}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">افزودن فصل جدید به ${course.title}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" class="chapter-form">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="add_chapter">
                                                    <input type="hidden" name="course_id" value="${course.id}">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">عنوان فصل</label>
                                                        <input type="text" class="form-control" name="title" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">تاریخ مطالعه (مثال: 1404/06/23)</label>
                                                        <input type="text" class="form-control j-datepicker" name="study_date" placeholder="1404/06/23">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">ساعات مطالعه (اختیاری)</label>
                                                        <input type="number" step="0.1" min="0" class="form-control" name="study_hours" placeholder="مثال: 2.5">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                                    <button type="submit" class="btn btn-primary">
                                                        ذخیره فصل
                                                        <span class="spinner-border spinner-border-sm spinner" role="status"></span>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal fade" id="editCourseModal${course.id}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">ویرایش دوره: ${course.title}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" class="course-form">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="edit_course">
                                                    <input type="hidden" name="course_id" value="${course.id}">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">عنوان دوره</label>
                                                        <input type="text" class="form-control" name="title" value="${course.title}" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">مدرس</label>
                                                        <input type="text" class="form-control" name="instructor" value="${course.instructor}" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">پلتفرم</label>
                                                        <select class="form-select" name="platform" required>
                                                            <option value="یوتیوب" ${course.platform === 'یوتیوب' ? 'selected' : ''}>یوتیوب</option>
                                                            <option value="Udemy" ${course.platform === 'Udemy' ? 'selected' : ''}>Udemy</option>
                                                            <option value="Coursera" ${course.platform === 'Coursera' ? 'selected' : ''}>Coursera</option>
                                                            <option value="فرادرس" ${course.platform === 'فرادرس' ? 'selected' : ''}>فرادرس</option>
                                                            <option value="مکتب‌خونه" ${course.platform === 'مکتب‌خونه' ? 'selected' : ''}>مکتب‌خونه</option>
                                                            <option value="دیگر" ${course.platform === 'دیگر' ? 'selected' : ''}>دیگر</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">وضعیت</label>
                                                        <select class="form-select" name="status" required>
                                                            <option value="در حال مشاهده" ${course.status === 'در حال مشاهده' ? 'selected' : ''}>در حال مشاهده</option>
                                                            <option value="تکمیل شده" ${course.status === 'تکمیل شده' ? 'selected' : ''}>تکمیل شده</option>
                                                            <option value="متوقف شده" ${course.status === 'متوقف شده' ? 'selected' : ''}>متوقف شده</option>
                                                            <option value="برنامه‌ریزی شده" ${course.status === 'برنامه‌ریزی شده' ? 'selected' : ''}>برنامه‌ریزی شده</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                                    <button type="submit" class="btn btn-primary">
                                                        ذخیره تغییرات
                                                        <span class="spinner-border spinner-border-sm spinner" role="status"></span>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        coursesList.append(courseHtml);
                        initDatepicker();
                    });
                } else {
                    alert('خطا در بارگذاری دوره‌ها: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching courses:', status, error, xhr.responseText);
                alert('خطا در ارتباط با سرور: ' + error);
            }
        });
    }

    // بارگذاری اولیه دوره‌ها
    updateCoursesList();

    // AJAX برای فرم‌ها
    $(document).on('submit', '.course-form, .chapter-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        showSpinner(submitBtn);
        
        $.ajax({
            type: 'POST',
            url: 'courses.php',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                hideSpinner(submitBtn);
                alert(response.message);
                if (response.success) {
                    form[0].reset();
                    $('.modal').modal('hide');
                    updateCoursesList(); // بروزرسانی لیست دوره‌ها بدون reload
                }
            },
            error: function(xhr, status, error) {
                hideSpinner(submitBtn);
                console.error('AJAX error:', status, error, xhr.responseText);
                alert('خطا در ارتباط با سرور: ' + error);
            }
        });
    });

    // لود داده‌ها برای ویرایش فصل
    $(document).on('click', '.edit-chapter-btn', function() {
        const modal = $(this).data('bs-target');
        const title = $(this).data('title');
        const study_date = $(this).data('study_date');
        const study_hours = $(this).data('study_hours');
        const chapter_id = $(this).data('chapter_id');
        
        $(modal).find('input[name="title"]').val(title);
        $(modal).find('input[name="study_date"]').val(study_date);
        $(modal).find('input[name="study_hours"]').val(study_hours);
        $(modal).find('input[name="chapter_id"]').val(chapter_id);
        initDatepicker();
    });

    // حذف دوره
    $(document).on('click', '.delete-course-btn', function() {
        if (!confirm('آیا از حذف این دوره و تمام فصل‌های آن مطمئن هستید؟')) return;
        const course_id = $(this).data('course-id');
        const btn = $(this);
        showSpinner(btn);
        
        $.ajax({
            type: 'POST',
            url: 'courses.php',
            data: {
                action: 'delete_course',
                course_id: course_id,
                csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
            },
            dataType: 'json',
            success: function(response) {
                hideSpinner(btn);
                alert(response.message);
                if (response.success) {
                    updateCoursesList();
                }
            },
            error: function(xhr, status, error) {
                hideSpinner(btn);
                console.error('Delete course error:', status, error, xhr.responseText);
                alert('خطا در ارتباط با سرور: ' + error);
            }
        });
    });

    // تغییر وضعیت فصل
    $(document).on('click', '.toggle-chapter-btn', function() {
        const chapter_id = $(this).data('chapter-id');
        const btn = $(this);
        showSpinner(btn);
        const courseId = btn.closest('div[data-course-id]').data('course-id');
        
        $.ajax({
            type: 'POST',
            url: 'courses.php',
            data: {
                action: 'toggle_chapter',
                chapter_id: chapter_id,
                csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
            },
            dataType: 'json',
            success: function(response) {
                hideSpinner(btn);
                alert(response.message);
                if (response.success) {
                    updateCoursesList();
                }
            },
            error: function(xhr, status, error) {
                hideSpinner(btn);
                console.error('Toggle chapter error:', status, error, xhr.responseText);
                alert('خطا در ارتباط با سرور: ' + error);
            }
        });
    });

    // حذف فصل
    $(document).on('click', '.delete-chapter-btn', function() {
        if (!confirm('آیا از حذف این فصل مطمئن هستید؟')) return;
        const chapter_id = $(this).data('chapter-id');
        const btn = $(this);
        showSpinner(btn);
        const courseId = btn.closest('div[data-course-id]').data('course-id');
        
        $.ajax({
            type: 'POST',
            url: 'courses.php',
            data: {
                action: 'delete_chapter',
                chapter_id: chapter_id,
                csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
            },
            dataType: 'json',
            success: function(response) {
                hideSpinner(btn);
                alert(response.message);
                if (response.success) {
                    updateCoursesList();
                }
            },
            error: function(xhr, status, error) {
                hideSpinner(btn);
                console.error('Delete chapter error:', status, error, xhr.responseText);
                alert('خطا در ارتباط با سرور: ' + error);
            }
        });
    });

    // اضافه کردن فصل جدید در مودال افزودن دوره
    let chapterIndex = 1;
    $(document).on('click', '.add-chapter-field', function() {
        const newChapter = `
            <div class="chapter-fields row mb-2">
                <div class="col-md-4">
                    <input type="text" name="chapters[${chapterIndex}][title]" class="form-control" placeholder="عنوان فصل ${chapterIndex + 1}">
                </div>
                <div class="col-md-4">
                    <input type="text" name="chapters[${chapterIndex}][study_date]" class="form-control j-datepicker" placeholder="1404/06/23">
                </div>
                <div class="col-md-3">
                    <input type="number" step="0.1" min="0" name="chapters[${chapterIndex}][study_hours]" class="form-control" placeholder="ساعت (اختیاری)">
                </div>
                <div class="col-md-1 d-flex align-items-center">
                    <i class="bi bi-dash-circle text-danger fs-5 cursor-pointer remove-chapter-btn"></i>
                </div>
            </div>
        `;
        $('#chapters-container').append(newChapter);
        initDatepicker();
        chapterIndex++;
    });

    $(document).on('click', '.remove-chapter-btn', function() {
        $(this).closest('.chapter-fields').remove();
    });
});
</script>

<?php include 'footer.php'; ?>