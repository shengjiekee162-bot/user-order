<?php
header("Content-Type: application/json");
include "db.php";

// If a file is uploaded via multipart/form-data with field 'image', handle the file upload and return JSON with path
if (!empty($_FILES['image'])) {
  $file = $_FILES['image'];
  if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["status"=>"fail","message"=>"上传错误"]);
    exit;
  }

  // Validate image
  $info = getimagesize($file['tmp_name']);
  if ($info === false) {
    echo json_encode(["status"=>"fail","message"=>"只允许上传图片文件"]);
    exit;
  }

  $maxSize = 5 * 1024 * 1024; // 5MB
  if ($file['size'] > $maxSize) {
    echo json_encode(["status"=>"fail","message"=>"文件过大，最大 5MB"]);
    exit;
  }

  $ext = image_type_to_extension($info[2]);
  $uploadsDir = __DIR__ . '/uploads';
  if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

  $filename = uniqid('img_', true) . $ext;
  $dest = $uploadsDir . '/' . $filename;
  if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(["status"=>"fail","message"=>"保存文件失败"]);
    exit;
  }

  // Return a web-path relative to project root
  $webPath = 'uploads/' . $filename;
  echo json_encode(["status"=>"ok","path"=> $webPath]);
  exit;
}

// Otherwise accept JSON product insertion (backwards compatible)
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
  echo json_encode(["status"=>"fail","message"=>"无效请求"]);
  exit;
}

$stmt = $conn->prepare("INSERT INTO products (name,price,image,description,category_id,seller_id) VALUES (?,?,?,?,?,?)");
$stmt->bind_param("sdssii",
  $data['name'], $data['price'], $data['image'], $data['description'], $data['category_id'], $data['seller_id']
);
$stmt->execute();
echo json_encode(["status"=>"ok","message"=>"商品上传成功","product_id"=> $stmt->insert_id]);
?>
