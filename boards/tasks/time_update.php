<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;

$id      = (int)($_POST['id'] ?? 0);
$task_id = (int)($_POST['task_id'] ?? 0);
$start   = trim($_POST['started_at'] ?? '');
$stop    = trim($_POST['stopped_at'] ?? '');

if ($id<=0 || $task_id<=0 || !$start || !$stop) { flash('err','Проверьте поля'); header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit; }

$ts1 = strtotime($start); $ts2 = strtotime($stop);
if (!$ts1 || !$ts2 || $ts2 <= $ts1) { flash('err','Конец должен быть позже начала'); header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit; }

// запрещаем редактировать «активный» (на всякий)
$chk = $pdo->prepare("SELECT stopped_at FROM crm_task_time WHERE id=:id AND task_id=:t");
$chk->execute([':id'=>$id, ':t'=>$task_id]);
$row = $chk->fetch();
if (!$row) { flash('err','Запись не найдена'); header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit; }
if ($row['stopped_at'] === null) { flash('err','Активный интервал нельзя редактировать'); header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit; }

$u = $pdo->prepare("UPDATE crm_task_time SET started_at=:s, stopped_at=:e WHERE id=:id AND task_id=:t");
$u->execute([':s'=>date('c',$ts1), ':e'=>date('c',$ts2), ':id'=>$id, ':t'=>$task_id]);

flash('ok','Интервал обновлён.');
header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit;
