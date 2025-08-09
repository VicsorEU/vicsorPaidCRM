<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
require_csrf();
require_once APP_ROOT . '/inc/inventory.php';
global $pdo;

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: '.url('boards/order/orders.php')); exit; }

// Возьмём склад заказа — пригодится при возврате
$st = $pdo->prepare("SELECT warehouse_id FROM crm_orders WHERE id=:id");
$st->execute([':id'=>$id]);
$warehouse_id = (int)($st->fetchColumn() ?: 0);

$pdo->beginTransaction();
try {
    // Есть ли списание OUT по этому заказу?
    $q = $pdo->prepare("SELECT id FROM crm_stock_moves WHERE order_id=:oid AND doc_type='out'");
    $q->execute([':oid'=>$id]);
    $outId = (int)($q->fetchColumn() ?: 0);

    // Есть ли уже возврат IN?
    $q = $pdo->prepare("SELECT id FROM crm_stock_moves WHERE order_id=:oid AND doc_type='in'");
    $q->execute([':oid'=>$id]);
    $inId = (int)($q->fetchColumn() ?: 0);

    // Если было списание и не было возврата — оформим возврат по удалению
    if ($outId && !$inId && $warehouse_id > 0) {
        $s = $pdo->prepare("INSERT INTO crm_stock_moves (doc_type, warehouse_id, order_id, notes)
                        VALUES ('in', :w, :oid, 'Возврат по удалению заказа') RETURNING id");
        $s->execute([':w'=>$warehouse_id, ':oid'=>$id]);
        $inId = (int)$s->fetchColumn();

        $ins = $pdo->prepare("INSERT INTO crm_stock_move_items (move_id, product_id, sku, name, qty, unit_price, line_total)
                          SELECT :m, COALESCE(oi.product_id, p.id), oi.sku, oi.product_name, oi.quantity, oi.unit_price, oi.unit_price*oi.quantity
                          FROM crm_order_items oi
                          LEFT JOIN crm_products p ON p.sku = oi.sku
                          WHERE oi.order_id = :oid");
        $ins->execute([':m'=>$inId, ':oid'=>$id]);

        stock_apply_move($pdo, $inId);
    }

    // Чистим сам заказ
    $pdo->prepare("DELETE FROM crm_order_items WHERE order_id=:id")->execute([':id'=>$id]);
    $pdo->prepare("DELETE FROM crm_orders WHERE id=:id")->execute([':id'=>$id]);

    $pdo->commit();
    flash('ok','Заказ удалён. Возврат на склад оформлен.');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('err','Ошибка удаления: '.$e->getMessage());
}
header('Location: '.url('boards/order/orders.php')); exit;
