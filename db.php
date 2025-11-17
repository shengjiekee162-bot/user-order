<?php
// Database configuration - keep silent on errors (they will be logged) to avoid
// breaking JSON responses from API endpoints. Adjust these values to your env.
$host = "localhost";
$user = "root";
$pass = "123qwe";
$dbname = "shopee_clone";

// Ensure errors are not printed to output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
if (!ini_get('error_log')) {
    ini_set('error_log', __DIR__ . '/php_errors.log');
}

// Create connection
$conn = @new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("DB connect error: " . $conn->connect_error);
    // Provide a minimal JSON error (no extra whitespace)
    echo json_encode([
        "status" => "error",
        "message" => "数据库连接失败"
    ]);
    exit;
}

// Set charset
$conn->set_charset("utf8mb4");
?>
