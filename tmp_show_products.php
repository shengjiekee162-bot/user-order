<?php
require 'db.php';
$res = $conn->query("SELECT id,name,image FROM products LIMIT 10");
$out = [];
while($r = $res->fetch_assoc()) $out[] = $r;
header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
