<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
$active = 'orders';

global $pdo;
$clients = $pdo->query("SELECT id, full_name, email FROM crm_customers ORDER BY created_at DESC LIMIT 100")->fetchAll();
$ware    = $pdo->query("SELECT id, name FROM crm_warehouses ORDER BY name")->fetchAll();

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

                <div class="form">
                    <div class="row">
                        <div>
                            <label>Клиент</label>
                            <select name="customer_id" required>
                                <option value="">— выберите клиента —</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['full_name'].' — '.$c['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Статус</label>
                            <select name="status">
                                <option value="new">Новый</option>
                                <option value="pending">Ожидает</option>
                                <option value="paid">Оплачен</option>
                                <option value="shipped">Отправлен</option>
                                <option value="canceled">Отменён</option>
                                <option value="refunded">Возврат</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label>Склад заказа</label>
                            <select name="warehouse_id" required>
                                <option value="">— выберите —</option>
                                <?php foreach ($ware as $w): ?>
                                    <option value="<?= (int)$w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Валюта</label>
                            <select name="currency">
                                <option value="UAH">UAH</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label>Источник</label>
                            <input type="text" name="source" placeholder="site / marketplace / ad">
                        </div>
                        <div>
                            <label>Примечание</label>
                            <input type="text" name="notes">
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top:12px;">
                    <h3 style="margin:0 0 12px;">Позиции</h3>
                    <table class="items-grid" id="itemsTable">
                        <thead>
                        <tr>
                            <th>Наименование</th>
                            <th>SKU</th>
                            <th>Кол-во</th>
                            <th>Цена</th>
                            <th>Сумма</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <div class="items-actions">
                        <a href="#" class="btn" id="addItem">+ Добавить позицию</a>
                    </div>
                </div>

                <div class="totals">
                    <div class="box">
                        <label>Комментарий к заказу</label>
                        <textarea name="notes" rows="4" placeholder="Условия доставки, комментарии и т.д."></textarea>
                    </div>

                    <div class="box">
                        <div class="grid">
                            <div>
                                <label>Сумма товаров</label>
                                <input id="total_items" name="total_items" type="number" step="0.01" readonly value="0.00">
                            </div>
                            <div>
                                <label>Доставка</label>
                                <input id="total_shipping" name="total_shipping" type="number" step="0.01" value="0.00">
                            </div>
                            <div>
                                <label>Скидка</label>
                                <input id="total_discount" name="total_discount" type="number" step="0.01" value="0.00">
                            </div>
                            <div>
                                <label>Налог</label>
                                <input id="total_tax" name="total_tax" type="number" step="0.01" value="0.00">
                            </div>
                            <div>
                                <label><b>Итого к оплате</b></label>
                                <input id="total_amount" name="total_amount" type="number" step="0.01" readonly value="0.00">
                                <div style="font-size:12px;color:#6b778c;margin-top:6px;">Итого: <b><span id="grandTotal">0.00</span></b></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="actions" style="margin-top:12px;">
                    <button class="btn primary" type="submit">Сохранить заказ</button>
                    <a class="btn" href="<?= url('boards/order/orders.php') ?>">Отмена</a>
                </div>
            </form>

        </section>
    </main>
</div>
<script src="<?= url('assets/js/order.js') ?>"></script>
<?php require APP_ROOT . '/inc/app_footer.php'; ?>
