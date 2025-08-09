<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;

$task_id = (int)($_POST['task_id'] ?? 0);
if ($task_id<=0 || empty($_FILES['file']['tmp_name'])) {
    flash('err','Файл не выбран'); header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit;
}

$upl = $_FILES['file'];
if ($upl['error'] !== UPLOAD_ERR_OK) { flash('err','Ошибка загрузки'); header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit; }

$orig = $upl['name'];
$mime = $upl['type'] ?? null;
$size = (int)$upl['size'];

$base = APP_ROOT . '/storage/tasks/' . $task_id;
if (!is_dir($base)) mkdir($base, 0775, true);

$ext = pathinfo($orig, PATHINFO_EXTENSION);
$stored = bin2hex(random_bytes(16)) . ($ext ? ('.'.$ext) : '.bin');
$path = $base . '/' . $stored;

if (!move_uploaded_file($upl['tmp_name'], $path)) {
    flash('err','Не удалось сохранить файл'); header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit;
}

$st = $pdo->prepare("INSERT INTO crm_task_files (task_id, user_id, stored_name, orig_name, mime, size_bytes)
                     VALUES (:t, :u, :s, :o, :m, :sz)");
$st->execute([
    ':t'=>$task_id, ':u'=>($_SESSION['user']['id'] ?? null),
    ':s'=>$stored, ':o'=>$orig, ':m'=>$mime, ':sz'=>$size
]);

flash('ok','Файл добавлен.');
header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit;
