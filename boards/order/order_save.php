<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
require_csrf();
require_once APP_ROOT . '/inc/inventory.php';
global $pdo;

$id           = (int)($_POST['id'] ?? 0);
$customer_id  = (int)($_POST['customer_id'] ?? 0);
$status       = $_POST['status'] ?? 'new';
$currency     = $_POST['currency'] ?? 'UAH';
$source       = trim($_POST['source'] ?? '');
$notes        = trim($_POST['notes'] ?? '');
$warehouse_id = (int)($_POST['warehouse_id'] ?? 0);

$prodIds = $_POST['item_product_id'] ?? [];
$names   = $_POST['item_name']        ?? [];
$skus    = $_POST['item_sku']         ?? [];
$units   = $_POST['item_unit']        ?? [];
$qtys    = $_POST['item_qty']         ?? [];
$prices  = $_POST['item_price']       ?? [];

$shipping = (float)str_replace(',','.', $_POST['total_shipping'] ?? 0);
$discount = (float)str_replace(',','.', $_POST['total_discount'] ?? 0);
$tax      = (float)str_replace(',','.', $_POST['total_tax'] ?? 0);

if ($customer_id <= 0) { flash('err','Выберите клиента.'); goto back; }
if (!in_array($status, ['new','pending','paid','shipped','canceled','refunded'], true)) $status = 'new';
if (!in_array($currency, ['UAH','USD','EUR'], true)) $currency = 'UAH';
if ($warehouse_id <= 0) { flash('err','Выберите склад заказа.'); goto back; }

// валидируем клиента
$st = $pdo->prepare("SELECT 1 FROM crm_customers WHERE id=:id");
$st->execute([':id'=>$customer_id]);
if (!$st->fetch()) { flash('err','Клиент не найден.'); goto back; }

// собираем позиции
$items = [];
$N = max(count($names), count($prodIds), count($qtys));
for ($i=0; $i<$N; $i++) {
    $pid  = (int)($prodIds[$i] ?? 0);
    $name = trim((string)($names[$i] ?? ''));
    $sku  = trim((string)($skus[$i]  ?? ''));
    $unit = trim((string)($units[$i] ?? 'шт'));
    $qty  = (float)str_replace(',','.', $qtys[$i] ?? 0);
    $pr   = (float)str_replace(',','.', $prices[$i] ?? 0);
    if ($name==='' && $pid<=0) continue;
    if ($qty <= 0 || $pr < 0) continue;

    if ($pid > 0) {
        $g = $pdo->prepare("SELECT sku,name,unit FROM crm_products WHERE id=:id");
        $g->execute([':id'=>$pid]); $p = $g->fetch();
        if ($p) {
            if ($name==='') $name = $p['name'];
            if ($sku==='')  $sku  = $p['sku'];
            if (!$unit)     $unit = $p['unit'] ?: 'шт';
        }
    }
    $line = round($qty * $pr, 2);
    $items[] = ['product_id'=>$pid?:null, 'name'=>$name, 'sku'=>$sku, 'unit'=>$unit?:'шт', 'qty'=>$qty, 'price'=>$pr, 'total'=>$line];
}
if (!$items) { flash('err','Добавьте хотя бы одну позицию.'); goto back; }

// суммы
$total_items  = array_reduce($items, fn($a,$b)=> $a + $b['total'], 0.0);
$total_amount = round($total_items + $shipping - $discount + $tax, 2);

$pdo->beginTransaction();
try {
    // INSERT/UPDATE заказа
    if ($id) {
        $sql = "UPDATE crm_orders
            SET customer_id=:cid, status=:st, currency=:cur, source=:src, notes=:n,
                total_items=:ti, total_shipping=:ts, total_discount=:td, total_tax=:tt, total_amount=:ta,
                warehouse_id=:wh
            WHERE id=:id";
        $pdo->prepare($sql)->execute([
            ':cid'=>$customer_id, ':st'=>$status, ':cur'=>$currency, ':src'=>$source?:null, ':n'=>$notes?:null,
            ':ti'=>$total_items, ':ts'=>$shipping, ':td'=>$discount, ':tt'=>$tax, ':ta'=>$total_amount,
            ':wh'=>$warehouse_id, ':id'=>$id
        ]);

        $pdo->prepare("DELETE FROM crm_order_items WHERE order_id=:id")->execute([':id'=>$id]);
        $ins = $pdo->prepare("INSERT INTO crm_order_items (order_id, product_id, product_name, sku, unit, quantity, unit_price, total)
                          VALUES (:oid, :pid, :nm, :sku, :unit, :q, :p, :t)");
        foreach ($items as $it) {
            $ins->execute([
                ':oid'=>$id, ':pid'=>$it['product_id'], ':nm'=>$it['name'], ':sku'=>$it['sku']?:null,
                ':unit'=>$it['unit'], ':q'=>$it['qty'], ':p'=>$it['price'], ':t'=>$it['total']
            ]);
        }
    } else {
        $sql = "INSERT INTO crm_orders (customer_id, status, currency, source, notes,
                                     total_items, total_shipping, total_discount, total_tax, total_amount, warehouse_id)
            VALUES (:cid,:st,:cur,:src,:n,:ti,:ts,:td,:tt,:ta,:wh)
            RETURNING id";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':cid'=>$customer_id, ':st'=>$status, ':cur'=>$currency, ':src'=>$source?:null, ':n'=>$notes?:null,
            ':ti'=>$total_items, ':ts'=>$shipping, ':td'=>$discount, ':tt'=>$tax, ':ta'=>$total_amount,
            ':wh'=>$warehouse_id
        ]);
        $id = (int)$st->fetchColumn();

        $ins = $pdo->prepare("INSERT INTO crm_order_items (order_id, product_id, product_name, sku, unit, quantity, unit_price, total)
                          VALUES (:oid, :pid, :nm, :sku, :unit, :q, :p, :t)");
        foreach ($items as $it) {
            $ins->execute([
                ':oid'=>$id, ':pid'=>$it['product_id'], ':nm'=>$it['name'], ':sku'=>$it['sku']?:null,
                ':unit'=>$it['unit'], ':q'=>$it['qty'], ':p'=>$it['price'], ':t'=>$it['total']
            ]);
        }
    }

    // ===== ДВИЖЕНИЕ СКЛАДА ПОД ЗАКАЗ =====
    // активные статусы – держим списание OUT; отменён/возврат – создаём возврат IN
    $active = in_array($status, ['new','pending','paid','shipped'], true);

    // OUT: создать/обновить
    if ($active) {
        // проверка остатков перед списанием
        foreach ($items as $r) {
            $pid = (int)($r['product_id'] ?? 0);
            if (!$pid && $r['sku'] !== '') {
                $s = $pdo->prepare("SELECT id FROM crm_products WHERE sku=:sku");
                $s->execute([':sku'=>$r['sku']]); $pid = (int)($s->fetchColumn() ?: 0);
            }
            if (!$pid) {
                $s = $pdo->prepare("SELECT id FROM crm_products WHERE name=:n LIMIT 1");
                $s->execute([':n'=>$r['name']]); $pid = (int)($s->fetchColumn() ?: 0);
            }
            if (!$pid) throw new RuntimeException('Не найден товар: '.$r['name']);

            $stq = $pdo->prepare("SELECT qty FROM crm_product_stock WHERE product_id=:p AND warehouse_id=:w");
            $stq->execute([':p'=>$pid, ':w'=>$warehouse_id]);
            $avail = (float)($stq->fetchColumn() ?: 0);
            if ($avail + 1e-9 < (float)$r['qty']) {
                throw new RuntimeException('Недостаточно остатка: '.$r['name'].' — нужно '.$r['qty'].', доступно '.$avail);
            }
        }

        // есть ли уже OUT?
        $m = $pdo->prepare("SELECT id FROM crm_stock_moves WHERE order_id=:oid AND doc_type='out'");
        $m->execute([':oid'=>$id]);
        $outId = (int)($m->fetchColumn() ?: 0);

        if ($outId) {
            stock_revert_move($pdo, $outId);
            $pdo->prepare("UPDATE crm_stock_moves SET warehouse_id=:w, notes='Списание по заказу' WHERE id=:id")
                ->execute([':w'=>$warehouse_id, ':id'=>$outId]);
            $pdo->prepare("DELETE FROM crm_stock_move_items WHERE move_id=:id")->execute([':id'=>$outId]);
            $ins = $pdo->prepare("INSERT INTO crm_stock_move_items (move_id, product_id, sku, name, qty, unit_price, line_total)
                            SELECT :m, COALESCE(oi.product_id, p.id), oi.sku, oi.product_name, oi.quantity, oi.unit_price, oi.unit_price*oi.quantity
                            FROM crm_order_items oi
                            LEFT JOIN crm_products p ON p.sku = oi.sku
                            WHERE oi.order_id = :oid");
            $ins->execute([':m'=>$outId, ':oid'=>$id]);
            stock_apply_move($pdo, $outId);
        } else {
            $s = $pdo->prepare("INSERT INTO crm_stock_moves (doc_type, warehouse_id, order_id, notes)
                          VALUES ('out', :w, :oid, 'Списание по заказу') RETURNING id");
            $s->execute([':w'=>$warehouse_id, ':oid'=>$id]);
            $outId = (int)$s->fetchColumn();

            $ins = $pdo->prepare("INSERT INTO crm_stock_move_items (move_id, product_id, sku, name, qty, unit_price, line_total)
                            SELECT :m, COALESCE(oi.product_id, p.id), oi.sku, oi.product_name, oi.quantity, oi.unit_price, oi.unit_price*oi.quantity
                            FROM crm_order_items oi
                            LEFT JOIN crm_products p ON p.sku = oi.sku
                            WHERE oi.order_id = :oid");
            $ins->execute([':m'=>$outId, ':oid'=>$id]);
            stock_apply_move($pdo, $outId);
        }

        // если ранее был возврат IN (например, после отмены) — убираем его
        $mi = $pdo->prepare("SELECT id FROM crm_stock_moves WHERE order_id=:oid AND doc_type='in'");
        $mi->execute([':oid'=>$id]);
        $inId = (int)($mi->fetchColumn() ?: 0);
        if ($inId) {
            stock_revert_move($pdo, $inId);
            $pdo->prepare("DELETE FROM crm_stock_move_items WHERE move_id=:id")->execute([':id'=>$inId]);
            $pdo->prepare("DELETE FROM crm_stock_moves WHERE id=:id")->execute([':id'=>$inId]);
        }

    } else { // canceled/refunded
        // обеспечим наличие OUT (для истории). Если его нет — создадим (и сразу вернём IN)
        $m = $pdo->prepare("SELECT id FROM crm_stock_moves WHERE order_id=:oid AND doc_type='out'");
        $m->execute([':oid'=>$id]);
        $outId = (int)($m->fetchColumn() ?: 0);
        if (!$outId) {
            $s = $pdo->prepare("INSERT INTO crm_stock_moves (doc_type, warehouse_id, order_id, notes)
                          VALUES ('out', :w, :oid, 'Списание по заказу') RETURNING id");
            $s->execute([':w'=>$warehouse_id, ':oid'=>$id]);
            $outId = (int)$s->fetchColumn();
            $ins = $pdo->prepare("INSERT INTO crm_stock_move_items (move_id, product_id, sku, name, qty, unit_price, line_total)
                            SELECT :m, COALESCE(oi.product_id, p.id), oi.sku, oi.product_name, oi.quantity, oi.unit_price, oi.unit_price*oi.quantity
                            FROM crm_order_items oi
                            LEFT JOIN crm_products p ON p.sku = oi.sku
                            WHERE oi.order_id = :oid");
            $ins->execute([':m'=>$outId, ':oid'=>$id]);
            stock_apply_move($pdo, $outId);
        }

        // создаём/обновляем IN (возврат)
        $mi = $pdo->prepare("SELECT id FROM crm_stock_moves WHERE order_id=:oid AND doc_type='in'");
        $mi->execute([':oid'=>$id]);
        $inId = (int)($mi->fetchColumn() ?: 0);

        if ($inId) {
            stock_revert_move($pdo, $inId);
            $pdo->prepare("UPDATE crm_stock_moves SET warehouse_id=:w, notes='Возврат по отмене/возврату заказа' WHERE id=:id")
                ->execute([':w'=>$warehouse_id, ':id'=>$inId]);
            $pdo->prepare("DELETE FROM crm_stock_move_items WHERE move_id=:id")->execute([':id'=>$inId]);
        } else {
            $s = $pdo->prepare("INSERT INTO crm_stock_moves (doc_type, warehouse_id, order_id, notes)
                          VALUES ('in', :w, :oid, 'Возврат по отмене/возврату заказа') RETURNING id");
            $s->execute([':w'=>$warehouse_id, ':oid'=>$id]);
            $inId = (int)$s->fetchColumn();
        }
        $ins = $pdo->prepare("INSERT INTO crm_stock_move_items (move_id, product_id, sku, name, qty, unit_price, line_total)
                          SELECT :m, COALESCE(oi.product_id, p.id), oi.sku, oi.product_name, oi.quantity, oi.unit_price, oi.unit_price*oi.quantity
                          FROM crm_order_items oi
                          LEFT JOIN crm_products p ON p.sku = oi.sku
                          WHERE oi.order_id = :oid");
        $ins->execute([':m'=>$inId, ':oid'=>$id]);
        stock_apply_move($pdo, $inId);
    }
    // ===== /ДВИЖЕНИЕ СКЛАДА ПОД ЗАКАЗ =====

    $pdo->commit();
    flash('ok','Заказ сохранён.');
    header('Location: '.url('boards/order/order_edit.php?id='.$id)); exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    flash('err','Ошибка сохранения: '.$e->getMessage());
    back:
    header('Location: '.($id?url('boards/order/order_edit.php?id='.$id):url('boards/order/order_new.php'))); exit;
}
