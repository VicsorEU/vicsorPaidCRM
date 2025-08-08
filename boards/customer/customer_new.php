<?php
require_once dirname(__DIR__, 2) . '/inc/util.php';
requireLogin();
$active = 'customers';
require_once dirname(__DIR__, 2) . '/inc/app_header.php';
?>
<div class="app">
    <?php require dirname(__DIR__, 2) . '/inc/app_sidebar.php'; ?>
    <main class="main">
        <?php require dirname(__DIR__, 2) . '/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>

            <div class="card">
                <h3 style="margin-bottom:12px;">Новый клиент</h3>
                <form class="form" method="post" action="customer_save.php">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div>
                            <label>Имя и фамилия</label>
                            <input type="text" name="full_name" required>
                        </div>
                        <div>
                            <label>Компания</label>
                            <input type="text" name="company_name">
                        </div>
                    </div>
                    <div class="row">
                        <div>
                            <label>E-mail</label>
                            <input type="email" name="email">
                        </div>
                        <div>
                            <label>Телефон</label>
                            <input type="text" name="phone">
                        </div>
                    </div>
                    <div class="actions">
                        <button class="btn primary" type="submit">Сохранить</button>
                        <a class="btn" href="customers.php">Отмена</a>
                    </div>
                </form>
            </div>

        </section>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/inc/app_footer.php'; ?>
