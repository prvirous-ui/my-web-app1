<?php
session_start();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطا</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-danger">
            <h4 class="alert-heading">خطا!</h4>
            <p><?php echo htmlspecialchars($_GET['msg'] ?? 'خطای ناشناخته'); ?></p>
            <a href="dashboard.php" class="btn btn-primary">بازگشت به داشبورد</a>
        </div>
    </div>
</body>
</html>