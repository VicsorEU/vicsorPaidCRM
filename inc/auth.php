<?php
// inc/auth.php
require_once __DIR__ . '/config.php';

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $base = rtrim(APP_BASE_URL ?? '/', '/');
        header('Location: ' . ($base === '' ? '/' : $base . '/') . 'login.php');
        exit;
    }
}


function loginUser(string $email, string $password, array &$errors = []): bool {
    global $pdo;

    $email = trim(mb_strtolower($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Неверный e-mail или пароль.';
        return false;
    }
    $stmt = $pdo->prepare("SELECT id, password_hash FROM crm_users WHERE LOWER(email) = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = (int)$user['id'];
        return true;
    }
    $errors[] = 'Неверный e-mail или пароль.';
    return false;
}

function registerUser(array $d, array &$errors = []): bool {
    global $pdo;

    $full  = trim($d['full_name'] ?? '');
    $email = trim(mb_strtolower($d['email'] ?? ''));
    $phone = trim($d['phone'] ?? '');
    $comp  = trim($d['company_name'] ?? '');
    $pass  = (string)($d['password'] ?? '');
    $pass2 = (string)($d['password_confirm'] ?? '');

    if ($full === '')   $errors[] = 'Укажите имя и фамилию.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Неверный e-mail.';
    if ($phone === '')  $errors[] = 'Укажите номер телефона.';
    if ($comp === '')   $errors[] = 'Укажите название компании.';
    if (strlen($pass) < 6) $errors[] = 'Пароль должен быть не менее 6 символов.';
    if ($pass !== $pass2)  $errors[] = 'Пароли не совпадают.';

    if ($errors) return false;

    // дубликат по e-mail
    $dup = $pdo->prepare("SELECT 1 FROM crm_users WHERE LOWER(email) = :email LIMIT 1");
    $dup->execute([':email' => $email]);
    if ($dup->fetch()) {
        $errors[] = 'Пользователь с таким e-mail уже существует.';
        return false;
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);

    // Вставка и возврат id
    $sql = "INSERT INTO crm_users
            (full_name, email, phone, company_name, password_hash)
            VALUES (:full, :email, :phone, :comp, :hash)
            RETURNING id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':full'  => $full,
        ':email' => $email,
        ':phone' => $phone,
        ':comp'  => $comp,
        ':hash'  => $hash,
    ]);
    $id = (int)$stmt->fetchColumn();

    $_SESSION['user_id'] = $id;
    return true;
}
