<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('err','Задача не найдена.');
    header('Location: '.url('boards/tasks/kanban.php')); exit;
}

try {
    $pdo->beginTransaction();

    // Удалить тайм-трекинг
    $pdo->prepare("DELETE FROM crm_task_time WHERE task_id=:t")->execute([':t'=>$id]);

    // Удалить комментарии
    $pdo->prepare("DELETE FROM crm_task_comments WHERE task_id=:t")->execute([':t'=>$id]);

    // Файлы: удалить записи и физические файлы
    $files = $pdo->prepare("SELECT stored_name FROM crm_task_files WHERE task_id=:t");
    $files->execute([':t'=>$id]);
    $dir = APP_ROOT . '/storage/tasks/' . $id;
    while ($row = $files->fetch()) {
        $path = $dir . '/' . $row['stored_name'];
        if (is_file($path)) @unlink($path);
    }
    $pdo->prepare("DELETE FROM crm_task_files WHERE task_id=:t")->execute([':t'=>$id]);
    if (is_dir($dir)) {
        @rmdir($dir); // удалим папку, если пуста
    }

    // Удалить саму задачу
    $pdo->prepare("DELETE FROM crm_tasks WHERE id=:t")->execute([':t'=>$id]);

    $pdo->commit();
    flash('ok','Задача удалена.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('err','Ошибка удаления: '.$e->getMessage());
}

header('Location: '.url('boards/tasks/kanban.php')); exit;
