<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
require_csrf();
require_once APP_ROOT . '/inc/inventory.php';
global $pdo;

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: '.url('boards/order/orders.php')); exit; }

$pdo->beginTransaction();
try {
    // если есть движение под заказ — откатим и удалим
    $m = $pdo->prepare("SELECT id FROM crm_stock_moves WHERE order_id=:oid");
    $m->execute([':oid'=>$id]);
    $moveId = (int)($m->fetchColumn() ?: 0);
    if ($moveId) {
        stock_revert_move($pdo, $moveId);
        $pdo->prepare("DELETE FROM crm_stock_moves WHERE id=:id")->execute([':id'=>$moveId]);
    }

    // удаляем позиции заказа и сам заказ
    $pdo->prepare("DELETE FROM crm_order_items WHERE order_id=:id")->execute([':id'=>$id]);
    $pdo->prepare("DELETE FROM crm_orders WHERE id=:id")->execute([':id'=>$id]);

    $pdo->commit();
    flash('ok','Заказ удалён.');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('err','Ошибка удаления: '.$e->getMessage());
}
header('Location: '.url('boards/order/orders.php')); exit;
