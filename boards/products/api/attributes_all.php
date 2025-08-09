<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

$productId = (int)($_GET['product_id'] ?? 0);

$attrs = $pdo->query("SELECT id,name,code,type FROM crm_attributes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// options for select-types
if ($attrs) {
    $ids = implode(',', array_map('intval', array_column($attrs,'id')));
    $optRows = $pdo->query("SELECT id,attribute_id,value,position FROM crm_attribute_options WHERE attribute_id IN ($ids) ORDER BY attribute_id, position, id")->fetchAll(PDO::FETCH_ASSOC);
    $byAttr = [];
    foreach ($optRows as $o) $byAttr[$o['attribute_id']][] = $o;
    foreach ($attrs as &$a) $a['options'] = $byAttr[$a['id']] ?? [];
    unset($a);
}

$values = [];
if ($productId>0) {
    $st = $pdo->prepare("SELECT * FROM crm_product_attribute_values WHERE product_id=:p ORDER BY id");
    $st->execute([':p'=>$productId]);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $aid = (int)$r['attribute_id'];
        $t = null;
        if ($r['option_id'] !== null) {
            // select/multiselect
            $values[$aid]['type'] = $values[$aid]['type'] ?? 'select';
            if (!isset($values[$aid]['values'])) $values[$aid]['values'] = [];
            $values[$aid]['values'][] = (int)$r['option_id'];
        } elseif ($r['value_number'] !== null) {
            $values[$aid] = ['type'=>'number','value'=>(float)$r['value_number']];
        } elseif ($r['value_bool'] !== null) {
            $values[$aid] = ['type'=>'bool','value'=> (bool)$r['value_bool']];
        } elseif ($r['value_date'] !== null) {
            $values[$aid] = ['type'=>'date','value'=> $r['value_date']];
        } else {
            $values[$aid] = ['type'=>'text','value'=> (string)$r['value_text']];
        }
    }
}

echo json_encode(['ok'=>true,'attributes'=>$attrs,'values'=>$values]);
