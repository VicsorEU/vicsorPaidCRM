<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); require_csrf();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

$productId = (int)($_POST['product_id'] ?? 0);
$payload   = (string)($_POST['attrs'] ?? '[]');
$items     = json_decode($payload, true);

if ($productId<=0 || !is_array($items)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad args']); exit; }

try {
    $pdo->beginTransaction();

    // Полностью очищаем все значения атрибутов для товара
    $pdo->prepare("DELETE FROM crm_product_attribute_values WHERE product_id=:p")->execute([':p'=>$productId]);

    // И записываем из payload
    foreach ($items as $it) {
        $attrId = (int)($it['id'] ?? 0);
        $type   = (string)($it['type'] ?? 'text');
        if ($attrId<=0) continue;

        if ($type === 'multiselect') {
            $ids = array_map('intval', $it['values'] ?? []);
            if ($ids) {
                $ins=$pdo->prepare("INSERT INTO crm_product_attribute_values(product_id,attribute_id,option_id) VALUES (:p,:a,:o)");
                foreach ($ids as $oid) $ins->execute([':p'=>$productId, ':a'=>$attrId, ':o'=>$oid]);
            }
        } elseif ($type === 'select') {
            $oid = (int)($it['value'] ?? 0);
            if ($oid>0) {
                $pdo->prepare("INSERT INTO crm_product_attribute_values(product_id,attribute_id,option_id) VALUES (:p,:a,:o)")
                    ->execute([':p'=>$productId, ':a'=>$attrId, ':o'=>$oid]);
            }
        } elseif ($type === 'number') {
            if ($it['value'] !== '' && $it['value'] !== null) {
                $pdo->prepare("INSERT INTO crm_product_attribute_values(product_id,attribute_id,value_number) VALUES (:p,:a,:v)")
                    ->execute([':p'=>$productId, ':a'=>$attrId, ':v'=>(float)$it['value']]);
            }
        } elseif ($type === 'bool') {
            $pdo->prepare("INSERT INTO crm_product_attribute_values(product_id,attribute_id,value_bool) VALUES (:p,:a,:v)")
                ->execute([':p'=>$productId, ':a'=>$attrId, ':v'=> !empty($it['value']) ? 1 : 0 ]);
        } elseif ($type === 'date') {
            if (!empty($it['value'])) {
                $pdo->prepare("INSERT INTO crm_product_attribute_values(product_id,attribute_id,value_date) VALUES (:p,:a,:v::date)")
                    ->execute([':p'=>$productId, ':a'=>$attrId, ':v'=>$it['value']]);
            }
        } else { // text
            if (($it['value'] ?? '') !== '') {
                $pdo->prepare("INSERT INTO crm_product_attribute_values(product_id,attribute_id,value_text) VALUES (:p,:a,:v)")
                    ->execute([':p'=>$productId, ':a'=>$attrId, ':v'=>$it['value']]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
