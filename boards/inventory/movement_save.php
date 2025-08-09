<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf();
require_once APP_ROOT.'/inc/inventory.php';
global $pdo;

$id       = (int)($_POST['id'] ?? 0);
$doc_type = $_POST['doc_type'] ?? 'in';
if(!in_array($doc_type,['in','out','transfer','adjust'],true)) $doc_type='in';

$warehouse_id      = (int)($_POST['warehouse_id'] ?? 0);
$src_warehouse_id  = (int)($_POST['src_warehouse_id'] ?? 0);
$dest_warehouse_id = (int)($_POST['dest_warehouse_id'] ?? 0);

$ttn_number = trim($_POST['ttn_number'] ?? '');
$ttn_date   = ($_POST['ttn_date'] ?? '') ?: null;
$carrier    = trim($_POST['carrier'] ?? '');
$notes      = trim($_POST['notes'] ?? '');

$prodIds = $_POST['item_product_id'] ?? [];    // НОВОЕ
$names   = $_POST['item_name']        ?? [];
$skus    = $_POST['item_sku']         ?? [];
$units   = $_POST['item_unit']        ?? [];    // НОВОЕ
$qty     = $_POST['qty']              ?? [];    // было qty[]
$price   = $_POST['price']            ?? [];    // было price[]

if ($doc_type==='transfer') {
    if ($src_warehouse_id<=0 || $dest_warehouse_id<=0 || $src_warehouse_id===$dest_warehouse_id) {
        flash('err','Укажите разные склады для перемещения.');
        header('Location: '.($id?url('boards/inventory/movement_edit.php?id='.$id):url('boards/inventory/movement_new.php?type=transfer'))); exit;
    }
} else {
    if ($warehouse_id<=0) { flash('err','Выберите склад.'); header('Location: '.($id?url('boards/inventory/movement_edit.php?id='.$id):url('boards/inventory/movement_new.php?type='.$doc_type))); exit; }
}

// ===== Сбор строк =====
$items=[];
$N = max(count($prodIds), count($names), count($qty));
for($i=0;$i<$N;$i++){
    $pid = (int)($prodIds[$i] ?? 0);
    $n   = trim((string)($names[$i] ?? ''));
    $s   = trim((string)($skus[$i]  ?? ''));
    $u   = trim((string)($units[$i] ?? 'шт'));
    $q   = (float)str_replace(',','.', $qty[$i]   ?? 0);
    $p   = (float)str_replace(',','.', $price[$i] ?? 0);
    if ($pid<=0 && $n==='') continue;     // пустая строка
    if ($q<=0) continue;

    // если есть product_id — подтянем данные
    if ($pid>0){
        $g=$pdo->prepare("SELECT sku,name,unit FROM crm_products WHERE id=:id");
        $g->execute([':id'=>$pid]);
        if ($pr=$g->fetch()){ if($n==='') $n=$pr['name']; if($s==='') $s=$pr['sku']; if(!$u) $u=$pr['unit'] ?: 'шт'; }
    } else {
        // режим каталога без id: для 'in' разрешаем, при необходимости создадим товар
        if ($doc_type==='in'){
            $ins=$pdo->prepare("INSERT INTO crm_products (sku,name,unit,price,cost_price) VALUES (:sku,:name,:unit,:price,:price) RETURNING id");
            $ins->execute([':sku'=>$s?:null, ':name'=>$n, ':unit'=>$u?:'шт', ':price'=>$p]);
            $pid=(int)$ins->fetchColumn();
        } else {
            throw new RuntimeException('Выберите товар из списка: '.$n);
        }
    }

    $items[]=['product_id'=>$pid,'name'=>$n,'sku'=>$s,'unit'=>$u?:'шт','qty'=>$q,'price'=>$p,'line'=>round($q*$p,2)];
}
if(!$items){ flash('err','Добавьте хотя бы одну позицию.'); header('Location: '.($id?url('boards/inventory/movement_edit.php?id='.$id):url('boards/inventory/movement_new.php?type='.$doc_type))); exit; }

// Предпроверка остатков (out/transfer)
if ($doc_type==='out' || $doc_type==='transfer') {
    $srcWh = $doc_type==='transfer' ? $src_warehouse_id : $warehouse_id;
    foreach ($items as $it) {
        $s = $pdo->prepare("SELECT qty FROM crm_product_stock WHERE product_id=:p AND warehouse_id=:w");
        $s->execute([':p'=>$it['product_id'], ':w'=>$srcWh]);
        $avail = (float)($s->fetchColumn() ?: 0);
        if ($avail + 1e-9 < $it['qty']) {
            throw new RuntimeException('Недостаточно остатка: '.$it['name'].' — нужно '.$it['qty'].', доступно '.$avail);
        }
    }
}

$pdo->beginTransaction();
try{
    if ($id) {
        // запретим правку adjust
        $ot=$pdo->prepare("SELECT doc_type FROM crm_stock_moves WHERE id=:id"); $ot->execute([':id'=>$id]);
        if (($ot->fetchColumn() ?: '')==='adjust') { throw new RuntimeException('Корректировка не редактируется. Удалите и создайте заново.'); }

        stock_revert_move($pdo, $id);

        $pdo->prepare("UPDATE crm_stock_moves SET doc_type=:t, warehouse_id=:w, src_warehouse_id=:sw, dest_warehouse_id=:dw,
                    ttn_number=:tn, ttn_date=:td, carrier=:cr, notes=:n WHERE id=:id")
            ->execute([
                ':t'=>$doc_type, ':w'=>$warehouse_id?:null, ':sw'=>$src_warehouse_id?:null, ':dw'=>$dest_warehouse_id?:null,
                ':tn'=>$ttn_number?:null, ':td'=>$ttn_date, ':cr'=>$carrier?:null, ':n'=>$notes?:null, ':id'=>$id
            ]);

        $pdo->prepare("DELETE FROM crm_stock_move_items WHERE move_id=:id")->execute([':id'=>$id]);
        $insI=$pdo->prepare("INSERT INTO crm_stock_move_items (move_id, product_id, sku, name, unit, qty, unit_price, line_total)
                         VALUES (:m,:p,:sku,:name,:unit,:q,:pr,:lt)");
        foreach($items as $it){
            $insI->execute([
                ':m'=>$id, ':p'=>$it['product_id'], ':sku'=>$it['sku']?:null, ':name'=>$it['name'],
                ':unit'=>$it['unit']?:'шт', ':q'=>$it['qty'], ':pr'=>$it['price'], ':lt'=>$it['line']
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

        $insI=$pdo->prepare("INSERT INTO crm_stock_move_items (move_id, product_id, sku, name, unit, qty, unit_price, line_total)
                         VALUES (:m,:p,:sku,:name,:unit,:q,:pr,:lt)");
        foreach($items as $it){
            $insI->execute([
                ':m'=>$id, ':p'=>$it['product_id'], ':sku'=>$it['sku']?:null, ':name'=>$it['name'],
                ':unit'=>$it['unit']?:'шт', ':q'=>$it['qty'], ':pr'=>$it['price'], ':lt'=>$it['line']
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
