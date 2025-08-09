<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;

$id      = (int)($_POST['id'] ?? 0);
$task_id = (int)($_POST['task_id'] ?? 0);
$body    = trim($_POST['body'] ?? '');

if ($id<=0 || $task_id<=0 || $body==='') {
    flash('err','Введите текст комментария');
    header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit;
}

// (опционально) проверка, что коммент принадлежит этой задаче
$chk = $pdo->prepare("SELECT id FROM crm_task_comments WHERE id=:id AND task_id=:t");
$chk->execute([':id'=>$id, ':t'=>$task_id]);
if (!$chk->fetch()) { flash('err','Комментарий не найден'); header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit; }

$u = $pdo->prepare("UPDATE crm_task_comments SET body=:b WHERE id=:id");
$u->execute([':b'=>$body, ':id'=>$id]);

flash('ok','Комментарий обновлён.');
header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit;
