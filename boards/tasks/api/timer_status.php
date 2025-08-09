<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

function current_user_id(): int {
    return (int)(
        $_SESSION['user']['id']
        ?? $_SESSION['user_id']
        ?? $_SESSION['auth']['id']
        ?? $_SESSION['auth_user']['id']
        ?? 0
    );
}

$userId = current_user_id();
if ($userId <= 0) { echo json_encode(['running'=>false]); exit; }

$q = $pdo->prepare("
    SELECT tt.task_id, tt.started_at, t.title
    FROM crm_task_time tt
    JOIN crm_tasks t ON t.id = tt.task_id
    WHERE tt.user_id = :u AND tt.stopped_at IS NULL
");
$q->execute([':u'=>$userId]);

if ($row = $q->fetch()) {
    echo json_encode([
        'running'    => true,
        'task_id'    => (int)$row['task_id'],
        'task_title' => (string)$row['title'],
        'started_at' => $row['started_at'],
    ]);
} else {
    echo json_encode(['running'=>false]);
}
