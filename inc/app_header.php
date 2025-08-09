<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <script>
        window.APP_BASE_URL = "<?= rtrim(APP_BASE_URL ?? '', '/') ?>"; // напр. "" или "/paidcrm"
    </script>

    <title>VicsorCRM — Дашборд</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
