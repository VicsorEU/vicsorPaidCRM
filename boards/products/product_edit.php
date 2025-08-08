<?php // boards/products/product_edit.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); $active='products';

$id = (int)($_GET['id'] ?? 0);
global $pdo;
$s = $pdo->prepare("SELECT * FROM crm_products WHERE id=:id");
$s->execute([':id'=>$id]);
$prod = $s->fetch();
if (!$prod) { http_response_code(404); exit('Not found'); }

require APP_ROOT.'/inc/app_header.php'; ?>
<div class="app">
    <?php require APP_ROOT.'/inc/app_sidebar.php'; ?>
    <main class="main"><?php require APP_ROOT.'/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>
            <div class="card">
                <h3 style="margin-bottom:12px;">Редактировать товар</h3>
                <form class="form" method="post" action="<?= url('boards/products/product_save.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
                    <div class="row">
                        <div><label>SKU</label><input name="sku" required value="<?= htmlspecialchars($prod['sku']) ?>"></div>
                        <div><label>Название</label><input name="name" required value="<?= htmlspecialchars($prod['name']) ?>"></div>
                    </div>
                    <div class="row">
                        <div><label>Штрихкод</label><input name="barcode" value="<?= htmlspecialchars($prod['barcode'] ?? '') ?>"></div>
                        <div><label>Единица</label><input name="unit" value="<?= htmlspecialchars($prod['unit']) ?>"></div>
                    </div>
                    <div class="row">
                        <div><label>Цена</label><input type="number" step="0.01" name="price" value="<?= number_format((float)$prod['price'],2,'.','') ?>"></div>
                        <div><label>Себестоимость</label><input type="number" step="0.01" name="cost_price" value="<?= number_format((float)$prod['cost_price'],2,'.','') ?>"></div>
                    </div>
                    <div class="actions">
                        <button class="btn primary" type="submit">Сохранить</button>
                        <a class="btn" href="<?= url('boards/products/products.php') ?>">Назад</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
<?php require APP_ROOT.'/inc/app_footer.php'; ?>
