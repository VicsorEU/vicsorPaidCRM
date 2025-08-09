<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); global $pdo;

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM crm_task_files WHERE id=:id");
$st->execute([':id'=>$id]); $f=$st->fetch();
if (!$f) { http_response_code(404); exit('Not found'); }

$path = APP_ROOT . '/storage/tasks/' . (int)$f['task_id'] . '/' . $f['stored_name'];
if (!is_file($path)) { http_response_code(404); exit('Not found'); }

header('Content-Type: '.($f['mime'] ?: 'application/octet-stream'));
header('Content-Length: '.(string)filesize($path));
header('Content-Disposition: attachment; filename="'.str_replace('"','',$f['orig_name']).'"');
readfile($path);
