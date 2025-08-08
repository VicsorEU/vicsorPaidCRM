<?php // boards/warehouses/warehouse_delete.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf(); global $pdo;
$id=(int)($_POST['id']??0);
if($id>0){ $pdo->prepare("DELETE FROM crm_warehouses WHERE id=:id")->execute([':id'=>$id]); flash('ok','Склад удалён.'); }
header('Location: '.url('boards/warehouses/warehouses.php')); exit;
