<?php
include 'header.php';
include 'config.php';
include 'jdate.php';

// توابع لازم
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('check_csrf_token')) {
    function check_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('get_user_id')) {
    function get_user_id() {
        return $_SESSION['user_id'] ?? 1; // بر اساس session
    }
}
if (!function_exists('check_user_ownership')) {
    function check_user_ownership($pdo, $table, $id, $user_id) {
        $stmt = $pdo->prepare("SELECT user_id FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        return $item && $item['user_id'] == $user_id;
    }
}

// تولید توکن CSRF
$csrf_token = generate_csrf_token();
$user_id = get_user_id();

// پردازش افزودن کتاب و فصل‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    if (!check_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: books.php");
        exit;
    }
    $title = sanitize_input($_POST['title']);
    $author = sanitize_input($_POST['author']);
    $category = sanitize_input($_POST['category']);
    $status = sanitize_input($_POST['status']);
    try {
        $stmt = $pdo->prepare("INSERT INTO books (user_id, title, author, category, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $title, $author, $category, $status]);
        $book_id = $pdo->lastInsertId();

        // اضافه کردن فصل‌ها
        if (isset($_POST['chapters']) && is_array($_POST['chapters'])) {
            foreach ($_POST['chapters'] as $chapter) {
                $ch_title = sanitize_input($chapter['title']);
                $study_date = sanitize_input($chapter['study_date']);
                $study_hours = floatval($chapter['study_hours']);

                if (!empty($study_date)) {
                    $date_parts = explode('/', $study_date);
                    $year = intval($date_parts[0]);
                    $month = intval($date_parts[1]);
                    $day = intval($date_parts[2]);
                    list($gy, $gm, $gd) = jalali_to_gregorian($year, $month, $day);
                    $study_date_mysql = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
                } else {
                    $study_date_mysql = null;
                }

                $stmt = $pdo->prepare("INSERT INTO book_chapters (book_id, user_id, title, study_date, study_hours) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$book_id, $user_id, $ch_title, $study_date_mysql, $study_hours]);
            }
        }

        $_SESSION['success'] = "کتاب و فصل‌ها با موفقیت افزوده شدند.";
        header("Location: books.php");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "خطا در افزودن کتاب: " . $e->getMessage();
        header("Location: books.php");
        exit;
    }
}

// پردازش ویرایش کتاب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_book'])) {
    if (!check_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: books.php");
        exit;
    }
    $book_id = intval($_POST['book_id']);
    $title = sanitize_input($_POST['title']);
    $author = sanitize_input($_POST['author']);
    $category = sanitize_input($_POST['category']);
    $status = sanitize_input($_POST['status']);
    if (!check_user_ownership($pdo, 'books', $book_id, $user_id)) {
        $_SESSION['error'] = "شما اجازه ویرایش این کتاب را ندارید.";
        header("Location: books.php");
        exit;
    }
    try {
        $stmt = $pdo->prepare("UPDATE books SET title = ?, author = ?, category = ?, status = ? WHERE id = ?");
        $stmt->execute([$title, $author, $category, $status, $book_id]);
        $_SESSION['success'] = "کتاب با موفقیت ویرایش شد.";
        header("Location: books.php");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "خطا در ویرایش کتاب: " . $e->getMessage();
        header("Location: books.php");
        exit;
    }
}

// پردازش حذف کتاب
if (isset($_GET['delete_book'])) {
    if (!check_csrf_token($_GET['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: books.php");
        exit;
    }
    $book_id = intval($_GET['delete_book']);
    if (!check_user_ownership($pdo, 'books', $book_id, $user_id)) {
        $_SESSION['error'] = "شما اجازه حذف این کتاب را ندارید.";
        header("Location: books.php");
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM book_chapters WHERE book_id = ?");
        $stmt->execute([$book_id]);
        $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
        $stmt->execute([$book_id]);
        $_SESSION['success'] = "کتاب و فصل‌های مربوطه با موفقیت حذف شدند.";
        header("Location: books.php");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "خطا در حذف کتاب: " . $e->getMessage();
        header("Location: books.php");
        exit;
    }
}

// پردازش اضافه فصل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_chapter'])) {
    if (!check_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: books.php");
        exit;
    }
    $book_id = intval($_POST['book_id']);
    $title = sanitize_input($_POST['title']);
    $study_date = sanitize_input($_POST['study_date'] ?? '');
    $study_hours = floatval($_POST['study_hours'] ?? 0);

    if (!empty($study_date)) {
        $date_parts = explode('/', $study_date);
        $year = intval($date_parts[0]);
        $month = intval($date_parts[1]);
        $day = intval($date_parts[2]);
        list($gy, $gm, $gd) = jalali_to_gregorian($year, $month, $day);
        $study_date_mysql = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    } else {
        $study_date_mysql = null;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO book_chapters (book_id, user_id, title, study_date, study_hours) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$book_id, $user_id, $title, $study_date_mysql, $study_hours]);
        $_SESSION['success'] = "فصل با موفقیت افزوده شد.";
        header("Location: books.php");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "خطا در افزودن فصل: " . $e->getMessage();
        header("Location: books.php");
        exit;
    }
}

// پردازش حذف فصل
if (isset($_GET['delete_chapter'])) {
    if (!check_csrf_token($_GET['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: books.php");
        exit;
    }
    $chapter_id = intval($_GET['delete_chapter']);
    if (!check_user_ownership($pdo, 'book_chapters', $chapter_id, $user_id)) {
        $_SESSION['error'] = "شما اجازه حذف این فصل را ندارید.";
        header("Location: books.php");
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM book_chapters WHERE id = ?");
        $stmt->execute([$chapter_id]);
        $_SESSION['success'] = "فصل با موفقیت حذف شد.";
        header("Location: books.php");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "خطا در حذف فصل: " . $e->getMessage();
        header("Location: books.php");
        exit;
    }
}

// پردازش تکمیل فصل
if (isset($_GET['toggle_chapter'])) {
    if (!check_csrf_token($_GET['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: books.php");
        exit;
    }
    $chapter_id = intval($_GET['toggle_chapter']);
    try {
        $stmt = $pdo->prepare("SELECT book_id FROM book_chapters WHERE id = ?");
        $stmt->execute([$chapter_id]);
        $chapter = $stmt->fetch();
        if (!$chapter || !check_user_ownership($pdo, 'books', $chapter['book_id'], $user_id)) {
            $_SESSION['error'] = "شما اجازه تغییر این فصل را ندارید.";
            header("Location: books.php");
            exit;
        }
        $stmt = $pdo->prepare("UPDATE book_chapters SET completed = NOT completed, completed_date = CASE WHEN completed = 0 THEN CURDATE() ELSE NULL END WHERE id = ?");
        $stmt->execute([$chapter_id]);
        // بروزرسانی progress کتاب
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(completed) as completed FROM book_chapters WHERE book_id = ?");
        $stmt->execute([$chapter['book_id']]);
        $chapters = $stmt->fetch();
        $progress = $chapters['total'] > 0 ? round(($chapters['completed'] / $chapters['total']) * 100) : 0;
        $stmt = $pdo->prepare("UPDATE books SET progress = ? WHERE id = ?");
        $stmt->execute([$progress, $chapter['book_id']]);
        $_SESSION['success'] = "وضعیت فصل تغییر کرد.";
        header("Location: books.php");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "خطا: " . $e->getMessage();
        header("Location: books.php");
        exit;
    }
}

// دریافت کتاب‌ها
try {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $books = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "خطا در دریافت کتاب‌ها: " . $e->getMessage();
    $books = [];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کتاب‌ها</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Vazirmatn', sans-serif;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #3a5bc7;
        }
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        .chapter-item {
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            transition: background-color 0.3s ease;
        }
        .chapter-item.completed {
            background-color: #d4edda;
        }
        .form-control {
            border-radius: 10px;
        }
        .modal-content {
            border-radius: 20px;
        }
        .add-chapter-btn {
            cursor: pointer;
            color: #4e73df;
        }
        .chapter-fields {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="bi bi-book me-2"></i>مدیریت کتاب‌ها</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal"><i class="bi bi-plus-circle me-2"></i>افزودن کتاب</button>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <?php if (empty($books)): ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-book fs-1 text-muted"></i>
                    <p class="text-muted">هیچ کتابی ثبت نشده است.</p>
                </div>
            <?php else: ?>
                <?php foreach ($books as $book): ?>
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM book_chapters WHERE book_id = ? ORDER BY id");
                    $stmt->execute([$book['id']]);
                    $chapters = $stmt->fetchAll();
                    $total_chapters = count($chapters);
                    $completed_chapters = 0;
                    foreach ($chapters as $ch) if ($ch['completed']) $completed_chapters++;
                    $progress = $total_chapters > 0 ? round(($completed_chapters / $total_chapters) * 100) : $book['progress'];
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($book['author']); ?> - <?php echo htmlspecialchars($book['category']); ?></p>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge bg-info"><?php echo htmlspecialchars($book['status']); ?></span>
                                    <span class="text-muted"><?php echo $completed_chapters; ?>/<?php echo $total_chapters; ?> فصل</span>
                                </div>
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editBookModal<?php echo $book['id']; ?>"><i class="bi bi-pencil"></i> ویرایش</button>
                                    <a href="?delete_book=<?php echo $book['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn btn-sm btn-danger" onclick="return confirm('مطمئنید؟')"><i class="bi bi-trash"></i> حذف</a>
                                </div>
                            </div>
                            <div class="card-footer">
                                <h6 class="mb-2">فصل‌ها:</h6>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($chapters as $chapter): ?>
                                        <div class="list-group-item chapter-item <?php if ($chapter['completed']) echo 'completed'; ?>">
                                            <div class="d-flex justify-content-between">
                                                <span><?php echo htmlspecialchars($chapter['title']); ?></span>
                                                <div>
                                                    <a href="?toggle_chapter=<?php echo $chapter['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn btn-sm <?php echo $chapter['completed'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                                        <i class="bi <?php echo $chapter['completed'] ? 'bi-arrow-counterclockwise' : 'bi-check'; ?>"></i>
                                                    </a>
                                                    <a href="?delete_chapter=<?php echo $chapter['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('مطمئنید؟')"><i class="bi bi-trash"></i></a>
                                                </div>
                                            </div>
                                            <small class="text-muted">تاریخ مطالعه: <?php echo $chapter['study_date'] ? format_jalali_date($chapter['study_date']) : 'نامشخص'; ?> - <?php echo $chapter['study_hours']; ?> ساعت</small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button class="btn btn-sm btn-outline-primary mt-2 w-100" data-bs-toggle="modal" data-bs-target="#addChapterModal<?php echo $book['id']; ?>"><i class="bi bi-plus-circle"></i> افزودن فصل</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- مودال اضافه فصل برای کتاب موجود -->
                    <div class="modal fade" id="addChapterModal<?php echo $book['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">افزودن فصل به <?php echo htmlspecialchars($book['title']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="add_chapter" value="1">
                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">عنوان فصل</label>
                                            <input type="text" name="title" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ مطالعه (Y/m/d)</label>
                                            <input type="text" class="form-control persian-date" name="study_date">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">ساعت مطالعه</label>
                                            <input type="number" step="0.1" name="study_hours" class="form-control">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                                        <button type="submit" class="btn btn-primary">افزودن</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- مودال ویرایش کتاب -->
                    <div class="modal fade" id="editBookModal<?php echo $book['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">ویرایش کتاب</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="edit_book" value="1">
                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">عنوان</label>
                                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($book['title']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">نویسنده</label>
                                            <input type="text" name="author" class="form-control" value="<?php echo htmlspecialchars($book['author']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">دسته‌بندی</label>
                                            <select name="category" class="form-select">
                                                <option value="ادبیات" <?php if ($book['category'] == 'ادبیات') echo 'selected'; ?>>ادبیات</option>
                                                <option value="رمان" <?php if ($book['category'] == 'رمان') echo 'selected'; ?>>رمان</option>
                                                <!-- گزینه‌های دیگر -->
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">وضعیت</label>
                                            <select name="status" class="form-select">
                                                <option value="برنامه‌ریزی شده" <?php if ($book['status'] == 'برنامه‌ریزی شده') echo 'selected'; ?>>برنامه‌ریزی شده</option>
                                                <option value="در حال مطالعه" <?php if ($book['status'] == 'در حال مطالعه') echo 'selected'; ?>>در حال مطالعه</option>
                                                <option value="لغو شده" <?php if ($book['status'] == 'لغو شده') echo 'selected'; ?>>لغو شده</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                                        <button type="submit" class="btn btn-primary">ذخیره</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- مودال اضافه کتاب جدید با فصل‌ها -->
    <div class="modal fade" id="addBookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">افزودن کتاب جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="add_book" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">عنوان</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نویسنده</label>
                                <input type="text" name="author" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">دسته‌بندی</label>
                                <select name="category" class="form-select" required>
                                    <option value="">انتخاب کنید</option>
                                    <option value="ادبیات">ادبیات</option>
                                    <option value="رمان">رمان</option>
                                    <!-- گزینه‌های دیگر -->
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">وضعیت</label>
                                <select name="status" class="form-select" required>
                                    <option value="برنامه‌ریزی شده">برنامه‌ریزی شده</option>
                                    <option value="در حال مطالعه">در حال مطالعه</option>
                                    <option value="لغو شده">لغو شده</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">فصل‌ها (می‌توانید چند فصل اضافه کنید)</label>
                            <div id="chapters-container">
                                <div class="chapter-fields row mb-2">
                                    <div class="col-md-4">
                                        <input type="text" name="chapters[0][title]" class="form-control" placeholder="عنوان فصل">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" name="chapters[0][study_date]" class="form-control persian-date" placeholder="تاریخ مطالعه (Y/m/d)">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" step="0.1" name="chapters[0][study_hours]" class="form-control" placeholder="ساعت">
                                    </div>
                                    <div class="col-md-1">
                                        <i class="bi bi-plus-circle add-chapter-btn fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                        <button type="submit" class="btn btn-primary">افزودن کتاب و فصل‌ها</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
    $(document).ready(function() {
        $('.persian-date').persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true
        });
        
        let chapterIndex = 1;
        $(document).on('click', '.add-chapter-btn', function() {
            const newChapter = `
                <div class="chapter-fields row mb-2">
                    <div class="col-md-4">
                        <input type="text" name="chapters[${chapterIndex}][title]" class="form-control" placeholder="عنوان فصل">
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="chapters[${chapterIndex}][study_date]" class="form-control persian-date" placeholder="تاریخ مطالعه (Y/m/d)">
                    </div>
                    <div class="col-md-3">
                        <input type="number" step="0.1" name="chapters[${chapterIndex}][study_hours]" class="form-control" placeholder="ساعت">
                    </div>
                    <div class="col-md-1">
                        <i class="bi bi-dash-circle text-danger fs-3 remove-chapter-btn"></i>
                    </div>
                </div>
            `;
            $('#chapters-container').append(newChapter);
            $('.persian-date').persianDatepicker({
                format: 'YYYY/MM/DD',
                autoClose: true
            });
            chapterIndex++;
        });
        
        $(document).on('click', '.remove-chapter-btn', function() {
            $(this).closest('.chapter-fields').remove();
        });
    });
</script>
</body>
</html>