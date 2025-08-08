<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); $active='inventory';

$id=(int)($_GET['id']??0); global $pdo;

$m=$pdo->prepare("SELECT * FROM crm_stock_moves WHERE id=:id"); $m->execute([':id'=>$id]);
$mv=$m->fetch(); if(!$mv){http_response_code(404);exit('Not found');}
$it=$pdo->prepare("SELECT * FROM crm_stock_move_items WHERE move_id=:id ORDER BY id"); $it->execute([':id'=>$id]); $items=$it->fetchAll();
$ware=$pdo->query("SELECT id,name FROM crm_warehouses ORDER BY name")->fetchAll();

$editable = $mv['doc_type']!=='adjust'; // корректировку не редактируем

require APP_ROOT.'/inc/app_header.php';
?>
<div class="app">
    <?php require APP_ROOT.'/inc/app_sidebar.php'; ?>
    <main class="main">
        <?php require APP_ROOT.'/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>

            <form class="card" method="post" action="<?= url('boards/inventory/movement_save.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$mv['id'] ?>">
                <input type="hidden" name="doc_type" value="<?= htmlspecialchars($mv['doc_type']) ?>">

                <div class="form">
                    <div class="row">
                        <?php if($mv['doc_type']==='transfer'): ?>
                            <div><label>Со склада</label>
                                <select name="src_warehouse_id" <?= $editable?'':'disabled' ?>>
                                    <?php foreach($ware as $w): $sel=$w['id']==$mv['src_warehouse_id']?' selected':''; ?>
                                        <option value="<?= (int)$w['id'] ?>"<?= $sel ?>><?= htmlspecialchars($w['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label>На склад</label>
                                <select name="dest_warehouse_id" <?= $editable?'':'disabled' ?>>
                                    <?php foreach($ware as $w): $sel=$w['id']==$mv['dest_warehouse_id']?' selected':''; ?>
                                        <option value="<?= (int)$w['id'] ?>"<?= $sel ?>><?= htmlspecialchars($w['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div><label>Склад</label>
                                <select name="warehouse_id" <?= $editable?'':'disabled' ?>>
                                    <?php foreach($ware as $w): $sel=$w['id']==$mv['warehouse_id']?' selected':''; ?>
                                        <option value="<?= (int)$w['id'] ?>"<?= $sel ?>><?= htmlspecialchars($w['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label>Статус</label><input disabled value="Проведен"></div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div><label>ТТН номер</label><input name="ttn_number" value="<?= htmlspecialchars($mv['ttn_number']??'') ?>" <?= $editable?'':'disabled' ?>></div>
                        <div><label>ТТН дата</label><input type="date" name="ttn_date" value="<?= htmlspecialchars($mv['ttn_date']??'') ?>" <?= $editable?'':'disabled' ?>></div>
                    </div>
                    <div class="row">
                        <div><label>Перевозчик</label><input name="carrier" value="<?= htmlspecialchars($mv['carrier']??'') ?>" <?= $editable?'':'disabled' ?>></div>
                        <div><label>Примечание</label><input name="notes" value="<?= htmlspecialchars($mv['notes']??'') ?>" <?= $editable?'':'disabled' ?>></div>
                    </div>
                </div>

                <?php $isPick = in_array($mv['doc_type'],['out','transfer'], true); ?>
                <div class="card" style="margin-top:12px;">
                    <h3 style="margin:0 0 12px;">Позиции</h3>
                    <table
                            class="items-grid"
                            id="mvItems"
                            data-mode="<?= $isPick ? 'pick' : 'free' ?>"
                            data-api-instock="<?= url('boards/api/instock.php') ?>"
                            data-doc-type="<?= htmlspecialchars($mv['doc_type']) ?>"
                    >
                        <thead>
                        <tr>
                            <?php if ($isPick): ?>
                                <th>Товар (из наличия)</th>
                            <?php else: ?>
                                <th>SKU</th><th>Наименование</th>
                            <?php endif; ?>
                            <th>Кол-во</th>
                            <th>Цена</th>
                            <th>Сумма</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($isPick): foreach($items as $r): ?>
                            <tr>
                                <td>
                                    <select class="prodsel" name="item_product_id[]" data-selected="<?= (int)$r['product_id'] ?>" <?= $editable?'':'disabled' ?>></select>
                                    <input type="hidden" name="item_name[]" value="<?= htmlspecialchars($r['name']) ?>">
                                    <input type="hidden" name="item_sku[]"  value="<?= htmlspecialchars($r['sku'] ?? '') ?>">
                                    <div class="muted" style="font-size:12px;color:#6b778c;margin-top:6px;">Доступно: <b class="avail">—</b></div>
                                </td>
                                <td style="width:120px;"><input name="item_qty[]" type="number" step="0.001" min="0" value="<?= number_format((float)$r['qty'],3,'.','') ?>" <?= $editable?'':'readonly' ?>></td>
                                <td style="width:140px;"><input name="item_price[]" type="number" step="0.01" min="0" value="<?= number_format((float)$r['unit_price'],2,'.','') ?>" <?= $editable?'':'readonly' ?>></td>
                                <td style="width:140px;" class="line-total"><?= number_format((float)$r['line_total'],2,'.','') ?></td>
                                <td style="width:60px;"><?= $editable?'<a href="#" class="btn" data-remove>×</a>':'' ?></td>
                            </tr>
                        <?php endforeach; else: foreach($items as $r): ?>
                            <tr>
                                <td style="width:140px;"><input name="item_sku[]" value="<?= htmlspecialchars($r['sku'] ?? '') ?>" <?= $editable?'':'readonly' ?>></td>
                                <td><input name="item_name[]" value="<?= htmlspecialchars($r['name']) ?>" required <?= $editable?'':'readonly' ?>></td>
                                <td style="width:120px;"><input name="item_qty[]" type="number" step="0.001" min="0" value="<?= number_format((float)$r['qty'],3,'.','') ?>" required <?= $editable?'':'readonly' ?>></td>
                                <td style="width:140px;"><input name="item_price[]" type="number" step="0.01" min="0" value="<?= number_format((float)$r['unit_price'],2,'.','') ?>" <?= $editable?'':'readonly' ?>></td>
                                <td style="width:140px;" class="line-total"><?= number_format((float)$r['line_total'],2,'.','') ?></td>
                                <td style="width:60px;"><?= $editable?'<a href="#" class="btn" data-remove>×</a>':'' ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    <?php if($editable): ?><div class="items-actions"><a class="btn" href="#" id="addItem">+ Добавить</a></div><?php endif; ?>
                    <div style="margin-top:8px;color:#6b778c;">Итого: <b><span id="docTotal">0.00</span></b></div>
                </div>

                <div class="actions" style="margin-top:12px;">
                    <?php if($editable): ?><button class="btn primary" type="submit">Сохранить</button><?php endif; ?>
                    <a class="btn" href="<?= url('boards/inventory/movements.php') ?>">Назад</a>
                </div>
            </form>

        </section>
    </main>
</div>
<script src="<?= asset('js/movement.js') ?>"></script>
<?php require APP_ROOT.'/inc/app_footer.php'; ?>
