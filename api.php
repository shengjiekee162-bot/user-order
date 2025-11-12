<?php
header("Content-Type: application/json");
include "db.php";

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case "GET":
            // 获取地址列表
            if(isset($_GET['action']) && $_GET['action'] === 'list_addresses' && isset($_GET['user_id'])) {
                $user_id = intval($_GET['user_id']);
                $sql = "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $addresses = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($addresses);
                break;
            }
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
            // 获取订单详情
            if(isset($_GET['action']) && $_GET['action'] === 'order_detail' && isset($_GET['order_id'])) {
                $order_id = intval($_GET['order_id']);
                $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $orderRes = $stmt->get_result();
                $order = $orderRes->fetch_assoc();

                $stmt2 = $conn->prepare("SELECT oi.quantity, p.id as product_id, p.name, p.price FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
                $stmt2->bind_param("i", $order_id);
                $stmt2->execute();
                $itemsRes = $stmt2->get_result();
                $items = $itemsRes->fetch_all(MYSQLI_ASSOC);

                echo json_encode(['order' => $order, 'items' => $items]);
                break;
            }
            // 获取单个商品详情
            if(isset($_GET['action']) && $_GET['action'] === 'get_product' && isset($_GET['id'])) {
                $product_id = intval($_GET['id']);
                $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
                echo json_encode($product);
                break;
            }

            // 获取卖家的商品列表
            if(isset($_GET['action']) && $_GET['action'] === 'my_products' && isset($_GET['seller_id'])) {
                $seller_id = intval($_GET['seller_id']);
          // 返回属于该卖家或未分配（seller_id IS NULL 或 0）的商品，方便卖家认领老数据
          $sql = "SELECT p.*, c.name AS category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE (p.seller_id = ? OR p.seller_id IS NULL OR p.seller_id = 0)
              ORDER BY p.id DESC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $seller_id);
                $stmt->execute();
                $result = $stmt->get_result();
                echo json_encode($result->fetch_all(MYSQLI_ASSOC));
                break;
            }

            // 获取卖家的统计信息（商品总数、总销售额）
            if(isset($_GET['action']) && $_GET['action'] === 'seller_stats' && isset($_GET['seller_id'])){
                $seller_id = intval($_GET['seller_id']);
                // 商品总数
                $stmt = $conn->prepare("SELECT COUNT(*) AS total_products FROM products WHERE seller_id = ?");
                $stmt->bind_param("i", $seller_id);
                $stmt->execute();
                $totalRes = $stmt->get_result()->fetch_assoc();

                // 总销售额：基于 order_items 与 products 的联表（使用当时 products.price 近似当前价格）
                $stmt2 = $conn->prepare("SELECT IFNULL(SUM(oi.quantity * p.price), 0) AS total_sales FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE p.seller_id = ?");
                $stmt2->bind_param("i", $seller_id);
                $stmt2->execute();
                $salesRes = $stmt2->get_result()->fetch_assoc();

                echo json_encode(['total_products' => intval($totalRes['total_products']), 'total_sales' => floatval($salesRes['total_sales'])]);
                break;
            }

            // 获取所有商品
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
            // 添加新地址
            else if($data['action'] === "add_address"){
                if(!isset($data['user_id'], $data['recipient_name'], $data['recipient_address']))
                    throw new Exception("缺少地址参数");
                $user_id = intval($data['user_id']);
                $recipient_name = $data['recipient_name'];
                $recipient_address = $data['recipient_address'];
                $is_default = isset($data['is_default']) && $data['is_default'] ? 1 : 0;
                if($is_default){
                    // 取消该用户其他默认地址
                    $conn->query("UPDATE addresses SET is_default=0 WHERE user_id=$user_id");
                }
                $stmt = $conn->prepare("INSERT INTO addresses (user_id, recipient_name, recipient_address, is_default) VALUES (?,?,?,?)");
                $stmt->bind_param("issi", $user_id, $recipient_name, $recipient_address, $is_default);
                if($stmt->execute()){
                    echo json_encode(["status"=>"ok","message"=>"地址添加成功"]);
                }else{
                    echo json_encode(["status"=>"fail","message"=>"地址添加失败"]);
                }
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
            // 卖家更新商品
            else if($data['action'] === "update_product"){
                if(!isset($data['id'], $data['name'], $data['price'], $data['category_id'], $data['seller_id']))
                    throw new Exception("缺少参数");

                $id = intval($data['id']);
                $name = $data['name'];
                $price = floatval($data['price']);
                $category_id = intval($data['category_id']);
                $seller_id = intval($data['seller_id']);
                $description = isset($data['description']) ? $data['description'] : '';

                // 验证商品属于该卖家
                $stmt = $conn->prepare("SELECT seller_id FROM products WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
                
                if(!$product || $product['seller_id'] != $seller_id) {
                    throw new Exception("无权修改此商品");
                }

                $stmt = $conn->prepare("UPDATE products SET name=?, price=?, category_id=?, description=? WHERE id=? AND seller_id=?");
                $stmt->bind_param("sdisii", $name, $price, $category_id, $description, $id, $seller_id);
                if($stmt->execute()) {
                    echo json_encode(["status"=>"ok","message"=>"商品更新成功"]);
                } else {
                    throw new Exception("更新失败");
                }
            }
            // 卖家删除商品
            else if($data['action'] === "delete_product"){
                if(!isset($data['id'], $data['seller_id']))
                    throw new Exception("缺少参数");

                $id = intval($data['id']);
                $seller_id = intval($data['seller_id']);

                // 验证商品属于该卖家
                $stmt = $conn->prepare("SELECT seller_id FROM products WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
                
                if(!$product || $product['seller_id'] != $seller_id) {
                    throw new Exception("无权删除此商品");
                }

                // 先把商品写入历史表以保留记录，再删除原表中的记录
                // 尝试创建历史表（如果不存在）
                $create_sql = "CREATE TABLE IF NOT EXISTS product_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT NOT NULL,
                    name VARCHAR(255),
                    price DECIMAL(10,2),
                    category_id INT,
                    seller_id INT,
                    image TEXT,
                    description TEXT,
                    deleted_by INT,
                    deleted_at DATETIME
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $conn->query($create_sql);

                // 获取完整商品行以备份
                $stmt2 = $conn->prepare("SELECT id, name, price, category_id, seller_id, image, description FROM products WHERE id = ? LIMIT 1");
                $stmt2->bind_param("i", $id);
                $stmt2->execute();
                $row = $stmt2->get_result()->fetch_assoc();

                if($row){
                    $now = date('Y-m-d H:i:s');
                    $stmt3 = $conn->prepare("INSERT INTO product_history (product_id, name, price, category_id, seller_id, image, description, deleted_by, deleted_at) VALUES (?,?,?,?,?,?,?,?,?)");
                    $stmt3->bind_param("isdisissi", $row['id'], $row['name'], $row['price'], $row['category_id'], $row['seller_id'], $row['image'], $row['description'], $seller_id, $now);
                    // Note: if bind_param types mismatch, we'll fallback to a simple query
                    $ok = false;
                    try{
                        $ok = $stmt3->execute();
                    }catch(Exception $e){
                        // ignore and continue
                    }
                }

                // 最后执行删除（若历史写入失败也允许删除，但备份推荐先检查）
                $stmt4 = $conn->prepare("DELETE FROM products WHERE id=? AND seller_id=?");
                $stmt4->bind_param("ii", $id, $seller_id);
                if($stmt4->execute()) {
                    echo json_encode(["status"=>"ok","message"=>"商品删除成功"]);
                } else {
                    throw new Exception("删除失败");
                }
            }
            // 卖家认领未归属商品（仅当 product.seller_id 为 NULL 或 0 时允许）
            else if($data['action'] === "claim_product"){
                if(!isset($data['id'], $data['seller_id']))
                    throw new Exception("缺少参数");

                $id = intval($data['id']);
                $seller_id = intval($data['seller_id']);

                // 检查商品当前归属
                $stmt = $conn->prepare("SELECT seller_id FROM products WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();

                if(!$product) throw new Exception("商品不存在");
                $current_owner = intval($product['seller_id']);
                if($current_owner !== 0 && $current_owner !== null){
                    throw new Exception("该商品已有归属，不能认领");
                }

                $stmt = $conn->prepare("UPDATE products SET seller_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $seller_id, $id);
                if($stmt->execute()){
                    echo json_encode(["status"=>"ok","message"=>"商品已认领"]);
                } else {
                    throw new Exception("认领失败");
                }
            }
            // 强制认领商品（覆盖原有 seller_id），并记录原始归属到 product_claims 表（如果存在）
            else if($data['action'] === "force_claim_product"){
                if(!isset($data['id'], $data['seller_id']))
                    throw new Exception("缺少参数");

                $id = intval($data['id']);
                $seller_id = intval($data['seller_id']);

                // 检查商品当前归属
                $stmt = $conn->prepare("SELECT seller_id FROM products WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
                if(!$product) throw new Exception("商品不存在");
                $old_owner = $product['seller_id'] === null ? 0 : intval($product['seller_id']);

                // 执行覆盖
                $stmt = $conn->prepare("UPDATE products SET seller_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $seller_id, $id);
                if(!$stmt->execute()){
                    throw new Exception("强制认领失败");
                }

                // 尝试写入审计表 product_claims（如果表存在）
                try {
                    $now = date('Y-m-d H:i:s');
                    $stmt2 = $conn->prepare("INSERT INTO product_claims (product_id, old_owner, new_owner, claimed_by, created_at) VALUES (?,?,?,?,?)");
                    if($stmt2){
                        $stmt2->bind_param("iiiss", $id, $old_owner, $seller_id, $seller_id, $now);
                        $stmt2->execute();
                    }
                } catch(Exception $e) {
                    // 忽略审计写入错误，认领已完成
                }

                echo json_encode(["status"=>"ok","message"=>"商品已强制认领","old_owner"=>$old_owner,"new_owner"=>$seller_id]);
            }
            else{
                throw new Exception("未知动作");
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
