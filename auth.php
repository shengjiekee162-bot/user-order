<?php
header("Content-Type: application/json");
include "db.php";
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
  case "POST":
    $input = json_decode(file_get_contents("php://input"), true);
    $action = $input['action'];

    if ($action == "register") {
        $u = $input['username'];
        $p = password_hash($input['password'], PASSWORD_DEFAULT);
        $role = $input['role'];
        $stmt = $conn->prepare("INSERT INTO users (username,password,role) VALUES (?,?,?)");
        $stmt->bind_param("sss",$u,$p,$role);
        $stmt->execute();
        echo json_encode(["status"=>"ok","message"=>"注册成功！"]);
    }

    if ($action == "login") {
        $u = $input['username'];
        $p = $input['password'];
        $res = $conn->query("SELECT * FROM users WHERE username='$u'");
        $user = $res->fetch_assoc();
        if ($user && password_verify($p, $user['password'])) {
            echo json_encode(["status"=>"ok","user"=>$user]);
        } else {
            echo json_encode(["status"=>"fail","message"=>"账号或密码错误"]);
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

        // 返回 token（本地开发环境无法发送邮件，因此直接返回 token 供使用）
        echo json_encode(["status"=>"ok","message"=>"重置码已生成（仅本地显示）","token"=>$token]);
        exit;
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
            echo json_encode(["status"=>"fail","message"=>"无效的重置码"]);
            exit;
        }
        if (strtotime($row['expires_at']) < time()) {
            echo json_encode(["status"=>"fail","message"=>"重置码已过期"]);
            exit;
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
            echo json_encode(["status"=>"ok","message"=>"密码已重置"]);
        } else {
            echo json_encode(["status"=>"fail","message"=>"重置失败"]);
        }
        exit;
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
            echo json_encode(["status"=>"ok","message"=>"角色切换成功","user"=>$user]);
        } else {
            echo json_encode(["status"=>"fail","message"=>"角色切换失败"]);
        }
    }
    break;
}
?>
