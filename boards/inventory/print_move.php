<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
global $pdo;

$id = (int)($_GET['id'] ?? 0);
if ($id<=0){ http_response_code(404); exit('Not found'); }

$m = $pdo->prepare("SELECT m.*, 
                           w.name  AS wh, 
                           ws.name AS whs, 
                           wd.name AS whd
                    FROM crm_stock_moves m
                    LEFT JOIN crm_warehouses w  ON w.id=m.warehouse_id
                    LEFT JOIN crm_warehouses ws ON ws.id=m.src_warehouse_id
                    LEFT JOIN crm_warehouses wd ON wd.id=m.dest_warehouse_id
                    WHERE m.id=:id");
$m->execute([':id'=>$id]); $mv = $m->fetch();
if (!$mv){ http_response_code(404); exit('Not found'); }

$it = $pdo->prepare("SELECT * FROM crm_stock_move_items WHERE move_id=:id ORDER BY id");
$it->execute([':id'=>$id]); $rows=$it->fetchAll();

$DOCN = (int)$mv['id'];
$DATE = date('d.m.Y', strtotime($mv['created_at']));
$TYPE = $mv['doc_type'];
$TITLE = ['out'=>'Накладная на отпуск (списание)','in'=>'Акт возврата на склад','transfer'=>'Акт перемещения','adjust'=>'Акт корректировки'][$TYPE] ?? 'Документ склада';

/** Реквизиты компании — настроить под себя в inc/config.php при желании */
$COMPANY = defined('COMPANY_NAME') ? COMPANY_NAME : 'VicsorCRM';
$C_ADDR  = defined('COMPANY_ADDR') ? COMPANY_ADDR : '';
$C_TEL   = defined('COMPANY_PHONE') ? COMPANY_PHONE : '';
$ORDER_ID= (int)($mv['order_id'] ?? 0);
$WHLINE = ($TYPE==='transfer')
    ? ($mv['whs'].' → '.$mv['whd'])
    : ($mv['wh'] ?: '');

$TOTAL = 0.0; foreach($rows as $r){ $TOTAL += (float)$r['line_total']; }

?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($TITLE) ?> №<?= $DOCN ?> от <?= $DATE ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/print.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
<div class="print-doc">
    <div class="print-head">
        <div>
            <div class="brand"><?= htmlspecialchars($COMPANY) ?></div>
            <?php if ($C_ADDR): ?><div class="print-meta"><?= htmlspecialchars($C_ADDR) ?></div><?php endif; ?>
            <?php if ($C_TEL): ?><div class="print-meta">Тел.: <?= htmlspecialchars($C_TEL) ?></div><?php endif; ?>
        </div>
        <div class="no-print">
            <a class="btn print" href="#" onclick="window.print();return false;">Печать</a>
            <a class="btn" href="<?= url('boards/inventory/movement_edit.php?id='.$DOCN) ?>">Назад</a>
        </div>
    </div>

    <h1 class="print-title"><?= htmlspecialchars($TITLE) ?></h1>
    <div class="print-meta">Документ № <b><?= $DOCN ?></b> от <?= $DATE ?></div>
    <?php if ($ORDER_ID): ?>
        <div class="print-meta">Основание: заказ № <?= (int)$ORDER_ID ?></div>
    <?php endif; ?>
    <div class="print-meta">Склад(ы): <?= htmlspecialchars($WHLINE) ?></div>
    <?php if ($mv['ttn_number'] || $mv['ttn_date']): ?>
        <div class="print-meta">ТТН: <?= htmlspecialchars($mv['ttn_number'] ?? '') ?> от <?= $mv['ttn_date'] ? date('d.m.Y', strtotime($mv['ttn_date'])) : '' ?></div>
    <?php endif; ?>
    <?php if ($mv['notes']): ?>
        <div class="print-meta">Примечание: <?= htmlspecialchars($mv['notes']) ?></div>
    <?php endif; ?>

    <table class="print-table">
        <thead>
        <tr>
            <th style="width:34px;">№</th>
            <th>Наименование</th>
            <th style="width:110px;">SKU</th>
            <th style="width:70px;">Ед.</th>
            <th style="width:90px;">Кол-во</th>
            <th style="width:110px;">Цена</th>
            <th style="width:120px;">Сумма</th>
        </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach($rows as $r): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['sku'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['unit'] ?? 'шт') ?></td>
                <td><?= number_format((float)$r['qty'],3,'.',' ') ?></td>
                <td><?= number_format((float)$r['unit_price'],2,'.',' ') ?></td>
                <td><?= number_format((float)$r['line_total'],2,'.',' ') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="7" style="text-align:center;">Нет позиций</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="print-sum">Итого по документу: <b><?= number_format($TOTAL,2,'.',' ') ?></b></div>

    <div class="signs">
        <div><div>Отпустил / Ответственный</div><div class="line"></div></div>
        <div><div>Принял</div><div class="line"></div></div>
    </div>
</div>
</body>
</html>
