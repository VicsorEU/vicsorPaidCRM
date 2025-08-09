<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;

$name  = trim($_POST['name'] ?? '');
$color = trim($_POST['color'] ?? '#64748b');
if ($name==='') { flash('err','Название доски пустое.'); header('Location: '.url('boards/tasks/kanban.php')); exit; }

$pos = (int)$pdo->query("SELECT COALESCE(MAX(position),0)+10 FROM crm_task_boards")->fetchColumn();
$st = $pdo->prepare("INSERT INTO crm_task_boards (name, position, color) VALUES (:n, :p, :c)");
$st->execute([':n'=>$name, ':p'=>$pos, ':c'=>$color ?: '#64748b']);

flash('ok','Доска создана.');
header('Location: '.url('boards/tasks/kanban.php')); exit;
