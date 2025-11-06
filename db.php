<?php
// 开启错误报告（开发阶段）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 数据库配置
$host = "localhost";
$user = "root";
$pass = "123qwe";
$dbname = "shopee_clone";

// 创建连接
$conn = new mysqli($host, $user, $pass, $dbname);

// 检查连接
if ($conn->connect_error) {
    // 返回 JSON 而不是 HTML
    echo json_encode([
        "status" => "error",
        "message" => "数据库连接失败: " . $conn->connect_error
    ]);
    exit;
}

// 设置字符集
$conn->set_charset("utf8mb4");
?>
