<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); $active='inventory';

$doc_type = ($_GET['type'] ?? '');
$wh = (int)($_GET['wh'] ?? 0);
$df = trim((string)($_GET['from'] ?? ''));
$dt = trim((string)($_GET['to'] ?? ''));

$where=['1=1']; $p=[];
if (in_array($doc_type,['in','out','transfer','adjust'],true)) { $where[]='m.doc_type=:t'; $p[':t']=$doc_type; }
if ($wh>0) { $where[]='(m.warehouse_id=:w OR m.src_warehouse_id=:w OR m.dest_warehouse_id=:w)'; $p[':w']=$wh; }
if ($df!==''){ $where[]='m.created_at >= :df::date'; $p[':df']=$df; }
if ($dt!==''){ $where[]='m.created_at < (:dt::date + interval \'1 day\')'; $p[':dt']=$dt; }
$whereSql = implode(' AND ', $where);

global $pdo;
$ware = $pdo->query("SELECT id,code,name FROM crm_warehouses ORDER BY name")->fetchAll();

$sql="SELECT m.*, COALESCE(w.name, ws.name || ' → ' || wd.name) as wh_title
  FROM crm_stock_moves m
  LEFT JOIN crm_warehouses w  ON w.id=m.warehouse_id
  LEFT JOIN crm_warehouses ws ON ws.id=m.src_warehouse_id
  LEFT JOIN crm_warehouses wd ON wd.id=m.dest_warehouse_id
  WHERE $whereSql
  ORDER BY m.created_at DESC
  LIMIT 100";
$st=$pdo->prepare($sql); $st->execute($p); $rows=$st->fetchAll();

require APP_ROOT.'/inc/app_header.php';
?>
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
                            <?php foreach(['in'=>'Приход','out'=>'Расход','transfer'=>'Перемещение','adjust'=>'Корректировка'] as $k=>$v): ?>
                                <option value="<?= $k ?>"<?= $doc_type===$k?' selected':'' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="wh">
                            <option value="0">Склад: любой</option>
                            <?php foreach($ware as $w): ?>
                                <option value="<?= (int)$w['id'] ?>"<?= $wh===$w['id']?' selected':'' ?>><?= htmlspecialchars($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="from" value="<?= htmlspecialchars($df) ?>">
                        <input type="date" name="to"   value="<?= htmlspecialchars($dt) ?>">
                        <button class="btn" type="submit">Фильтр</button>
                    </form>
                    <div class="actions">
                        <a class="btn primary" href="<?= url('boards/inventory/movement_new.php?type=in') ?>">+ Приход</a>
                        <a class="btn" href="<?= url('boards/inventory/movement_new.php?type=out') ?>">− Расход</a>
                        <a class="btn" href="<?= url('boards/inventory/movement_new.php?type=transfer') ?>">⇄ Перемещение</a>
                        <a class="btn" href="<?= url('boards/inventory/movement_new.php?type=adjust') ?>">⚙ Корректировка</a>
                        <a class="btn" href="<?= url('boards/warehouses/warehouses.php') ?>">Склады</a>
                    </div>
                </div>

                <table class="table">
                    <thead><tr><th>№</th><th>Тип</th><th>Склад</th><th>ТТН</th><th>Примечание</th><th>Дата</th><th></th></tr></thead>
                    <tbody>
                    <?php if(!$rows): ?><tr><td colspan="7" style="text-align:center;background:#fff;">Нет документов</td></tr>
                    <?php else: foreach($rows as $r): ?>
                        <tr>
                            <td>#<?= (int)$r['doc_no'] ?></td>
                            <td><?= ['in'=>'Приход','out'=>'Расход','transfer'=>'Перемещение','adjust'=>'Корректировка'][$r['doc_type']] ?></td>
                            <td><?= htmlspecialchars($r['wh_title'] ?? '') ?></td>
                            <td><?= htmlspecialchars(trim(($r['ttn_number']??'').' '.($r['ttn_date']??''))) ?></td>
                            <td><?= htmlspecialchars($r['notes'] ?? '') ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
                            <td style="text-align:right;">
                                <a class="btn" href="<?= url('boards/inventory/movement_edit.php?id='.(int)$r['id']) ?>">Открыть</a>
                                <form action="<?= url('boards/inventory/movement_delete.php') ?>" method="post" style="display:inline" onsubmit="return confirm('Удалить документ #<?= (int)$r['doc_no'] ?>? Это откатит остатки.');">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>

            </div>
        </section>
    </main>
</div>
<?php require APP_ROOT.'/inc/app_footer.php'; ?>
