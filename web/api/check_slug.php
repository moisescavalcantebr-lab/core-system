<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../../../app/bootstrap/bootstrap.php';

header('Content-Type: application/json');

$slug = strtolower(trim($_GET['slug'] ?? ''));

$slug = preg_replace('/[^a-z0-9\-]/','',$slug);

if(strlen($slug) < 3){
echo json_encode([
'status'=>'invalid'
]);
exit;
}

$stmt = $pdo->prepare("
SELECT id
FROM projects
WHERE slug = :slug
LIMIT 1
");

$stmt->execute(['slug'=>$slug]);

if($stmt->fetch()){

echo json_encode([
'status'=>'taken'
]);

}else{

echo json_encode([
'status'=>'available'
]);

}