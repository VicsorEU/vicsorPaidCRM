<?php
// inc/config.php
// Старт сессии + безопасные параметры cookie
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Подключение к PostgreSQL
$dbHost = 'ifjhb228.psql.tools';
$dbPort = '10228';
$dbName = 'vicsorcrm';
$dbUser = 'vicsorcrm';
$dbPass = 'd565d42bgy';

try {
    $pdo = new PDO(
        "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('DB connection error');
}

define('APP_BASE_URL', '/');
if (!function_exists('url')) {
    function url(string $path): string {
        return rtrim(APP_BASE_URL, '/') . '/' . ltrim($path, '/');
    }
}
if (!function_exists('asset')) {
    function asset(string $path): string {
        return url('assets/' . ltrim($path, '/'));
    }
}