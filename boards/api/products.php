<?php
// boards/api/products.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

$where = 'p.is_active = TRUE';
$params = [];
if ($q !== '') {
    $where .= ' AND (p.sku ILIKE :q OR p.name ILIKE :q OR p.barcode ILIKE :q)';
    $params[':q'] = '%'.$q.'%';
}

$sql = "SELECT p.id, p.sku, p.name, p.unit, p.price
        FROM crm_products p
        WHERE $where
        ORDER BY p.name
        LIMIT :lim";

global $pdo;
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':lim', $limit, PDO::PARAM_INT);
$st->execute();

echo json_encode($st->fetchAll() ?: [], JSON_UNESCAPED_UNICODE);
