<?php
header("Content-Type: application/json");
include "db.php";

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case "GET":
            // 获取用户订单
            if(isset($_GET['action']) && $_GET['action'] === 'my_orders' && isset($_GET['user_id'])) {
                $user_id = intval($_GET['user_id']);
                $sql = "SELECT o.*, 
                       GROUP_CONCAT(CONCAT(p.name, ' x', oi.quantity, ' (RM', p.price, ')') SEPARATOR '\n') as items
                       FROM orders o
                       JOIN order_items oi ON o.id = oi.order_id
                       JOIN products p ON oi.product_id = p.id
                       WHERE o.user_id = ?
                       GROUP BY o.id
                       ORDER BY o.created_at DESC";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $orders = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($orders);
                break;
            }
            
            // 获取商品
            $category = isset($_GET['category']) ? $_GET['category'] : '';
            $keyword = isset($_GET['search']) ? $_GET['search'] : '';
            $sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE 1";

            if($category){
                $category_safe = $conn->real_escape_string($category);
                $sql .= " AND c.name LIKE '%$category_safe%'";
            }
            if($keyword){
                $keyword_safe = $conn->real_escape_string($keyword);
                $sql .= " AND p.name LIKE '%$keyword_safe%'";
            }

            $res = $conn->query($sql);
            if(!$res) throw new Exception($conn->error);
            echo json_encode($res->fetch_all(MYSQLI_ASSOC));
            break;

        case "POST":
            $data = json_decode(file_get_contents("php://input"), true);
            if(!$data) throw new Exception("POST 数据无效");
            if(!isset($data['action'])) throw new Exception("缺少 action 参数");

            // 买家下单
            if($data['action'] === "create_order"){
                if(!isset($data['user_id'], $data['items'], $data['recipient_name'], $data['recipient_address']))
                    throw new Exception("缺少参数");

                $user_id = intval($data['user_id']);
                $items = $data['items'];
                $recipient_name = $data['recipient_name'];
                $recipient_address = $data['recipient_address'];

                $total = 0;
                foreach($items as $i) $total += floatval($i['price']) * intval($i['quantity']);

                $payment_method = $data['payment_method'] ?? 'cash';
                $payment_status = $payment_method === 'cash' ? 'pending' : 'paid';

                $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, recipient_name, recipient_address, payment_method, payment_status) VALUES (?,?,?,?,?,?)");
                $stmt->bind_param("idssss", $user_id, $total, $recipient_name, $recipient_address, $payment_method, $payment_status);
                $stmt->execute();
                $order_id = $stmt->insert_id;

                $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?,?,?)");
                foreach($items as $i){
                    $pid = intval($i['id']);
                    $qty = intval($i['quantity']);
                    $stmt2->bind_param("iii", $order_id, $pid, $qty);
                    $stmt2->execute();
                }

                echo json_encode(["status"=>"ok","message"=>"订单创建成功","order_id"=>$order_id]);
            }

            // 卖家添加商品
            else if($data['action'] === "add_product"){
                if(!isset($data['name'], $data['price'], $data['category_id'], $data['seller_id']))
                    throw new Exception("缺少参数");

                $name = $data['name'];
                $price = floatval($data['price']);
                $category_id = intval($data['category_id']);
                $seller_id = intval($data['seller_id']);
                $image = isset($data['image']) ? $data['image'] : '';
                $description = isset($data['description']) ? $data['description'] : '';

                $stmt = $conn->prepare("INSERT INTO products (name, price, category_id, seller_id, image, description) VALUES (?,?,?,?,?,?)");
                $stmt->bind_param("sdiiss",$name,$price,$category_id,$seller_id,$image,$description);
                $stmt->execute();
                echo json_encode(["status"=>"ok","message"=>"商品添加成功","product_id"=>$stmt->insert_id]);
            }
            else{
                throw new Exception("未知动作");
            }
            break;

        case "GET":
            // 获取用户订单
            if(isset($_GET['action']) && $_GET['action'] === 'my_orders' && isset($_GET['user_id'])) {
                $user_id = intval($_GET['user_id']);
                $sql = "SELECT o.*, 
                       GROUP_CONCAT(CONCAT(p.name, ' x', oi.quantity, ' (RM', p.price, ')') SEPARATOR '\n') as items
                       FROM orders o
                       JOIN order_items oi ON o.id = oi.order_id
                       JOIN products p ON oi.product_id = p.id
                       WHERE o.user_id = ?
                       GROUP BY o.id
                       ORDER BY o.created_at DESC";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $orders = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($orders);
                break;
            }
            break;

        default:
            echo json_encode(["status"=>"error","message"=>"不支持的请求方法"]);
            break;
    }
}catch(Exception $e){
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
?>
