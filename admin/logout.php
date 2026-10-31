<?php
// admin/logout.php - Đăng xuất admin
session_start();

// Xóa tất cả session
$_SESSION = array();

// Xóa cookie session nếu có
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Hủy session
session_destroy();

// Chuyển về trang đăng nhập admin
header('Location: login.php');
exit();
?>


