<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf();
require_once APP_ROOT.'/inc/inventory.php';
global $pdo;

$id       = (int)($_POST['id'] ?? 0);
$doc_type = $_POST['doc_type'] ?? 'in';
if(!in_array($doc_type,['in','out','transfer','adjust'],true)) $doc_type='in';

$warehouse_id     = (int)($_POST['warehouse_id'] ?? 0);
$src_warehouse_id = (int)($_POST['src_warehouse_id'] ?? 0);
$dest_warehouse_id= (int)($_POST['dest_warehouse_id'] ?? 0);

$ttn_number = trim($_POST['ttn_number'] ?? '');
$ttn_date   = ($_POST['ttn_date'] ?? '') ?: null;
$carrier    = trim($_POST['carrier'] ?? '');
$notes      = trim($_POST['notes'] ?? '');

$sku   = $_POST['item_sku']   ?? [];
$name  = $_POST['item_name']  ?? [];
$qty   = $_POST['item_qty']   ?? [];
$price = $_POST['item_price'] ?? [];
$prodIds = $_POST['item_product_id'] ?? [];

// валидация складов
if ($doc_type==='transfer') {
    if ($src_warehouse_id<=0 || $dest_warehouse_id<=0 || $src_warehouse_id===$dest_warehouse_id) {
        flash('err','Укажите разные склады для перемещения.');
        header('Location: '.($id?url('boards/inventory/movement_edit.php?id='.$id):url('boards/inventory/movement_new.php?type=transfer'))); exit;
    }
} else {
    if ($warehouse_id<=0) { flash('err','Выберите склад.'); header('Location: '.($id?url('boards/inventory/movement_edit.php?id='.$id):url('boards/inventory/movement_new.php?type='.$doc_type))); exit; }
}

// собираем строки (приоритет product_id для pick-режима)
$items = [];
if ($prodIds) {
    for ($i=0; $i<count($prodIds); $i++) {
        $pid = (int)$prodIds[$i];
        if ($pid <= 0) continue;
        $q   = (float)str_replace(',','.', $qty[$i] ?? 0); if ($q <= 0) continue;
        $pr  = (float)str_replace(',','.', $price[$i] ?? 0);
        $n   = trim((string)($name[$i] ?? ''));
        $s   = trim((string)($sku[$i]  ?? ''));
        $items[] = ['product_id'=>$pid,'name'=>$n,'sku'=>$s,'qty'=>$q,'price'=>$pr,'line'=>round($q*$pr,2)];
    }
} else {
    for ($i=0; $i<count($name); $i++) {
        $n = trim((string)$name[$i]); if ($n==='') continue;
        $qv = (float)str_replace(',','.', $qty[$i] ?? 0); if ($qv<=0) continue;
        $pr = (float)str_replace(',','.', $price[$i] ?? 0);
        $s  = trim((string)($sku[$i] ?? ''));

        $pid = null;
        if ($s !== '') {
            $g = $pdo->prepare("SELECT id FROM crm_products WHERE sku=:sku");
            $g->execute([':sku'=>$s]); $pid = (int)($g->fetchColumn() ?: 0);
        }
        if (!$pid) {
            $g = $pdo->prepare("SELECT id FROM crm_products WHERE name=:n LIMIT 1");
            $g->execute([':n'=>$n]); $pid = (int)($g->fetchColumn() ?: 0);
        }
        if (!$pid && $doc_type === 'in') {
            $ins=$pdo->prepare("INSERT INTO crm_products (sku,name,unit,price,cost_price) VALUES (:sku,:name,'шт',:price,:price) RETURNING id");
            $ins->execute([':sku'=>$s?:null,':name'=>$n,':price'=>$pr]);
            $pid=(int)$ins->fetchColumn();
        }
        if (!$pid) throw new RuntimeException('Не удалось определить товар: '.$n.' (задайте SKU или используйте выбор из наличия).');

        $items[] = ['product_id'=>$pid,'name'=>$n,'sku'=>$s,'qty'=>$qv,'price'=>$pr,'line'=>round($qv*$pr,2)];
    }
}
if (!$items) { flash('err','Добавьте хотя бы одну позицию.'); header('Location: '.($id?url('boards/inventory/movement_edit.php?id='.$id):url('boards/inventory/movement_new.php?type='.$doc_type))); exit; }

// Предпроверка остатков для out/transfer
if ($doc_type === 'out' || $doc_type === 'transfer') {
    $srcWh = $doc_type==='transfer' ? $src_warehouse_id : $warehouse_id;
    foreach ($items as $it) {
        $s = $pdo->prepare("SELECT qty FROM crm_product_stock WHERE product_id=:p AND warehouse_id=:w");
        $s->execute([':p'=>$it['product_id'], ':w'=>$srcWh]);
        $avail = (float)($s->fetchColumn() ?: 0);
        if ($avail + 1e-9 < $it['qty']) {
            throw new RuntimeException('Недостаточно остатка: товар ID '.$it['product_id'].' — нужно '.$it['qty'].', доступно '.$avail);
        }
    }
}

$pdo->beginTransaction();
try{
    if ($id) {
        $old=$pdo->prepare("SELECT doc_type FROM crm_stock_moves WHERE id=:id"); $old->execute([':id'=>$id]);
        $ot=$old->fetchColumn();
        if ($ot==='adjust') { throw new RuntimeException('Корректировка не редактируется. Удалите и создайте заново.'); }

        stock_revert_move($pdo, $id);

        $pdo->prepare("UPDATE crm_stock_moves SET doc_type=:t, warehouse_id=:w, src_warehouse_id=:sw, dest_warehouse_id=:dw, 
          ttn_number=:tn, ttn_date=:td, carrier=:cr, notes=:n WHERE id=:id")
            ->execute([
                ':t'=>$doc_type, ':w'=>$warehouse_id?:null, ':sw'=>$src_warehouse_id?:null, ':dw'=>$dest_warehouse_id?:null,
                ':tn'=>$ttn_number?:null, ':td'=>$ttn_date, ':cr'=>$carrier?:null, ':n'=>$notes?:null, ':id'=>$id
            ]);

        $pdo->prepare("DELETE FROM crm_stock_move_items WHERE move_id=:id")->execute([':id'=>$id]);
        $insI=$pdo->prepare("INSERT INTO crm_stock_move_items (move_id, product_id, sku, name, qty, unit_price, line_total) 
                         VALUES (:m,:p,:sku,:name,:q,:pr,:lt)");
        foreach($items as $it){
            $insI->execute([
                ':m'=>$id,':p'=>$it['product_id'],':sku'=>$it['sku']?:null,':name'=>$it['name'],
                ':q'=>$it['qty'],':pr'=>$it['price'],':lt'=>$it['line']
            ]);
        }
        stock_apply_move($pdo, $id);

    } else {
        $st=$pdo->prepare("INSERT INTO crm_stock_moves (doc_type, warehouse_id, src_warehouse_id, dest_warehouse_id, ttn_number, ttn_date, carrier, notes)
                       VALUES (:t,:w,:sw,:dw,:tn,:td,:cr,:n) RETURNING id");
        $st->execute([
            ':t'=>$doc_type, ':w'=>$warehouse_id?:null, ':sw'=>$src_warehouse_id?:null, ':dw'=>$dest_warehouse_id?:null,
            ':tn'=>$ttn_number?:null, ':td'=>$ttn_date, ':cr'=>$carrier?:null, ':n'=>$notes?:null
        ]);
        $id=(int)$st->fetchColumn();

        $insI=$pdo->prepare("INSERT INTO crm_stock_move_items (move_id, product_id, sku, name, qty, unit_price, line_total) 
                         VALUES (:m,:p,:sku,:name,:q,:pr,:lt)");
        foreach($items as $it){
            $insI->execute([
                ':m'=>$id,':p'=>$it['product_id'],':sku'=>$it['sku']?:null,':name'=>$it['name'],
                ':q'=>$it['qty'],':pr'=>$it['price'],':lt'=>$it['line']
            ]);
        }
        stock_apply_move($pdo, $id);
    }

    $pdo->commit();
    flash('ok','Документ сохранён.');
    header('Location: '.url('boards/inventory/movement_edit.php?id='.$id)); exit;

}catch(Throwable $e){
    $pdo->rollBack();
    flash('err','Ошибка: '.$e->getMessage());
    header('Location: '.($id?url('boards/inventory/movement_edit.php?id='.$id):url('boards/inventory/movement_new.php?type='.$doc_type))); exit;
}
