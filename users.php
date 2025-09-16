<?php
require_once 'init.php';

// بررسی نقش کاربر
if (!is_admin()) {
    header("Location: dashboard.php");
    exit;
}

// بررسی CSRF token برای فرم‌های POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: users.php");
        exit;
    }
}

// عملیات مختلف مدیریت کاربران
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'add':
            // افزودن کاربر
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // پردازش فرم افزودن کاربر
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = sanitize_input($_POST['role']);
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$username, $email, $password, $role]);
                    
                    $_SESSION['success'] = "کاربر با موفقیت افزوده شد";
                    header("Location: users.php");
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['error'] = "خطا در افزودن کاربر: " . $e->getMessage();
                }
            }
            break;
            
        case 'edit':
            // ویرایش کاربر
            if (isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $user_id = intval($_GET['id']);
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $role = sanitize_input($_POST['role']);
                
                try {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$username, $email, $role, $user_id]);
                    
                    $_SESSION['success'] = "کاربر با موفقیت ویرایش شد";
                    header("Location: users.php");
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['error'] = "خطا در ویرایش کاربر: " . $e->getMessage();
                }
            }
            break;
            
        case 'delete':
            // حذف کاربر
            if (isset($_GET['id'])) {
                $user_id = intval($_GET['id']);
                
                // بررسی CSRF token برای حذف
                if (!isset($_GET['csrf_token']) || !validate_csrf_token($_GET['csrf_token'])) {
                    $_SESSION['error'] = "Invalid CSRF token";
                    header("Location: users.php");
                    exit;
                }
                
                // جلوگیری از حذف خود کاربر
                if ($user_id == $_SESSION['user_id']) {
                    $_SESSION['error'] = "شما نمی‌توانید حساب خودتان را حذف کنید";
                    header("Location: users.php");
                    exit;
                }
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    $_SESSION['success'] = "کاربر با موفقیت حذف شد";
                    header("Location: users.php");
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['error'] = "خطا در حذف کاربر: " . $e->getMessage();
                }
            }
            break;
            
        case 'change_password':
            // تغییر رمز عبور
            if (isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $user_id = intval($_GET['id']);
                $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                
                try {
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$new_password, $user_id]);
                    
                    $_SESSION['success'] = "رمز عبور با موفقیت تغییر یافت";
                    header("Location: users.php");
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['error'] = "خطا در تغییر رمز عبور: " . $e->getMessage();
                }
            }
            break;
    }
}

// دریافت اطلاعات کاربر برای ویرایش
$edit_user = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit_form' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $edit_user = $stmt->fetch();
        
        if (!$edit_user) {
            $_SESSION['error'] = "کاربر مورد نظر یافت نشد";
            header("Location: users.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "خطا در دریافت اطلاعات کاربر";
        header("Location: users.php");
        exit;
    }
}

// دریافت اطلاعات کاربر برای تغییر رمز
$change_password_user = null;
if (isset($_GET['action']) && $_GET['action'] == 'change_password_form' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $change_password_user = $stmt->fetch();
        
        if (!$change_password_user) {
            $_SESSION['error'] = "کاربر مورد نظر یافت نشد";
            header("Location: users.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "خطا در دریافت اطلاعات کاربر";
        header("Location: users.php");
        exit;
    }
}

include 'header.php';
?>

<div class="container-fluid">
    <h2>مدیریت کاربران</h2>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <!-- فرم ویرایش کاربر -->
    <?php if ($edit_user): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5>ویرایش کاربر: <?php echo htmlspecialchars($edit_user['username']); ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="users.php?action=edit&id=<?php echo $edit_user['id']; ?>">
                <?php echo csrf_field(); ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>نام کاربری</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>ایمیل</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>نقش</label>
                    <select name="role" class="form-control">
                        <option value="user" <?php echo $edit_user['role'] === 'user' ? 'selected' : ''; ?>>کاربر عادی</option>
                        <option value="admin" <?php echo $edit_user['role'] === 'admin' ? 'selected' : ''; ?>>مدیر</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                <a href="users.php" class="btn btn-secondary">لغو</a>
            </form>
        </div>
    </div>
    
    <!-- فرم تغییر رمز عبور -->
    <?php elseif ($change_password_user): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5>تغییر رمز عبور کاربر: <?php echo htmlspecialchars($change_password_user['username']); ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="users.php?action=change_password&id=<?php echo $change_password_user['id']; ?>">
                <?php echo csrf_field(); ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>رمز عبور جدید</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>تکرار رمز عبور</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">تغییر رمز عبور</button>
                <a href="users.php" class="btn btn-secondary">لغو</a>
            </form>
        </div>
    </div>
    
    <!-- فرم افزودن کاربر -->
    <?php else: ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5>افزودن کاربر جدید</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="users.php?action=add">
                <?php echo csrf_field(); ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>نام کاربری</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>ایمیل</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>رمز عبور</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>تکرار رمز عبور</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>نقش</label>
                    <select name="role" class="form-control">
                        <option value="user">کاربر عادی</option>
                        <option value="admin">مدیر</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">افزودن کاربر</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- لیست کاربران -->
    <div class="card">
        <div class="card-header">
            <h5>لیست کاربران</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>نام کاربری</th>
                            <th>ایمیل</th>
                            <th>نقش</th>
                            <th>تاریخ ایجاد</th>
                            <th>آخرین بروزرسانی</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
                            $users = $stmt->fetchAll();
                            
                            if (empty($users)) {
                                echo '<tr><td colspan="7" class="text-center text-muted">هیچ کاربری یافت نشد</td></tr>';
                            } else {
                                foreach ($users as $user): 
                                    $is_current_user = ($user['id'] == $_SESSION['user_id']);
                                ?>
                                <tr class="<?php echo $is_current_user ? 'table-info' : ''; ?>">
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($is_current_user): ?>
                                        <span class="badge bg-info">شما</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                            <?php echo $user['role'] === 'admin' ? 'مدیر' : 'کاربر'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if (isset($user['created_at'])) {
                                            echo function_exists('format_jalali_date') ? 
                                                format_jalali_date($user['created_at']) : 
                                                $user['created_at'];
                                        } else {
                                            echo 'نامشخص';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (isset($user['updated_at']) && $user['updated_at'] != '0000-00-00 00:00:00') {
                                            echo function_exists('format_jalali_date') ? 
                                                format_jalali_date($user['updated_at']) : 
                                                $user['updated_at'];
                                        } else {
                                            echo 'نامشخص';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="users.php?action=edit_form&id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-warning">ویرایش</a>
                                            <?php if (!$is_current_user): ?>
                                            <a href="users.php?action=delete&id=<?php echo $user['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('آیا از حذف این کاربر مطمئن هستید؟')">حذف</a>
                                            <?php endif; ?>
                                            <a href="users.php?action=change_password_form&id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-info">تغییر رمز</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach;
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='7' class='text-center text-danger'>خطا در بارگذاری کاربران: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// اعتبارسنجی فرم‌ها
document.addEventListener('DOMContentLoaded', function() {
    // اعتبارسنجی فرم افزودن کاربر
    const addForm = document.querySelector('form[action*="action=add"]');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            const password = this.querySelector('input[name="password"]').value;
            const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('رمز عبور و تکرار آن مطابقت ندارند');
            }
        });
    }
    
    // اعتبارسنجی فرم تغییر رمز عبور
    const changePasswordForm = document.querySelector('form[action*="action=change_password"]');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', function(e) {
            const newPassword = this.querySelector('input[name="new_password"]').value;
            const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('رمز عبور جدید و تکرار آن مطابقت ندارند');
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('رمز عبور باید حداقل 6 کاراکتر باشد');
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>