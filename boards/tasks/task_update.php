<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('err', 'Задача не найдена');
    header('Location: '.url('boards/tasks/kanban.php')); exit;
}

$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$type_id     = (int)($_POST['type_id'] ?? 0) ?: null;
$due_date    = ($_POST['due_date'] ?? '') ?: null;
$assignee_id = (int)($_POST['assignee_id'] ?? 0) ?: null;
$priority    = (int)($_POST['priority'] ?? 0); // <— ВАЖНО: читаем приоритет

if ($title === '') {
    flash('err','Укажите название');
    header('Location: '.url('boards/tasks/task_view.php?id='.$id)); exit;
}

$st = $pdo->prepare("
  UPDATE crm_tasks
     SET title       = :t,
         description = :d,
         type_id     = :ty,
         due_date    = :dd,
         assignee_id = :a,
         priority    = :pr,   -- <— пишем приоритет
         updated_at  = now()
   WHERE id = :id
");
$st->execute([
    ':t'=>$title,
    ':d'=>$description ?: null,
    ':ty'=>$type_id,
    ':dd'=>$due_date,
    ':a'=>$assignee_id,
    ':pr'=>$priority,
    ':id'=>$id
]);

flash('ok','Сохранено.');
header('Location: '.url('boards/tasks/task_view.php?id='.$id)); exit;
