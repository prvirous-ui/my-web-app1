<?php
include 'header.php';
include 'config.php';

// بررسی وجود جدول notes و ایجاد آن در صورت عدم وجود
try {
    $stmt = $pdo->query("SELECT 1 FROM notes LIMIT 1");
} catch (PDOException $e) {
    // جدول وجود ندارد، آن را ایجاد می‌کنیم
    $pdo->exec("CREATE TABLE notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        category VARCHAR(100) DEFAULT 'عمومی',
        tags VARCHAR(500) DEFAULT '',
        is_important BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

// پردازش فرم افزودن یادداشت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $title = sanitize_input($_POST['title']);
    $content = sanitize_input($_POST['content']);
    $category = sanitize_input($_POST['category']);
    $tags = sanitize_input($_POST['tags']);
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content, category, tags, is_important) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $content, $category, $tags, $is_important]);
        
        $_SESSION['success'] = "یادداشت با موفقیت افزوده شد.";
        header("Location: study_materials.php");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "خطا در افزودن یادداشت: " . $e->getMessage();
        header("Location: study_materials.php");
        exit;
    }
}

// پردازش ویرایش یادداشت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_note'])) {
    $editId = (int)$_POST['edit_id'];
    $title = sanitize_input($_POST['title']);
    $content = sanitize_input($_POST['content']);
    $category = sanitize_input($_POST['category']);
    $tags = sanitize_input($_POST['tags']);
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, category = ?, tags = ?, is_important = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $content, $category, $tags, $is_important, $editId, $user_id]);
        
        $_SESSION['success'] = "یادداشت با موفقیت ویرایش شد.";
        header("Location: study_materials.php");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "خطا در ویرایش یادداشت: " . $e->getMessage();
        header("Location: study_materials.php");
        exit;
    }
}

// پردازش حذف یادداشت
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
        $stmt->execute([$deleteId, $user_id]);
        
        $_SESSION['success'] = "یادداشت با موفقیت حذف شد.";
        header("Location: study_materials.php");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "خطا در حذف یادداشت: " . $e->getMessage();
        header("Location: study_materials.php");
        exit;
    }
}

// پردازش تغییر وضعیت مهم
if (isset($_GET['toggle_important'])) {
    $noteId = (int)$_GET['toggle_important'];
    
    try {
        // دریافت وضعیت فعلی
        $stmt = $pdo->prepare("SELECT is_important FROM notes WHERE id = ? AND user_id = ?");
        $stmt->execute([$noteId, $user_id]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($note) {
            $new_status = $note['is_important'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE notes SET is_important = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$new_status, $noteId, $user_id]);
            
            $_SESSION['success'] = "وضعیت یادداشت تغییر کرد.";
        }
        header("Location: study_materials.php");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "خطا در تغییر وضعیت: " . $e->getMessage();
        header("Location: study_materials.php");
        exit;
    }
}

// دریافت یادداشت‌ها از دیتابیس با قابلیت جستجو
$search_condition = "WHERE user_id = ?";
$params = [$user_id];
$search_query = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = sanitize_input($_GET['search']);
    $search_term = '%' . $search_query . '%';
    $search_condition .= " AND (title LIKE ? OR content LIKE ? OR tags LIKE ? OR category LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

// فیلتر بر اساس دسته‌بندی
if (isset($_GET['category']) && !empty($_GET['category']) && $_GET['category'] != 'all') {
    $category_filter = sanitize_input($_GET['category']);
    $search_condition .= " AND category = ?";
    $params[] = $category_filter;
}

// فیلتر بر اساس وضعیت مهم
if (isset($_GET['important']) && !empty($_GET['important']) && $_GET['important'] != 'all') {
    $important_filter = sanitize_input($_GET['important']);
    $important_value = ($important_filter == 'important') ? 1 : 0;
    $search_condition .= " AND is_important = ?";
    $params[] = $important_value;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM notes $search_condition ORDER BY is_important DESC, updated_at DESC");
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت دسته‌بندی‌های منحصر به فرد برای فیلتر
    $stmt = $pdo->prepare("SELECT DISTINCT category FROM notes WHERE user_id = ? ORDER BY category");
    $stmt->execute([$user_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    die("خطا در دریافت یادداشت‌ها: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دفترچه یادداشت - اتوماسیون برنامه‌ریزی شخصی</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-light: #7a9ff7;
            --primary-dark: #2a4b8d;
            --secondary: #6f42c1;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
            --gray-100: #f8f9fc;
            --gray-200: #eaecf4;
            --gray-300: #dddfeb;
            --gray-400: #d1d3e2;
            --gray-500: #b7b9cc;
            --gray-600: #858796;
            --gray-700: #6e707e;
            --gray-800: #5a5c69;
            --gray-900: #2d3436;
        }

        body {
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
            background-color: var(--gray-100);
            color: var(--gray-800);
        }

        .note-card {
            background: white;
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .note-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15);
        }

        .note-card.important::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(180deg, var(--warning) 10%, #dda20a 100%);
        }

        .note-header {
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 1.35rem;
        }

        .note-body {
            padding: 1.25rem;
        }

        .note-content {
            max-height: 200px;
            overflow: hidden;
            position: relative;
        }

        .note-content::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: linear-gradient(transparent, white);
        }

        .note-tags {
            margin-top: 1rem;
        }

        .tag {
            display: inline-block;
            background: var(--gray-200);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.8rem;
            margin-left: 0.25rem;
            color: var(--gray-700);
        }

        .search-box {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .filter-section {
            background: white;
            border-radius: 0.75rem;
            padding: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .highlight {
            background-color: yellow;
            font-weight: bold;
        }

        .btn-primary {
            background: linear-gradient(180deg, var(--primary) 10%, var(--primary-dark) 100%);
            border: none;
            border-radius: 0.35rem;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: linear-gradient(180deg, var(--primary-light) 10%, var(--primary) 100%);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800"><i class="bi bi-journal-bookmark me-2"></i>دفترچه یادداشت</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
            <i class="bi bi-plus-circle me-2"></i>یادداشت جدید
        </button>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- جستجو -->
    <div class="search-box">
        <form method="GET" action="">
            <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="جستجو در یادداشت‌ها..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i> جستجو
                </button>
            </div>
        </form>
    </div>

    <!-- فیلترها -->
    <div class="filter-section">
        <div class="row">
            <div class="col-md-4 mb-2">
                <form method="GET" action="">
                    <label class="form-label">دسته‌بندی:</label>
                    <select class="form-select" name="category" onchange="this.form.submit()">
                        <option value="all" <?php echo (!isset($_GET['category']) || $_GET['category'] == 'all') ? 'selected' : ''; ?>>همه دسته‌بندی‌ها</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if(isset($_GET['search'])): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
                    <?php endif; ?>
                    <?php if(isset($_GET['important'])): ?>
                        <input type="hidden" name="important" value="<?php echo htmlspecialchars($_GET['important']); ?>">
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-md-4 mb-2">
                <form method="GET" action="">
                    <label class="form-label">وضعیت:</label>
                    <select class="form-select" name="important" onchange="this.form.submit()">
                        <option value="all" <?php echo (!isset($_GET['important']) || $_GET['important'] == 'all') ? 'selected' : ''; ?>>همه یادداشت‌ها</option>
                        <option value="important" <?php echo (isset($_GET['important']) && $_GET['important'] == 'important') ? 'selected' : ''; ?>>فقط مهم‌ها</option>
                        <option value="normal" <?php echo (isset($_GET['important']) && $_GET['important'] == 'normal') ? 'selected' : ''; ?>>فقط معمولی‌ها</option>
                    </select>
                    <?php if(isset($_GET['search'])): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
                    <?php endif; ?>
                    <?php if(isset($_GET['category'])): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($_GET['category']); ?>">
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-md-4 mb-2 d-flex align-items-end">
                <?php if(isset($_GET['search']) || isset($_GET['category']) || isset($_GET['important'])): ?>
                    <a href="study_materials.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-2"></i>حذف فیلترها
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- نمایش یادداشت‌ها -->
    <div class="row">
        <?php if (count($notes) === 0): ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="bi bi-journal-text"></i>
                    <h5>هیچ یادداشتی یافت نشد</h5>
                    <p class="mb-3"><?php echo $search_query ? 'هیچ نتیجه‌ای برای جستجوی شما یافت نشد.' : 'اولین یادداشت خود را ایجاد کنید.'; ?></p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                        <i class="bi bi-plus-circle me-2"></i>افزودن یادداشت جدید
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($notes as $note): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="note-card <?php echo $note['is_important'] ? 'important' : ''; ?>">
                        <div class="note-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?php echo htmlspecialchars($note['title']); ?></h6>
                            <div class="d-flex gap-2">
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($note['category']); ?></span>
                                <?php if ($note['is_important']): ?>
                                    <span class="badge bg-warning">مهم</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="note-body">
                            <div class="note-content">
                                <?php 
                                $content = nl2br(htmlspecialchars($note['content']));
                                if ($search_query) {
                                    $content = preg_replace("/(" . preg_quote($search_query, '/') . ")/i", "<span class='highlight'>$1</span>", $content);
                                }
                                echo $content;
                                ?>
                            </div>
                            
                            <?php if (!empty($note['tags'])): ?>
                                <div class="note-tags">
                                    <?php 
                                    $tags = explode(',', $note['tags']);
                                    foreach ($tags as $tag):
                                        if (trim($tag)):
                                            $tag_display = trim($tag);
                                            if ($search_query) {
                                                $tag_display = preg_replace("/(" . preg_quote($search_query, '/') . ")/i", "<span class='highlight'>$1</span>", $tag_display);
                                            }
                                    ?>
                                        <span class="tag"><?php echo $tag_display; ?></span>
                                    <?php endif; endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="text-muted">
                                    <?php echo jdate('Y/m/d H:i', strtotime($note['updated_at'])); ?>
                                </small>
                                <div class="note-actions">
                                    <a href="study_materials.php?toggle_important=<?php echo $note['id']; ?>" class="btn btn-sm btn-outline-warning" title="<?php echo $note['is_important'] ? 'حذف از مهم‌ها' : 'علامت‌گذاری به عنوان مهم'; ?>">
                                        <i class="bi bi-star<?php echo $note['is_important'] ? '-fill' : ''; ?>"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editNoteModal<?php echo $note['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="study_materials.php?delete=<?php echo $note['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('آیا از حذف این یادداشت مطمئن هستید؟')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- مودال ویرایش یادداشت -->
                <div class="modal fade" id="editNoteModal<?php echo $note['id']; ?>" tabindex="-1" aria-labelledby="editNoteModalLabel<?php echo $note['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editNoteModalLabel<?php echo $note['id']; ?>">ویرایش یادداشت</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="modal-body">
                                    <input type="hidden" name="edit_note" value="1">
                                    <input type="hidden" name="edit_id" value="<?php echo $note['id']; ?>">
                                    <div class="mb-3">
                                        <label for="editTitle<?php echo $note['id']; ?>" class="form-label">عنوان</label>
                                        <input type="text" class="form-control" id="editTitle<?php echo $note['id']; ?>" name="title" value="<?php echo htmlspecialchars($note['title']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="editContent<?php echo $note['id']; ?>" class="form-label">محتوا</label>
                                        <textarea class="form-control" id="editContent<?php echo $note['id']; ?>" name="content" rows="6" required><?php echo htmlspecialchars($note['content']); ?></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="editCategory<?php echo $note['id']; ?>" class="form-label">دسته‌بندی</label>
                                                <input type="text" class="form-control" id="editCategory<?php echo $note['id']; ?>" name="category" value="<?php echo htmlspecialchars($note['category']); ?>" list="categoriesList">
                                                <datalist id="categoriesList">
                                                    <?php foreach ($categories as $cat): ?>
                                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                                    <?php endforeach; ?>
                                                </datalist>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="editTags<?php echo $note['id']; ?>" class="form-label">برچسب‌ها (با کاما جدا کنید)</label>
                                                <input type="text" class="form-control" id="editTags<?php echo $note['id']; ?>" name="tags" value="<?php echo htmlspecialchars($note['tags']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="editImportant<?php echo $note['id']; ?>" name="is_important" <?php echo $note['is_important'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="editImportant<?php echo $note['id']; ?>">
                                                علامت‌گذاری به عنوان مهم
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                    <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- مودال افزودن یادداشت جدید -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNoteModalLabel">یادداشت جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-body">
                    <input type="hidden" name="add_note" value="1">
                    <div class="mb-3">
                        <label for="title" class="form-label">عنوان</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="content" class="form-label">محتوا</label>
                        <textarea class="form-control" id="content" name="content" rows="6" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">دسته‌بندی</label>
                                <input type="text" class="form-control" id="category" name="category" value="عمومی" list="categoriesList">
                                <datalist id="categoriesList">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tags" class="form-label">برچسب‌ها (با کاما جدا کنید)</label>
                                <input type="text" class="form-control" id="tags" name="tags" placeholder="مثال: کار,مطالعه,شخصی">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_important" name="is_important">
                            <label class="form-check-label" for="is_important">
                                علامت‌گذاری به عنوان مهم
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">ذخیره یادداشت</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// هایلایت کردن نتایج جستجو
function highlightText(element, searchText) {
    if (!searchText) return;
    
    const text = element.textContent;
    const regex = new RegExp(`(${searchText})`, 'gi');
    const newText = text.replace(regex, '<span class="highlight">$1</span>');
    element.innerHTML = newText;
}

// اجرای هایلایت پس از لود صفحه
document.addEventListener('DOMContentLoaded', function() {
    const searchQuery = "<?php echo $search_query; ?>";
    if (searchQuery) {
        // هایلایت عناوین
        document.querySelectorAll('.note-header h6').forEach(title => {
            highlightText(title, searchQuery);
        });
    }
});
</script>

<?php include 'footer.php'; ?>