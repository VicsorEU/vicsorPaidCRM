<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
$active = 'products';

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 20;
$off  = ($page-1)*$per;

$params = [];
$where  = '1=1';
if ($q !== '') { $where = "(sku ILIKE :q OR name ILIKE :q OR barcode ILIKE :q)"; $params[':q'] = '%'.$q.'%'; }

global $pdo;
$cnt = $pdo->prepare("SELECT COUNT(*) FROM crm_products WHERE $where");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

$sql = "SELECT p.*,
   COALESCE((SELECT SUM(qty) FROM crm_product_stock s WHERE s.product_id=p.id),0) AS total_qty
  FROM crm_products p
  WHERE $where
  ORDER BY created_at DESC
  LIMIT :lim OFFSET :off";
$st = $pdo->prepare($sql);
foreach($params as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':lim',$per,PDO::PARAM_INT);
$st->bindValue(':off',$off,PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

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
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="SKU / Название / Штрихкод">
                        <button class="btn" type="submit">Найти</button>
                    </form>
                    <div class="actions">
                        <a class="btn primary" href="<?= url('boards/products/product_new.php') ?>">+ Новый товар</a>
                        <a class="btn" href="<?= url('boards/warehouses/warehouses.php') ?>">Склады</a>
                    </div>
                </div>

                <table class="table">
                    <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Название</th>
                        <th>Штрихкод</th>
                        <th>Ед.</th>
                        <th>Цена</th>
                        <th>Себестоимость</th>
                        <th>Остаток</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8" style="text-align:center;background:#fff;">Нет данных</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['sku']) ?></td>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td><?= htmlspecialchars($r['barcode'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['unit']) ?></td>
                            <td><?= number_format((float)$r['price'],2,'.',' ') ?></td>
                            <td><?= number_format((float)$r['cost_price'],2,'.',' ') ?></td>
                            <td><?= number_format((float)$r['total_qty'],3,'.',' ') ?></td>
                            <td style="text-align:right;">
                                <a class="btn" href="<?= url('boards/products/product_edit.php?id='.(int)$r['id']) ?>">Редактировать</a>
                                <a class="btn" href="<?= url('boards/products/product_history.php?id='.(int)$r['id']) ?>">История</a>
                                <form action="<?= url('boards/products/product_delete.php') ?>" method="post" style="display:inline" onsubmit="return confirm('Удалить товар?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn" type="submit">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?= paginate($total,$page,$per, url('boards/products/products.php').($q!==''?('?q='.urlencode($q)):'') ) ?>
            </div>
        </section>
    </main>
</div>
<?php require APP_ROOT.'/inc/app_footer.php'; ?>
