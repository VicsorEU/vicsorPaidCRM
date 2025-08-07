<?php
require_once __DIR__ . '/inc/auth.php';
requireLogin();
require __DIR__ . '/inc/header.php';
?>
<div style="padding:20px;">
    <h1>Добро пожаловать в CRM!</h1>
    <p>Вы вошли как пользователь #<?=htmlspecialchars($_SESSION['user_id'])?></p>
    <p><a href="logout.php">Выйти</a></p>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
