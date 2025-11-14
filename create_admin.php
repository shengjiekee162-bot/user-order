<?php
// Usage (CLI): php create_admin.php username password
// Example: php create_admin.php admin@example.com StrongPass123

require_once __DIR__ . '/db.php';

if(PHP_SAPI !== 'cli'){
    echo "This script must be run from CLI.\n";
    exit(1);
}

if($argc < 3){
    echo "Usage: php create_admin.php <username> <password>\n";
    exit(1);
}

$username = $argv[1];
$password = $argv[2];

// Check if user exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();
if($res && $res->num_rows > 0){
    echo "User with username {$username} already exists.\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$role = 'admin';
$stmt2 = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?,?,?)");
$stmt2->bind_param("sss", $username, $hash, $role);
if($stmt2->execute()){
    echo "Admin user created with id: " . $stmt2->insert_id . "\n";
    exit(0);
} else {
    echo "Failed to create admin: " . $stmt2->error . "\n";
    exit(1);
}
