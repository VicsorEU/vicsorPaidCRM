<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
$active = 'products';
global $pdo;

/* autodetect колонок в crm_products */
function pick_col(PDO $pdo, string $table, array $cands): ?string {
    $sql = "SELECT column_name FROM information_schema.columns
          WHERE table_schema=current_schema() AND table_name=:t";
    $st = $pdo->prepare($sql); $st->execute([':t'=>strtolower($table)]);
    $cols = array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'column_name'));
    foreach ($cands as $c) if (in_array(strtolower($c), $cols, true)) return $c;
    return null;
}
$PRODUCTS_TBL = 'crm_products';
$nameCol  = pick_col($pdo,$PRODUCTS_TBL,['name','title','product_name']);
$skuCol   = pick_col($pdo,$PRODUCTS_TBL,['sku','article','code']);
$priceCol = pick_col($pdo,$PRODUCTS_TBL,['price','base_price','cost']);

$id = (int)($_GET['id'] ?? 0);
$product = null;

if ($id > 0) {
    $sel = [];
    if ($nameCol)  $sel[] = "p.$nameCol AS name";
    if ($skuCol)   $sel[] = "p.$skuCol  AS sku";
    if ($priceCol) $sel[] = "p.$priceCol::numeric AS price";
    $sel[] = "p.short_description, p.description";

    $sql = "SELECT p.id, ".implode(', ', $sel)." FROM $PRODUCTS_TBL p WHERE p.id=:id";
    $st = $pdo->prepare($sql); $st->execute([':id'=>$id]); $product = $st->fetch();
}

/* дерево категорий и отмеченные категории товара */
$cats = $pdo->query("
  WITH RECURSIVE t AS (
    SELECT id,name,parent_id,position,0 depth FROM crm_product_categories WHERE parent_id IS NULL
    UNION ALL
    SELECT c.id,c.name,c.parent_id,c.position, t.depth+1
    FROM crm_product_categories c JOIN t ON c.parent_id=t.id
  )
  SELECT * FROM t ORDER BY depth, parent_id NULLS FIRST, position, id
")->fetchAll(PDO::FETCH_ASSOC);

$checkedCatIds = [];
if ($id>0) {
    $st=$pdo->prepare("SELECT category_id FROM crm_product_category_map WHERE product_id=:p");
    $st->execute([':p'=>$id]);
    $checkedCatIds = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC),'category_id'));
}

/* изображения */
$images = [];
if ($id>0) {
    $st=$pdo->prepare("SELECT id,original_name,stored_name,is_primary,sort_order
                     FROM crm_product_images WHERE product_id=:p ORDER BY is_primary DESC, sort_order, id");
    $st->execute([':p'=>$id]); $images = $st->fetchAll();
}

require APP_ROOT . '/inc/app_header.php';

function render_cat_tree(array $rows, array $checked) {
    $byParent=[]; foreach($rows as $r) $byParent[$r['parent_id']][]=$r;
    $out=function($pid) use (&$out,$byParent,$checked){
        if (empty($byParent[$pid])) return '';
        $h='<ul class="cat-checklist">';
        foreach($byParent[$pid] as $n){
            $chk = in_array((int)$n['id'],$checked,true)?' checked':'';
            $h.='<li><label><input type="checkbox" name="cat_ids[]" value="'.$n['id'].'"'.$chk.'> '.htmlspecialchars($n['name']).'</label>';
            $h.=$out($n['id']).'</li>';
        }
        $h.='</ul>'; return $h;
    };
    return $out(NULL);
}
?>
<div class="app">
    <?php require APP_ROOT . '/inc/app_sidebar.php'; ?>
    <main class="main">
        <?php require APP_ROOT . '/inc/app_topbar.php'; ?>
        <section class="content">
            <?= flash_render() ?>

            <div class="grid-2">
                <!-- Мета товара -->
                <div class="card">
                    <h3><?= $id? 'Товар #'.(int)$id : 'Новый товар' ?></h3>
                    <form class="form" id="productMetaForm" method="post" action="<?= url('boards/products/api/product_save.php') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$id ?>">

                        <div class="grid-2">
                            <?php if ($nameCol): ?>
                                <label>Название
                                    <input type="text" name="name" required value="<?= htmlspecialchars($product['name'] ?? '') ?>">
                                </label>
                            <?php endif; ?>

                            <?php if ($skuCol): ?>
                                <label>SKU/Артикул
                                    <input type="text" name="sku" value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
                                </label>
                            <?php endif; ?>

                            <?php if ($priceCol): ?>
                                <label>Цена
                                    <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($product['price'] ?? '') ?>">
                                </label>
                            <?php endif; ?>
                        </div>

                        <label>Короткое описание
                            <textarea name="short_description" rows="3"><?= htmlspecialchars($product['short_description'] ?? '') ?></textarea>
                        </label>
                        <label>Описание
                            <textarea name="description" rows="6"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                        </label>

                        <h4 style="margin-top:14px;">Категории</h4>
                        <div class="cat-search">
                            <input type="text" id="catSearch" placeholder="Поиск категории...">
                        </div>
                        <div class="cat-tree-box" id="productCats">
                            <?= render_cat_tree($cats, $checkedCatIds) ?>
                        </div>


                        <div class="actions" style="margin-top:12px;">
                            <button class="btn primary" type="submit">Сохранить</button>
                            <a class="btn" href="<?= url('boards/products/products.php') ?>">К списку</a>
                        </div>
                    </form>
                </div>

                <!-- Атрибуты -->
                <div class="card">
                    <h3>Атрибуты</h3>
                    <?php if ($id<=0): ?>
                        <p class="muted">Сначала сохраните карточку — затем станут доступны атрибуты и галерея.</p>
                    <?php else: ?>
                        <div id="attrPanel"
                             data-fetch="<?= htmlspecialchars(url('boards/products/api/attributes_all.php')) ?>"
                             data-save="<?= htmlspecialchars(url('boards/products/api/product_attr_save.php')) ?>"
                             data-product-id="<?= (int)$id ?>"
                             data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
                            <div class="muted" id="attrHint">Добавьте нужные атрибуты и задайте значения.</div>

                            <div class="attr-toolbar" style="display:flex;gap:8px;margin-bottom:8px;">
                                <select id="attrAddSelect" style="min-width:260px;"></select>
                                <button class="btn" id="attrAddBtn" type="button">+ Добавить атрибут</button>
                            </div>

                            <form id="attrForm" class="form" style="display:none;">
                                <div id="attrFields"></div>
                                <div class="actions" style="margin-top:12px;">
                                    <button class="btn primary" type="submit">Сохранить атрибуты</button>
                                </div>
                            </form>
                        </div>

                    <?php endif; ?>
                </div>
            </div>

            <?php if ($id>0): ?>
                <!-- Галерея -->
                <div class="card">
                    <h3>Фотографии</h3>
                    <div class="gallery-toolbar">
                        <input type="file" id="imageUpload" multiple>
                    </div>
                    <div id="imageGrid" class="image-grid">
                        <?php foreach ($images as $img): ?>
                            <div class="image-item" data-id="<?= (int)$img['id'] ?>">
                                <img src="<?= asset('storage/products/'.(int)$id.'/'.$img['stored_name']) ?>" alt="">
                                <div class="image-actions">
                                    <?php if ($img['is_primary']): ?>
                                        <span class="tag">Обложка</span>
                                    <?php else: ?>
                                        <button class="btn sm" data-act="primary">Сделать обложкой</button>
                                    <?php endif; ?>
                                    <button class="btn sm danger" data-act="delete">Удалить</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<link rel="stylesheet" href="<?= asset('css/product.css') ?>">
<script src="<?= asset('js/product_categories.js') ?>"></script>
<script src="<?= asset('js/product_attributes.js') ?>"></script>
<script src="<?= asset('js/product_images.js') ?>"></script>
<?php require APP_ROOT . '/inc/app_footer.php'; ?>
