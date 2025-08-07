<?php
// inc/auth.php
require_once __DIR__ . '/config.php';

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function loginUser(string $email, string $password, array &$errors = []): bool {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, password_hash FROM crm_users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        return true;
    }
    $errors[] = 'Неверный e-mail или пароль.';
    return false;
}

function registerUser(array $data, array &$errors = []): bool {
    global $pdo;
    // валидация
    foreach (['last_name','first_name','email','phone','company','domain','password','password_confirm'] as $f) {
        if (empty($data[$f])) {
            $errors[] = "Заполните «{$f}».";
        }
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Неправильный e-mail.';
    }
    if ($data['password'] !== $data['password_confirm']) {
        $errors[] = 'Пароли не совпадают.';
    }
    if ($errors) return false;

    // проверяем дубликаты
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_users WHERE email = :email");
    $stmt->execute([':email'=>$data['email']]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Пользователь с таким e-mail уже существует.';
        return false;
    }

    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $sql = "INSERT INTO crm_users
      (last_name, first_name, email, phone, company_name, domain_name, password_hash)
      VALUES
      (:last_name, :first_name, :email, :phone, :company, :domain, :hash)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':last_name'=>$data['last_name'],
        ':first_name'=>$data['first_name'],
        ':email'=>$data['email'],
        ':phone'=>$data['phone'],
        ':company'=>$data['company'],
        ':domain'=>$data['domain'],
        ':hash'=>$hash,
    ]);
    $_SESSION['user_id'] = $pdo->lastInsertId();
    return true;
}
