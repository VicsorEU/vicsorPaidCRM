<?php
require_once dirname(__DIR__, 2) . '/inc/util.php';
requireLogin();
require_csrf();

$id           = (int)($_POST['id'] ?? 0);
$full_name    = trim($_POST['full_name'] ?? '');
$email        = trim(mb_strtolower($_POST['email'] ?? ''));
$phone        = trim($_POST['phone'] ?? '');
$company_name = trim($_POST['company_name'] ?? '');

if ($full_name === '') {
    flash('err','Укажите имя и фамилию.');
    header('Location: '.($id?'customer_edit.php?id='.$id:'customer_new.php')); exit;
}

global $pdo;

// Проверка дубликата e-mail (если задан)
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $sql = "SELECT id FROM crm_customers WHERE LOWER(email)=:email".($id?' AND id<>:id':'')." LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':email',$email);
    if ($id) $stmt->bindValue(':id',$id, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->fetch()) {
        flash('err','Клиент с таким e-mail уже существует.');
        header('Location: '.($id?'customer_edit.php?id='.$id:'customer_new.php')); exit;
    }
} elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('err','Неверный e-mail.');
    header('Location: '.($id?'customer_edit.php?id='.$id:'customer_new.php')); exit;
}

if ($id) {
    $stmt = $pdo->prepare("UPDATE crm_customers
        SET full_name=:full, email=:email, phone=:phone, company_name=:comp
        WHERE id=:id");
    $stmt->execute([
        ':full'=>$full_name, ':email'=>($email?:null), ':phone'=>($phone?:null),
        ':comp'=>($company_name?:null), ':id'=>$id
    ]);
    flash('ok','Клиент обновлён.');
} else {
    $stmt = $pdo->prepare("INSERT INTO crm_customers (full_name, email, phone, company_name)
                           VALUES (:full, :email, :phone, :comp)");
    $stmt->execute([
        ':full'=>$full_name, ':email'=>($email?:null), ':phone'=>($phone?:null),
        ':comp'=>($company_name?:null)
    ]);
    flash('ok','Клиент создан.');
}

header('Location: customers.php'); exit;
