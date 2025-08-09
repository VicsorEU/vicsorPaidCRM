<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); $active='inventory';
global $pdo;

$type   = trim((string)($_GET['type'] ?? '')); // in|out|transfer|adjust
$q      = trim((string)($_GET['q'] ?? ''));    // поиск по SKU/названию/примечанию/№ заказа
$wh     = (int)($_GET['warehouse_id'] ?? 0);
$from   = trim((string)($_GET['from'] ?? ''));
$to     = trim((string)($_GET['to'] ?? ''));
$page   = max(1,(int)($_GET['page'] ?? 1));
$per    = 25; $off = ($page-1)*$per;

$ware = $pdo->query("SELECT id,name FROM crm_warehouses ORDER BY name")->fetchAll();

$w = ["1=1"];
$p = [];
if ($type && in_array($type,['in','out','transfer','adjust'],true)) { $w[]="m.doc_type=:t"; $p[':t']=$type; }
if ($wh>0) { $w[]="(m.warehouse_id=:w OR m.src_warehouse_id=:w OR m.dest_warehouse_id=:w)"; $p[':w']=$wh; }
if ($from && preg_match('~^\d{4}-\d{2}-\d{2}$~',$from)) { $w[]="m.created_at >= :f::date"; $p[':f']=$from; }
if ($to && preg_match('~^\d{4}-\d{2}-\d{2}$~',$to))     { $w[]="m.created_at < (:t::date + INTERVAL '1 day')"; $p[':t']=$to; }
if ($q!=='') {
    $w[]="(i.sku ILIKE :q OR i.name ILIKE :q OR m.notes ILIKE :q OR CAST(m.order_id AS TEXT) ILIKE :q)";
    $p[':q'] = '%'.$q.'%';
}
$ws = implode(' AND ',$w);

$cnt = $pdo->prepare("SELECT COUNT(DISTINCT m.id)
                      FROM crm_stock_moves m
                      LEFT JOIN crm_stock_move_items i ON i.move_id=m.id
                      WHERE $ws");
foreach($p as $k=>$v) $cnt->bindValue($k,$v);
$cnt->execute(); $total=(int)$cnt->fetchColumn();

$sql="SELECT m.*, 
             COALESCE(w.name,'') AS wh,
             ws.name AS whs, wd.name AS whd,
             (SELECT SUM(line_total) FROM crm_stock_move_items WHERE move_id=m.id) AS amount
      FROM crm_stock_moves m
      LEFT JOIN crm_warehouses w  ON w.id=m.warehouse_id
      LEFT JOIN crm_warehouses ws ON ws.id=m.src_warehouse_id
      LEFT JOIN crm_warehouses wd ON wd.id=m.dest_warehouse_id
      LEFT JOIN crm_stock_move_items i ON i.move_id=m.id
      WHERE $ws
      GROUP BY m.id, w.name, ws.name, wd.name
      ORDER BY m.created_at DESC
      LIMIT :lim OFFSET :off";
$st=$pdo->prepare($sql);
foreach($p as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':lim',$per,PDO::PARAM_INT);
$st->bindValue(':off',$off,PDO::PARAM_INT);
$st->execute(); $rows=$st->fetchAll();

$T=['in'=>'Возврат/Приход','out'=>'Списание','transfer'=>'Перемещение','adjust'=>'Корректировка'];

require APP_ROOT.'/inc/app_header.php'; ?>
<div class="app">
    <?php require APP_ROOT.'/inc/app_sidebar.php'; ?>
    <main class="main">
        <?php require APP_ROOT.'/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>

            <div class="card">
                <div class="toolbar">
                    <form class="filters" method="get">
                        <select name="type">
                            <option value="">Тип: любой</option>
                            <?php foreach(['in','out','transfer','adjust'] as $t): ?>
                                <option value="<?= $t ?>"<?= $type===$t?' selected':'' ?>><?= $T[$t] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="warehouse_id">
                            <option value="0">Склад: любой</option>
                            <?php foreach($ware as $w): ?>
                                <option value="<?= (int)$w['id'] ?>"<?= $wh===$w['id']?' selected':'' ?>><?= htmlspecialchars($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
                        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
                        <input type="text" name="q" placeholder="Поиск: SKU / товар / примечание / #заказ" value="<?= htmlspecialchars($q) ?>">
                        <button class="btn" type="submit">Фильтр</button>
                    </form>
                    <div class="actions">
                        <a class="btn primary" href="<?= url('boards/inventory/movement_new.php?type=in') ?>">+ Приход</a>
                        <a class="btn" href="<?= url('boards/inventory/movement_new.php?type=out') ?>">Списание</a>
                        <a class="btn" href="<?= url('boards/inventory/movement_new.php?type=transfer') ?>">Перемещение</a>
                        <a class="btn" href="<?= url('boards/inventory/movement_new.php?type=adjust') ?>">Корректировка</a>
                    </div>
                </div>

                <table class="table">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Тип</th>
                        <th>Склад(ы)</th>
                        <th>Сумма</th>
                        <th>Дата</th>
                        <th>Связь</th>
                        <th>Примечание</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if(!$rows): ?>
                        <tr><td colspan="8" style="text-align:center;background:#fff;">Документов нет</td></tr>
                    <?php else: foreach($rows as $r): ?>
                        <tr>
                            <td>#<?= (int)$r['id'] ?></td>
                            <td>
                                <span class="badge <?= htmlspecialchars($r['doc_type']) ?>"><?= $T[$r['doc_type']] ?? $r['doc_type'] ?></span>
                                <?php if ($r['order_id']): ?><span class="badge order">по заказу</span><?php endif; ?>
                                <?php if ($r['doc_type']==='in' && stripos((string)$r['notes'],'отмен')!==false): ?>
                                    <span class="badge cancel">отмена/возврат</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($r['doc_type']==='transfer'): ?>
                                    <?= htmlspecialchars($r['whs'] ?? '') ?> → <?= htmlspecialchars($r['whd'] ?? '') ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($r['wh'] ?? '') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format((float)$r['amount'],2,'.',' ') ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
                            <td>
                                <?php if ($r['order_id']): ?>
                                    <a class="btn" href="<?= url('boards/order/order_edit.php?id='.(int)$r['order_id']) ?>">Заказ #<?= (int)$r['order_id'] ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($r['notes'] ?? '') ?></td>
                            <td style="text-align:right;">
                                <a class="btn" href="<?= url('boards/inventory/movement_edit.php?id='.(int)$r['id']) ?>">Открыть</a>
                                <a class="btn print" target="_blank" href="<?= url('boards/inventory/print_move.php?id='.(int)$r['id']) ?>" title="Печать">
                                    <svg viewBox="0 0 24 24" fill="none"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6v-7Z" stroke="currentColor" stroke-width="1.6"/></svg>
                                    Печать
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?php
                $base = url('boards/inventory/movements.php').'?' . http_build_query(array_filter(['type'=>$type,'warehouse_id'=>$wh,'from'=>$from,'to'=>$to,'q'=>$q]));
                echo paginate($total, $page, $per, $base);
                ?>
            </div>
        </section>
    </main>
</div>
<?php require APP_ROOT.'/inc/app_footer.php'; ?>
