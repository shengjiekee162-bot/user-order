<?php
header("Content-Type: application/json");
// Prevent accidental PHP warnings or whitespace from breaking JSON responses.
// Turn off displaying errors to the client and log them to a file instead.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Start output buffering so any accidental output (not JSON) can be cleaned
// before we send a JSON response. Start buffering before including other files
// to ensure they don't emit raw output that breaks JSON.
ob_start();
include "db.php";

function json_out($data) {
    // clear any buffered non-JSON output
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    // ensure no further output
    exit;
}
$config = [];
if (file_exists(__DIR__ . '/config.php')) {
    $config = include __DIR__ . '/config.php';
} else if (file_exists(__DIR__ . '/config.sample.php')) {
    $config = include __DIR__ . '/config.sample.php';
}
$ADMIN_INVITE_CODE = $config['ADMIN_INVITE_CODE'] ?? '';
// optional mail config (create mail_config.php from mail_config.sample.php to enable)
$mailCfg = [];
if (file_exists(__DIR__ . '/mail_config.php')) {
    $mailCfg = include __DIR__ . '/mail_config.php';
}
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
  case "POST":
    $input = json_decode(file_get_contents("php://input"), true);
    $action = $input['action'];

    if ($action == "register") {
        $u = $input['username'];
        $raw_pw = $input['password'];
        $requested_role = $input['role'] ?? 'buyer';
        $invite_code = $input['invite_code'] ?? '';

        // basic username check
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt_check->bind_param("s", $u);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        if ($res_check && $res_check->num_rows > 0) {
            json_out(["status"=>"fail","message"=>"用户名已存在"]);
        }

        // determine final role
        // Start by computing allowed roles based on DB enum (if available) to avoid inserting invalid values.
        $final_role = 'buyer';
        try {
            $allowed_roles = ['buyer','seller'];
            $res_col = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
            if ($res_col) {
                $col = $res_col->fetch_assoc();
                if (!empty($col['Type'])) {
                    // Type looks like: enum('buyer','seller',...)
                    if (preg_match("/enum\\((.*)\\)/i", $col['Type'], $m)) {
                        $vals = $m[1];
                        // split by comma, trim quotes
                        $parts = array_map(function($s){ return trim($s, " '\""); }, explode(',', $vals));
                        if (!empty($parts)) {
                            $allowed_roles = $parts;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // fallback to defaults
            error_log('Failed to read role enum: ' . $e->getMessage());
            $allowed_roles = ['buyer','seller'];
        }

        // Respect requested role if it's allowed; otherwise fallback to buyer
        if (in_array($requested_role, $allowed_roles, true)) {
            $final_role = $requested_role;
        } else {
            $final_role = $allowed_roles[0] ?? 'buyer';
        }

        $p = password_hash($raw_pw, PASSWORD_DEFAULT);

        // Safety: ensure final_role is actually allowed by the DB enum to avoid mysqli exceptions
        if (!in_array($final_role, $allowed_roles, true)) {
            error_log('Requested final_role ' . $final_role . ' is not in allowed_roles: ' . json_encode($allowed_roles));
            // fallback to first allowed role
            $final_role = $allowed_roles[0] ?? 'buyer';
        }

        $stmt = $conn->prepare("INSERT INTO users (username,password,role) VALUES (?,?,?)");
        $stmt->bind_param("sss", $u, $p, $final_role);
        try {
            // use Throwable to catch mysqli_sql_exception as well
            if ($stmt->execute()) {
                json_out(["status"=>"ok","message"=>"注册成功！"]);
            } else {
                json_out(["status"=>"fail","message"=>"注册失败: " . $stmt->error]);
            }
        } catch (Throwable $e) {
            error_log('User insert failed: ' . $e->getMessage());
            json_out(["status"=>"fail","message"=>"注册失败（数据库错误），请检查日志。"]);
        }
    }

    if ($action == "login") {
        $u = $input['username'];
        $p = $input['password'];
        $res = $conn->query("SELECT * FROM users WHERE username='$u'");
        $user = $res->fetch_assoc();
        if ($user && password_verify($p, $user['password'])) {
            json_out(["status"=>"ok","user"=>$user]);
        } else {
            json_out(["status"=>"fail","message"=>"账号或密码错误"]);
        }
    }

    // 申请重置密码（返回一个重置 token，用于本地开发环境显示）
    if ($action == "request_password_reset") {
        $u = $input['username'] ?? '';
        if (!$u) {
            echo json_encode(["status"=>"fail","message"=>"缺少用户名"]);
            exit;
        }
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $u);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        if (!$user) {
            echo json_encode(["status"=>"fail","message"=>"用户不存在"]);
            exit;
        }

        $user_id = intval($user['id']);
        // 生成 token
        try {
            $token = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $token = bin2hex(openssl_random_pseudo_bytes(16));
        }
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        // 创建表（如果不存在）
        $create = "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(128) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($create);

        // 写入 token（同一用户可覆盖最新）
        $stmt2 = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)");
        $stmt2->bind_param("iss", $user_id, $token, $expires);
        $stmt2->execute();
        // Attempt to send reset email if mail is configured. Assume username is the email address.
        $user_email = $u;
        $site_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        $base = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . $site_host;
        // append script dir if not root
        if ($script_dir && $script_dir !== '/' && $script_dir !== '\\') $base .= $script_dir;
        $reset_link = rtrim($base, '/') . '/reset_password.html?token=' . urlencode($token);

        $from_email = $mailCfg['from_email'] ?? ('noreply@' . $site_host);
        $from_name = $mailCfg['from_name'] ?? 'Shopee Clone';
        $subject = '密码重置请求';
        $body = "您好，\n\n我们收到了重置密码的请求。如果是您本人操作，请点击下方链接重置密码（1小时内有效）：\n\n" . $reset_link . "\n\n如果您未请求重置，请忽略此邮件。\n\n--\nShopee Clone";

        $mail_sent = false;
        // Prefer PHPMailer if available and SMTP configured
        if (!empty($mailCfg['smtp']) && file_exists(__DIR__ . '/vendor/autoload.php')) {
            try {
                require_once __DIR__ . '/vendor/autoload.php';
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $smtp = $mailCfg['smtp'];
                $mail->isSMTP();
                $mail->Host = $smtp['host'];
                $mail->Port = $smtp['port'] ?? 587;
                $mail->SMTPAuth = true;
                $mail->Username = $smtp['username'];
                $mail->Password = $smtp['password'];
                if (!empty($smtp['secure'])) $mail->SMTPSecure = $smtp['secure'];
                $mail->setFrom($from_email, $from_name);
                $mail->addAddress($user_email);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->send();
                $mail_sent = true;
            } catch (Exception $e) {
                error_log('PHPMailer failed: ' . $e->getMessage());
                $mail_sent = false;
            }
        } else {
            // Fallback to PHP mail(); ensure headers
            $headers = 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n" . 'Content-Type: text/plain; charset=utf-8';
            try {
                $mail_sent = mail($user_email, $subject, $body, $headers);
            } catch (Exception $e) {
                error_log('mail() failed: ' . $e->getMessage());
                $mail_sent = false;
            }
        }

        if ($mail_sent) {
            json_out(["status"=>"ok","message"=>"重置邮件已发送，请检查你的邮箱（如果未收到请检查垃圾邮件）"]);
        } else {
            // mail failed — return token for local/dev use
            json_out(["status"=>"ok","message"=>"重置码已生成（邮件发送失败，返回 token 供本地使用）","token"=>$token]);
        }
    }

    // 使用 token 重置密码
    if ($action == "reset_password") {
        $token = $input['token'] ?? '';
        $newpw = $input['password'] ?? '';
        if (!$token || !$newpw) {
            echo json_encode(["status"=>"fail","message"=>"缺少参数"]);
            exit;
        }

        // 查找 token
        $stmt = $conn->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ? LIMIT 1");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if (!$row) {
            json_out(["status"=>"fail","message"=>"无效的重置码"]);
        }
        if (strtotime($row['expires_at']) < time()) {
            json_out(["status"=>"fail","message"=>"重置码已过期"]);
        }

        $uid = intval($row['user_id']);
        $hash = password_hash($newpw, PASSWORD_DEFAULT);
        $stmt2 = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt2->bind_param("si", $hash, $uid);
        if ($stmt2->execute()) {
            // 删除已使用 token
            $stmt3 = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt3->bind_param("s", $token);
            $stmt3->execute();
            json_out(["status"=>"ok","message"=>"密码已重置"]);
        } else {
            json_out(["status"=>"fail","message"=>"重置失败"]);
        }
    }

    if ($action == "switch_role") {
        $user_id = $input['user_id'];
        $new_role = $input['role'];
        
        // 验证新角色值
        if ($new_role !== 'buyer' && $new_role !== 'seller') {
            echo json_encode(["status"=>"fail","message"=>"无效的角色"]);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $user_id);
        if ($stmt->execute()) {
            // 获取更新后的用户信息
            $res = $conn->query("SELECT * FROM users WHERE id = $user_id");
            $user = $res->fetch_assoc();
            json_out(["status"=>"ok","message"=>"角色切换成功","user"=>$user]);
        } else {
            json_out(["status"=>"fail","message"=>"角色切换失败"]);
        }
    }
    break;
}
?>
