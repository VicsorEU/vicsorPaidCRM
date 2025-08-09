<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;

$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest');

$board_id    = (int)($_POST['board_id'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$desc        = trim($_POST['description'] ?? '');
$type_id     = (int)($_POST['type_id'] ?? 0) ?: null;
$due_date    = ($_POST['due_date'] ?? '') ?: null;
$assignee_id = (int)($_POST['assignee_id'] ?? 0) ?: null;
$priority    = (int)($_POST['priority'] ?? 0);
$comment     = trim($_POST['comment'] ?? '');

if ($title === '') {
    if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'title required']); exit; }
    flash('err','Укажите название задачи.'); header('Location: '.url('boards/tasks/kanban.php')); exit;
}
if ($board_id <= 0) {
    $board_id = (int)$pdo->query("SELECT id FROM crm_task_boards ORDER BY position, id LIMIT 1")->fetchColumn();
    if ($board_id <= 0) {
        if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no boards']); exit; }
        flash('err','Сначала создайте доску.'); header('Location: '.url('boards/tasks/kanban.php')); exit;
    }
}

$pos = $pdo->prepare("SELECT COALESCE(MAX(position),0)+100 FROM crm_tasks WHERE board_id=:b");
$pos->execute([':b'=>$board_id]); $position = (float)$pos->fetchColumn();

$pdo->beginTransaction();
try {
    // Задача
    $st = $pdo->prepare("INSERT INTO crm_tasks
      (board_id, title, description, type_id, due_date, assignee_id, priority, position, created_by)
      VALUES (:b,:t,:d,:ty,:dd,:a,:pr,:p,:u)
      RETURNING id, position");
    $st->execute([
        ':b'=>$board_id, ':t'=>$title, ':d'=>$desc ?: null, ':ty'=>$type_id, ':dd'=>$due_date,
        ':a'=>$assignee_id, ':pr'=>$priority, ':p'=>$position,
        ':u'=>($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? null)
    ]);
    $row = $st->fetch(); $id = (int)$row['id']; $position = (float)$row['position'];

    // Первый комментарий (если есть)
    if ($comment !== '') {
        $c = $pdo->prepare("INSERT INTO crm_task_comments (task_id, user_id, body) VALUES (:t,:u,:b)");
        $c->execute([':t'=>$id, ':u'=>($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? null), ':b'=>$comment]);
    }

    // Файлы (множественный инпут files[])
    if (!empty($_FILES['files']) && is_array($_FILES['files']['tmp_name'])) {
        $dir = APP_ROOT.'/storage/tasks/'.$id;
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $count = count($_FILES['files']['tmp_name']);
        for ($i=0; $i<$count; $i++) {
            if (($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $tmp  = $_FILES['files']['tmp_name'][$i];
            $orig = $_FILES['files']['name'][$i];
            $mime = $_FILES['files']['type'][$i] ?? null;
            $size = (int)($_FILES['files']['size'][$i] ?? 0);

            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $stored = bin2hex(random_bytes(16)).($ext?'.'.$ext:'.bin');
            if (move_uploaded_file($tmp, $dir.'/'.$stored)) {
                $p = $pdo->prepare("INSERT INTO crm_task_files (task_id, user_id, stored_name, orig_name, mime, size_bytes)
                            VALUES (:t,:u,:s,:o,:m,:sz)");
                $p->execute([':t'=>$id, ':u'=>($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? null),
                    ':s'=>$stored, ':o'=>$orig, ':m'=>$mime, ':sz'=>$size]);
            }
        }
    }

    $pdo->commit();

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'=>true,
            'id'=>$id,
            'board_id'=>$board_id,
            'position'=>$position,
            'title'=>$title,
            'description'=>$desc,
            'due_date'=>$due_date,
            'priority'=>$priority
        ]);
        exit;
    }

    flash('ok','Задача создана.');
    header('Location: '.url('boards/tasks/kanban.php')); exit;

} catch(Throwable $e){
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($isAjax) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit; }
    flash('err','Ошибка: '.$e->getMessage()); header('Location: '.url('boards/tasks/kanban.php')); exit;
}
