<?php
ob_start();

// Include required files
require_once 'config.php';
require_once 'auth.php';
require_once 'jdate.php';

// Check login for non-AJAX requests
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

// Check database connection
if (!isset($pdo) || $pdo === null) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'خطا در اتصال به پایگاه داده']);
        exit;
    } else {
        die("خطا در اتصال به پایگاه داده. لطفاً بعداً تلاش کنید.");
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Get user_id
$user_id = get_user_id();
if (!$user_id) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'کاربر وارد نشده است']);
        exit;
    } else {
        header("Location: login.php");
        exit;
    }
}

// Get user information
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'کاربر نامعتبر است']);
            exit;
        } else {
            header("Location: login.php");
            exit;
        }
    }
} catch(PDOException $e) {
    $user = ['username' => 'کاربر', 'role' => 'user'];
}

// Check user role for admin menu
$is_admin = is_admin();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم برنامه‌ریزی شخصی</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <style>
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --secondary: #858796;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f8f9fc;
        }
        #wrapper { display: flex; }
        #content-wrapper { width: 100%; overflow-x: hidden; }
        #content { flex: 1 0 auto; }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary) 10%, #224abe 100%);
            color: white;
        }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); }
        .sidebar .nav-link:hover { background-color: rgba(255, 255, 255, 0.1); color: white; }
        .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.2); font-weight: bold; }
        .topbar { height: 4.375rem; background-color: white; box-shadow: 0 0.15rem 1.75rem rgba(58, 59, 69, 0.15); }
        
        .task-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 4px solid #6c757d;
        }
        .task-item.completed {
            opacity: 0.6;
            text-decoration: line-through;
        }
        .priority-high {
            border-left-color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }
        .priority-medium {
            border-left-color: #ffc107;
            background-color: rgba(255, 193, 7, 0.1);
        }
        .priority-low {
            border-left-color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
        }
        .progress {
            height: 8px;
            margin-top: 5px;
        }
        .material-badge {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 12px;
        }
        .chapter-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        /* استایل‌های جدید برای منو */
        .sidebar-brand {
            padding: 1rem;
            text-align: center;
        }
        .sidebar-brand-icon {
            font-size: 1.5rem;
        }
        .sidebar-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            margin: 1rem 0;
        }
        .nav-item {
            margin: 0.2rem 0;
        }
        .nav-link {
            border-radius: 0.35rem;
            margin: 0 0.5rem;
        }
        .img-profile {
            width: 40px;
            height: 40px;
        }
        .dropdown-menu {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .dropdown-item {
            padding: 0.5rem 1rem;
        }
        .dropdown-divider {
            border-top: 1px solid #e3e6f0;
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- نوار کناری -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="dashboard.php">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="sidebar-brand-text mx-3">برنامه‌ریزی شخصی</div>
            </a>

            <hr class="sidebar-divider my-0">

            <!-- Nav Items -->
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-house-door"></i>
                    <span>صفحه اصلی</span>
                </a>
            </li>

            <!-- منوی مدیریت برای ادمین‌ها -->
            <?php if ($is_admin): ?>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="bi bi-people"></i>
                    <span>مدیریت کاربران</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item">
                <a class="nav-link" href="calendar.php">
                    <i class="bi bi-calendar-event"></i>
                    <span>تقویم برنامه‌ها</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="tasks.php">
                    <i class="bi bi-list-task"></i>
                    <span>کارهای روزمره</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="books.php">
                    <i class="bi bi-book"></i>
                    <span>مدیریت کتاب‌ها</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="courses.php">
                    <i class="bi bi-mortarboard"></i>
                    <span>مدیریت دوره‌ها</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="study_materials.php">
                    <i class="bi bi-journal"></i>
                    <span>محتوای مطالعاتی</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="bi bi-bar-chart"></i>
                    <span>گزارش‌گیری</span>
                </a>
            </li>

            <hr class="sidebar-divider d-none d-md-block">

            <!-- Sidebar Toggler (Sidebar) -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="me-2 d-none d-lg-inline text-gray-600 small">
                                    👤 <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($is_admin): ?>
                                    <span class="badge bg-danger">مدیر</span>
                                    <?php endif; ?>
                                </span>
                                <img class="img-profile rounded-circle" src="https://via.placeholder.com/60x60/4e73df/ffffff?text=<?php echo substr($user['username'], 0, 1); ?>">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="profile.php">
                                    <i class="bi bi-person me-2"></i> پروفایل
                                </a>
                                <a class="dropdown-item" href="#">
                                    <i class="bi bi-gear me-2"></i> تنظیمات
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="bi bi-power me-2"></i> خروج
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">