<?php
// boards/api/instock.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$q  = trim((string)($_GET['q'] ?? ''));
$wh = (int)($_GET['warehouse_id'] ?? 0);
$limit = 100;

$params = [];
$cond = ["COALESCE(s.qty,0) > 0"];

if ($wh > 0) { $cond[] = "s.warehouse_id = :wh"; $params[':wh']=$wh; }
if ($q !== '') {
    $cond[] = "(p.sku ILIKE :q OR p.name ILIKE :q OR p.barcode ILIKE :q)";
    $params[':q'] = '%'.$q.'%';
}
$where = $cond ? ('WHERE '.implode(' AND ',$cond)) : '';

$sql = "SELECT p.id, p.sku, p.name, p.unit, COALESCE(s.qty,0) AS qty
        FROM crm_products p
        LEFT JOIN crm_product_stock s ON s.product_id=p.id".($wh>0?" AND s.warehouse_id=:wh":"")."
        $where
        ORDER BY p.name
        LIMIT $limit";

global $pdo;
$st = $pdo->prepare($sql);
$st->execute($params);
echo json_encode($st->fetchAll() ?: [], JSON_UNESCAPED_UNICODE);
