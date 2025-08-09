<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;

$id      = (int)($_POST['id'] ?? 0);
$task_id = (int)($_POST['task_id'] ?? 0);
if ($id<=0 || $task_id<=0) { header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit; }

$pdo->prepare("DELETE FROM crm_task_time WHERE id=:id AND task_id=:t")->execute([':id'=>$id, ':t'=>$task_id]);

flash('ok','Интервал удалён.');
header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit;
