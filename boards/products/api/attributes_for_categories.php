<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

$productId = (int)($_GET['product_id'] ?? 0);
$catParam  = trim((string)($_GET['cat_ids'] ?? '')); // "1,2,3"
$catIds    = array_values(array_filter(array_map('intval', explode(',', $catParam)), fn($v)=>$v>0));
if (!$catIds) { echo json_encode(['ok'=>true,'attributes'=>[]]); exit; }

$in = implode(',', array_map('intval',$catIds));

$attrs = $pdo->query("
  SELECT DISTINCT a.id, a.name, a.code, a.type, a.unit, a.is_required
  FROM crm_attributes a
  JOIN crm_attribute_category ac ON ac.attribute_id = a.id
  WHERE ac.category_id IN ($in)
  ORDER BY a.name
")->fetchAll(PDO::FETCH_ASSOC);

// options
$optSt = $pdo->prepare("SELECT id, value, position FROM crm_attribute_options WHERE attribute_id=:a ORDER BY position, id");
// current values
$valSt = null;
if ($productId>0) {
    $valSt = $pdo->prepare("SELECT * FROM crm_product_attribute_values WHERE product_id=:p AND attribute_id=:a ORDER BY id");
}

$result = [];
foreach ($attrs as $a) {
    $a['options'] = [];
    if (in_array($a['type'], ['select','multiselect'], true)) {
        $optSt->execute([':a'=>$a['id']]);
        $a['options'] = $optSt->fetchAll(PDO::FETCH_ASSOC);
    }
    $a['value'] = null;
    $a['values'] = [];
    if ($productId>0 && $valSt) {
        $valSt->execute([':p'=>$productId, ':a'=>$a['id']]);
        $vals = $valSt->fetchAll(PDO::FETCH_ASSOC);
        if ($a['type']==='multiselect') {
            $a['values'] = array_map('intval', array_column($vals,'option_id'));
        } elseif ($a['type']==='select') {
            $a['value'] = (int)($vals[0]['option_id'] ?? 0);
        } elseif ($a['type']==='number') {
            $a['value'] = $vals ? (float)$vals[0]['value_number'] : null;
        } elseif ($a['type']==='bool') {
            $a['value'] = $vals ? (bool)$vals[0]['value_bool'] : false;
        } elseif ($a['type']==='date') {
            $a['value'] = $vals ? ($vals[0]['value_date']) : null;
        } else { // text
            $a['value'] = $vals ? (string)$vals[0]['value_text'] : '';
        }
    }
    $result[] = $a;
}

echo json_encode(['ok'=>true,'attributes'=>$result]);
