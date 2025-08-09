// Таймер start/stop
(function(){
    const startBtn = document.getElementById('startTimerBtn');
    const stopBtn  = document.getElementById('stopTimerBtn');
    if (startBtn) startBtn.addEventListener('click', ()=> {
        const id = Number(startBtn.getAttribute('data-task-id'));
        if (id) window.TaskTimer?.start(id);
    });
    if (stopBtn)  stopBtn.addEventListener('click', ()=> window.TaskTimer?.stop());
})();

// Тоггл редактирования записей времени
(function(){
    document.querySelectorAll('.js-edit-time').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const id = btn.getAttribute('data-id');
            const row = document.querySelector(`.js-edit-row[data-id="${id}"]`);
            if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
        });
    });
    document.querySelectorAll('.js-cancel-edit').forEach(btn=>{
        btn.addEventListener('click', ()=> {
            const tr = btn.closest('.js-edit-row') || btn.closest('.js-comment-edit-form');
            if (tr) tr.style.display='none';
        });
    });
})();

// Редактирование комментариев
(function(){
    document.querySelectorAll('.js-edit-comment').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const id = btn.getAttribute('data-id');
            const form = btn.closest('.comment')?.querySelector('.js-comment-edit-form');
            if (form) form.style.display = form.style.display === 'none' ? '' : 'none';
        });
    });
})();
