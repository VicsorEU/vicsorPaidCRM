<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin(); require_csrf();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad id']); exit; }

try {
    $pdo->beginTransaction();
    // удалятся и подкатегории благодаря ON DELETE CASCADE
    $st = $pdo->prepare("DELETE FROM crm_product_categories WHERE id=:id");
    $st->execute([':id'=>$id]);
    $pdo->commit();
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
