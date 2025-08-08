<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); $active='inventory';

$type = $_GET['type'] ?? 'in';
if(!in_array($type,['in','out','transfer','adjust'],true)) $type='in';

global $pdo;
$ware = $pdo->query("SELECT id, name FROM crm_warehouses ORDER BY name")->fetchAll();

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
                <input type="hidden" name="doc_type" value="<?= htmlspecialchars($type) ?>">

                <div class="form">
                    <div class="row">
                        <?php if($type==='transfer'): ?>
                            <div>
                                <label>Со склада</label>
                                <select name="src_warehouse_id" required>
                                    <option value="">— выберите —</option>
                                    <?php foreach($ware as $w): ?>
                                        <option value="<?= (int)$w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>На склад</label>
                                <select name="dest_warehouse_id" required>
                                    <option value="">— выберите —</option>
                                    <?php foreach($ware as $w): ?>
                                        <option value="<?= (int)$w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div>
                                <label>Склад</label>
                                <select name="warehouse_id" required>
                                    <option value="">— выберите —</option>
                                    <?php foreach($ware as $w): ?>
                                        <option value="<?= (int)$w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Статус</label>
                                <input disabled value="Проведен">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div><label>ТТН номер</label><input name="ttn_number" placeholder="необязательно"></div>
                        <div><label>ТТН дата</label><input type="date" name="ttn_date"></div>
                    </div>
                    <div class="row">
                        <div><label>Перевозчик</label><input name="carrier" placeholder="Новая Пошта / Meest / ..."></div>
                        <div><label>Примечание</label><input name="notes"></div>
                    </div>
                </div>

                <?php $isPickFromStock = in_array($type,['out','transfer'], true); ?>
                <div class="card" style="margin-top:12px;">
                    <h3 style="margin:0 0 12px;">Позиции</h3>

                    <table
                            class="items-grid"
                            id="mvItems"
                            data-mode="<?= $isPickFromStock ? 'pick' : 'free' ?>"
                            data-api-instock="<?= url('boards/api/instock.php') ?>"
                            data-doc-type="<?= htmlspecialchars($type) ?>"
                    >
                        <thead>
                        <tr>
                            <?php if ($isPickFromStock): ?>
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
                        <tbody></tbody>
                    </table>

                    <div class="items-actions">
                        <a href="#" class="btn" id="addItem">+ Добавить</a>
                    </div>
                    <div style="margin-top:8px;color:#6b778c;">Итого: <b><span id="docTotal">0.00</span></b></div>
                </div>

                <div class="actions" style="margin-top:12px;">
                    <button class="btn primary" type="submit">Сохранить документ</button>
                    <a class="btn" href="<?= url('boards/inventory/movements.php') ?>">Отмена</a>
                </div>
            </form>

        </section>
    </main>
</div>
<script src="<?= asset('js/movement.js') ?>"></script>
<?php require APP_ROOT.'/inc/app_footer.php'; ?>
