<?php
require_once 'init.php';

// بررسی لاگین
$user_id = get_user_id();
require_login();

// تولید و ذخیره توکن CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// بررسی CSRF token برای فرم‌های POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        if (isset($_POST['action']) && $_POST['action'] === 'complete_task') {
            echo json_encode(['success' => false, 'error' => 'توکن CSRF نامعتبر است']);
            exit;
        } else {
            $_SESSION['error'] = "Invalid CSRF token";
            header("Location: tasks.php");
            exit;
        }
    }
}

// مدیریت درخواست‌های AJAX برای تکمیل کار
if (isset($_POST['action']) && $_POST['action'] === 'complete_task' && isset($_POST['task_id'])) {
    try {
        $task_id = filter_var($_POST['task_id'], FILTER_SANITIZE_NUMBER_INT);
        $stmt = $pdo->prepare("UPDATE tasks SET completed = 1, completed_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$task_id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'کار با موفقیت تکمیل شد']);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'خطا در تکمیل کار: ' . $e->getMessage()]);
        exit;
    }
}

// عملیات مربوط به تسک‌ها
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'add':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $title = isset($_POST['title']) ? sanitize_input($_POST['title']) : '';
                $description = isset($_POST['description']) ? sanitize_input($_POST['description']) : '';
                $priority = isset($_POST['priority']) ? sanitize_input($_POST['priority']) : 'low';
                $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

                // اعتبارسنجی ورودی‌ها
                if (empty($title)) {
                    $_SESSION['error'] = "عنوان تسک نمی‌تواند خالی باشد";
                    header("Location: tasks.php");
                    exit;
                }

                try {
                    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, priority, due_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$user_id, $title, $description, $priority, $due_date]);
                    
                    $_SESSION['success'] = "تسک با موفقیت افزوده شد";
                    header("Location: tasks.php");
                    exit;
                } catch (PDOException $e) {
                    // لاگ کردن خطا برای دیباگ
                    error_log("خطا در افزودن تسک: " . $e->getMessage());
                    $_SESSION['error'] = "خطا در افزودن تسک: " . $e->getMessage();
                    header("Location: tasks.php");
                    exit;
                }
            }
            break;
            
        case 'edit':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
                $title = isset($_POST['title']) ? sanitize_input($_POST['title']) : '';
                $description = isset($_POST['description']) ? sanitize_input($_POST['description']) : '';
                $priority = isset($_POST['priority']) ? sanitize_input($_POST['priority']) : 'low';
                $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
                
                if (empty($task_id) || empty($title)) {
                    $_SESSION['error'] = "اطلاعات ناقص است";
                    header("Location: tasks.php");
                    exit;
                }

                try {
                    $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, priority = ?, due_date = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
                    $stmt->execute([$title, $description, $priority, $due_date, $task_id, $user_id]);
                    
                    $_SESSION['success'] = "تسک با موفقیت ویرایش شد";
                    header("Location: tasks.php");
                    exit;
                } catch (PDOException $e) {
                    error_log("خطا در ویرایش تسک: " . $e->getMessage());
                    $_SESSION['error'] = "خطا در ویرایش تسک: " . $e->getMessage();
                    header("Location: tasks.php");
                    exit;
                }
            }
            break;
            
        case 'delete':
            if (isset($_GET['id'])) {
                $task_id = intval($_GET['id']);
                
                if (!isset($_GET['csrf_token']) || !validate_csrf_token($_GET['csrf_token'])) {
                    $_SESSION['error'] = "Invalid CSRF token";
                    header("Location: tasks.php");
                    exit;
                }
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
                    $stmt->execute([$task_id, $user_id]);
                    
                    $_SESSION['success'] = "تسک با موفقیت حذف شد";
                    header("Location: tasks.php");
                    exit;
                } catch (PDOException $e) {
                    error_log("خطا در حذف تسک: " . $e->getMessage());
                    $_SESSION['error'] = "خطا در حذف تسک: " . $e->getMessage();
                    header("Location: tasks.php");
                    exit;
                }
            }
            break;
            
        case 'complete':
            if (isset($_GET['id'])) {
                $task_id = intval($_GET['id']);
                
                if (!isset($_GET['csrf_token']) || !validate_csrf_token($_GET['csrf_token'])) {
                    $_SESSION['error'] = "Invalid CSRF token";
                    header("Location: tasks.php");
                    exit;
                }
                
                try {
                    $stmt = $pdo->prepare("UPDATE tasks SET completed = 1, completed_at = NOW() WHERE id = ? AND user_id = ?");
                    $stmt->execute([$task_id, $user_id]);
                    
                    $_SESSION['success'] = "تسک با موفقیت تکمیل شد";
                    header("Location: tasks.php");
                    exit;
                } catch (PDOException $e) {
                    error_log("خطا در تکمیل تسک: " . $e->getMessage());
                    $_SESSION['error'] = "خطا در تکمیل تسک: " . $e->getMessage();
                    header("Location: tasks.php");
                    exit;
                }
            }
            break;
            
        case 'incomplete':
            if (isset($_GET['id'])) {
                $task_id = intval($_GET['id']);
                
                if (!isset($_GET['csrf_token']) || !validate_csrf_token($_GET['csrf_token'])) {
                    $_SESSION['error'] = "Invalid CSRF token";
                    header("Location: tasks.php");
                    exit;
                }
                
                try {
                    $stmt = $pdo->prepare("UPDATE tasks SET completed = 0, completed_at = NULL WHERE id = ? AND user_id = ?");
                    $stmt->execute([$task_id, $user_id]);
                    
                    $_SESSION['success'] = "تسک به حالت ناتمام بازگردانده شد";
                    header("Location: tasks.php");
                    exit;
                } catch (PDOException $e) {
                    error_log("خطا در تغییر وضعیت تسک: " . $e->getMessage());
                    $_SESSION['error'] = "خطا در تغییر وضعیت تسک: " . $e->getMessage();
                    header("Location: tasks.php");
                    exit;
                }
            }
            break;
    }
}

// دریافت تسک‌ها
$tasks = [];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

try {
    $query = "SELECT * FROM tasks WHERE user_id = ?";
    $params = [$user_id];
    
    switch ($filter) {
        case 'completed':
            $query .= " AND completed = 1";
            break;
        case 'active':
            $query .= " AND completed = 0";
            break;
        case 'high':
            $query .= " AND priority = 'high' AND completed = 0";
            break;
        case 'today':
            $query .= " AND due_date = CURDATE() AND completed = 0";
            break;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("خطا در دریافت تسک‌ها: " . $e->getMessage());
    $_SESSION['error'] = "خطا در دریافت تسک‌ها: " . $e->getMessage();
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کارها</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-light: #7a9ff7;
            --primary-dark: #2a4b8d;
            --success: #1cc88a;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
            --gradient-primary: linear-gradient(180deg, var(--primary) 10%, var(--primary-dark) 100%);
            --shadow-sm: 0 0.15rem 0.75rem rgba(0, 0, 0, 0.1);
            --shadow-md: 0 0.3rem 1rem rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(180deg, var(--light) 0%, #e8ecf6 100%);
            color: var(--dark);
            min-height: 100vh;
        }

        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--shadow-sm);
            background: white;
        }

        .card-header {
            background: var(--gradient-primary);
            color: white;
            border-radius: 1rem 1rem 0 0;
            font-weight: 600;
        }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(180deg, var(--primary-light) 10%, var(--primary) 100%);
            transform: translateY(-2px);
        }

        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }

        .table-responsive {
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .table th, .table td {
            vertical-align: middle;
        }

        .badge {
            padding: 0.5em 1em;
            font-size: 0.8rem;
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
<body>
    <!-- Toast برای نمایش پیام‌ها -->
    <div id="toast" class="toast">
        <span id="toast-message"></span>
    </div>

    <div class="container-fluid py-4">
        <h2>مدیریت کارها</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <!-- فیلترها -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">فیلترها</h5>
            </div>
            <div class="card-body">
                <div class="btn-group" role="group">
                    <a href="tasks.php?filter=all" class="btn btn-<?php echo $filter == 'all' ? 'primary' : 'outline-primary'; ?>">همه</a>
                    <a href="tasks.php?filter=active" class="btn btn-<?php echo $filter == 'active' ? 'primary' : 'outline-primary'; ?>">فعال</a>
                    <a href="tasks.php?filter=completed" class="btn btn-<?php echo $filter == 'completed' ? 'primary' : 'outline-primary'; ?>">تکمیل شده</a>
                    <a href="tasks.php?filter=high" class="btn btn-<?php echo $filter == 'high' ? 'primary' : 'outline-primary'; ?>">فوری</a>
                    <a href="tasks.php?filter=today" class="btn btn-<?php echo $filter == 'today' ? 'primary' : 'outline-primary'; ?>">امروز</a>
                </div>
            </div>
        </div>
        
        <!-- باتن افزودن تسک جدید -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#taskModal" onclick="resetModalForm()">
                    <i class="bi bi-plus-circle"></i> افزودن تسک جدید
                </button>
            </div>
        </div>
        
        <!-- Modal برای افزودن/ویرایش تسک -->
        <div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="taskModalLabel">افزودن تسک جدید</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="taskForm" method="POST" action="tasks.php?action=add">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="task_id" id="task_id" value="">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">عنوان تسک *</label>
                                        <input type="text" name="title" id="title" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">اولویت</label>
                                        <select name="priority" id="priority" class="form-control">
                                            <option value="low">کم</option>
                                            <option value="medium">متوسط</option>
                                            <option value="high">زیاد</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">توضیحات</label>
                                <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">تاریخ موعد (اختیاری)</label>
                                <input type="date" name="due_date" id="due_date" class="form-control">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                            <button type="submit" class="btn btn-primary" id="submitBtn">افزودن تسک</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- جستجو و لیست تسک‌ها -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">لیست کارها</h5>
                <div class="input-group mt-2">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control" placeholder="جستجو بر اساس عنوان...">
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-3">هیچ کاری یافت نشد</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="tasksTable">
                            <thead>
                                <tr>
                                    <th>وضعیت</th>
                                    <th>عنوان</th>
                                    <th>اولویت</th>
                                    <th>تاریخ موعد</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): 
                                    $priority_class = '';
                                    $priority_text = '';
                                    switch ($task['priority']) {
                                        case 'high':
                                            $priority_class = 'danger';
                                            $priority_text = 'فوری';
                                            break;
                                        case 'medium':
                                            $priority_class = 'warning';
                                            $priority_text = 'متوسط';
                                            break;
                                        case 'low':
                                            $priority_class = 'success';
                                            $priority_text = 'کم';
                                            break;
                                    }
                                    
                                    $is_overdue = $task['due_date'] && !$task['completed'] && strtotime($task['due_date']) < time();
                                ?>
                                <tr class="<?php echo $task['completed'] ? 'table-success' : ($is_overdue ? 'table-danger' : ''); ?>" data-task-id="<?php echo $task['id']; ?>" data-title="<?php echo htmlspecialchars($task['title']); ?>" data-description="<?php echo htmlspecialchars($task['description'] ?? ''); ?>" data-priority="<?php echo $task['priority']; ?>" data-due-date="<?php echo $task['due_date'] ?? ''; ?>">
                                    <td>
                                        <?php if ($task['completed']): ?>
                                            <span class="badge bg-success">تکمیل شده</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">در حال انجام</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                        <?php if ($task['description']): ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($task['description'], 0, 50)); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $priority_class; ?>"><?php echo $priority_text; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($task['due_date']): ?>
                                            <?php echo function_exists('format_jalali_date') ? format_jalali_date($task['due_date']) : $task['due_date']; ?>
                                            <?php if ($is_overdue): ?>
                                                <br>
                                                <small class="text-danger">✗ گذشته</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">تعیین نشده</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo function_exists('format_jalali_date') ? format_jalali_date($task['created_at']) : $task['created_at']; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if (!$task['completed']): ?>
                                                <button class="btn btn-sm btn-success complete-task" data-task-id="<?php echo $task['id']; ?>" data-csrf-token="<?php echo $csrf_token; ?>" title="تکمیل">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning edit-task" data-bs-toggle="modal" data-bs-target="#taskModal" title="ویرایش" data-task-id="<?php echo $task['id']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            <?php else: ?>
                                                <a href="tasks.php?action=incomplete&id=<?php echo $task['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" 
                                                   class="btn btn-sm btn-secondary" title="بازگشت به حالت ناتمام">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="tasks.php?action=delete&id=<?php echo $task['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('آیا از حذف این تسک مطمئن هستید؟')" title="حذف">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // مدیریت تکمیل کارها با AJAX
            document.querySelectorAll('.complete-task').forEach(button => {
                button.addEventListener('click', function() {
                    const taskId = this.getAttribute('data-task-id');
                    const csrfToken = this.getAttribute('data-csrf-token');
                    const taskRow = this.closest('tr');

                    fetch('tasks.php', {
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
                            taskRow.style.transition = 'opacity 0.5s ease';
                            taskRow.style.opacity = '0';
                            setTimeout(() => {
                                taskRow.remove();
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

            // مدیریت ویرایش تسک
            document.querySelectorAll('.edit-task').forEach(button => {
                button.addEventListener('click', function() {
                    const taskId = this.getAttribute('data-task-id');
                    const row = this.closest('tr');
                    const title = row.getAttribute('data-title');
                    const description = row.getAttribute('data-description');
                    const priority = row.getAttribute('data-priority');
                    const dueDate = row.getAttribute('data-due-date');

                    document.getElementById('task_id').value = taskId;
                    document.getElementById('title').value = title;
                    document.getElementById('description').value = description || '';
                    document.getElementById('priority').value = priority;
                    document.getElementById('due_date').value = dueDate || '';
                    document.getElementById('taskModalLabel').textContent = 'ویرایش تسک';
                    document.getElementById('submitBtn').textContent = 'ذخیره تغییرات';

                    // تغییر action فرم به edit
                    document.getElementById('taskForm').action = `tasks.php?action=edit`;
                });
            });

            // ریست فرم برای افزودن جدید
            function resetModalForm() {
                document.getElementById('task_id').value = '';
                document.getElementById('title').value = '';
                document.getElementById('description').value = '';
                document.getElementById('priority').value = 'low';
                document.getElementById('due_date').value = '';
                document.getElementById('taskModalLabel').textContent = 'افزودن تسک جدید';
                document.getElementById('submitBtn').textContent = 'افزودن تسک';
                document.getElementById('taskForm').action = 'tasks.php?action=add';
            }

            // بستن modal و ریست
            const taskModal = document.getElementById('taskModal');
            taskModal.addEventListener('hidden.bs.modal', function () {
                resetModalForm();
            });

            // جستجو بر اساس عنوان
            const searchInput = document.getElementById('searchInput');
            const tableRows = document.querySelectorAll('#tasksTable tbody tr');

            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                tableRows.forEach(row => {
                    const title = row.getAttribute('data-title').toLowerCase();
                    if (title.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php include 'footer.php'; ?>