<?php
// logout.php - Đăng xuất
session_start();

// Xóa tất cả session
$_SESSION = array();

// Xóa cookie session nếu có
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Hủy session
session_destroy();

// Chuyển về trang chủ
header('Location: homepage.php');
exit();
?>


