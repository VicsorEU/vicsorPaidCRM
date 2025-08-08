<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
require_csrf();
require_once APP_ROOT . '/inc/inventory.php'; // для автосписания
global $pdo;

$id           = (int)($_POST['id'] ?? 0);
$customer_id  = (int)($_POST['customer_id'] ?? 0);
$status       = $_POST['status'] ?? 'new';
$currency     = $_POST['currency'] ?? 'UAH';
$source       = trim($_POST['source'] ?? '');
$notes        = trim($_POST['notes'] ?? '');
$warehouse_id = (int)($_POST['warehouse_id'] ?? 0);

$names  = $_POST['item_name']  ?? [];
$skus   = $_POST['item_sku']   ?? [];
$qtys   = $_POST['item_qty']   ?? [];
$prices = $_POST['item_price'] ?? [];

$shipping = (float)str_replace(',','.', $_POST['total_shipping'] ?? 0);
$discount = (float)str_replace(',','.', $_POST['total_discount'] ?? 0);
$tax      = (float)str_replace(',','.', $_POST['total_tax'] ?? 0);

if ($customer_id <= 0) { flash('err','Выберите клиента.'); header('Location: '.($id?url('boards/order/order_edit.php?id='.$id):url('boards/order/order_new.php'))); exit; }
if (!in_array($status, ['new','pending','paid','shipped','canceled','refunded'], true)) $status = 'new';
if (!in_array($currency, ['UAH','USD','EUR'], true)) $currency = 'UAH';
if ($warehouse_id <= 0) { flash('err','Выберите склад заказа.'); header('Location: '.($id?url('boards/order/order_edit.php?id='.$id):url('boards/order/order_new.php'))); exit; }

// проверка клиента
$st = $pdo->prepare("SELECT 1 FROM crm_customers WHERE id=:id");
$st->execute([':id'=>$customer_id]);
if (!$st->fetch()) { flash('err','Клиент не найден.'); header('Location: '.($id?url('boards/order/order_edit.php?id='.$id):url('boards/order/order_new.php'))); exit; }

// собираем позиции
$items = [];
for ($i=0; $i<count($names); $i++) {
    $name = trim((string)$names[$i]);
    if ($name==='') continue;
    $sku  = trim((string)($skus[$i] ?? ''));
    $qty  = (float)str_replace(',','.', $qtys[$i] ?? 0);
    $pr   = (float)str_replace(',','.', $prices[$i] ?? 0);
    if ($qty <= 0 || $pr < 0) continue;
    $line = round($qty * $pr, 2);
    $items[] = ['name'=>$name, 'sku'=>$sku, 'qty'=>$qty, 'price'=>$pr, 'total'=>$line];
}
if (!$items) { flash('err','Добавьте хотя бы одну позицию.'); header('Location: '.($id?url('boards/order/order_edit.php?id='.$id):url('boards/order/order_new.php'))); exit; }

// подсчёт
$total_items  = array_reduce($items, fn($a,$b)=> $a + $b['total'], 0.0);
$total_amount = round($total_items + $shipping - $discount + $tax, 2);

$pdo->beginTransaction();
try {
    if ($id) {
        $sql = "UPDATE crm_orders
            SET customer_id=:cid, status=:st, currency=:cur, source=:src, notes=:n,
                total_items=:ti, total_shipping=:ts, total_discount=:td, total_tax=:tt, total_amount=:ta,
                warehouse_id=:wh
            WHERE id=:id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cid'=>$customer_id, ':st'=>$status, ':cur'=>$currency, ':src'=>$source?:null, ':n'=>$notes?:null,
            ':ti'=>$total_items, ':ts'=>$shipping, ':td'=>$discount, ':tt'=>$tax, ':ta'=>$total_amount,
            ':wh'=>$warehouse_id, ':id'=>$id
        ]);

        // Пересобираем позиции
        $pdo->prepare("DELETE FROM crm_order_items WHERE order_id=:id")->execute([':id'=>$id]);
        $ins = $pdo->prepare("INSERT INTO crm_order_items (order_id, product_id, product_name, sku, quantity, unit_price, total)
                          VALUES (:oid, :pid, :nm, :sku, :q, :p, :t)");
        foreach ($items as $it) {
            // product_id попробуем определить по SKU (не обязательно)
            $pid = null;
            if ($it['sku'] !== '') {
                $g = $pdo->prepare("SELECT id FROM crm_products WHERE sku=:sku");
                $g->execute([':sku'=>$it['sku']]);
                $pid = (int)($g->fetchColumn() ?: 0);
            }
            $ins->execute([
                ':oid'=>$id, ':pid'=>$pid?:null,
                ':nm'=>$it['name'], ':sku'=>$it['sku']?:null,
                ':q'=>$it['qty'], ':p'=>$it['price'], ':t'=>$it['total']
            ]);
        }
    } else {
        $sql = "INSERT INTO crm_orders (customer_id, status, currency, source, notes,
                                     total_items, total_shipping, total_discount, total_tax, total_amount, warehouse_id)
            VALUES (:cid,:st,:cur,:src,:n,:ti,:ts,:td,:tt,:ta,:wh)
            RETURNING id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cid'=>$customer_id, ':st'=>$status, ':cur'=>$currency, ':src'=>$source?:null, ':n'=>$notes?:null,
            ':ti'=>$total_items, ':ts'=>$shipping, ':td'=>$discount, ':tt'=>$tax, ':ta'=>$total_amount,
            ':wh'=>$warehouse_id
        ]);
        $id = (int)$stmt->fetchColumn();

        $ins = $pdo->prepare("INSERT INTO crm_order_items (order_id, product_id, product_name, sku, quantity, unit_price, total)
                          VALUES (:oid, :pid, :nm, :sku, :q, :p, :t)");
        foreach ($items as $it) {
            $pid = null;
            if ($it['sku'] !== '') {
                $g = $pdo->prepare("SELECT id FROM crm_products WHERE sku=:sku");
                $g->execute([':sku'=>$it['sku']]);
                $pid = (int)($g->fetchColumn() ?: 0);
            }
            $ins->execute([
                ':oid'=>$id, ':pid'=>$pid?:null,
                ':nm'=>$it['name'], ':sku'=>$it['sku']?:null,
                ':q'=>$it['qty'], ':p'=>$it['price'], ':t'=>$it['total']
            ]);
        }
    }

    // ======= Авто-списание склада при статусе paid =======
    // если заказ оплачен -> создать/обновить документ расхода out, если нет -> удалить существующий
    // (см. таблицы склада и inc/inventory.php)
    // Проверим существующий move
    $m = $pdo->prepare("SELECT id FROM crm_stock_moves WHERE order_id=:oid");
    $m->execute([':oid'=>$id]);
    $moveId = (int)($m->fetchColumn() ?: 0);

    if ($status === 'paid') {
        // валидируем остатки под все строки
        $oi = $pdo->prepare("SELECT product_id, product_name, sku, quantity, unit_price FROM crm_order_items WHERE order_id=:oid");
        $oi->execute([':oid'=>$id]);
        $rows = $oi->fetchAll();
        if (!$rows) throw new RuntimeException('В заказе нет позиций.');

        foreach ($rows as $r) {
            $pid = (int)($r['product_id'] ?? 0);
            if (!$pid && !empty($r['sku'])) {
                $s = $pdo->prepare("SELECT id FROM crm_products WHERE sku=:sku");
                $s->execute([':sku'=>$r['sku']]); $pid = (int)($s->fetchColumn() ?: 0);
            }
            if (!$pid) {
                $s = $pdo->prepare("SELECT id FROM crm_products WHERE name=:n LIMIT 1");
                $s->execute([':n'=>$r['product_name']]); $pid = (int)($s->fetchColumn() ?: 0);
            }
            if (!$pid) throw new RuntimeException('Не найден товар для списания: '.$r['product_name']);

            $stq = $pdo->prepare("SELECT qty FROM crm_product_stock WHERE product_id=:p AND warehouse_id=:w");
            $stq->execute([':p'=>$pid, ':w'=>$warehouse_id]);
            $avail = (float)($stq->fetchColumn() ?: 0);
            if ($avail + 1e-9 < (float)$r['quantity']) {
                throw new RuntimeException('Недостаточно остатка: '.$r['product_name'].' — нужно '.$r['quantity'].', доступно '.$avail);
            }
        }

        if ($moveId) {
            // откатим старое и перезапишем
            stock_revert_move($pdo, $moveId);
            $pdo->prepare("UPDATE crm_stock_moves SET doc_type='out', warehouse_id=:w WHERE id=:id")
                ->execute([':w'=>$warehouse_id, ':id'=>$moveId]);
            $pdo->prepare("DELETE FROM crm_stock_move_items WHERE move_id=:id")->execute([':id'=>$moveId]);

            $ins = $pdo->prepare("INSERT INTO crm_stock_move_items (move_id, product_id, sku, name, qty, unit_price, line_total)
                            SELECT :m, COALESCE(oi.product_id, p.id), oi.sku, oi.product_name, oi.quantity, oi.unit_price, oi.unit_price*oi.quantity
                            FROM crm_order_items oi
                            LEFT JOIN crm_products p ON p.sku = oi.sku
                            WHERE oi.order_id = :oid");
            $ins->execute([':m'=>$moveId, ':oid'=>$id]);

            stock_apply_move($pdo, $moveId);

        } else {
            // новый расход
            $s = $pdo->prepare("INSERT INTO crm_stock_moves (doc_type, warehouse_id, order_id, notes)
                          VALUES ('out', :w, :oid, 'Автосписание по оплате заказа') RETURNING id");
            $s->execute([':w'=>$warehouse_id, ':oid'=>$id]);
            $moveId = (int)$s->fetchColumn();

            $ins = $pdo->prepare("INSERT INTO crm_stock_move_items (move_id, product_id, sku, name, qty, unit_price, line_total)
                            SELECT :m, COALESCE(oi.product_id, p.id), oi.sku, oi.product_name, oi.quantity, oi.unit_price, oi.unit_price*oi.quantity
                            FROM crm_order_items oi
                            LEFT JOIN crm_products p ON p.sku = oi.sku
                            WHERE oi.order_id = :oid");
            $ins->execute([':m'=>$moveId, ':oid'=>$id]);

            stock_apply_move($pdo, $moveId);
        }
    } else {
        if ($moveId) {
            stock_revert_move($pdo, $moveId);
            $pdo->prepare("DELETE FROM crm_stock_moves WHERE id=:id")->execute([':id'=>$moveId]);
        }
    }
    // ======= /Авто-списание =======

    $pdo->commit();
    flash('ok','Заказ сохранён.');
    header('Location: '.url('boards/order/order_edit.php?id='.$id)); exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    flash('err','Ошибка сохранения: '.$e->getMessage());
    header('Location: '.($id?url('boards/order/order_edit.php?id='.$id):url('boards/order/order_new.php'))); exit;
}
