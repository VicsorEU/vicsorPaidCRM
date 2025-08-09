<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin(); require_csrf();
global $pdo;

$id   = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$code = trim((string)($_POST['code'] ?? ''));
$type = trim((string)($_POST['type'] ?? 'text'));

if ($name==='' || !in_array($type, ['text','number','bool','select','multiselect','date'], true)) {
    flash('err','Заполните обязательные поля'); header('Location: '.url('boards/products/attributes.php')); exit;
}

try {
    $pdo->beginTransaction();

    if ($id>0) {
        $st = $pdo->prepare("UPDATE crm_attributes
      SET name=:n, code=NULLIF(:c,''), type=:t, updated_at=now()
      WHERE id=:id");
        $st->execute([':n'=>$name, ':c'=>$code, ':t'=>$type, ':id'=>$id]);
    } else {
        $st = $pdo->prepare("INSERT INTO crm_attributes(name,code,type)
                         VALUES (:n, NULLIF(:c,''), :t) RETURNING id");
        $st->execute([':n'=>$name, ':c'=>$code, ':t'=>$type]);
        $id = (int)$st->fetchColumn();
    }

    // Опции для select/multiselect
    $opt_ids  = $_POST['opt_id'] ?? [];
    $opt_vals = $_POST['opt_value'] ?? [];
    $opt_pos  = $_POST['opt_position'] ?? [];
    if (in_array($type, ['select','multiselect'], true)) {
        $existing = $pdo->prepare("SELECT id FROM crm_attribute_options WHERE attribute_id=:a");
        $existing->execute([':a'=>$id]);
        $existingIds = array_map('intval', array_column($existing->fetchAll(PDO::FETCH_ASSOC),'id'));
        $seen = [];

        $max = max(count($opt_vals), count($opt_ids));
        for ($i=0; $i<$max; $i++) {
            $oid = isset($opt_ids[$i]) ? (int)$opt_ids[$i] : 0;
            $val = trim((string)($opt_vals[$i] ?? ''));
            $pos = (int)($opt_pos[$i] ?? 0);
            if ($val === '') continue;

            if ($oid>0) {
                $seen[] = $oid;
                $st = $pdo->prepare("UPDATE crm_attribute_options
                             SET value=:v, position=:p
                             WHERE id=:id AND attribute_id=:a");
                $st->execute([':v'=>$val, ':p'=>$pos, ':id'=>$oid, ':a'=>$id]);
            } else {
                $st = $pdo->prepare("INSERT INTO crm_attribute_options(attribute_id,value,position)
                             VALUES (:a,:v,:p)");
                $st->execute([':a'=>$id, ':v'=>$val, ':p'=>$pos]);
            }
        }
        $toDel = array_diff($existingIds, $seen);
        if ($toDel) {
            $pdo->exec("DELETE FROM crm_attribute_options WHERE attribute_id=$id AND id IN (".implode(',',array_map('intval',$toDel)).")");
        }
    } else {
        $pdo->prepare("DELETE FROM crm_attribute_options WHERE attribute_id=:a")->execute([':a'=>$id]);
    }

    $pdo->commit();
    flash('ok','Атрибут сохранён');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('err','Ошибка: '.$e->getMessage());
}
header('Location: '.url('boards/products/attributes.php')); exit;
