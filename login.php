<?php
require_once __DIR__ . '/inc/auth.php';

if (isLoggedIn()) {
    header('Location: index.php'); exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '', $errors);
    if ($ok) { header('Location: index.php'); exit; }
}

require __DIR__ . '/inc/header.php';
?>
<div class="auth-shell">
    <div class="auth-grid">

        <div class="auth-left">
            <div class="brand"><span class="dot"></span> VicsorCRM</div>
        </div>

        <div class="auth-right">
            <div class="card">

                <div class="card-top">
                    <div class="lang">Рус</div>
                </div>

                <h1>Вход в аккаунт VicsorCMR</h1>
                <div class="sub">Введите данные для авторизации</div>

                <?php foreach($errors as $e): ?>
                    <div class="alert error"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>

                <form method="post" autocomplete="off">
                    <div class="field">
                        <label class="label">E-mail</label>
                        <div class="input-wrap">
                            <input class="input" type="email" name="email" required>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label">Пароль</label>
                        <div class="input-wrap">
                            <input class="input" type="password" name="password" required>
                            <button type="button" class="eye" data-toggle="password" aria-label="Показать пароль">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/>
                                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="row">
                        <label class="checkbox">
                            <input type="checkbox" name="remember" value="1"> Запомнить меня
                        </label>
                        <a class="link" href="#">Забыли пароль?</a>
                    </div>

                    <button class="button" type="submit">Войти</button>

                    <div class="card-bottom">
                        Нет аккаунта? <a href="register.php">Зарегистрироваться</a>
                    </div>
                </form>

            </div>
        </div>

    </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
