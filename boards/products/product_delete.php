<?php // boards/products/product_delete.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf();
global $pdo;
$id = (int)($_POST['id'] ?? 0);
if ($id>0) {
    $pdo->prepare("DELETE FROM crm_products WHERE id=:id")->execute([':id'=>$id]);
    flash('ok','Товар удалён.');
}
header('Location: '.url('boards/products/products.php')); exit;
