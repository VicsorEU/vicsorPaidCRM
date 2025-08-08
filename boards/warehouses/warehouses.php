<?php // boards/warehouses/warehouses.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
$active='warehouses'; // или 'inventory', как тебе
$q = trim((string)($_GET['q'] ?? ''));
$where='1=1'; $p=[];
if ($q!==''){ $where="(code ILIKE :q OR name ILIKE :q OR address ILIKE :q)"; $p[':q']='%'.$q.'%';}
global $pdo;
$st=$pdo->prepare("SELECT * FROM crm_warehouses WHERE $where ORDER BY created_at DESC");
$st->execute($p); $rows=$st->fetchAll();
require APP_ROOT.'/inc/app_header.php'; ?>
<div class="app">
    <?php require APP_ROOT.'/inc/app_sidebar.php'; ?>
    <main class="main"><?php require APP_ROOT.'/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>
            <div class="card">
                <div class="toolbar">
                    <form class="filters"><input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Поиск: код/название/адрес"><button class="btn">Найти</button></form>
                    <div class="actions"><a class="btn primary" href="<?= url('boards/warehouses/warehouse_new.php') ?>">+ Новый склад</a></div>
                </div>
                <table class="table">
                    <thead><tr><th>Код</th><th>Название</th><th>Адрес</th><th></th></tr></thead>
                    <tbody>
                    <?php if(!$rows): ?><tr><td colspan="4" style="text-align:center;background:#fff;">Пусто</td></tr>
                    <?php else: foreach($rows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['code']) ?></td>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td><?= htmlspecialchars($r['address'] ?? '') ?></td>
                            <td style="text-align:right;">
                                <a class="btn" href="<?= url('boards/warehouses/warehouse_edit.php?id='.(int)$r['id']) ?>">Редактировать</a>
                                <form action="<?= url('boards/warehouses/warehouse_delete.php') ?>" method="post" style="display:inline" onsubmit="return confirm('Удалить склад?');">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn" type="submit">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<?php require APP_ROOT.'/inc/app_footer.php'; ?>
