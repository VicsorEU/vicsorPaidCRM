<?php
require_once __DIR__ . '/inc/util.php';
requireLogin();
$active = 'dashboard';
global $pdo;

/* ---------- helpers для схемы/форматирования ---------- */

function table_has_col(PDO $pdo, string $table, string $col): bool {
    $sql = "SELECT 1 FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name   = :t
              AND column_name  = :c";
    $st = $pdo->prepare($sql);
    $st->execute([':t'=>strtolower($table), ':c'=>strtolower($col)]);
    return (bool)$st->fetchColumn();
}
function pick_col(PDO $pdo, string $table, array $candidates): ?string {
    foreach ($candidates as $c) if (table_has_col($pdo, $table, $c)) return $c;
    return null;
}
function table_cols(PDO $pdo, string $table): array {
    $st = $pdo->prepare("SELECT lower(column_name)
                         FROM information_schema.columns
                         WHERE table_schema = current_schema()
                           AND table_name   = :t");
    $st->execute([':t'=>strtolower($table)]);
    return array_column($st->fetchAll(PDO::FETCH_NUM), 0);
}
if (!function_exists('time_ago')) {
    function time_ago($ts): string {
        $t = is_numeric($ts) ? (int)$ts : strtotime((string)$ts);
        $d = time() - $t;
        if ($d < 60)   return $d.' сек назад';
        if ($d < 3600) return floor($d/60).' мин назад';
        if ($d < 86400)return floor($d/3600).' ч назад';
        return floor($d/86400).' дн назад';
    }
}
function hrn($n){ return number_format((float)$n, 0, '.', ' '); }

/* ---------- autodetect колонок в заказах ---------- */

$ORDERS_TBL = 'crm_orders';
$CUST_TBL   = 'crm_customers';

$orderNoCol     = pick_col($pdo, $ORDERS_TBL, ['order_no','number','order_number','order_code','code','public_id','external_id']);
$amountCol      = pick_col($pdo, $ORDERS_TBL, ['total_amount','amount','total','grand_total','sum']);
$statusCol      = pick_col($pdo, $ORDERS_TBL, ['status','state','status_text']);
$createdCol     = pick_col($pdo, $ORDERS_TBL, ['created_at','created','date_created','created_on','inserted_at','created_time']);
$customerIdCol  = pick_col($pdo, $ORDERS_TBL, ['customer_id','client_id','buyer_id','contact_id']);
$sourceCol      = pick_col($pdo, $ORDERS_TBL, ['source','channel','utm_source']);

if (!$createdCol) $createdCol = 'created_at';
$orderNoExpr = $orderNoCol ? "COALESCE(o.$orderNoCol::text, o.id::text)" : "o.id::text";
$amountExpr  = $amountCol  ? "COALESCE(o.$amountCol::numeric, 0::numeric)" : "0::numeric";
$statusExpr  = $statusCol  ? "COALESCE(o.$statusCol::text, ''::text)"     : "''::text";

/* ---------- подготовка дат ---------- */

$todayStartSql     = "date_trunc('day', now())";
$tomorrowStartSql  = "date_trunc('day', now()) + interval '1 day'";
$yesterdayStartSql = "date_trunc('day', now()) - interval '1 day'";

/* ---------- KPI по заказам ---------- */

$today = $pdo->query("
  SELECT
    COUNT(*)::INT AS orders_cnt,
    COALESCE(SUM($amountExpr), 0)::NUMERIC AS revenue,
    SUM(CASE WHEN lower($statusExpr) LIKE 'paid%' OR lower($statusExpr) LIKE '%оплач%' THEN 1 ELSE 0 END)::INT AS paid_cnt
  FROM $ORDERS_TBL o
  WHERE o.$createdCol >= $todayStartSql AND o.$createdCol < $tomorrowStartSql
")->fetch() ?: ['orders_cnt'=>0,'revenue'=>0,'paid_cnt'=>0];

$yest = $pdo->query("
  SELECT
    COUNT(*)::INT AS orders_cnt,
    COALESCE(SUM($amountExpr), 0)::NUMERIC AS revenue,
    SUM(CASE WHEN lower($statusExpr) LIKE 'paid%' OR lower($statusExpr) LIKE '%оплач%' THEN 1 ELSE 0 END)::INT AS paid_cnt
  FROM $ORDERS_TBL o
  WHERE o.$createdCol >= $yesterdayStartSql AND o.$createdCol < $todayStartSql
")->fetch() ?: ['orders_cnt'=>0,'revenue'=>0,'paid_cnt'=>0];

$avg7 = $pdo->query("
  SELECT COALESCE(SUM($amountExpr),0)::NUMERIC AS sum7, COUNT(*)::INT AS cnt7
  FROM $ORDERS_TBL o
  WHERE o.$createdCol >= (date_trunc('day', now()) - interval '6 day')
")->fetch();
$avgCheck = ($avg7['cnt7'] ?? 0) > 0 ? (float)$avg7['sum7'] / (int)$avg7['cnt7'] : 0.0;

function pct_change($todayVal, $yestVal): string {
    if ((float)$yestVal == 0.0) return $todayVal > 0 ? '+∞% к вчера' : '0%';
    $p = round(100.0 * ((float)$todayVal - (float)$yestVal) / (float)$yestVal, 1);
    return ($p >= 0 ? '+' : '').$p.'% к вчера';
}
$revTrend = pct_change($today['revenue'], $yest['revenue']);
$ordTrend = pct_change($today['orders_cnt'], $yest['orders_cnt']);

/* ---------- график «Продажи за 30 дней» ---------- */

$salesRows = $pdo->query("
  WITH days AS (
    SELECT generate_series(current_date - interval '29 day', current_date, interval '1 day')::date AS d
  )
  SELECT d AS day, COALESCE(SUM($amountExpr), 0)::NUMERIC AS sum
  FROM days
  LEFT JOIN $ORDERS_TBL o ON o.$createdCol::date = d
  GROUP BY d
  ORDER BY d
")->fetchAll();
$salesSeries = array_map(fn($r)=> ['day'=>$r['day'], 'sum'=>(float)$r['sum']], $salesRows);

/* ---------- задачи: KPI ---------- */

$doneBoardIds = $pdo->query("
  SELECT COALESCE(array_agg(id), '{}') FROM crm_task_boards
  WHERE lower(name) ~ '(готово|done|закры)'
")->fetchColumn() ?: '{}';

$tasksOverdue = (int)$pdo->query("
  SELECT COUNT(*) FROM crm_tasks
  WHERE due_date IS NOT NULL
    AND due_date < current_date
    AND (board_id IS NULL OR board_id <> ALL ('$doneBoardIds'::bigint[]))
")->fetchColumn();

$tasksToday = (int)$pdo->query("
  SELECT COUNT(*) FROM crm_tasks
  WHERE due_date = current_date
    AND (board_id IS NULL OR board_id <> ALL ('$doneBoardIds'::bigint[]))
")->fetchColumn();

$tasksOpen = (int)$pdo->query("
  SELECT COUNT(*) FROM crm_tasks
  WHERE (board_id IS NULL OR board_id <> ALL ('$doneBoardIds'::bigint[]))
")->fetchColumn();

$tasksDone7 = (int)$pdo->query("
  SELECT COUNT(*) FROM crm_tasks
  WHERE board_id = ANY ('$doneBoardIds'::bigint[])
    AND updated_at >= (now() - interval '7 day')
")->fetchColumn();

/* ---------- задачи: график по доскам за 30 дней (создано в доске) ---------- */
$boards = $pdo->query("
  SELECT id, name, COALESCE(NULLIF(color,''), '#64748b') AS color
  FROM crm_task_boards
  ORDER BY position, id
")->fetchAll();

$boardSeries = [];
if ($boards) {
    $rows = $pdo->query("
    WITH days AS (
      SELECT generate_series(current_date - interval '29 day', current_date, interval '1 day')::date AS d
    )
    SELECT b.id, b.name, b.color, d.d AS day, COALESCE(COUNT(t.id),0)::int AS cnt
    FROM crm_task_boards b
    CROSS JOIN days d
    LEFT JOIN crm_tasks t
      ON t.board_id = b.id
     AND t.created_at::date = d.d
    GROUP BY b.id, b.name, b.color, d.d
    ORDER BY b.id, d.d
  ")->fetchAll();

    $map = [];
    foreach ($rows as $r) {
        $bid = (int)$r['id'];
        if (!isset($map[$bid])) {
            $map[$bid] = [
                    'id'     => $bid,
                    'name'   => $r['name'],
                    'color'  => $r['color'] ?: '#64748b',
                    'series' => []
            ];
        }
        $map[$bid]['series'][] = ['day' => $r['day'], 'cnt' => (int)$r['cnt']];
    }
    $boardSeries = array_values($map);
}

/* ---------- «Последние заказы» ---------- */

$customerTableExists = (bool)$pdo->query("SELECT to_regclass('$CUST_TBL')")->fetchColumn();
$joinCustomer = ($customerIdCol && $customerTableExists) ? "LEFT JOIN $CUST_TBL c ON c.id = o.$customerIdCol" : "";

$custNameExpr = "'—'::text";
if ($joinCustomer) {
    $cols = table_cols($pdo, $CUST_TBL);
    $has  = fn($n)=> in_array(strtolower($n), $cols, true);
    $exprs = [];
    if ($has('full_name'))     $exprs[] = "NULLIF(c.full_name::text, ''::text)";
    if ($has('name'))          $exprs[] = "NULLIF(c.name::text, ''::text)";
    if ($has('company_name'))  $exprs[] = "NULLIF(c.company_name::text, ''::text)";
    if ($has('title'))         $exprs[] = "NULLIF(c.title::text, ''::text)";
    $fio = [];
    if ($has('last_name'))   $fio[] = "c.last_name::text";
    if ($has('first_name'))  $fio[] = "c.first_name::text";
    if ($has('middle_name')) $fio[] = "c.middle_name::text";
    if ($fio) $exprs[] = "NULLIF(trim(concat_ws(' ', ".implode(', ', $fio).")), ''::text)";
    if ($has('email')) $exprs[] = "NULLIF(c.email::text, ''::text)";
    if ($has('phone')) $exprs[] = "NULLIF(c.phone::text, ''::text)";
    if ($exprs) $custNameExpr = "COALESCE(".implode(', ', $exprs).", '—'::text)";
}

$latestOrders = $pdo->query("
  SELECT
    o.id,
    $orderNoExpr AS order_no,
    $amountExpr  AS total_amount,
    $statusExpr  AS status,
    o.$createdCol AS created_at,
    ".($joinCustomer ? "$custNameExpr AS customer_name" : "NULL::text AS customer_name")."
  FROM $ORDERS_TBL o
  $joinCustomer
  ORDER BY o.$createdCol DESC
  LIMIT 10
")->fetchAll();

/* ---------- Каналы (если есть колонка source) ---------- */

$sourceRows = [];
if ($sourceCol) {
    $sourceRows = $pdo->query("
    SELECT $sourceCol::text AS source, COUNT(*)::INT AS cnt
    FROM $ORDERS_TBL
    WHERE $createdCol >= now() - interval '30 day'
    GROUP BY $sourceCol
    ORDER BY cnt DESC
    LIMIT 4
  ")->fetchAll();
}
$channelsTotal = array_sum(array_map(fn($r)=> (int)$r['cnt'], $sourceRows)) ?: 1;

/* ---------- Активность 24ч ---------- */

$events = [];
$rowsOrd = $pdo->query("
  SELECT id, $orderNoExpr AS order_no, $amountExpr AS total_amount, o.$createdCol AS created_at
  FROM $ORDERS_TBL o
  WHERE o.$createdCol >= now() - interval '24 hour'
  ORDER BY o.$createdCol DESC
  LIMIT 10
")->fetchAll();
foreach ($rowsOrd as $r) {
    $events[] = [
            'dt'=>$r['created_at'],
            'text'=>"Создан заказ #".htmlspecialchars($r['order_no'])." на ₴ ".number_format((float)$r['total_amount'], 0, '.', ' ')
    ];
}
$rowsTasks = $pdo->query("
  SELECT id, title, created_at
  FROM crm_tasks
  WHERE created_at >= now() - interval '24 hour'
  ORDER BY created_at DESC
  LIMIT 10
")->fetchAll();
foreach ($rowsTasks as $r) {
    $events[] = ['dt'=>$r['created_at'], 'text'=>"Создана задача: <b>".htmlspecialchars($r['title'])."</b>"];
}
usort($events, fn($a,$b)=> strcmp($b['dt'],$a['dt']));
$events = array_slice($events, 0, 8);

/* ---------- VIEW ---------- */

require __DIR__ . '/inc/app_header.php';

/* единый маппинг статусов (как в списке заказов) */
$statuses = function_exists('order_statuses_map')
        ? order_statuses_map()
        : ['new'=>'Новый','pending'=>'Ожидает','paid'=>'Оплачен','shipped'=>'Отправлен','canceled'=>'Отменён','refunded'=>'Возврат'];
?>
<div class="app">
    <?php require __DIR__ . '/inc/app_sidebar.php'; ?>
    <main class="main">
        <?php require __DIR__ . '/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>

            <!-- KPIs -->
            <div class="kpis">
                <div class="card">
                    <h3>Выручка сегодня</h3>
                    <div class="value">₴ <?= hrn($today['revenue']) ?></div>
                    <div class="trend"><?= htmlspecialchars($revTrend) ?></div>
                </div>
                <div class="card">
                    <h3>Заказы</h3>
                    <div class="value"><?= (int)$today['orders_cnt'] ?></div>
                    <div class="trend"><?= htmlspecialchars($ordTrend) ?></div>
                </div>
                <div class="card">
                    <h3>Средний чек (7д)</h3>
                    <div class="value">₴ <?= hrn($avgCheck) ?></div>
                    <div class="trend muted">за последние 7 дней</div>
                </div>
                <div class="card">
                    <h3>Задачи</h3>
                    <div class="value"><?= (int)$tasksOpen ?> открыто</div>
                    <div class="trend">просрочено: <?= (int)$tasksOverdue ?> • до сегодня: <?= (int)$tasksToday ?> • завершено 7д: <?= (int)$tasksDone7 ?></div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h3>Продажи за 30 дней</h3>
                    <div class="chart-wrap">
                        <canvas id="salesChart"
                                data-series='<?= htmlspecialchars(json_encode($salesSeries), ENT_QUOTES) ?>'></canvas>
                    </div>
                </div>

                <div class="card">
                    <h3>Задачи за 30 дней</h3>
                    <div class="chart-wrap">
                        <canvas id="tasksChart"
                                data-boards='<?= htmlspecialchars(json_encode($boardSeries), ENT_QUOTES) ?>'></canvas>
                        <div id="tasksLegend" class="chart-legend" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;"></div>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h3>Последние заказы</h3>
                    <table class="table">
                        <thead><tr><th>№</th><th>Клиент</th><th>Сумма</th><th>Статус</th><th>Дата</th></tr></thead>
                        <tbody>
                        <?php if (!$latestOrders): ?>
                            <tr><td colspan="5" class="muted" style="text-align:center;">Заказов пока нет</td></tr>
                        <?php else: foreach ($latestOrders as $o): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($o['order_no']) ?></td>
                                <td><?= htmlspecialchars($o['customer_name'] ?? '—') ?></td>
                                <td>₴ <?= hrn($o['total_amount']) ?></td>
                                <td>
                                    <?php
                                    $raw = strtolower((string)($o['status'] ?? ''));
                                    $label = $statuses[$raw] ?? ($o['status'] ?? '—');
                                    ?>
                                    <span class="status <?= htmlspecialchars($raw) ?>">
                    <?= htmlspecialchars($label) ?>
                  </span>
                                </td>
                                <td><?= htmlspecialchars(date('d.m H:i', strtotime($o['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <h3>Каналы</h3>
                    <ul class="channels">
                        <?php if ($sourceRows): ?>
                            <?php foreach ($sourceRows as $r):
                                $pct = round(100 * (int)$r['cnt'] / $channelsTotal);
                                $w   = min(100, max(0, $pct));
                                $name = $r['source'] === null ? '—' : $r['source'];
                                ?>
                                <li class="channel">
                                    <span><?= htmlspecialchars($name) ?></span>
                                    <div class="bar"><i style="width:<?= $w ?>%"></i></div>
                                    <span class="pct"><?= $pct ?>%</span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="muted">Источник не настроен</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="card">
                <h3>Активность</h3>
                <ul class="activity">
                    <?php if (!$events): ?>
                        <li class="muted">Событий пока нет</li>
                    <?php else: foreach ($events as $ev): ?>
                        <li>
                            <i class="dot"></i>
                            <div>
                                <div class="t"><?= $ev['text'] ?></div>
                                <div class="time"><?= htmlspecialchars(time_ago($ev['dt'])) ?></div>
                            </div>
                        </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>
        </section>
    </main>
</div>

<!-- Без внешних библиотек -->
<script src="<?= asset('js/dashboard.js') ?>"></script>
<?php require __DIR__ . '/inc/app_footer.php'; ?>
