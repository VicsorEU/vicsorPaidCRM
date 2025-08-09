<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
$active = 'products';
global $pdo;

$attrs = $pdo->query("
  SELECT a.*,
         COUNT(o.id)::int AS options_cnt
  FROM crm_attributes a
  LEFT JOIN crm_attribute_options o ON o.attribute_id=a.id
  GROUP BY a.id
  ORDER BY a.name
")->fetchAll();

require APP_ROOT . '/inc/app_header.php';
?>
<div class="app">
    <?php require APP_ROOT . '/inc/app_sidebar.php'; ?>
    <main class="main">
        <?php require APP_ROOT . '/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>

            <div class="card">
                <div class="toolbar">
                    <div class="actions">
                        <a class="btn primary" href="<?= url('boards/products/attribute_edit.php') ?>">+ Новый атрибут</a>
                    </div>
                </div>

                <table class="table">
                    <thead><tr><th>Название</th><th>Код</th><th>Тип</th><th>Опций</th><th></th></tr></thead>
                    <tbody>
                    <?php if(!$attrs): ?>
                        <tr><td colspan="5" style="text-align:center;background:#fff;">Атрибутов пока нет</td></tr>
                    <?php else: foreach ($attrs as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['name']) ?></td>
                            <td><code><?= htmlspecialchars($a['code'] ?? '') ?></code></td>
                            <td><?= htmlspecialchars($a['type']) ?></td>
                            <td><?= (int)$a['options_cnt'] ?></td>
                            <td style="text-align:right;">
                                <a class="btn" href="<?= url('boards/products/attribute_edit.php?id='.(int)$a['id']) ?>">Открыть</a>
                                <form method="post" action="<?= url('boards/products/api/attribute_delete.php') ?>" style="display:inline" onsubmit="return confirm('Удалить атрибут &laquo;<?= htmlspecialchars($a['name']) ?>&raquo;?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button class="btn danger" type="submit">Удалить</button>
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
<?php require APP_ROOT . '/inc/app_footer.php'; ?>
