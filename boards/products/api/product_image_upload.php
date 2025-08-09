<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin(); require_csrf();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

$productId = (int)($_POST['product_id'] ?? 0);
if ($productId<=0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad product']); exit; }

$files = $_FILES['files'] ?? null;
if (!$files) { echo json_encode(['ok'=>true,'files'=>[]]); exit; }

$dir = APP_ROOT . '/storage/products/' . $productId;
if (!is_dir($dir)) @mkdir($dir, 0775, true);

$out = [];
$hasPrimary = (bool)$pdo->prepare("SELECT EXISTS(SELECT 1 FROM crm_product_images WHERE product_id=:p AND is_primary)")
        ->execute([':p'=>$productId]) && (bool)$pdo->query("SELECT COUNT(*) FROM crm_product_images WHERE product_id={$productId} AND is_primary")->fetchColumn();

try {
    $pdo->beginTransaction();
    $ins = $pdo->prepare("INSERT INTO crm_product_images(product_id,original_name,stored_name,is_primary,sort_order)
                        VALUES (:p,:o,:s,:pr,(SELECT COALESCE(MAX(sort_order),0)+1 FROM crm_product_images WHERE product_id=:p2))
                        RETURNING id,is_primary,stored_name");
    $count = is_array($files['name']) ? count($files['name']) : 0;
    for ($i=0; $i<$count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $orig = $files['name'][$i];
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $name = bin2hex(random_bytes(8)).($ext?('.'.$ext):'');
        $tmp  = $files['tmp_name'][$i];
        if (!move_uploaded_file($tmp, $dir.'/'.$name)) continue;

        $st=$ins->execute([':p'=>$productId, ':o'=>$orig, ':s'=>$name, ':pr'=> $hasPrimary?0:1, ':p2'=>$productId]);
        $row=$ins->fetch(PDO::FETCH_ASSOC);
        $hasPrimary = $hasPrimary || (bool)$row['is_primary'];
        $out[] = [
            'id' => (int)$row['id'],
            'url'=> asset('storage/products/'.$productId.'/'.$row['stored_name']),
            'is_primary' => (bool)$row['is_primary']
        ];
    }
    $pdo->commit();
    echo json_encode(['ok'=>true,'files'=>$out]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
