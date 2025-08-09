<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin(); require_csrf();
global $pdo;

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) { flash('err','Неверный запрос'); header('Location: '.url('boards/products/attributes.php')); exit; }

try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM crm_attribute_category WHERE attribute_id=:a")->execute([':a'=>$id]);
    $pdo->prepare("DELETE FROM crm_attribute_options WHERE attribute_id=:a")->execute([':a'=>$id]);
    $pdo->prepare("DELETE FROM crm_product_attribute_values WHERE attribute_id=:a")->execute([':a'=>$id]);
    $pdo->prepare("DELETE FROM crm_attributes WHERE id=:a")->execute([':a'=>$id]);
    $pdo->commit();
    flash('ok','Атрибут удалён');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('err','Ошибка: '.$e->getMessage());
}
header('Location: '.url('boards/products/attributes.php')); exit;
