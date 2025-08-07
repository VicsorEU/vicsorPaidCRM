<?php
require_once __DIR__ . '/inc/auth.php';
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (loginUser($_POST['email'] ?? '', $_POST['password'] ?? '', $errors)) {
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
            <h2>Вход в аккаунт</h2>
            <?php foreach($errors as $e): ?>
                <div class="error"><?=htmlspecialchars($e)?></div>
            <?php endforeach; ?>
            <form method="post">
                <label>E-mail</label>
                <input type="email" name="email" required>
                <label>Пароль</label>
                <input type="password" name="password" required>
                <button type="submit">Войти</button>
                <a class="link" href="register.php">Нет аккаунта? Зарегистрироваться</a>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
