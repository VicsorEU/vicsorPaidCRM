<?php
// inc/config.php
session_start();

$dbHost = 'ifjhb228.psql.tools';
$dbPort = '10228';
$dbName = 'vicsorcrm';
$dbUser = 'vicsorcrm';
$dbPass = 'd565d42bgy';

try {
    $pdo = new PDO(
        "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
