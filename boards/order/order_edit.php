<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
$active = 'orders';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit('Not found'); }

global $pdo;
$ord = $pdo->prepare("SELECT * FROM crm_orders WHERE id=:id");
$ord->execute([':id'=>$id]);
$order = $ord->fetch();
if (!$order) { http_response_code(404); exit('Not found'); }

$it = $pdo->prepare("SELECT * FROM crm_order_items WHERE order_id=:id ORDER BY id");
$it->execute([':id'=>$id]);
$items = $it->fetchAll();

$clients = $pdo->query("SELECT id, full_name, email FROM crm_customers ORDER BY created_at DESC LIMIT 100")->fetchAll();
$ware    = $pdo->query("SELECT id, name FROM crm_warehouses ORDER BY name")->fetchAll();

$statuses = ['new'=>'Новый','pending'=>'Ожидает','paid'=>'Оплачен','shipped'=>'Отправлен','canceled'=>'Отменён','refunded'=>'Возврат'];
$UNIT_OPTIONS = ['шт','упак','кг','г','л','м','см','м2','м3','час','компл'];

require APP_ROOT . '/inc/app_header.php';
?>
<div class="app">
    <?php require APP_ROOT . '/inc/app_sidebar.php'; ?>
    <main class="main">
        <?php require APP_ROOT . '/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>

            <form class="card" method="post" action="<?= url('boards/order/order_save.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">

                <div class="form">
                    <div class="row">
                        <div>
                            <label>Клиент</label>
                            <select name="customer_id" required>
                                <option value="">— выберите клиента —</option>
                                <?php foreach ($clients as $c): $sel = ($c['id']==$order['customer_id'])?' selected':''; ?>
                                    <option value="<?= (int)$c['id'] ?>"<?= $sel ?>><?= htmlspecialchars($c['full_name'].' — '.$c['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Статус</label>
                            <select name="status">
                                <?php foreach ($statuses as $k=>$v): $sel = $order['status']===$k?' selected':''; ?>
                                    <option value="<?= $k ?>"<?= $sel ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label>Склад заказа</label>
                            <select name="warehouse_id" required>
                                <option value="">— выберите —</option>
                                <?php foreach ($ware as $w): $sel = ((int)$order['warehouse_id']===(int)$w['id'])?' selected':''; ?>
                                    <option value="<?= (int)$w['id'] ?>"<?= $sel ?>><?= htmlspecialchars($w['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Валюта</label>
                            <select name="currency">
                                <?php foreach (['UAH','USD','EUR'] as $cur): $sel = $order['currency']===$cur?' selected':''; ?>
                                    <option value="<?= $cur ?>"<?= $sel ?>><?= $cur ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label>Источник</label>
                            <input type="text" name="source" value="<?= htmlspecialchars($order['source'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Примечание</label>
                            <input type="text" name="notes" value="<?= htmlspecialchars($order['notes'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top:12px;">
                    <h3 style="margin:0 0 12px;">Позиции</h3>

                    <table class="items-grid" id="itemsTable"
                           data-api="<?= htmlspecialchars(url('boards/api/products.php')) ?>"
                           data-units='<?= json_encode($UNIT_OPTIONS, JSON_UNESCAPED_UNICODE) ?>'>
                        <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Ед.</th>
                            <th>Кол-во</th>
                            <th>Цена</th>
                            <th>Сумма</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td class="product-picker">
                                    <input class="product-search" placeholder="Поиск товара (SKU/название)" autocomplete="off"
                                           value="<?= htmlspecialchars(($it['sku'] ? $it['sku'].' — ' : '') . $it['product_name']) ?>">
                                    <input type="hidden" name="item_product_id[]" value="<?= (int)($it['product_id'] ?? 0) ?>">
                                    <input type="hidden" name="item_name[]" value="<?= htmlspecialchars($it['product_name']) ?>">
                                    <input type="hidden" name="item_sku[]"  value="<?= htmlspecialchars($it['sku'] ?? '') ?>">
                                    <div class="picker-list" hidden></div>
                                    <div class="muted" style="font-size:12px;color:#6b778c;margin-top:6px;">SKU: <b class="sku-out"><?= htmlspecialchars($it['sku'] ?? '—') ?></b></div>
                                </td>
                                <td style="width:130px;">
                                    <select name="item_unit[]">
                                        <?php
                                        $curUnit = $it['unit'] ?? 'шт';
                                        foreach ($UNIT_OPTIONS as $u) {
                                            $sel = ($u === $curUnit) ? ' selected' : '';
                                            echo '<option value="'.htmlspecialchars($u).'"'.$sel.'>'.htmlspecialchars($u).'</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td style="width:120px;"><input name="item_qty[]" type="number" step="0.001" min="0" value="<?= htmlspecialchars((float)$it['quantity']) ?>" required></td>
                                <td style="width:140px;"><input name="item_price[]" type="number" step="0.01" min="0" value="<?= htmlspecialchars(number_format((float)$it['unit_price'],2,'.','')) ?>"></td>
                                <td style="width:140px;" class="line-total"><?= number_format((float)$it['total'],2,'.','') ?></td>
                                <td style="width:60px;"><a href="#" data-remove class="btn">×</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="items-actions">
                        <a href="#" class="btn" id="addItem">+ Добавить позицию</a>
                    </div>
                </div>

                <div class="totals">
                    <div class="box">
                        <label>Комментарий к заказу</label>
                        <textarea name="notes" rows="4"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="box">
                        <div class="grid">
                            <div>
                                <label>Сумма товаров</label>
                                <input id="total_items" name="total_items" type="number" step="0.01" readonly value="<?= number_format((float)$order['total_items'],2,'.','') ?>">
                            </div>
                            <div>
                                <label>Доставка</label>
                                <input id="total_shipping" name="total_shipping" type="number" step="0.01" value="<?= number_format((float)$order['total_shipping'],2,'.','') ?>">
                            </div>
                            <div>
                                <label>Скидка</label>
                                <input id="total_discount" name="total_discount" type="number" step="0.01" value="<?= number_format((float)$order['total_discount'],2,'.','') ?>">
                            </div>
                            <div>
                                <label>Налог</label>
                                <input id="total_tax" name="total_tax" type="number" step="0.01" value="<?= number_format((float)$order['total_tax'],2,'.','') ?>">
                            </div>
                            <div>
                                <label><b>Итого к оплате</b></label>
                                <input id="total_amount" name="total_amount" type="number" step="0.01" readonly value="<?= number_format((float)$order['total_amount'],2,'.','') ?>">
                                <div style="font-size:12px;color:#6b778c;margin-top:6px;">Итого: <b><span id="grandTotal"><?= number_format((float)$order['total_amount'],2,'.','') ?></span></b></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="actions" style="margin-top:12px;">
                    <button class="btn primary" type="submit">Сохранить</button>
                    <a class="btn" href="<?= url('boards/order/orders.php') ?>">Назад к списку</a>
                    <?php
                    $moveOut = $pdo->prepare("SELECT id FROM crm_stock_moves WHERE order_id=:oid AND doc_type='out'");
                    $moveOut->execute([':oid'=>$order['id']]); $moveOutId = (int)($moveOut->fetchColumn() ?: 0);
                    $moveIn  = $pdo->prepare("SELECT id FROM crm_stock_moves WHERE order_id=:oid AND doc_type='in'");
                    $moveIn->execute([':oid'=>$order['id']]); $moveInId = (int)($moveIn->fetchColumn() ?: 0);
                    ?>
                    <?php if ($moveOutId || $moveInId): ?>
                        <div class="card" style="margin-top:12px;">
                            <h3 style="margin:0 0 12px;">Печать документов</h3>
                            <div class="actions">
                                <?php if ($moveOutId): ?>
                                    <a class="btn print" target="_blank" href="<?= url('boards/inventory/print_move.php?id='.$moveOutId) ?>">Накладная (списание)</a>
                                <?php endif; ?>
                                <?php if ($moveInId): ?>
                                    <a class="btn print" target="_blank" href="<?= url('boards/inventory/print_move.php?id='.$moveInId) ?>">Акт возврата</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </form>

        </section>
    </main>
</div>
<script src="<?= asset('js/product-picker.js') ?>"></script>
<script src="<?= asset('js/order.js') ?>"></script>
<?php require APP_ROOT . '/inc/app_footer.php'; ?>
