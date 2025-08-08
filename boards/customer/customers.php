<?php
require_once dirname(__DIR__, 2) . '/inc/util.php';
requireLogin();
$active = 'customers';

// Параметры
$q       = trim((string)qparam('q',''));
$page    = max(1, (int)qparam('page', 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// Фильтр
$where  = '1=1';
$params = [];
if ($q !== '') {
    $where = "(full_name ILIKE :q OR email ILIKE :q OR phone ILIKE :q OR company_name ILIKE :q)";
    $params[':q'] = '%'.$q.'%';
}

// Подсчёт
global $pdo;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_customers WHERE $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// Данные
$sql = "SELECT id, full_name, email, phone, company_name, created_at
        FROM crm_customers
        WHERE $where
        ORDER BY created_at DESC
        LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $stmt->bindValue($k, $v, PDO::PARAM_STR); }
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

require_once dirname(__DIR__, 2) . '/inc/app_header.php';
?>
<div class="app">
    <?php require dirname(__DIR__, 2) . '/inc/app_sidebar.php'; ?>
    <main class="main">
        <?php require dirname(__DIR__, 2) . '/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>

            <div class="card">
                <div class="toolbar">
                    <form class="filters" method="get" action="customers.php">
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Поиск: имя, e-mail, телефон, компания">
                        <button class="btn" type="submit">Найти</button>
                    </form>
                    <div class="actions">
                        <a class="btn primary" href="customer_new.php">+ Новый клиент</a>
                    </div>
                </div>

                <table class="table">
                    <thead>
                    <tr>
                        <th>Клиент</th>
                        <th>E-mail</th>
                        <th>Телефон</th>
                        <th>Компания</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="6" style="text-align:center;background:#fff;">Ничего не найдено</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['full_name']) ?></td>
                            <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['company_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
                            <td style="text-align:right;">
                                <a class="btn" href="customer_edit.php?id=<?= (int)$r['id'] ?>">Редактировать</a>
                                <form action="customer_delete.php" method="post" style="display:inline" onsubmit="return confirm('Удалить клиента?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn" type="submit">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?= paginate($total, $page, $perPage, 'customers.php'.($q!==''?('?q='.urlencode($q)):'') ) ?>
            </div>
        </section>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/inc/app_footer.php'; ?>
