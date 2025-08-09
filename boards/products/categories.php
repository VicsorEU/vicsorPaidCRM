<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
$active = 'products';

global $pdo;
$tree = $pdo->query("
  WITH RECURSIVE t AS (
    SELECT id,name,slug,description,parent_id,position, 0 AS depth
    FROM crm_product_categories
    WHERE parent_id IS NULL
    UNION ALL
    SELECT c.id,c.name,c.slug,c.description,c.parent_id,c.position, t.depth+1
    FROM crm_product_categories c
    JOIN t ON c.parent_id = t.id
  )
  SELECT * FROM t ORDER BY depth, parent_id NULLS FIRST, position, id
")->fetchAll(PDO::FETCH_ASSOC);

function render_tree($rows) {
    $byParent = [];
    foreach ($rows as $r) $byParent[$r['parent_id']][] = $r;
    $out = function($pid) use (&$out, $byParent){
        if (empty($byParent[$pid])) return '';
        $html = '<ul class="cat-tree">';
        foreach ($byParent[$pid] as $n) {
            $html .= '<li data-id="'.$n['id'].'" data-slug="'.htmlspecialchars($n['slug'] ?? '',ENT_QUOTES).'" data-desc="'.htmlspecialchars($n['description'] ?? '',ENT_QUOTES).'">'
                    .  '<div class="node">'
                    .    '<span class="drag-handle" title="Перетащить">⋮⋮</span>'
                    .    '<span class="node-title">'.htmlspecialchars($n['name']).'</span>'
                    .    '<span class="node-actions">'
                    .      '<button class="btn sm add" data-id="'.$n['id'].'">+ Подкатегория</button>'
                    .      '<button class="btn sm rename" data-id="'.$n['id'].'">Редактировать</button>'
                    .      '<button class="btn sm danger del" data-id="'.$n['id'].'">Удалить</button>'
                    .    '</span>'
                    .  '</div>'
                    .  $out($n['id'])
                    . '</li>';
        }
        $html .= '</ul>';
        return $html;
    };
    return $out(NULL);
}

require APP_ROOT . '/inc/app_header.php';
?>
<div class="app">
    <?php require APP_ROOT . '/inc/app_sidebar.php'; ?>
    <main class="main">
        <?php require APP_ROOT . '/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>

            <div class="card">
                <div class="toolbar">
                    <div class="actions">
                        <button class="btn primary" id="addRootBtn">+ Корневая категория</button>
                    </div>
                </div>

                <div id="catTree"
                     class="cat-tree-wrap"
                     data-api-save="<?= htmlspecialchars(url('boards/products/api/category_save.php')) ?>"
                     data-api-del="<?= htmlspecialchars(url('boards/products/api/category_delete.php')) ?>"
                     data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
                    <?= render_tree($tree) ?>
                </div>
            </div>
        </section>
    </main>
</div>
<link rel="stylesheet" href="<?= asset('css/categories.css') ?>">
<script src="<?= asset('js/categories.js') ?>"></script>
<?php require APP_ROOT . '/inc/app_footer.php'; ?>
