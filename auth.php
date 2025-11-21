<?php
// 输出 JSON，关闭错误显示，开启错误日志
header("Content-Type: application/json");
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . "/php_errors.log");

ob_start(); // 开启输出缓冲
require_once "db.php"; // 引入数据库连接
// Determine which column holds the account identifier: prefer 'username', fall back to 'name', then 'email'
$user_col = 'username';
$colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'username'");
if (!($colRes && $colRes->num_rows > 0)) {
    $colRes2 = $conn->query("SHOW COLUMNS FROM users LIKE 'name'");
    if ($colRes2 && $colRes2->num_rows > 0) {
        $user_col = 'name';
    } else {
        $colRes3 = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
        if ($colRes3 && $colRes3->num_rows > 0) {
            $user_col = 'email';
        }
    }
}

// ===================== 发邮件函数（支持 SMTP 和 mail()） =====================
function send_mail($to, $subject, $body, $from_email = null, $from_name = null) {
    // 自动获取当前站点域名
    $site_host = preg_replace('/:\d+$/', '', ($_SERVER['HTTP_HOST'] ?? 'localhost'));

    // 载入邮件配置
    $mailCfg = [];
    if (file_exists(__DIR__ . '/mail_config.php')) {
        $mailCfg = include __DIR__ . '/mail_config.php';
    }

    // 默认发件人
    $from_email = $from_email ?? ($mailCfg['from_email'] ?? ('noreply@' . $site_host));
    $from_name  = $from_name  ?? ($mailCfg['from_name'] ?? 'App');

    // 若配置了 SMTP，且存在 composer autoload，则使用 PHPMailer
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

            $mail->setFrom($from_email, $from_name);  // 发件人
            $mail->addAddress($to);                  // 收件人
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('PHPMailer failed: ' . $e->getMessage());
            return false;
        }
    }

    // ===================== fallback：使用 PHP mail() =====================

    // 为避免中文标题乱码，进行 UTF-8 编码
    if (function_exists('mb_encode_mimeheader')) {
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
        $encodedFromName = mb_encode_mimeheader($from_name, 'UTF-8', 'B', "\r\n");
    } else {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = preg_match('/[\x80-\xff]/', $from_name)
            ? ('=?UTF-8?B?' . base64_encode($from_name) . '?=')
            : $from_name;
    }

    // 邮件头
    $headers  = 'From: ' . $encodedFromName . ' <' . $from_email . '>' . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: text/plain; charset=utf-8' . "\r\n";
    $headers .= 'Content-Transfer-Encoding: 8bit' . "\r\n";

    try {
        return mail($to, $encodedSubject, $body, $headers);
    } catch (Exception $e) {
        error_log('mail() failed: ' . $e->getMessage());
        return false;
    }
}

// ===================== JSON 输出统一函数 =====================
function json_out($data){
    if (ob_get_length()) ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===================== 限制必须 POST 请求 =====================
$method = $_SERVER["REQUEST_METHOD"];
if ($method !== "POST") json_out(["status"=>"fail","message"=>"只允许 POST"]);

// 读取输入 JSON
$input = json_decode(file_get_contents("php://input"), true);
$action = $input["action"] ?? "";

// ===================== 注册 =====================
if ($action === "register") {
    $u     = trim($input["username"] ?? "");
    $email = trim($input["email"] ?? "");
    $pw    = trim($input["password"] ?? "");
    $role  = $input["role"] ?? "buyer"; // 默认买家

    if (!$u || !$pw || !$email)
        json_out(["status"=>"fail","message"=>"缺少用户名、邮箱或密码"]);

    // 验证 email 格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        json_out(["status"=>"fail","message"=>"邮箱格式不正确"]);

    // 若 users 表没有 email 字段，则自动添加
    $colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
    if (!$colRes || $colRes->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL");
    }

    // 查用户名是否重复 (对不同的 schema 支持多种列名)
    $stmt = $conn->prepare("SELECT id FROM users WHERE " . $user_col . "=? LIMIT 1");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0)
        json_out(["status"=>"fail","message"=>"用户名已存在"]);

    // 查邮箱是否重复
    $stmtE = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmtE->bind_param("s", $email);
    $stmtE->execute();
    if ($stmtE->get_result()->num_rows > 0)
        json_out(["status"=>"fail","message"=>"邮箱已被使用"]);

    // 加密密码
    $hash = password_hash($pw, PASSWORD_DEFAULT);

    // 只允许 buyer 和 seller
    $role = strtolower($role);
    $role = in_array($role, ["buyer","seller"]) ? $role : "buyer";

    // 写入数据库 (在不同 schema 中使用检测到的用户列名)
    $stmt = $conn->prepare("INSERT INTO users(" . $user_col . ",password,role,email) VALUES(?,?,?,?)");
    $stmt->bind_param("ssss", $u, $hash, $role, $email);
    if (!$stmt->execute())
        json_out(["status"=>"fail","message"=>"注册失败：" . $conn->error]);

    json_out(["status"=>"ok","message"=>"注册成功"]);
}

// ===================== 登录 =====================
if ($action === "login") {
    $u = trim($input["username"] ?? "");
    $pw = trim($input["password"] ?? "");

    if (!$u || !$pw) json_out(["status"=>"fail","message"=>"请输入用户名密码"]);

    // 从数据库查用户
    $stmt = $conn->prepare("SELECT * FROM users WHERE " . $user_col . "=? LIMIT 1");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    // 如果数据表中使用的是其他列名（如 name 或 email），确保返回结果中有 'username' 键
    if ($user && $user_col !== 'username' && isset($user[$user_col])) {
        $user['username'] = $user[$user_col];
    }

    // 验证密码
    if ($user && password_verify($pw, $user["password"])) {
        json_out(["status"=>"ok","user"=>$user]);
    } else {
        json_out(["status"=>"fail","message"=>"账号或密码错误"]);
    }
}

// ===================== 切换角色（buyer <-> seller） =====================
if ($action === "switch_role") {
    $user_id = intval($input["user_id"] ?? 0);
    $new_role = trim($input["role"] ?? "");

    if (!$user_id || !in_array($new_role, ["buyer","seller"]))
        json_out(["status"=>"fail","message"=>"缺少参数或角色无效"]);

    // 更新角色
    $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
    $stmt->bind_param("si", $new_role, $user_id);
    $stmt->execute();

    // 返回更新后的用户（去掉密码）
    $stmt2 = $conn->prepare("SELECT id, " . $user_col . " AS username, role FROM users WHERE id=? LIMIT 1");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $user = $stmt2->get_result()->fetch_assoc();

    json_out(["status"=>"ok","user"=>$user]);
}

// ===================== 申请密码重置（发送验证码） =====================
if ($action === "request_password_reset") {
    $u = trim($input["username"] ?? "");
    $email = trim($input["email"] ?? "");

    if (!$u) json_out(["status"=>"fail","message"=>"缺少用户名"]);
    if (!$email) json_out(["status"=>"fail","message"=>"缺少邮箱地址"]);

    // 查用户名
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE " . $user_col . "=? LIMIT 1");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) json_out(["status"=>"fail","message"=>"用户不存在"]);

    // 检查邮箱是否与注册邮箱一致
    if (strcasecmp(trim($user['email']), $email) !== 0)
        json_out(["status"=>"fail","message"=>"邮箱与注册邮箱不匹配"]);

    // 生成 6 位验证码
    $code = str_pad((string)random_int(0,999999), 6, '0', STR_PAD_LEFT);
    $expires = date("Y-m-d H:i:s", time() + 900); // 15 分钟

    // 确保表存在
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(128) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 保存验证码
    $stmt2 = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)");
    $stmt2->bind_param("iss", $user['id'], $code, $expires);
    $stmt2->execute();

    // 发邮件
    $subject = "密码重置验证码";
    $body = "您好，您的验证码为： $code \n15分钟后失效。";

    if (send_mail($email, $subject, $body))
        json_out(["status"=>"ok","message"=>"验证码已发送"]);
    else
        json_out(["status"=>"fail","message"=>"邮件发送失败"]);
}

// ===================== 使用验证码重置密码 =====================
if ($action === "reset_password") {
    $u = trim($input["username"] ?? "");
    $code = trim($input["code"] ?? "");
    $pw = trim($input["new_password"] ?? "");

    if (!$u || !$code || !$pw)
        json_out(["status"=>"fail","message"=>"缺少信息"]);

    // 查用户
    $stmt = $conn->prepare("SELECT id FROM users WHERE " . $user_col . "=? LIMIT 1");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) json_out(["status"=>"fail","message"=>"用户不存在"]);

    // 查验证码
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE user_id=? AND token=? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("is", $user['id'], $code);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) json_out(["status"=>"fail","message"=>"验证码无效"]);
    if (strtotime($data['expires_at']) < time()) json_out(["status"=>"fail","message"=>"验证码已过期"]);

    // 更新密码
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $stmt2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt2->bind_param("si", $hash, $user['id']);
    $stmt2->execute();

    // 删除验证码记录
    $conn->query("DELETE FROM password_resets WHERE user_id=" . intval($user['id']));

    json_out(["status"=>"ok","message"=>"密码重置成功"]);
}

// ===================== 使用旧密码修改密码 =====================
if ($action === "change_password") {
    $u = trim($input["username"] ?? "");
    $old = trim($input["old_password"] ?? "");
    $new = trim($input["new_password"] ?? "");

    if (!$u || !$old || !$new)
        json_out(["status"=>"fail","message"=>"缺少信息"]);

    if (strlen($new) < 6)
        json_out(["status"=>"fail","message"=>"新密码至少 6 位"]);

    // 查用户
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE " . $user_col . "=? LIMIT 1");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) json_out(["status"=>"fail","message"=>"用户不存在"]);

    // 验证旧密码
    if (!password_verify($old, $user['password']))
        json_out(["status"=>"fail","message"=>"旧密码不正确"]);

    // 更新新密码
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt2->bind_param("si", $hash, $user['id']);
    $stmt2->execute();

    json_out(["status"=>"ok","message"=>"密码已更新"]);
}

// 若 action 不存在
json_out(["status"=>"fail","message"=>"未知操作"]);
