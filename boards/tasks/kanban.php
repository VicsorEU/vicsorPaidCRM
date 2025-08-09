<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
requireLogin(); $active='tasks';
global $pdo;

// Доски
$boards = $pdo->query("SELECT id, name, color FROM crm_task_boards ORDER BY position, id")->fetchAll();
$firstBoardId = $boards ? (int)$boards[0]['id'] : 0;

// Задачи по доскам
$tasksByBoard = [];
if ($boards) {
    $ids = implode(',', array_map('intval', array_column($boards,'id')));
    $q = $pdo->query("SELECT id, board_id, title, description, priority, due_date, position
                    FROM crm_tasks
                    WHERE board_id IN ($ids)
                    ORDER BY position, id");
    while ($r = $q->fetch()) { $tasksByBoard[$r['board_id']][] = $r; }
}

// Справочники для модалки
$types = $pdo->query("SELECT id, name FROM crm_task_types ORDER BY name")->fetchAll();
$users = $pdo->query("SELECT id, full_name FROM crm_users ORDER BY full_name")->fetchAll();

require APP_ROOT . '/inc/app_header.php';
?>
<div class="app">
    <?php require APP_ROOT . '/inc/app_sidebar.php'; ?>
    <main class="main">
        <?php require APP_ROOT . '/inc/app_topbar.php'; ?>

        <section class="content">
            <?= flash_render() ?>

            <div class="toolbar">
                <div class="filters">
                    <h2 style="margin:0;">Таск-менеджер</h2>
                </div>
                <div class="actions" style="display:flex;gap:8px;align-items:center;">
                    <button class="btn primary" id="addTaskBtn" <?= $firstBoardId? '' : 'disabled' ?>>+ Добавить задачу</button>
                    <form class="form-inline" method="post" action="<?= url('boards/tasks/board_save.php') ?>" style="display:flex; gap:8px;">
                        <?= csrf_field() ?>
                        <input type="text" name="name" placeholder="Новая доска" required>
                        <input type="color" name="color" value="#6b7280" title="Цвет">
                        <button class="btn" type="submit">+ Доска</button>
                    </form>
                </div>
            </div>

            <div class="kanban"
                 data-first-board-id="<?= (int)$firstBoardId ?>"
                 data-move-api="<?= htmlspecialchars(url('boards/tasks/api/task_move.php')) ?>"
                 data-create-api="<?= htmlspecialchars(url('boards/tasks/task_save.php')) ?>"
                 data-csrf="<?= htmlspecialchars(csrf_token()) ?>">

            <?php foreach ($boards as $b): ?>
                    <div class="kanban-col" data-board-id="<?= (int)$b['id'] ?>">
                        <div class="kanban-col-head">
                            <span class="dot" style="background:<?= htmlspecialchars($b['color'] ?? '#64748b') ?>"></span>
                            <span class="title"><?= htmlspecialchars($b['name']) ?></span>
                        </div>

                        <div class="kanban-col-body" data-board-id="<?= (int)$b['id'] ?>">
                            <?php foreach ($tasksByBoard[$b['id']] ?? [] as $t): ?>
                                <div class="task-card"
                                     draggable="true"
                                     data-task-id="<?= (int)$t['id'] ?>"
                                     data-position="<?= htmlspecialchars($t['position']) ?>"
                                     data-board-id="<?= (int)$b['id'] ?>">
                                    <div class="task-title"><?= htmlspecialchars($t['title']) ?></div>
                                    <?php if (!empty($t['description'])): ?>
                                        <div class="task-desc"><?= nl2br(htmlspecialchars($t['description'])) ?></div>
                                    <?php endif; ?>
                                    <div class="task-meta">
                                        <?php if ($t['due_date']): ?>
                                            <span class="meta due">до <?= htmlspecialchars(date('d.m.Y', strtotime($t['due_date']))) ?></span>
                                        <?php endif; ?>
                                        <?php if ((int)$t['priority']>0): ?>
                                            <span class="meta prio">P<?= (int)$t['priority'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if(!$boards): ?>
                    <div class="muted" style="padding:16px;background:#fff;border:1px dashed var(--border);border-radius:12px;">
                        Пока нет досок. Создай первую через форму в правом верхнем углу.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<!-- Модалка "Новая задача" -->
<div id="taskCreateModal" class="modal" hidden>
    <div class="modal-backdrop"></div>
    <div class="modal-dialog">
        <form id="taskCreateForm" class="form" method="post" action="<?= url('boards/tasks/task_save.php') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="board_id" value="<?= (int)$firstBoardId ?>">

            <h3 style="margin:0 0 12px;">Новая задача</h3>

            <div class="row">
                <div>
                    <label>Название</label>
                    <input name="title" required placeholder="Коротко о задаче">
                </div>
                <div>
                    <label>Тип задачи</label>
                    <select name="type_id">
                        <option value="">— не выбран —</option>
                        <?php foreach ($types as $ty): ?>
                            <option value="<?= (int)$ty['id'] ?>"><?= htmlspecialchars($ty['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Срок выполнения</label>
                    <input type="date" name="due_date">
                </div>
                <div>
                    <label>Ответственный</label>
                    <select name="assignee_id">
                        <option value="">— не назначен —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div>
                    <label>Степень важности</label>
                    <select name="priority">
                        <option value="0">Обычная</option>
                        <option value="1">Высокая (P1)</option>
                        <option value="2">Очень высокая (P2)</option>
                        <option value="3">Критично (P3)</option>
                    </select>
                </div>

                <div>
                    <label>Файлы</label>
                    <div class="files-group">
                        <div class="file-row"><input type="file" name="files[]" multiple></div>
                        <button type="button" class="btn" id="addMoreFilesBtn">+ Ещё файл</button>
                        <div class="muted" style="margin-top:4px;">Можно выбрать несколько файлов сразу (⌘/Ctrl-клик) или добавить поля кнопкой.</div>
                    </div>
                </div>
            </div>


            <div>
                <label>Описание</label>
                <textarea name="description" rows="5" placeholder="Детали задачи..."></textarea>
            </div>

            <div>
                <label>Комментарий</label>
                <textarea name="comment" rows="3" placeholder="Первый комментарий…"></textarea>
            </div>

            <div class="actions" style="margin-top:12px;">
                <button class="btn primary" type="submit">Создать</button>
                <button class="btn" type="button" id="taskCreateCancel">Отмена</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= asset('js/kanban.js') ?>"></script>
<?php require APP_ROOT . '/inc/app_footer.php'; ?>
