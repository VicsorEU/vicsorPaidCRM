<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf();
global $pdo;

function pick_col(PDO $pdo, string $table, array $cands): ?string {
    $st=$pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema=current_schema() AND table_name=:t");
    $st->execute([':t'=>strtolower($table)]);
    $cols = array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC),'column_name'));
    foreach ($cands as $c) if (in_array(strtolower($c),$cols,true)) return $c;
    return null;
}

$tbl='crm_products';
$nameCol  = pick_col($pdo,$tbl,['name','title','product_name']);
$skuCol   = pick_col($pdo,$tbl,['sku','article','code']);
$priceCol = pick_col($pdo,$tbl,['price','base_price','cost']);

$id = (int)($_POST['id'] ?? 0);

$fields = [
    'short_description' => trim((string)($_POST['short_description'] ?? '')),
    'description'       => trim((string)($_POST['description'] ?? '')),
];
if ($nameCol)  $fields[$nameCol]  = trim((string)($_POST['name'] ?? ''));
if ($skuCol)   $fields[$skuCol]   = trim((string)($_POST['sku'] ?? ''));
if ($priceCol) $fields[$priceCol] = ($_POST['price'] === '' ? null : (float)$_POST['price']);

try {
    $pdo->beginTransaction();

    if ($id>0) {
        $sets=[]; $params=[':id'=>$id];
        foreach ($fields as $k=>$v){ $sets[]="$k=:$k"; $params[":$k"]=$v; }
        $sql="UPDATE $tbl SET ".implode(', ',$sets).", updated_at=now() WHERE id=:id";
        $st=$pdo->prepare($sql); $st->execute($params);
    } else {
        // создаём
        $cols=[]; $vals=[]; $params=[];
        foreach ($fields as $k=>$v){ $cols[]=$k; $vals[]=":$k"; $params[":$k"]=$v; }
        if (!$cols) { $cols=['short_description']; $vals=['NULL']; } // fallback
        $sql="INSERT INTO $tbl (".implode(',',$cols).") VALUES (".implode(',',$vals).") RETURNING id";
        $st=$pdo->prepare($sql); $st->execute($params); $id=(int)$st->fetchColumn();
    }

    // категории
    $cat_ids = array_map('intval', $_POST['cat_ids'] ?? []);
    $pdo->prepare("DELETE FROM crm_product_category_map WHERE product_id=:p")->execute([':p'=>$id]);
    if ($cat_ids) {
        $ins=$pdo->prepare("INSERT INTO crm_product_category_map(product_id,category_id) VALUES (:p,:c)");
        foreach ($cat_ids as $cid) $ins->execute([':p'=>$id,':c'=>$cid]);
    }

    $pdo->commit();
    flash('ok','Товар сохранён');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('err','Ошибка: '.$e->getMessage());
}
header('Location: '.url('boards/products/product_edit.php?id='.$id)); exit;
