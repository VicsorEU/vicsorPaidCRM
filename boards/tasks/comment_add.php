<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;

$task_id = (int)($_POST['task_id'] ?? 0);
$body = trim($_POST['body'] ?? '');
if ($task_id<=0 || $body===''){ flash('err','Введите текст'); header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit; }

$st = $pdo->prepare("INSERT INTO crm_task_comments (task_id, user_id, body) VALUES (:t,:u,:b)");
$st->execute([':t'=>$task_id, ':u'=>($_SESSION['user']['id'] ?? null), ':b'=>$body]);

flash('ok','Комментарий добавлен.');
header('Location: '.url('boards/tasks/task_view.php?id='.$task_id)); exit;
