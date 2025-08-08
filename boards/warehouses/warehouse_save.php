<?php // boards/warehouses/warehouse_save.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
require_csrf();
global $pdo;
$id=(int)($_POST['id']??0);
$code=trim($_POST['code']??''); $name=trim($_POST['name']??''); $addr=trim($_POST['address']??'');
if($code===''||$name===''){ flash('err','Заполните код и название'); header('Location: '.($id?url('boards/warehouses/warehouse_edit.php?id='.$id):url('boards/warehouses/warehouse_new.php'))); exit; }
try{
    if($id){
        $pdo->prepare("UPDATE crm_warehouses SET code=:c,name=:n,address=:a WHERE id=:id")->execute([':c'=>$code,':n'=>$name,':a'=>$addr,':id'=>$id]);
        flash('ok','Склад обновлён.');
        header('Location: '.url('boards/warehouses/warehouse_edit.php?id='.$id)); exit;
    }else{
        $pdo->prepare("INSERT INTO crm_warehouses (code,name,address) VALUES (:c,:n,:a)")->execute([':c'=>$code,':n'=>$name,':a'=>$addr]);
        flash('ok','Склад создан.');
        header('Location: '.url('boards/warehouses/warehouses.php')); exit;
    }
}catch(Throwable $e){ flash('err','Ошибка: '.$e->getMessage()); header('Location: '.($id?url('boards/warehouses/warehouse_edit.php?id='.$id):url('boards/warehouses/warehouse_new.php'))); exit; }
