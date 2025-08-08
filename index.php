<?php
require_once __DIR__ . '/inc/util.php';
requireLogin();
$active = 'dashboard';
require __DIR__ . '/inc/app_header.php';
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
                    <div class="value">₴ 128 450</div>
                    <div class="trend">+12,4% к вчера</div>
                </div>
                <div class="card">
                    <h3>Заказы</h3>
                    <div class="value">356</div>
                    <div class="trend">+4,1% за 24ч</div>
                </div>
                <div class="card">
                    <h3>Средний чек</h3>
                    <div class="value">₴ 1 230</div>
                    <div class="trend">−1,2% неделя</div>
                </div>
                <div class="card">
                    <h3>Конверсия</h3>
                    <div class="value">2,8%</div>
                    <div class="trend" style="color:#177a3a">↑ стабильная</div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h3>Продажи за 30 дней</h3>
                    <div class="chart-wrap"><canvas id="salesChart"></canvas></div>
                </div>

                <div class="card">
                    <h3>Каналы</h3>
                    <ul class="channels">
                        <li class="channel">
                            <span>Органика</span>
                            <div class="bar"><i style="width:58%"></i></div>
                            <span class="pct">58%</span>
                        </li>
                        <li class="channel">
                            <span>Реклама</span>
                            <div class="bar"><i style="width:26%;background:#22c55e"></i></div>
                            <span class="pct">26%</span>
                        </li>
                        <li class="channel">
                            <span>Прямые</span>
                            <div class="bar"><i style="width:10%;background:#f59e0b"></i></div>
                            <span class="pct">10%</span>
                        </li>
                        <li class="channel">
                            <span>Маркетплейсы</span>
                            <div class="bar"><i style="width:6%;background:#ef4444"></i></div>
                            <span class="pct">6%</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h3>Последние заказы</h3>
                    <table class="table">
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Клиент</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th>Дата</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>#10234</td>
                            <td>Иван Петров</td>
                            <td>₴ 2 150</td>
                            <td><span class="badge ok">Оплачен</span></td>
                            <td>08.08 10:22</td>
                        </tr>
                        <tr>
                            <td>#10233</td>
                            <td>Марина П.</td>
                            <td>₴ 980</td>
                            <td><span class="badge warn">Ожидает</span></td>
                            <td>08.08 09:58</td>
                        </tr>
                        <tr>
                            <td>#10232</td>
                            <td>ООО «Альфа»</td>
                            <td>₴ 15 430</td>
                            <td><span class="badge ok">Оплачен</span></td>
                            <td>08.08 09:41</td>
                        </tr>
                        <tr>
                            <td>#10231</td>
                            <td>Дмитрий С.</td>
                            <td>₴ 1 250</td>
                            <td><span class="badge ok">Оплачен</span></td>
                            <td>08.08 09:20</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <h3>Активность</h3>
                    <ul class="activity">
                        <li>
                            <i class="dot"></i>
                            <div>
                                <div class="t">Создан заказ <b>#10234</b> на ₴ 2 150</div>
                                <div class="time">10 минут назад</div>
                            </div>
                        </li>
                        <li>
                            <i class="dot"></i>
                            <div>
                                <div class="t">Новый клиент: <b>Иван Петров</b></div>
                                <div class="time">28 минут назад</div>
                            </div>
                        </li>
                        <li>
                            <i class="dot"></i>
                            <div>
                                <div class="t">Платёж по заказу <b>#10231</b> подтверждён</div>
                                <div class="time">1 час назад</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/inc/app_footer.php'; ?>
