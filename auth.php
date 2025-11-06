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
    break;
}
?>
