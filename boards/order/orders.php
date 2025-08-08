<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
$active = 'orders';

$q        = trim((string)($_GET['q'] ?? ''));             // №, клиент, email, телефон
$status   = trim((string)($_GET['status'] ?? ''));        // new|pending|paid|shipped|canceled|refunded
$df       = trim((string)($_GET['date_from'] ?? ''));     // YYYY-MM-DD
$dt       = trim((string)($_GET['date_to'] ?? ''));       // YYYY-MM-DD
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($q !== '') {
    $where[] = "(CAST(o.order_number AS TEXT) ILIKE :q
           OR c.full_name ILIKE :q
           OR c.email ILIKE :q
           OR c.phone ILIKE :q)";
    $params[':q'] = '%'.$q.'%';
}
if ($status !== '' && in_array($status, ['new','pending','paid','shipped','canceled','refunded'], true)) {
    $where[] = "o.status = :status";
    $params[':status'] = $status;
}
if ($df !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~',$df)) {
    $where[] = "o.created_at >= :df::date";
    $params[':df'] = $df;
}
if ($dt !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~',$dt)) {
    $where[] = "o.created_at < (:dt::date + INTERVAL '1 day')";
    $params[':dt'] = $dt;
}

$whereSql = implode(' AND ', $where);

global $pdo;
$cnt = $pdo->prepare("SELECT COUNT(*) 
                      FROM crm_orders o 
                      JOIN crm_customers c ON c.id=o.customer_id
                      WHERE $whereSql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

$sql = "SELECT o.id, o.order_number, o.status, o.currency, o.total_amount, o.created_at,
               c.full_name, c.email
        FROM crm_orders o
        JOIN crm_customers c ON c.id=o.customer_id
        WHERE $whereSql
        ORDER BY o.created_at DESC
        LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$statuses = ['new'=>'Новый','pending'=>'Ожидает','paid'=>'Оплачен','shipped'=>'Отправлен','canceled'=>'Отменён','refunded'=>'Возврат'];

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
                    <form class="filters" method="get" action="">
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Поиск: №, клиент, e-mail, телефон">
                        <select name="status">
                            <option value="">Статус: любой</option>
                            <?php foreach ($statuses as $k=>$v): ?>
                                <option value="<?= $k ?>"<?= $status===$k?' selected':'' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($df) ?>">
                        <input type="date" name="date_to" value="<?= htmlspecialchars($dt) ?>">
                        <button class="btn" type="submit">Фильтровать</button>
                    </form>
                    <div class="actions">
                        <a class="btn primary" href="<?= url('boards/order/order_new.php') ?>">+ Новый заказ</a>
                    </div>
                </div>

                <table class="table">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Клиент</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="6" style="text-align:center;background:#fff;">Нет заказов</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td>#<?= (int)$r['order_number'] ?></td>
                            <td><?= htmlspecialchars($r['full_name']) ?><br><span style="color:#6b778c;font-size:12px;"><?= htmlspecialchars($r['email'] ?? '') ?></span></td>
                            <td><?= htmlspecialchars($r['currency']) ?> <?= number_format((float)$r['total_amount'], 2, '.', ' ') ?></td>
                            <td><span class="status <?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($statuses[$r['status']] ?? $r['status']) ?></span></td>
                            <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
                            <td style="text-align:right;">
                                <a class="btn" href="<?= url('boards/order/order_edit.php?id='.(int)$r['id']) ?>">Открыть</a>
                                <form action="<?= url('boards/order/order_delete.php') ?>" method="post" style="display:inline" onsubmit="return confirm('Удалить заказ #<?= (int)$r['order_number'] ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn" type="submit">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?php
                $qs = array_filter(['q'=>$q,'status'=>$status,'date_from'=>$df,'date_to'=>$dt]);
                echo paginate($total, $page, $perPage, url('boards/order/orders.php') . ($qs ? ('?'.http_build_query($qs)) : ''));
                ?>
            </div>
        </section>
    </main>
</div>
<?php require APP_ROOT . '/inc/app_footer.php'; ?>
