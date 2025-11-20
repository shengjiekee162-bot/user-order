<?php
header("Content-Type: application/json");
include "db.php";

// Temporary debug: show errors to aid diagnosing 500 responses (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {

// If a file is uploaded via multipart/form-data with field 'image', handle the file upload and return JSON with path
// Support multiple files uploaded as 'files[]' (images and videos)
if (!empty($_FILES['files'])) {
  $files = $_FILES['files'];
  $count = is_array($files['name']) ? count($files['name']) : 0;
  $uploadsDir = __DIR__ . '/uploads';
  if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

  $paths = [];
  $maxImageSize = 5 * 1024 * 1024; // 5MB
  $maxVideoSize = 50 * 1024 * 1024; // 50MB
  $allowedVideoExts = ['mp4','webm','ogg','mov','avi','mkv'];

  // prepare error log
  $errorLog = $uploadsDir . '/upload_errors.log';
  for ($i = 0; $i < $count; $i++) {
    try {
      if (!isset($files['error'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
        // log and skip
        @file_put_contents($errorLog, "[WARN] file index $i upload error code: " . ($files['error'][$i] ?? 'n/a') . "\n", FILE_APPEND | LOCK_EX);
        continue;
      }
      $tmp = $files['tmp_name'][$i];
      $origName = $files['name'][$i] ?? '';
      $size = $files['size'][$i] ?? 0;

      if (!is_uploaded_file($tmp) && !file_exists($tmp)) {
        @file_put_contents($errorLog, "[WARN] tmp file missing for index $i: $tmp\n", FILE_APPEND | LOCK_EX);
        continue;
      }

      // detect mime - prefer fileinfo but fall back if extension missing
      $mime = '';
      if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);
      } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmp);
      } else {
        // Last-resort: rely on client-provided type if available
        $mime = $files['type'][$i] ?? '';
      }

      $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

      $isImage = stripos($mime, 'image/') === 0;
      $isVideo = (stripos($mime, 'video/') === 0) || in_array($ext, $allowedVideoExts);

      // image validation
      if ($isImage) {
        if ($size > $maxImageSize) {
          @file_put_contents($errorLog, "[WARN] image too large for index $i: $size\n", FILE_APPEND | LOCK_EX);
          continue;
        }
        $info = @getimagesize($tmp);
        if ($info === false) {
          @file_put_contents($errorLog, "[WARN] getimagesize failed for index $i\n", FILE_APPEND | LOCK_EX);
          continue;
        }
        $imgExt = image_type_to_extension($info[2]);
        // ensure extension starts with dot
        if ($imgExt && $imgExt[0] !== '.') $imgExt = '.' . $imgExt;
        $filename = uniqid('img_', true) . $imgExt;
      }
      // video validation
      else if ($isVideo) {
        if ($size > $maxVideoSize) {
          @file_put_contents($errorLog, "[WARN] video too large for index $i: $size\n", FILE_APPEND | LOCK_EX);
          continue;
        }
        if (!$ext) $ext = 'mp4';
        // normalize extension with dot
        $ext = '.' . ltrim($ext, '.');
        $filename = uniqid('vid_', true) . $ext;
      } else {
        // unsupported type; log and skip
        @file_put_contents($errorLog, "[WARN] unsupported mime for index $i: {$mime} ext={$ext}\n", FILE_APPEND | LOCK_EX);
        continue;
      }

      $dest = $uploadsDir . '/' . $filename;
      if (@move_uploaded_file($tmp, $dest) || @rename($tmp, $dest)) {
        $paths[] = 'uploads/' . $filename;
      } else {
        @file_put_contents($errorLog, "[ERROR] move_uploaded_file failed for index $i to $dest\n", FILE_APPEND | LOCK_EX);
      }
    } catch (Throwable $e) {
      @file_put_contents($errorLog, sprintf("[EXCEPTION] index %d: %s in %s:%d\n", $i, $e->getMessage(), $e->getFile(), $e->getLine()), FILE_APPEND | LOCK_EX);
      continue;
    }
  }

  if (count($paths) === 0) {
    echo json_encode(["status"=>"fail","message"=>"没有有效的上传文件或文件类型不受支持"]);
    exit;
  }

  echo json_encode(["status"=>"ok","paths"=> $paths]);
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
} catch (Throwable $e) {
  // return error details as JSON to help debugging (temporary)
  http_response_code(500);
  echo json_encode(["status"=>"error","message"=>$e->getMessage(),"file"=>$e->getFile(),"line"=>$e->getLine()]);
  exit;
}
?>
