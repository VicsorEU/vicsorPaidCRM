<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
$active = 'products';
global $pdo;

$id = (int)($_GET['id'] ?? 0);
$attr = null;
$options = [];

if ($id>0) {
    $st = $pdo->prepare("SELECT * FROM crm_attributes WHERE id=:id");
    $st->execute([':id'=>$id]); $attr = $st->fetch();

    $st = $pdo->prepare("SELECT * FROM crm_attribute_options WHERE attribute_id=:id ORDER BY position,id");
    $st->execute([':id'=>$id]); $options = $st->fetchAll();
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
                <h3><?= $id? 'Редактирование атрибута' : 'Новый атрибут' ?></h3>
                <form class="form" method="post" action="<?= url('boards/products/api/attribute_save.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="grid-2">
                        <label>
                            Название
                            <input type="text" name="name" required value="<?= htmlspecialchars($attr['name'] ?? '') ?>">
                        </label>
                        <label>
                            Код (латиницей, уникально)
                            <input type="text" name="code" value="<?= htmlspecialchars($attr['code'] ?? '') ?>">
                        </label>
                        <label>
                            Тип
                            <select name="type" id="attrType" required>
                                <?php
                                $types = ['text'=>'Текст','number'=>'Число','bool'=>'Да/нет','date'=>'Дата','select'=>'Список','multiselect'=>'Список (неск.)'];
                                $cur = $attr['type'] ?? 'text';
                                foreach ($types as $k=>$v) {
                                    $sel = $k===$cur? ' selected':'';
                                    echo "<option value=\"$k\"$sel>$v</option>";
                                }
                                ?>
                            </select>
                        </label>
                    </div>

                    <div id="optionsBlock"<?= (isset($attr['type']) && in_array($attr['type'],['select','multiselect']))?'':' style="display:none;"' ?>>
                        <h4>Опции</h4>
                        <div class="opts">
                            <?php if ($options): foreach ($options as $o): ?>
                                <div class="opt-row">
                                    <input type="hidden" name="opt_id[]" value="<?= (int)$o['id'] ?>">
                                    <input type="text" name="opt_value[]" value="<?= htmlspecialchars($o['value']) ?>" placeholder="Значение">
                                    <input type="number" name="opt_position[]" value="<?= (int)$o['position'] ?>" style="width:100px" placeholder="Порядок">
                                    <button class="btn danger del-opt" type="button">Удалить</button>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <button class="btn" type="button" id="addOptBtn">+ Опция</button>
                    </div>

                    <div class="actions" style="margin-top:12px;">
                        <button class="btn primary" type="submit">Сохранить</button>
                        <a class="btn" href="<?= url('boards/products/attributes.php') ?>">Отмена</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
<script src="<?= asset('js/attributes.js') ?>"></script>
<?php require APP_ROOT . '/inc/app_footer.php'; ?>
