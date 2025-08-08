<?php // boards/warehouses/warehouse_edit.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); $active='products';
$id=(int)($_GET['id']??0); global $pdo;
$s=$pdo->prepare("SELECT * FROM crm_warehouses WHERE id=:id"); $s->execute([':id'=>$id]); $w=$s->fetch();
if(!$w){http_response_code(404);exit('Not found');}
require APP_ROOT.'/inc/app_header.php'; ?>
<div class="app"><?php require APP_ROOT.'/inc/app_sidebar.php'; ?><main class="main"><?php require APP_ROOT.'/inc/app_topbar.php'; ?><section class="content">
            <?= flash_render() ?>
            <div class="card">
                <h3 style="margin-bottom:12px;">Редактировать склад</h3>
                <form class="form" method="post" action="<?= url('boards/warehouses/warehouse_save.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                    <div class="row"><div><label>Код</label><input name="code" required value="<?= htmlspecialchars($w['code']) ?>"></div><div><label>Название</label><input name="name" required value="<?= htmlspecialchars($w['name']) ?>"></div></div>
                    <label>Адрес</label><input name="address" value="<?= htmlspecialchars($w['address'] ?? '') ?>">
                    <div class="actions"><button class="btn primary">Сохранить</button><a class="btn" href="<?= url('boards/warehouses/warehouses.php') ?>">Назад</a></div>
                </form>
            </div>
        </section></main></div><?php require APP_ROOT.'/inc/app_footer.php'; ?>
