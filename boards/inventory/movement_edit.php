<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); $active='inventory';

$id=(int)($_GET['id']??0); global $pdo;
$m=$pdo->prepare("SELECT * FROM crm_stock_moves WHERE id=:id"); $m->execute([':id'=>$id]); $mv=$m->fetch();
if(!$mv){http_response_code(404);exit('Not found');}

$it=$pdo->prepare("SELECT * FROM crm_stock_move_items WHERE move_id=:id ORDER BY id");
$it->execute([':id'=>$id]); $items=$it->fetchAll();

$ware=$pdo->query("SELECT id,name FROM crm_warehouses ORDER BY name")->fetchAll();
$editable = $mv['doc_type']!=='adjust'; // корректировку не редактируем
$UNIT_OPTIONS = ['шт','упак','кг','г','л','м','см','м2','м3','час','компл'];
$isPickFromStock = in_array($mv['doc_type'],['out','transfer'], true);

require APP_ROOT.'/inc/app_header.php'; ?>
<div class="app"><?php require APP_ROOT.'/inc/app_sidebar.php'; ?><main class="main"><?php require APP_ROOT.'/inc/app_topbar.php'; ?>
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
                                        <option value="<?= (int)$w['id'] ?>"<?= $sel ?>><?= htmlspecialchars($w['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div><label>На склад</label>
                                <select name="dest_warehouse_id" <?= $editable?'':'disabled' ?>>
                                    <?php foreach($ware as $w): $sel=$w['id']==$mv['dest_warehouse_id']?' selected':''; ?>
                                        <option value="<?= (int)$w['id'] ?>"<?= $sel ?>><?= htmlspecialchars($w['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div><label>Склад</label>
                                <select name="warehouse_id" <?= $editable?'':'disabled' ?>>
                                    <?php foreach($ware as $w): $sel=$w['id']==$mv['warehouse_id']?' selected':''; ?>
                                        <option value="<?= (int)$w['id'] ?>"<?= $sel ?>><?= htmlspecialchars($w['name']) ?></option><?php endforeach; ?>
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

                <div class="card" style="margin-top:12px;">
                    <h3 style="margin:0 0 12px;">Позиции</h3>
                    <table class="items-grid" id="mvItems"
                           data-mode="<?= $isPickFromStock ? 'pick' : 'free' ?>"
                           data-api-catalog="<?= htmlspecialchars(url('boards/api/products.php')) ?>"
                           data-api-instock="<?= htmlspecialchars(url('boards/api/instock.php')) ?>"
                           data-units='<?= json_encode($UNIT_OPTIONS, JSON_UNESCAPED_UNICODE) ?>'>
                        <thead>
                        <tr>
                            <th><?= $isPickFromStock ? 'Товар (из наличия)' : 'Товар (из каталога)' ?></th>
                            <th>Ед.</th>
                            <th>Кол-во</th>
                            <th>Цена</th>
                            <th>Сумма</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach($items as $it): ?>
                            <tr>
                                <td class="product-picker">
                                    <input class="product-search" placeholder="Поиск товара" autocomplete="off"
                                           value="<?= htmlspecialchars(($it['sku'] ? $it['sku'].' — ' : '') . $it['name']) ?>" <?= $editable?'':'readonly' ?>>
                                    <input type="hidden" name="item_product_id[]" value="<?= (int)$it['product_id'] ?>">
                                    <input type="hidden" name="item_name[]" value="<?= htmlspecialchars($it['name']) ?>">
                                    <input type="hidden" name="item_sku[]"  value="<?= htmlspecialchars($it['sku'] ?? '') ?>">
                                    <div class="picker-list" hidden></div>
                                    <?php if($isPickFromStock): ?>
                                        <div class="muted" style="font-size:12px;color:#6b778c;margin-top:6px;">Доступно: <b class="avail">—</b></div>
                                    <?php else: ?>
                                        <div class="muted" style="font-size:12px;color:#6b778c;margin-top:6px;">SKU: <b class="sku-out"><?= htmlspecialchars($it['sku'] ?? '—') ?></b></div>
                                    <?php endif; ?>
                                </td>
                                <td style="width:130px;">
                                    <select name="item_unit[]" <?= $editable?'':'disabled' ?>>
                                        <?php $curUnit = $it['unit'] ?? 'шт';
                                        foreach ($UNIT_OPTIONS as $u) {
                                            $sel = ($u===$curUnit)?' selected':'';
                                            echo '<option value="'.htmlspecialchars($u).'"'.$sel.'>'.htmlspecialchars($u).'</option>';
                                        } ?>
                                    </select>
                                </td>
                                <td style="width:120px;"><input name="qty[]" type="number" step="0.001" min="0" value="<?= htmlspecialchars((float)$it['qty']) ?>" required <?= $editable?'':'readonly' ?>></td>
                                <td style="width:140px;"><input name="price[]" type="number" step="0.01" min="0" value="<?= htmlspecialchars(number_format((float)$it['unit_price'],2,'.','')) ?>" <?= $editable?'':'readonly' ?>></td>
                                <td style="width:140px;" class="line-total"><?= number_format((float)$it['line_total'],2,'.','') ?></td>
                                <td style="width:60px;"><?= $editable?'<a href="#" class="btn" data-remove>×</a>':'' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if($editable): ?><div class="items-actions"><a class="btn" href="#" id="addItem">+ Добавить</a></div><?php endif; ?>
                    <div style="margin-top:8px;color:#6b778c;">Итого: <b><span id="docTotal">0.00</span></b></div>
                </div>

                <div class="actions" style="margin-top:12px;">
                    <?php if($editable): ?><button class="btn primary" type="submit">Сохранить</button><?php endif; ?>
                    <a class="btn" href="<?= url('boards/inventory/movements.php') ?>">Назад</a>
                    <a class="btn print" target="_blank" href="<?= url('boards/inventory/print_move.php?id='.(int)$mv['id']) ?>">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6v-7Z" stroke="currentColor" stroke-width="1.6"/></svg>
                        Печать
                    </a>

                </div>
            </form>
        </section></main></div>
<script src="<?= asset('js/movement-picker.js') ?>"></script>
<script src="<?= asset('js/movement.js') ?>"></script>
<?php require APP_ROOT.'/inc/app_footer.php'; ?>
