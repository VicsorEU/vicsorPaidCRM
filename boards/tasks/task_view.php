<?php
// boards/tasks/task_view.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin();
$active = 'tasks';
global $pdo;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit('Task not found'); }

$st = $pdo->prepare("
    SELECT t.*,
           u.full_name AS assignee_name,
           ty.name      AS type_name
    FROM crm_tasks t
    LEFT JOIN crm_users u       ON u.id  = t.assignee_id
    LEFT JOIN crm_task_types ty ON ty.id = t.type_id
    WHERE t.id = :id
");
$st->execute([':id' => $id]); $task = $st->fetch();
if (!$task) { http_response_code(404); exit('Task not found'); }

$types = $pdo->query("SELECT id, name FROM crm_task_types ORDER BY name")->fetchAll();
$users = $pdo->query("SELECT id, full_name FROM crm_users ORDER BY full_name")->fetchAll();

$st = $pdo->prepare("SELECT * FROM crm_task_files WHERE task_id=:id ORDER BY created_at DESC");
$st->execute([':id' => $id]); $files = $st->fetchAll();

$st = $pdo->prepare("
    SELECT c.*, u.full_name
    FROM crm_task_comments c
    LEFT JOIN crm_users u ON u.id=c.user_id
    WHERE c.task_id=:id
    ORDER BY c.created_at ASC
");
$st->execute([':id' => $id]); $comments = $st->fetchAll();

$st = $pdo->prepare("
    SELECT tt.*, u.full_name
    FROM crm_task_time tt
    LEFT JOIN crm_users u ON u.id = tt.user_id
    WHERE tt.task_id = :id
    ORDER BY tt.started_at ASC
");
$st->execute([':id' => $id]); $times = $st->fetchAll();

$totalSeconds = 0;
foreach ($times as $row) {
    $end = $row['stopped_at'] ?: date('c');
    $totalSeconds += max(0, strtotime($end) - strtotime($row['started_at']));
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
                    <h2 style="margin:0;">Задача #<?= (int)$task['id'] ?></h2>
                    <div class="actions">
                        <a class="btn" href="<?= url('boards/tasks/kanban.php') ?>">К Канбану</a>
                    </div>
                </div>

                <form method="post" action="<?= url('boards/tasks/task_update.php') ?>" class="form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">

                    <div class="row">
                        <div>
                            <label>Название</label>
                            <input name="title" value="<?= htmlspecialchars($task['title']) ?>" required>
                        </div>
                        <div>
                            <label>Тип задачи</label>
                            <select name="type_id">
                                <option value="">— не выбран —</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>"<?= ($task['type_id']==$t['id']?' selected':'') ?>>
                                        <?= htmlspecialchars($t['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Срок выполнения</label>
                            <input type="date" name="due_date" value="<?= htmlspecialchars($task['due_date'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Ответственный</label>
                            <select name="assignee_id">
                                <option value="">— не назначен —</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>"<?= ($task['assignee_id']==$u['id']?' selected':'') ?>>
                                        <?= htmlspecialchars($u['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label>Степень важности</label>
                            <select name="priority">
                                <option value="0"<?= ((int)$task['priority']===0?' selected':'') ?>>Обычная</option>
                                <option value="1"<?= ((int)$task['priority']===1?' selected':'') ?>>Высокая (P1)</option>
                                <option value="2"<?= ((int)$task['priority']===2?' selected':'') ?>>Очень высокая (P2)</option>
                                <option value="3"<?= ((int)$task['priority']===3?' selected':'') ?>>Критично (P3)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label>Описание</label>
                        <textarea name="description" rows="6" placeholder="Что нужно сделать?"><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
                    </div>

                    <div class="actions" style="margin-top:12px;">
                        <button class="btn primary" type="submit">Сохранить</button>
                        <button class="btn" type="button" id="startTimerBtn" data-task-id="<?= (int)$task['id'] ?>">▶ Старт таймера</button>
                        <button class="btn" type="button" id="stopTimerBtn">■ Стоп</button>
                        <span class="muted" id="taskTimeTotal" style="margin-left:12px;">
              Всего по задаче: <b><?= gmdate('H:i:s', max(0, $totalSeconds)) ?></b>
            </span>
                    </div>
                </form>
            </div>

            <div class="grid2" style="display:grid;grid-template-columns: 1fr 1fr; gap:12px; align-items:start;">
                <!-- ФАЙЛЫ -->
                <div class="card">
                    <h3 style="margin:0 0 12px;">Файлы</h3>
                    <form method="post" action="<?= url('boards/tasks/task_file_upload.php') ?>" enctype="multipart/form-data" class="form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                        <input type="file" name="file" required>
                        <button class="btn" type="submit">Загрузить</button>
                    </form>
                    <ul style="margin-top:12px;">
                        <?php if (!$files): ?>
                            <li class="muted">Файлов нет</li>
                        <?php else: foreach ($files as $f): ?>
                            <li>
                                <a href="<?= url('boards/tasks/file_download.php?id='.(int)$f['id']) ?>"><?= htmlspecialchars($f['orig_name']) ?></a>
                                <span class="muted"> (<?= number_format((int)$f['size_bytes']/1024, 1, '.', ' ') ?> KB<?= $f['mime']? ', '.htmlspecialchars($f['mime']):'' ?>)</span>
                                <form method="post" action="<?= url('boards/tasks/file_delete.php') ?>" style="display:inline;margin-left:8px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                    <button class="btn" onclick="return confirm('Удалить файл?')">Удалить</button>
                                </form>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>

                <!-- КОММЕНТАРИИ -->
                <div class="card">
                    <h3 style="margin:0 0 12px;">Комментарии</h3>
                    <div class="comments">
                        <?php if (!$comments): ?>
                            <div class="muted">Пока нет комментариев</div>
                        <?php else: foreach ($comments as $c): ?>
                            <div class="comment" style="border-bottom:1px solid var(--border); padding:8px 0;">
                                <div style="font-size:12px;color:#6b778c;display:flex;gap:8px;align-items:center;">
                                    <span><?= htmlspecialchars($c['full_name'] ?? 'Пользователь') ?></span>
                                    <span>• <?= htmlspecialchars(date('d.m.Y H:i', strtotime($c['created_at']))) ?></span>
                                    <button class="btn btn-xs js-edit-comment" data-id="<?= (int)$c['id'] ?>">Редактировать</button>
                                </div>
                                <div class="comment-body" data-id="<?= (int)$c['id'] ?>"><?= nl2br(htmlspecialchars($c['body'])) ?></div>

                                <form class="form-inline js-comment-edit-form" method="post" action="<?= url('boards/tasks/comment_update.php') ?>" style="display:none;margin-top:6px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <textarea name="body" rows="3" style="width:100%;"><?= htmlspecialchars($c['body']) ?></textarea>
                                    <div class="actions" style="margin-top:6px;">
                                        <button class="btn" type="submit">Сохранить</button>
                                        <button class="btn js-cancel-edit" type="button">Отмена</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <form method="post" action="<?= url('boards/tasks/comment_add.php') ?>" style="margin-top:8px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                        <textarea name="body" rows="3" placeholder="Написать комментарий..." required></textarea>
                        <div class="actions" style="margin-top:8px;">
                            <button class="btn" type="submit">Отправить</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- УЧЁТ ВРЕМЕНИ -->
            <div class="card" style="margin-top:12px;">
                <h3 style="margin:0 0 12px;">Учёт времени</h3>
                <table class="table">
                    <thead><tr><th>Пользователь</th><th>Начало</th><th>Конец</th><th>Длительность</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$times): ?>
                        <tr><td colspan="5" style="text-align:center;">Записей нет</td></tr>
                    <?php else: foreach ($times as $row):
                        $end = $row['stopped_at'];
                        $dur = max(0, strtotime($end ?: 'now') - strtotime($row['started_at']));
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['full_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($row['started_at']))) ?></td>
                            <td><?= $end ? htmlspecialchars(date('d.m.Y H:i', strtotime($end))) : '<span class="badge out">идёт</span>' ?></td>
                            <td><?= gmdate('H:i:s', $dur) ?></td>
                            <td style="text-align:right;">
                                <?php if ($end): ?><button class="btn js-edit-time" data-id="<?= (int)$row['id'] ?>">Редактировать</button><?php endif; ?>
                                <form method="post" action="<?= url('boards/tasks/time_delete.php') ?>" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn" onclick="return confirm('Удалить запись времени?')">Удалить</button>
                                </form>
                            </td>
                        </tr>
                        <?php if ($end): ?>
                        <tr class="js-edit-row" data-id="<?= (int)$row['id'] ?>" style="display:none;background:#fff;">
                            <td colspan="5">
                                <form class="form-inline" method="post" action="<?= url('boards/tasks/time_update.php') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <label>Начало</label>
                                    <input type="datetime-local" name="started_at" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($row['started_at']))) ?>" required>
                                    <label>Конец</label>
                                    <input type="datetime-local" name="stopped_at" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($end))) ?>" required>
                                    <button class="btn" type="submit">Сохранить</button>
                                    <button class="btn js-cancel-edit" type="button">Отмена</button>
                                </form>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <h4 style="margin:12px 0 8px;">Добавить интервал вручную</h4>
                <form class="form-inline" method="post" action="<?= url('boards/tasks/time_add.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                    <label>Начало</label>
                    <input type="datetime-local" name="started_at" required>
                    <label>Конец</label>
                    <input type="datetime-local" name="stopped_at" required>
                    <button class="btn" type="submit">Добавить</button>
                </form>
                <form method="post" action="<?= url('boards/tasks/task_delete.php') ?>" style="display:inline;margin-left:8px;" onsubmit="return confirm('Удалить задачу безвозвратно?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                    <button class="btn" style="background:#ef4444;color:#fff;">Удалить</button>
                </form>
            </div>

        </section>
    </main>
</div>

<script src="<?= asset('js/task-view.js') ?>"></script>
<?php require APP_ROOT . '/inc/app_footer.php'; ?>
