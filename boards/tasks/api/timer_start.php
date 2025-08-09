<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin();
require_csrf();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

// Унифицированное определение текущего пользователя
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
$taskId = (int)($_POST['task_id'] ?? 0);

if ($userId <= 0) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthenticated']); exit; }
if ($taskId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad task_id']); exit; }

try {
    $pdo->beginTransaction();

    // не позволяем больше одного активного таймера на пользователя — гасим предыдущий
    $q = $pdo->prepare("SELECT id FROM crm_task_time WHERE user_id = :u AND stopped_at IS NULL");
    $q->execute([':u'=>$userId]);
    if ($row = $q->fetch()) {
        $pdo->prepare("UPDATE crm_task_time SET stopped_at = now() WHERE id = :id")->execute([':id'=>$row['id']]);
    }

    // старт нового интервала (каждый запуск — отдельная запись)
    $st = $pdo->prepare("INSERT INTO crm_task_time (task_id, user_id) VALUES (:t, :u) RETURNING started_at");
    $st->execute([':t'=>$taskId, ':u'=>$userId]);
    $started_at = $st->fetchColumn();

    $t = $pdo->prepare("SELECT title FROM crm_tasks WHERE id=:id");
    $t->execute([':id'=>$taskId]);
    $title = (string)$t->fetchColumn();

    $pdo->commit();
    echo json_encode(['ok'=>true,'task_id'=>$taskId,'task_title'=>$title,'started_at'=>$started_at]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
