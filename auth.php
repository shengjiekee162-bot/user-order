<?php
header("Content-Type: application/json");
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . "/php_errors.log");

ob_start();
require_once "db.php";

// Minimal send_mail implementation (kept inline to avoid adding new files)
function send_mail($to, $subject, $body, $from_email = null, $from_name = null) {
    $site_host = preg_replace('/:\d+$/', '', ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $mailCfg = [];
    if (file_exists(__DIR__ . '/mail_config.php')) {
        $mailCfg = include __DIR__ . '/mail_config.php';
    }

    $from_email = $from_email ?? ($mailCfg['from_email'] ?? ('noreply@' . $site_host));
    $from_name = $from_name ?? ($mailCfg['from_name'] ?? 'App');

    // Try PHPMailer SMTP if configured and autoload exists
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
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('PHPMailer failed: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback to PHP mail()
    $headers = 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n" . 'Content-Type: text/plain; charset=utf-8';
    try {
        return mail($to, $subject, $body, $headers);
    } catch (Exception $e) {
        error_log('mail() failed: ' . $e->getMessage());
        return false;
    }
}

function json_out($data){
    if (ob_get_length()) ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];
if ($method !== "POST") json_out(["status"=>"fail","message"=>"只允许 POST"]);

$input = json_decode(file_get_contents("php://input"), true);
$action = $input["action"] ?? "";

/* -------------------- 注册 -------------------- */
if ($action === "register") {
    $u = trim($input["username"] ?? "");
    $pw = trim($input["password"] ?? "");
    $role = $input["role"] ?? "buyer";

    if (!$u || !$pw) json_out(["status"=>"fail","message"=>"缺少用户名或密码"]);

    // 查重复
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0)
        json_out(["status"=>"fail","message"=>"用户名已存在"]);

    // 加密密码
    $hash = password_hash($pw, PASSWORD_DEFAULT);

    $role = in_array($role, ["buyer","seller"]) ? $role : "buyer";

    $stmt = $conn->prepare("INSERT INTO users(username,password,role) VALUES(?,?,?)");
    $stmt->bind_param("sss", $u, $hash, $role);
    $stmt->execute();

    json_out(["status"=>"ok","message"=>"注册成功"]);
}

/* -------------------- 登录 -------------------- */
if ($action === "login") {
    $u = trim($input["username"] ?? "");
    $pw = trim($input["password"] ?? "");

    if (!$u || !$pw) json_out(["status"=>"fail","message"=>"请输入用户名密码"]);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($pw, $user["password"])) {
        json_out(["status"=>"ok","user"=>$user]);
    } else {
        json_out(["status"=>"fail","message"=>"账号或密码错误"]);
    }
}

/* -------------------- 切换用户角色（buyer <-> seller） -------------------- */
if ($action === "switch_role") {
    $user_id = intval($input["user_id"] ?? 0);
    $new_role = trim($input["role"] ?? "");
    if (!$user_id || !in_array($new_role, ["buyer", "seller"])) {
        json_out(["status"=>"fail","message"=>"缺少参数或角色无效"]);
    }

    $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
    $stmt->bind_param("si", $new_role, $user_id);
    if (!$stmt->execute()) {
        json_out(["status"=>"fail","message"=>"更新角色失败"]);
    }

    // 返回更新后的用户信息（不包含敏感字段）
    $stmt2 = $conn->prepare("SELECT id, username, role FROM users WHERE id=? LIMIT 1");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $user = $stmt2->get_result()->fetch_assoc();
    json_out(["status"=>"ok","user"=>$user]);
}

/* -------------------- 请求密码重置（发邮件：6位验证码） -------------------- */
if ($action === "request_password_reset") {
    $u = trim($input["username"] ?? "");
    $email = trim($input["email"] ?? "");
    if (!$u) json_out(["status"=>"fail","message"=>"缺少用户名"]);
    if (!$email) json_out(["status"=>"fail","message"=>"缺少邮箱地址"]);

    // 查用户（用户名存在即可，不强制和邮箱一致，因为用户注册时可能未填写邮箱）
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) json_out(["status"=>"fail","message"=>"用户不存在"]);

    $user_id = intval($user["id"]);
    try {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        $code = str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    $expires = date("Y-m-d H:i:s", time() + 15 * 60);

    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(128) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt2 = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)");
    $stmt2->bind_param("iss", $user_id, $code, $expires);
    $stmt2->execute();

    $subject = "密码重置验证码";
    $body = "您好，\n\n您的密码重置验证码为： " . $code . "\n\n此验证码将在 15 分钟后失效。\n\n如果不是您本人操作，请忽略本邮件。";

    // 发送到用户在表单中填写的邮箱（不要使用注册时可能不存在的邮箱字段）
    $sent = send_mail($email, $subject, $body);
    if ($sent) json_out(["status"=>"ok","message"=>"验证码已发送"]);
    else json_out(["status"=>"fail","message"=>"邮件发送失败"]);
}

/* -------------------- 使用验证码重置密码 -------------------- */
if ($action === "reset_password") {
    $u = trim($input["username"] ?? "");
    $code = trim($input["code"] ?? "");
    $pw = $input["new_password"] ?? "";

    if (!$u || !$code || !$pw) json_out(["status"=>"fail","message"=>"缺少用户名、验证码或新密码"]);

    $stmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) json_out(["status"=>"fail","message"=>"用户不存在"]);
    $user_id = intval($user["id"]);

    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE user_id=? AND token=? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("is", $user_id, $code);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    if (!$data) json_out(["status"=>"fail","message"=>"无效的验证码"]);
    if (strtotime($data["expires_at"]) < time()) json_out(["status"=>"fail","message"=>"验证码已过期"]);

    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $stmt2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt2->bind_param("si", $hash, $user_id);
    $stmt2->execute();

    $stmt3 = $conn->prepare("DELETE FROM password_resets WHERE user_id=?");
    $stmt3->bind_param("i", $user_id);
    $stmt3->execute();

    json_out(["status"=>"ok","message"=>"密码重置成功"]);
}

/* Password-reset endpoints removed as requested */

   json_out(["status"=>"fail","message"=>"未知操作"]);
  