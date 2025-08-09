<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin(); require_csrf();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad id']); exit; }

$st=$pdo->prepare("SELECT product_id,stored_name,is_primary FROM crm_product_images WHERE id=:id");
$st->execute([':id'=>$id]); $img = $st->fetch();
if (!$img) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }

$productId = (int)$img['product_id'];
$file = APP_ROOT.'/storage/products/'.$productId.'/'.$img['stored_name'];

try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM crm_product_images WHERE id=:id")->execute([':id'=>$id]);
    if (is_file($file)) @unlink($file);

    if (!empty($img['is_primary'])) {
        // назначим обложкой первую оставшуюся
        $row = $pdo->prepare("SELECT id FROM crm_product_images WHERE product_id=:p ORDER BY sort_order,id LIMIT 1");
        $row->execute([':p'=>$productId]); $next = $row->fetchColumn();
        if ($next) {
            $pdo->prepare("UPDATE crm_product_images SET is_primary=TRUE WHERE id=:id")->execute([':id'=>$next]);
        }
    }

    $pdo->commit();
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
