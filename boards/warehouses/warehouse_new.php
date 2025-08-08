<?php // boards/warehouses/warehouse_new.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); $active='products';
require APP_ROOT.'/inc/app_header.php'; ?>
<div class="app"><?php require APP_ROOT.'/inc/app_sidebar.php'; ?><main class="main"><?php require APP_ROOT.'/inc/app_topbar.php'; ?><section class="content">
            <?= flash_render() ?>
            <div class="card">
                <h3 style="margin-bottom:12px;">Новый склад</h3>
                <form class="form" method="post" action="<?= url('boards/warehouses/warehouse_save.php') ?>">
                    <?= csrf_field() ?>
                    <div class="row"><div><label>Код</label><input name="code" required></div><div><label>Название</label><input name="name" required></div></div>
                    <label>Адрес</label><input name="address">
                    <div class="actions"><button class="btn primary">Сохранить</button><a class="btn" href="<?= url('boards/warehouses/warehouses.php') ?>">Отмена</a></div>
                </form>
            </div>
        </section></main></div><?php require APP_ROOT.'/inc/app_footer.php'; ?>
