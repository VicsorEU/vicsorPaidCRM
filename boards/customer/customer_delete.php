<?php
require_once dirname(__DIR__, 2) . '/inc/util.php';
requireLogin();
require_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: customers.php'); exit; }

global $pdo;
$pdo->prepare("DELETE FROM crm_customers WHERE id=:id")->execute([':id'=>$id]);

flash('ok','Клиент удалён.');
header('Location: customers.php'); exit;
