<?php
header('Content-Type: application/json');
include "db.php";

// Ensure uploads directory exists
$uploadDir = __DIR__ . '/uploads';
if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Accept a multipart/form-data upload with field 'image'
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['status'=>'fail','message'=>'只支持 POST 上传']);
    exit;
}

if(!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK){
    echo json_encode(['status'=>'fail','message'=>'未收到图片或上传失败']);
    exit;
}

$file = $_FILES['image'];
$origName = basename($file['name']);
$ext = pathinfo($origName, PATHINFO_EXTENSION);
$allowed = ['jpg','jpeg','png','gif','webp'];
if(!in_array(strtolower($ext), $allowed)){
    echo json_encode(['status'=>'fail','message'=>'不支持的图片格式']);
    exit;
}

$targetName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$targetPath = $uploadDir . '/' . $targetName;
if(!move_uploaded_file($file['tmp_name'], $targetPath)){
    echo json_encode(['status'=>'fail','message'=>'保存图片失败']);
    exit;
}

// If vision_config.php exists with an API key, try Google Cloud Vision to get labels/entities
$visionLabels = [];
if(file_exists(__DIR__ . '/vision_config.php')){
    $visionConf = include __DIR__ . '/vision_config.php';
    if(!empty($visionConf['api_key'])){
        $apiKey = $visionConf['api_key'];
        $imgData = base64_encode(file_get_contents($targetPath));
        $payload = [
            'requests' => [[
                'image' => ['content' => $imgData],
                'features' => [
                    ['type' => 'WEB_DETECTION', 'maxResults' => 10],
                    ['type' => 'LABEL_DETECTION', 'maxResults' => 10]
                ]
            ]]
        ];
        $ch = curl_init('https://vision.googleapis.com/v1/images:annotate?key=' . urlencode($apiKey));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if($resp && !$err){
            $json = json_decode($resp, true);
            if(isset($json['responses'][0])){
                $r = $json['responses'][0];
                // collect webDetection best guess labels and labelAnnotations descriptions
                if(isset($r['webDetection']['bestGuessLabels'])){
                    foreach($r['webDetection']['bestGuessLabels'] as $b) $visionLabels[] = $b['label'];
                }
                if(isset($r['webDetection']['webEntities'])){
                    foreach($r['webDetection']['webEntities'] as $we) if(!empty($we['description'])) $visionLabels[] = $we['description'];
                }
                if(isset($r['labelAnnotations'])){
                    foreach($r['labelAnnotations'] as $la) if(!empty($la['description'])) $visionLabels[] = $la['description'];
                }
                // dedupe labels
                $visionLabels = array_values(array_unique(array_map('strtolower', $visionLabels)));
            }
        }
    }
}

// If we have vision labels, use them to search product names/descriptions
if(!empty($visionLabels)){
    // build a WHERE clause using LIKE for each label
    $conds = [];
    foreach($visionLabels as $lbl){
        $safe = $conn->real_escape_string($lbl);
        $conds[] = "(name LIKE '%$safe%' OR description LIKE '%$safe%')";
    }
    if(!empty($conds)){
        $sqlv = "SELECT * FROM products WHERE " . implode(' OR ', $conds) . " LIMIT 50";
        $resv = $conn->query($sqlv);
        if($resv && $resv->num_rows > 0){
            $products = $resv->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status'=>'ok','message'=>'基于视觉标签找到匹配商品','products'=>$products,'uploaded'=>$targetName,'labels'=>$visionLabels]);
            exit;
        }
    }
}

// Try to find products whose image field contains the original filename or the uploaded filename
$searchName = $conn->real_escape_string($origName);
$searchTarget = $conn->real_escape_string($targetName);
$sql = "SELECT * FROM products WHERE image LIKE '%$searchName%' OR image LIKE '%$searchTarget%' LIMIT 50";
$res = $conn->query($sql);
if($res && $res->num_rows > 0){
    $products = $res->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['status'=>'ok','message'=>'找到匹配的商品','products'=>$products,'uploaded'=>$targetName]);
    exit;
}

// 如果没有找到匹配项，可以尝试更宽泛的匹配：用文件名不带扩展部分
$base = pathinfo($origName, PATHINFO_FILENAME);
$baseEsc = $conn->real_escape_string($base);
$sql2 = "SELECT * FROM products WHERE name LIKE '%$baseEsc%' OR description LIKE '%$baseEsc%' LIMIT 50";
$res2 = $conn->query($sql2);
if($res2 && $res2->num_rows > 0){
    $products = $res2->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['status'=>'ok','message'=>'根据文件名在名称/描述中搜索到结果','products'=>$products,'uploaded'=>$targetName]);
    exit;
}

// 回退：返回空结果，让前端决定是否显示全部商品
echo json_encode(['status'=>'ok','message'=>'未找到直接匹配的商品','products'=>[], 'uploaded'=>$targetName]);
exit;
?>
