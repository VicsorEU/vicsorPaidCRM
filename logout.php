<?php
require_once __DIR__ . '/inc/config.php';
session_start();
session_unset();
session_destroy();
$base = rtrim(APP_BASE_URL ?? '/', '/');
header('Location: ' . ($base === '' ? '/' : $base . '/') . 'login.php');
exit;
