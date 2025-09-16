<?php
include 'header.php';
include 'config.php';

// دریافت اطلاعات کاربر
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("خطا در دریافت اطلاعات کاربر: " . $e->getMessage());
}

// پردازش فرم ویرایش پروفایل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = sanitize_input($_POST['fullname']);
    $email = sanitize_input($_POST['email']);
    $bio = sanitize_input($_POST['bio']);
    
    // بررسی آپلود تصویر
    $profile_image = $user['profile_image'];
    if (!empty($_FILES['profile_image']['name'])) {
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
        $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // بررسی نوع فایل
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                // حذف تصویر قبلی اگر وجود دارد
                if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                    unlink($user['profile_image']);
                }
                $profile_image = $target_file;
            }
        }
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, bio = ?, profile_image = ? WHERE id = ?");
        $stmt->execute([$fullname, $email, $bio, $profile_image, $user_id]);
        
        $_SESSION['success'] = "پروفایل با موفقیت به‌روزرسانی شد";
        header("Location: profile.php");
        exit;
    } catch(PDOException $e) {
        $error = "خطا در به‌روزرسانی پروفایل: " . $e->getMessage();
    }
}

// پردازش تغییر رمز عبور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // بررسی صحت رمز عبور فعلی
    if (!password_verify($current_password, $user['password'])) {
        $password_error = "رمز عبور فعلی نادرست است";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "تکرار رمز عبور جدید مطابقت ندارد";
    } elseif (strlen($new_password) < 6) {
        $password_error = "رمز عبور جدید باید حداقل ۶ کاراکتر باشد";
    } else {
        // به‌روزرسانی رمز عبور
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $_SESSION['success'] = "رمز عبور با موفقیت تغییر یافت";
            header("Location: profile.php");
            exit;
        } catch(PDOException $e) {
            $password_error = "خطا در تغییر رمز عبور: " . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">پروفایل کاربری</h1>
</div>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">اطلاعات کاربری</h6>
            </div>
            <div class="card-body text-center">
                <img src="<?php echo !empty($user['profile_image']) ? $user['profile_image'] : 'https://via.placeholder.com/150/4e73df/ffffff?text=' . substr($user['username'], 0, 1); ?>" 
                     class="img-fluid rounded-circle mb-3" alt="تصویر پروفایل" style="width: 150px; height: 150px; object-fit: cover;">
                <h5 class="card-title"><?php echo !empty($user['fullname']) ? htmlspecialchars($user['fullname']) : htmlspecialchars($user['username']); ?></h5>
                <p class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                <?php if (!empty($user['bio'])): ?>
                <p class="card-text"><?php echo htmlspecialchars($user['bio']); ?></p>
                <?php endif; ?>
                <p class="text-muted"><i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?></p>
                <p class="text-muted"><i class="bi bi-calendar me-2"></i>عضو شده از: <?php echo format_jalali_date(substr($user['created_at'], 0, 10)); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">ویرایش اطلاعات کاربری</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="fullname" class="form-label">نام کامل</label>
                            <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo !empty($user['fullname']) ? htmlspecialchars($user['fullname']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">ایمیل</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="bio" class="form-label">بیوگرافی</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo !empty($user['bio']) ? htmlspecialchars($user['bio']) : ''; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="profile_image" class="form-label">تصویر پروفایل</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                        <div class="form-text">فرمت‌های مجاز: JPG, PNG, GIF. حداکثر حجم: 2MB</div>
                    </div>
                    <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                </form>
            </div>
        </div>
        
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">تغییر رمز عبور</h6>
            </div>
            <div class="card-body">
                <?php if (isset($password_error)): ?>
                <div class="alert alert-danger"><?php echo $password_error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="change_password" value="1">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">رمز عبور فعلی</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="new_password" class="form-label">رمز عبور جدید</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">تکرار رمز عبور جدید</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">تغییر رمز عبور</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>