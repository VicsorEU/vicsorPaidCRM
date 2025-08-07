<?php
require_once __DIR__ . '/inc/auth.php';
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (registerUser([
        'last_name'=>$_POST['last_name'] ?? '',
        'first_name'=>$_POST['first_name'] ?? '',
        'email'=>$_POST['email'] ?? '',
        'phone'=>$_POST['phone'] ?? '',
        'company'=>$_POST['company_name'] ?? '',
        'domain'=>$_POST['domain_name'] ?? '',
        'password'=>$_POST['password'] ?? '',
        'password_confirm'=>$_POST['password_confirm'] ?? '',
    ], $errors)) {
        header('Location: index.php');
        exit;
    }
}

require __DIR__ . '/inc/header.php';
?>
<div class="container">
    <div class="left">
        <div class="logo">KeyCRM</div>
    </div>
    <div class="right">
        <div class="form-wrapper">
            <h2>Регистрация</h2>
            <?php foreach($errors as $e): ?>
                <div class="error"><?=htmlspecialchars($e)?></div>
            <?php endforeach; ?>
            <form method="post">
                <label>Фамилия</label>
                <input type="text" name="last_name" required>
                <label>Имя</label>
                <input type="text" name="first_name" required>
                <label>E-mail</label>
                <input type="email" name="email" required>
                <label>Телефон</label>
                <input type="text" name="phone" required>
                <label>Компания</label>
                <input type="text" name="company_name" required>
                <label>Домен</label>
                <input type="text" name="domain_name" required>
                <label>Пароль</label>
                <input type="password" name="password" required>
                <label>Повторите пароль</label>
                <input type="password" name="password_confirm" required>
                <button type="submit">Зарегистрироваться</button>
                <a class="link" href="login.php">Уже есть аккаунт? Войти</a>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
