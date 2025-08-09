<?php
// boards/api/instock.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$q  = trim((string)($_GET['q'] ?? ''));
$wh = (int)($_GET['warehouse_id'] ?? 0);
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));

if ($wh <= 0) { echo json_encode([]); exit; }

$params = [':wh'=>$wh];
$whereQ = '';
if ($q !== '') { $whereQ = "AND (p.sku ILIKE :q OR p.name ILIKE :q OR p.barcode ILIKE :q)"; $params[':q'] = '%'.$q.'%'; }

$sql = "SELECT p.id, p.sku, p.name, p.unit, p.price, COALESCE(s.qty,0) AS qty
        FROM crm_products p
        JOIN crm_product_stock s ON s.product_id = p.id AND s.warehouse_id = :wh
        WHERE COALESCE(s.qty,0) > 0 $whereQ
        ORDER BY p.name
        LIMIT $limit";

global $pdo;
$st = $pdo->prepare($sql);
$st->execute($params);
echo json_encode($st->fetchAll() ?: [], JSON_UNESCAPED_UNICODE);
