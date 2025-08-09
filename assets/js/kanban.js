// Kanban: drag&drop, открытие задачи, создание через модалку, множественные файлы
(function () {
    const kb = document.querySelector('.kanban');
    if (!kb) return;

    const BASE = (window.APP_BASE_URL || '').replace(/\/+$/, '');
    const abs = (p) => (BASE ? `${BASE}/${String(p).replace(/^\/+/, '')}` : `/${String(p).replace(/^\/+/, '')}`);

    let API_MOVE   = kb.getAttribute('data-move-api')   || 'boards/tasks/api/task_move.php';
    let API_CREATE = kb.getAttribute('data-create-api') || 'boards/tasks/task_save.php';
    if (!/^https?:|^\//.test(API_MOVE))   API_MOVE   = abs(API_MOVE);
    if (!/^https?:|^\//.test(API_CREATE)) API_CREATE = abs(API_CREATE);

    const FIRST_BOARD_ID = Number(kb.getAttribute('data-first-board-id') || 0);
    const CSRF = kb.getAttribute('data-csrf') || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // ---- DnD ----
    let dragCard = null, moved = false;

    kb.addEventListener('dragstart', (e) => {
        const card = e.target.closest('.task-card');
        if (!card) return;
        dragCard = card; moved = true;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.dataset.taskId);
        setTimeout(() => card.classList.add('dragging'), 0);
    });

    kb.addEventListener('dragend', () => {
        if (dragCard) dragCard.classList.remove('dragging');
        setTimeout(() => (moved = false), 50);
        dragCard = null;
    });

    kb.querySelectorAll('.kanban-col-body').forEach((col) => {
        col.addEventListener('dragover', (e) => {
            e.preventDefault();
            const after = getAfter(col, e.clientY);
            const dragging = kb.querySelector('.task-card.dragging');
            if (!dragging) return;
            if (after == null) col.appendChild(dragging);
            else col.insertBefore(dragging, after);
        });

        col.addEventListener('drop', async (e) => {
            e.preventDefault();
            const card = kb.querySelector('.task-card.dragging');
            if (!card) return;

            const arr = Array.from(col.querySelectorAll('.task-card'));
            const idx = arr.indexOf(card);
            const prev = idx > 0 ? arr[idx - 1] : null;
            const next = idx < arr.length - 1 ? arr[idx + 1] : null;

            const fd = new FormData();
            fd.append('csrf', CSRF);
            fd.append('task_id', Number(card.dataset.taskId));
            fd.append('to_board_id', Number(col.dataset.boardId));
            if (prev) fd.append('prev_id', Number(prev.dataset.taskId));
            if (next) fd.append('next_id', Number(next.dataset.taskId));

            try {
                const r = await fetch(API_MOVE, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                });
                const js = await r.json();
                if (!r.ok || !js.ok) throw new Error(js.error || 'move failed');

                card.dataset.boardId = String(col.dataset.boardId);
                if (typeof js.position !== 'undefined') card.dataset.position = String(js.position);
            } catch (err) {
                console.error(err);
                alert('Не удалось сохранить новое положение. Обновите страницу.');
            }
        });
    });

    function getAfter(container, y) {
        const els = [...container.querySelectorAll('.task-card:not(.dragging)')];
        return (
            els.reduce(
                (closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;
                    if (offset < 0 && offset > closest.offset) return { offset, element: child };
                    return closest;
                },
                { offset: Number.NEGATIVE_INFINITY, element: null }
            ).element || null
        );
    }

    // ---- Клик по карточке -> открыть задачу ----
    kb.addEventListener('click', (e) => {
        const card = e.target.closest('.task-card');
        if (!card || moved) return;
        const id = card.dataset.taskId;
        if (id) window.location.href = abs(`boards/tasks/task_view.php?id=${id}`);
    }, true);

    // ---- Модалка "Новая задача" ----
    const modal     = document.getElementById('taskCreateModal');
    const form      = document.getElementById('taskCreateForm');
    const btnOpen   = document.getElementById('addTaskBtn');
    const btnCancel = document.getElementById('taskCreateCancel');

    const openModal  = () => modal && (modal.hidden = false);
    const closeModal = () => modal && (modal.hidden = true);

    btnOpen?.addEventListener('click', openModal);
    btnCancel?.addEventListener('click', closeModal);
    modal?.querySelector('.modal-backdrop')?.addEventListener('click', closeModal);

    // "+ Ещё файл" — дополнительные inputs (каждый multiple)
    const addMoreBtn = document.getElementById('addMoreFilesBtn');
    if (addMoreBtn && form) {
        addMoreBtn.addEventListener('click', () => {
            const wrap = form.querySelector('.files-group');
            if (!wrap) return;
            const row = document.createElement('div');
            row.className = 'file-row';
            const inp = document.createElement('input');
            inp.type = 'file';
            inp.name = 'files[]';
            inp.multiple = true;
            row.appendChild(inp);
            wrap.insertBefore(row, addMoreBtn);
        });
    }

    // Сабмит модалки
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        if (!fd.get('board_id') || Number(fd.get('board_id')) <= 0) {
            fd.set('board_id', String(FIRST_BOARD_ID));
        }
        try {
            const r = await fetch(API_CREATE, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const js = await r.json();
            if (!r.ok || !js.ok) throw new Error(js.error || 'create failed');

            const boardId = js.board_id || FIRST_BOARD_ID;
            const col = kb.querySelector(`.kanban-col-body[data-board-id="${boardId}"]`);
            if (col) col.appendChild(makeCard(js));

            form.reset();
            closeModal();
        } catch (err) {
            console.error(err);
            alert('Не удалось создать задачу.');
        }
    });

    // ---- helpers ----
    function makeCard(t) {
        const card = document.createElement('div');
        card.className = 'task-card';
        card.draggable = true;
        card.dataset.taskId = t.id;
        card.dataset.position = t.position;
        card.dataset.boardId = t.board_id;

        const dueStr = t.due_date ? formatDate(t.due_date) : '';
        const prio = Number(t.priority || 0);

        card.innerHTML = `
      <div class="task-title"></div>
      ${t.description ? `<div class="task-desc"></div>` : ``}
      <div class="task-meta">
        ${dueStr ? `<span class="meta due">до ${dueStr}</span>` : ``}
        ${prio > 0 ? `<span class="meta prio">P${prio}</span>` : ``}
      </div>
    `;
        card.querySelector('.task-title').textContent = t.title || '';
        if (t.description) card.querySelector('.task-desc').textContent = t.description;
        return card;
    }

    function formatDate(iso) {
        try {
            const d = new Date(iso);
            const dd = String(d.getDate()).padStart(2, '0');
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const yyyy = d.getFullYear();
            return `${dd}.${mm}.${yyyy}`;
        } catch { return ''; }
    }
})();
