<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); $active='products';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit('Not found'); }

global $pdo;
$p = $pdo->prepare("SELECT * FROM crm_products WHERE id=:id");
$p->execute([':id'=>$id]); $prod = $p->fetch();
if (!$prod) { http_response_code(404); exit('Not found'); }

$ware = $pdo->query("SELECT id, name FROM crm_warehouses ORDER BY name")->fetchAll();
$wh = (int)($_GET['wh'] ?? 0);

$sql = "SELECT m.id, m.doc_no, m.doc_type, m.created_at, m.order_id,
               COALESCE(w.name, ws.name || ' → ' || wd.name) as wh_title,
               i.qty
        FROM crm_stock_move_items i
        JOIN crm_stock_moves m ON m.id=i.move_id
        LEFT JOIN crm_warehouses w  ON w.id=m.warehouse_id
        LEFT JOIN crm_warehouses ws ON ws.id=m.src_warehouse_id
        LEFT JOIN crm_warehouses wd ON wd.id=m.dest_warehouse_id
        WHERE i.product_id=:pid".
        ($wh>0 ? " AND (m.warehouse_id=:wh OR m.src_warehouse_id=:wh OR m.dest_warehouse_id=:wh)" : "").
        " ORDER BY m.created_at ASC";
$st = $pdo->prepare($sql);
$params = [':pid'=>$id];
if ($wh>0) $params[':wh']=$wh;
$st->execute($params);
$rows = $st->fetchAll();

$s = $pdo->prepare("SELECT w.name, s.qty FROM crm_product_stock s JOIN crm_warehouses w ON w.id=s.warehouse_id WHERE s.product_id=:p ORDER BY w.name");
$s->execute([':p'=>$id]); $stock = $s->fetchAll();

require APP_ROOT.'/inc/app_header.php';
?>
<div class="app">
    <?php require APP_ROOT.'/inc/app_sidebar.php'; ?>
    <main class="main">
        <?php require APP_ROOT.'/inc/app_topbar.php'; ?>
        <section class="content">
            <div class="card">
                <h3 style="margin:0 0 10px;">История движения — <?= htmlspecialchars($prod['sku'].' — '.$prod['name']) ?></h3>
                <div class="toolbar">
                    <div class="filters">
                        <form method="get">
                            <input type="hidden" name="id" value="<?= (int)$id ?>">
                            <select name="wh" onchange="this.form.submit()">
                                <option value="0">Все склады</option>
                                <?php foreach($ware as $w): $sel=$wh===$w['id']?' selected':''; ?>
                                    <option value="<?= (int)$w['id'] ?>"<?= $sel ?>><?= htmlspecialchars($w['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="actions">
                        <a class="btn" href="<?= url('boards/products/products.php') ?>">← К товарам</a>
                    </div>
                </div>

                <table class="table">
                    <thead>
                    <tr><th>Дата</th><th>Документ</th><th>Склад</th><th>Изм.</th><th>Баланс*</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $balance = 0.0;
                    foreach ($rows as $r):
                        $delta = (float)$r['qty'];
                        if ($r['doc_type'] === 'out') $delta = -abs($delta);
                        $balance = round($balance + $delta, 3);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
                            <td>#<?= (int)$r['doc_no'] ?> (<?= ['in'=>'Приход','out'=>'Расход','transfer'=>'Перемещение','adjust'=>'Корректировка'][$r['doc_type']] ?>)
                                <?php if ($r['order_id']): ?>
                                    <span class="muted" style="color:#6b778c"> / заказ #<?= (int)$r['order_id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($r['wh_title'] ?? '') ?></td>
                            <td><?= ($delta>=0?'+':'').number_format($delta,3,'.',' ') ?></td>
                            <td><?= number_format($balance,3,'.',' ') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="card" style="margin-top:12px;">
                    <h3 style="margin:0 0 8px;">Текущие остатки по складам</h3>
                    <table class="table">
                        <thead><tr><th>Склад</th><th>Остаток</th></tr></thead>
                        <tbody>
                        <?php if(!$stock): ?><tr><td colspan="2" style="background:#fff;text-align:center;">Нет остатков</td></tr>
                        <?php else: foreach($stock as $s): ?>
                            <tr><td><?= htmlspecialchars($s['name']) ?></td><td><?= number_format((float)$s['qty'],3,'.',' ') ?></td></tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    <div class="muted" style="color:#6b778c;margin-top:8px;">* Баланс в таблице — последовательный суммарный итог по выбранной выборке, для наглядности.</div>
                </div>

            </div>
        </section>
    </main>
</div>
<?php require APP_ROOT.'/inc/app_footer.php'; ?>
