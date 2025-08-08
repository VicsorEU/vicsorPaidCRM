<?php // boards/products/product_save.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf();
global $pdo;

$id   = (int)($_POST['id'] ?? 0);
$sku  = trim($_POST['sku'] ?? '');
$name = trim($_POST['name'] ?? '');
$barcode = trim($_POST['barcode'] ?? '');
$unit = trim($_POST['unit'] ?? 'шт');
$price = (float)str_replace(',','.', $_POST['price'] ?? 0);
$cost  = (float)str_replace(',','.', $_POST['cost_price'] ?? 0);

if ($sku==='' || $name==='') { flash('err','Заполните SKU и Название'); header('Location: '.($id?url('boards/products/product_edit.php?id='.$id):url('boards/products/product_new.php'))); exit; }

try {
    if ($id) {
        $sql = "UPDATE crm_products SET sku=:sku, name=:name, barcode=:barcode, unit=:unit, price=:price, cost_price=:cost WHERE id=:id";
        $pdo->prepare($sql)->execute([':sku'=>$sku,':name'=>$name,':barcode'=>$barcode,':unit'=>$unit,':price'=>$price,':cost'=>$cost,':id'=>$id]);
        flash('ok','Товар обновлён.');
        header('Location: '.url('boards/products/product_edit.php?id='.$id)); exit;
    } else {
        $sql = "INSERT INTO crm_products (sku,name,barcode,unit,price,cost_price) VALUES (:sku,:name,:barcode,:unit,:price,:cost)";
        $pdo->prepare($sql)->execute([':sku'=>$sku,':name'=>$name,':barcode'=>$barcode,':unit'=>$unit,':price'=>$price,':cost'=>$cost]);
        flash('ok','Товар создан.');
        header('Location: '.url('boards/products/products.php')); exit;
    }
} catch (Throwable $e) {
    flash('err','Ошибка: '.$e->getMessage());
    header('Location: '.($id?url('boards/products/product_edit.php?id='.$id):url('boards/products/product_new.php'))); exit;
}
