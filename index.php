<?php
ob_start();
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
} else {
    header("Location: login.php");
    exit;
}
?>