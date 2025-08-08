<?php // boards/products/product_new.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); $active='products';
require APP_ROOT.'/inc/app_header.php'; ?>
<div class="app">
    <?php require APP_ROOT.'/inc/app_sidebar.php'; ?>
    <main class="main"><?php require APP_ROOT.'/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>
            <div class="card">
                <h3 style="margin-bottom:12px;">Новый товар</h3>
                <form class="form" method="post" action="<?= url('boards/products/product_save.php') ?>">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div><label>SKU</label><input name="sku" required></div>
                        <div><label>Название</label><input name="name" required></div>
                    </div>
                    <div class="row">
                        <div><label>Штрихкод</label><input name="barcode"></div>
                        <div><label>Единица</label><input name="unit" value="шт"></div>
                    </div>
                    <div class="row">
                        <div><label>Цена</label><input type="number" step="0.01" name="price" value="0.00"></div>
                        <div><label>Себестоимость</label><input type="number" step="0.01" name="cost_price" value="0.00"></div>
                    </div>
                    <div class="actions">
                        <button class="btn primary" type="submit">Сохранить</button>
                        <a class="btn" href="<?= url('boards/products/products.php') ?>">Отмена</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
<?php require APP_ROOT.'/inc/app_footer.php'; ?>
