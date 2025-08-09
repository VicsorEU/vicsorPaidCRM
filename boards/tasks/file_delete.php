<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;

$id = (int)($_POST['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM crm_task_files WHERE id=:id");
$st->execute([':id'=>$id]); $f=$st->fetch();
if(!$f){ flash('err','Файл не найден'); header('Location: '.url('boards/tasks/task_view.php?id='.(int)($_POST['task_id'] ?? 0))); exit; }

$path = APP_ROOT . '/storage/tasks/' . (int)$f['task_id'] . '/' . $f['stored_name'];
if (is_file($path)) @unlink($path);
$pdo->prepare("DELETE FROM crm_task_files WHERE id=:id")->execute([':id'=>$id]);

flash('ok','Файл удалён.');
header('Location: '.url('boards/tasks/task_view.php?id='.(int)$f['task_id'])); exit;
