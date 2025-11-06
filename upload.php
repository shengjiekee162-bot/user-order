<?php
header("Content-Type: application/json");
include "db.php";
$data = json_decode(file_get_contents("php://input"), true);

$stmt = $conn->prepare("INSERT INTO products (name,price,image,description,category_id,seller_id) VALUES (?,?,?,?,?,?)");
$stmt->bind_param("sdssii",
  $data['name'], $data['price'], $data['image'], $data['description'], $data['category_id'], $data['seller_id']
);
$stmt->execute();
echo json_encode(["message"=>"商品上传成功"]);
?>
