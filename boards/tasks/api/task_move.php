<?php
// boards/tasks/api/task_move.php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin();
require_csrf();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

$taskId = (int)($_POST['task_id'] ?? 0);
$toBid  = (int)($_POST['to_board_id'] ?? 0);
$prevId = (int)($_POST['prev_id'] ?? 0);
$nextId = (int)($_POST['next_id'] ?? 0);

if ($taskId <= 0 || $toBid <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'bad args']); exit;
}

try {
    $pdo->beginTransaction();

    // Позиции соседей (только если они в этой же целевой доске)
    $getPos = $pdo->prepare("SELECT id, board_id, position FROM crm_tasks WHERE id=:id");
    $prevPos = $nextPos = null;

    if ($prevId > 0) {
        $getPos->execute([':id'=>$prevId]);
        $pr = $getPos->fetch();
        if ($pr && (int)$pr['board_id'] === $toBid) $prevPos = (float)$pr['position'];
    }
    if ($nextId > 0) {
        $getPos->execute([':id'=>$nextId]);
        $nx = $getPos->fetch();
        if ($nx && (int)$nx['board_id'] === $toBid) $nextPos = (float)$nx['position'];
    }

    // Рассчитать новую позицию
    if ($prevPos !== null && $nextPos !== null) {
        $position = ($prevPos + $nextPos) / 2.0;
    } elseif ($prevPos !== null) {
        $position = $prevPos + 100.0;
    } elseif ($nextPos !== null) {
        $position = $nextPos - 100.0;
    } else {
        // пустая колонка или перенос в пустую позицию
        $st = $pdo->prepare("SELECT COALESCE(MAX(position),0)+100.0 FROM crm_tasks WHERE board_id=:b");
        $st->execute([':b'=>$toBid]);
        $position = (float)$st->fetchColumn();
    }

    // Обновление карточки
    $upd = $pdo->prepare("UPDATE crm_tasks SET board_id=:b, position=:p, updated_at=now() WHERE id=:id");
    $upd->execute([':b'=>$toBid, ':p'=>$position, ':id'=>$taskId]);

    $pdo->commit();
    echo json_encode(['ok'=>true, 'position'=>$position]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
