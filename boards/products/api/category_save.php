<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
requireLogin(); require_csrf();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

$action = $_POST['action'] ?? '';
try {
    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $parent_id = $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
        if ($name === '') throw new RuntimeException('name empty');

        $pos = 0;
        if ($parent_id) {
            $st = $pdo->prepare("SELECT COALESCE(MAX(position),0)+1 FROM crm_product_categories WHERE parent_id=:p");
            $st->execute([':p'=>$parent_id]);
            $pos = (int)$st->fetchColumn();
        } else {
            $st = $pdo->query("SELECT COALESCE(MAX(position),0)+1 FROM crm_product_categories WHERE parent_id IS NULL");
            $pos = (int)$st->fetchColumn();
        }

        $st = $pdo->prepare("INSERT INTO crm_product_categories(name,slug,description,parent_id,position)
                         VALUES (:n,NULLIF(:s,''),NULLIF(:d,''),:p,:pos) RETURNING id,slug,description");
        $st->execute([':n'=>$name, ':s'=>$slug, ':d'=>$desc, ':p'=>$parent_id, ':pos'=>$pos]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['ok'=>true,'id'=>(int)$r['id'],'slug'=>$r['slug'],'description'=>$r['description'],'position'=>$pos]); exit;
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        if ($id<=0 || $name==='') throw new RuntimeException('bad args');

        $st = $pdo->prepare("UPDATE crm_product_categories
                         SET name=:n, slug=NULLIF(:s,''), description=NULLIF(:d,''), updated_at=now()
                         WHERE id=:id");
        $st->execute([':n'=>$name, ':s'=>$slug, ':d'=>$desc, ':id'=>$id]);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'move') {
        $id = (int)$_POST['id'];
        $newParent = $_POST['parent_id']!=='' ? (int)$_POST['parent_id'] : null;
        if ($id<=0) throw new RuntimeException('bad id');

        $pos = 0;
        if ($newParent) {
            $st = $pdo->prepare("SELECT COALESCE(MAX(position),0)+1 FROM crm_product_categories WHERE parent_id=:p");
            $st->execute([':p'=>$newParent]);
            $pos = (int)$st->fetchColumn();
        } else {
            $st = $pdo->query("SELECT COALESCE(MAX(position),0)+1 FROM crm_product_categories WHERE parent_id IS NULL");
            $pos = (int)$st->fetchColumn();
        }

        $st = $pdo->prepare("UPDATE crm_product_categories SET parent_id=:p, position=:pos, updated_at=now() WHERE id=:id");
        $st->execute([':p'=>$newParent, ':pos'=>$pos, ':id'=>$id]);
        echo json_encode(['ok'=>true,'position'=>$pos]); exit;
    }

    if ($action === 'reorder') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $pdo->beginTransaction();
        $i = 1;
        $st = $pdo->prepare("UPDATE crm_product_categories SET position=:pos WHERE id=:id");
        foreach ($ids as $cid) $st->execute([':pos'=>$i++, ':id'=>(int)$cid]);
        $pdo->commit();
        echo json_encode(['ok'=>true]); exit;
    }

    throw new RuntimeException('unknown action');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
