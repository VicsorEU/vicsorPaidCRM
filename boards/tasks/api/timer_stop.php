<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin();
require_csrf();
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
if ($userId <= 0) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthenticated']); exit; }

try {
    $pdo->beginTransaction();

    $q = $pdo->prepare("SELECT id FROM crm_task_time WHERE user_id=:u AND stopped_at IS NULL");
    $q->execute([':u'=>$userId]);
    if ($row = $q->fetch()) {
        $pdo->prepare("UPDATE crm_task_time SET stopped_at = now() WHERE id = :id")->execute([':id'=>$row['id']]);
    }

    $pdo->commit();
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
