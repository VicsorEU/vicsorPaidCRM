<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf();
require_once APP_ROOT.'/inc/inventory.php';
global $pdo;

$id=(int)($_POST['id']??0);
if($id<=0){ header('Location: '.url('boards/inventory/movements.php')); exit; }

$pdo->beginTransaction();
try{
    stock_revert_move($pdo, $id);
    $pdo->prepare("DELETE FROM crm_stock_moves WHERE id=:id")->execute([':id'=>$id]);
    $pdo->commit();
    flash('ok','Документ удалён, остатки откатены.');
}catch(Throwable $e){
    $pdo->rollBack();
    flash('err','Ошибка удаления: '.$e->getMessage());
}
header('Location: '.url('boards/inventory/movements.php')); exit;
