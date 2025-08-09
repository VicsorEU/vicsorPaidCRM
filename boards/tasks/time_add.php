<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;

$task_id = (int)($_POST['task_id'] ?? 0);
$start   = trim($_POST['started_at'] ?? '');
$stop    = trim($_POST['stopped_at'] ?? '');
if ($task_id<=0 || !$start || !$stop) { flash('err','Заполните дату начала и конца'); header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit; }

$ts1 = strtotime($start); $ts2 = strtotime($stop);
if (!$ts1 || !$ts2 || $ts2 <= $ts1) { flash('err','Время конца должно быть позже начала'); header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit; }

$st = $pdo->prepare("INSERT INTO crm_task_time (task_id, user_id, started_at, stopped_at) VALUES (:t,:u,:s,:e)");
$st->execute([
    ':t'=>$task_id, ':u'=>($_SESSION['user']['id'] ?? null),
    ':s'=>date('c', $ts1), ':e'=>date('c', $ts2)
]);

flash('ok','Интервал добавлен.');
header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit;
