<?php
// inc/inventory.php
require_once __DIR__ . '/util.php';

/** Применяет движение к остаткам. Вызывать в транзакции. */
function stock_apply_move(PDO $pdo, int $move_id): void {
    $mv = fetch_move($pdo, $move_id);
    $items = fetch_move_items($pdo, $move_id);

    if ($mv['doc_type'] === 'transfer') {
        if (!$mv['src_warehouse_id'] || !$mv['dest_warehouse_id']) {
            throw new RuntimeException('Не указаны склады для перемещения');
        }
        foreach ($items as $it) {
            stock_change($pdo, (int)$it['product_id'], (int)$mv['src_warehouse_id'], -$it['qty']);
            stock_change($pdo, (int)$it['product_id'], (int)$mv['dest_warehouse_id'], +$it['qty']);
        }
    } else {
        $wh = (int)$mv['warehouse_id'];
        if (!$wh) throw new RuntimeException('Не указан склад');
        foreach ($items as $it) {
            $qty = (float)$it['qty'];
            if ($mv['doc_type'] === 'in')      stock_change($pdo, (int)$it['product_id'], $wh, +$qty);
            elseif ($mv['doc_type'] === 'out') stock_change($pdo, (int)$it['product_id'], $wh, -$qty);
            elseif ($mv['doc_type'] === 'adjust') stock_set($pdo, (int)$it['product_id'], $wh, $qty);
        }
    }
}

/** Откатывает влияние движения. Вызывать в транзакции. */
function stock_revert_move(PDO $pdo, int $move_id): void {
    $mv = fetch_move($pdo, $move_id);
    $items = fetch_move_items($pdo, $move_id);

    if ($mv['doc_type'] === 'transfer') {
        foreach ($items as $it) {
            stock_change($pdo, (int)$it['product_id'], (int)$mv['src_warehouse_id'], +$it['qty']);
            stock_change($pdo, (int)$it['product_id'], (int)$mv['dest_warehouse_id'], -$it['qty']);
        }
    } else {
        $wh = (int)$mv['warehouse_id'];
        foreach ($items as $it) {
            $qty = (float)$it['qty'];
            if ($mv['doc_type'] === 'in')      stock_change($pdo, (int)$it['product_id'], $wh, -$qty);
            elseif ($mv['doc_type'] === 'out') stock_change($pdo, (int)$it['product_id'], $wh, +$qty);
            elseif ($mv['doc_type'] === 'adjust') { /* корректировку не редактируем — удаляем документ целиком при необходимости */ }
        }
    }
}

/** helpers */
function fetch_move(PDO $pdo, int $id): array {
    $s = $pdo->prepare("SELECT * FROM crm_stock_moves WHERE id=:id");
    $s->execute([':id'=>$id]);
    $mv = $s->fetch();
    if (!$mv) throw new RuntimeException('Документ не найден');
    return $mv;
}
function fetch_move_items(PDO $pdo, int $id): array {
    $s = $pdo->prepare("SELECT product_id, qty FROM crm_stock_move_items WHERE move_id=:id");
    $s->execute([':id'=>$id]);
    return $s->fetchAll() ?: [];
}
function stock_change(PDO $pdo, int $product_id, int $warehouse_id, float $delta): void {
    $sql = "INSERT INTO crm_product_stock (product_id, warehouse_id, qty)
            VALUES (:p, :w, :q)
            ON CONFLICT (product_id, warehouse_id)
            DO UPDATE SET qty = crm_product_stock.qty + EXCLUDED.qty";
    $pdo->prepare($sql)->execute([':p'=>$product_id, ':w'=>$warehouse_id, ':q'=>$delta]);
}
function stock_set(PDO $pdo, int $product_id, int $warehouse_id, float $qty): void {
    $sql = "INSERT INTO crm_product_stock (product_id, warehouse_id, qty)
            VALUES (:p,:w,:q)
            ON CONFLICT (product_id, warehouse_id)
            DO UPDATE SET qty = EXCLUDED.qty";
    $pdo->prepare($sql)->execute([':p'=>$product_id, ':w'=>$warehouse_id, ':q'=>$qty]);
}
