<?php
header("Content-Type: application/json");
include "db.php";  // 引入数据库连接

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case "GET":
            // 查看用户订单
            if (isset($_GET['action']) && $_GET['action'] === "my_orders") {
                if (!isset($_GET['user_id'])) throw new Exception("缺少 user_id 参数");
                $user_id = intval($_GET['user_id']);

                $sql = "SELECT o.id AS order_id, o.total_price, o.created_at, o.recipient_name, o.recipient_address,
                               p.name AS product_name, p.price AS product_price, oi.quantity
                        FROM orders o
                        JOIN order_items oi ON o.id = oi.order_id
                        JOIN products p ON oi.product_id = p.id
                        WHERE o.user_id = $user_id
                        ORDER BY o.created_at DESC";
                $res = $conn->query($sql);
                if (!$res) throw new Exception($conn->error);

                $orders_raw = $res->fetch_all(MYSQLI_ASSOC);
                $orders = [];
                foreach ($orders_raw as $row) {
                    $oid = $row['order_id'];
                    if (!isset($orders[$oid])) $orders[$oid] = [
                        "order_id" => $oid,
                        "total_price" => $row['total_price'],
                        "created_at" => $row['created_at'],
                        "recipient_name" => $row['recipient_name'],
                        "recipient_address" => $row['recipient_address'],
                        "items" => []
                    ];
                    $orders[$oid]['items'][] = [
                        "name" => $row['product_name'],
                        "price" => $row['product_price'],
                        "quantity" => $row['quantity']
                    ];
                }
                echo json_encode(array_values($orders));
                break;
            }

            // 商品查询
            $category = isset($_GET['category']) ? $_GET['category'] : '';
            $keyword = isset($_GET['search']) ? $_GET['search'] : '';

            $sql = "SELECT p.*, c.name AS category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE 1";

            if ($category) {
                $category_safe = $conn->real_escape_string($category);
                $sql .= " AND c.name LIKE '%$category_safe%'";
            }
            if ($keyword) {
                $keyword_safe = $conn->real_escape_string($keyword);
                $sql .= " AND p.name LIKE '%$keyword_safe%'";
            }

            $res = $conn->query($sql);
            if (!$res) throw new Exception($conn->error);

            $products = $res->fetch_all(MYSQLI_ASSOC);
            echo json_encode($products);
            break;

        case "POST":
            // 下单功能
            $data = json_decode(file_get_contents("php://input"), true);
            if (!$data) throw new Exception("POST 数据无效");

            // 检查必要参数
            if (!isset($data['user_id'], $data['items'], $data['recipient_name'], $data['recipient_address'])) {
                throw new Exception("缺少参数");
            }

            $user_id = intval($data['user_id']);
            $items = $data['items'];
            $recipient_name = trim($data['recipient_name']);
            $recipient_address = trim($data['recipient_address']);

            if (!$recipient_name || !$recipient_address) {
                throw new Exception("收件人姓名和地址不能为空");
            }

            // 计算总价
            $total = 0;
            foreach ($items as $i) {
                $total += floatval($i['price']) * intval($i['quantity']);
            }

            // 创建订单
            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, recipient_name, recipient_address) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("idss", $user_id, $total, $recipient_name, $recipient_address);
            $stmt->execute();
            $order_id = $stmt->insert_id;

            // 插入订单明细
            $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");
            foreach ($items as $i) {
                $pid = intval($i['id']);
                $qty = intval($i['quantity']);
                $stmt2->bind_param("iii", $order_id, $pid, $qty);
                $stmt2->execute();
            }

            echo json_encode([
                "status" => "ok",
                "message" => "订单创建成功",
                "order_id" => $order_id
            ]);
            break;

        default:
            echo json_encode([
                "status" => "error",
                "message" => "未知动作"
            ]);
            break;
    }
} catch(Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
