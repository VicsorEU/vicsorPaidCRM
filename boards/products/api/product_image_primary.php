<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin(); require_csrf();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad id']); exit; }

$st=$pdo->prepare("SELECT product_id FROM crm_product_images WHERE id=:id");
$st->execute([':id'=>$id]); $pid = $st->fetchColumn();
if (!$pid) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }

$pdo->beginTransaction();
$pdo->prepare("UPDATE crm_product_images SET is_primary=FALSE WHERE product_id=:p")->execute([':p'=>$pid]);
$pdo->prepare("UPDATE crm_product_images SET is_primary=TRUE WHERE id=:id")->execute([':id'=>$id]);
$pdo->commit();

echo json_encode(['ok'=>true]);
