<?php
// config/db.php - Kết nối database

$servername = "localhost";
$username   = "root"; 
$password   = ""; 
$dbname     = "webtrangsuc_tink";
$port       = 3306;

try {
    $pdo = new PDO(
        "mysql:host=$servername;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    // Chế độ báo lỗi chuẩn
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Trả về dạng mảng kết hợp (thường dùng)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
