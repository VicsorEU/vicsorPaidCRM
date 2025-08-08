<?php
require_once __DIR__ . '/inc/auth.php';

if (isLoggedIn()) {
    header('Location: index.php'); exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = registerUser([
            'full_name'        => $_POST['full_name']        ?? '',
            'email'            => $_POST['email']            ?? '',
            'phone'            => $_POST['phone']            ?? '',
            'company_name'     => $_POST['company_name']     ?? '',
            'password'         => $_POST['password']         ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
    ], $errors);

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

                <h1>Регистрация</h1>
                <div class="sub">Создайте аккаунт для работы в системе</div>

                <?php foreach($errors as $e): ?>
                    <div class="alert error"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>

                <form method="post" autocomplete="off">
                    <div class="field">
                        <label class="label">Имя и фамилия</label>
                        <input class="input" type="text" name="full_name" required>
                    </div>

                    <div class="field">
                        <label class="label">E-mail</label>
                        <input class="input" type="email" name="email" required>
                    </div>

                    <div class="field">
                        <label class="label">Телефон</label>
                        <input class="input" type="text" name="phone" required>
                    </div>

                    <div class="field">
                        <label class="label">Название компании</label>
                        <input class="input" type="text" name="company_name" required>
                    </div>

                    <div class="field">
                        <label class="label">Пароль</label>
                        <div class="input-wrap">
                            <input class="input" type="password" name="password" required minlength="6">
                            <button type="button" class="eye" data-toggle="password" aria-label="Показать пароль">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/>
                                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label">Повторите пароль</label>
                        <input class="input" type="password" name="password_confirm" required minlength="6">
                    </div>

                    <button class="button" type="submit">Зарегистрироваться</button>

                    <div class="card-bottom">
                        Уже есть аккаунт? <a href="login.php">Войти</a>
                    </div>
                </form>

            </div>
        </div>

    </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
