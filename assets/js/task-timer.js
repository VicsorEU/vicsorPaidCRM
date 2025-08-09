// Глобальный виджет таймера задач (правый нижний угол) + API start/stop/status
(function () {
    // --- Абсолютные пути и CSRF ---
    const BASE = (window.APP_BASE_URL || '').replace(/\/+$/, '');
    const abs = (p) => {
        p = String(p || '').replace(/^\/+/, '');
        return BASE ? `${BASE}/${p}` : `/${p}`;
    };
    const meta = document.querySelector('meta[name="csrf-token"]');
    const CSRF = meta ? meta.getAttribute('content') : '';

    const API = {
        status: abs('boards/tasks/api/timer_status.php'),
        start:  abs('boards/tasks/api/timer_start.php'),
        stop:   abs('boards/tasks/api/timer_stop.php'),
    };

    // --- Виджет ---
    const box = document.createElement('div');
    box.id = 'taskTimerWidget';
    box.style.cssText = `
    position: fixed; right: 18px; bottom: 18px; z-index: 9999;
    background: #111827; color: #fff; border-radius: 12px; padding: 10px 12px;
    box-shadow: 0 10px 20px rgba(0,0,0,.2); display:none; min-width: 240px; cursor: pointer;
  `;
    box.innerHTML = `
    <div style="display:flex;align-items:center;gap:8px;">
      <div style="font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width: 420px;">
        ⏱ <span class="tt-title">Задача</span>
      </div>
      <div style="margin-left:auto;font-variant-numeric:tabular-nums;"><span class="tt-time">00:00:00</span></div>
      <button class="tt-stop" title="Стоп" style="margin-left:8px;background:#ef4444;border:0;color:#fff;border-radius:8px;padding:6px 8px;cursor:pointer;">■</button>
    </div>
  `;
    (document.body || document.documentElement).appendChild(box);

    const elTitle = box.querySelector('.tt-title');
    const elTime  = box.querySelector('.tt-time');
    const btnStop = box.querySelector('.tt-stop');

    let running = null; // {task_id, task_title, started_ms}
    let tickId = null;

    // Helpers
    const formatHMS = (s) => {
        const h = Math.floor(s/3600), m = Math.floor((s%3600)/60), ss = s%60;
        return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(ss).padStart(2,'0');
    };
    function startTick(){ stopTick(); tickId=setInterval(update,1000); update(); }
    function stopTick(){ if (tickId){ clearInterval(tickId); tickId=null; } }
    function setRunning(obj){ running=obj; elTitle.textContent=obj.task_title||`Задача #${obj.task_id}`; box.style.display='block'; startTick(); }
    function clearRunning(){ running=null; stopTick(); box.style.display='none'; }
    function update(){ if (!running) return; const sec=Math.max(0,Math.floor((Date.now()-running.started_ms)/1000)); elTime.textContent=formatHMS(sec); }

    // Надёжный парсер ответа (если прилетит HTML)
    async function parseJsonOrThrow(res){
        const ct = (res.headers.get('content-type')||'').toLowerCase();
        if (ct.includes('application/json')) return res.json();
        const text = await res.text();
        throw new Error(text || `HTTP ${res.status}`);
    }

    // API
    async function poll(){
        try{
            const r = await fetch(API.status, { credentials:'same-origin' });
            const js = await parseJsonOrThrow(r);
            if (js.running) setRunning({ task_id: js.task_id, task_title: js.task_title, started_ms: Date.parse(js.started_at) });
            else clearRunning();
        }catch{/* ignore */}
    }

    btnStop.addEventListener('click', async (e)=>{
        e.stopPropagation();
        try{
            const body = new URLSearchParams({ csrf: CSRF });
            const r = await fetch(API.stop, { method:'POST', body, credentials:'same-origin' });
            const js = await parseJsonOrThrow(r);
            if (!js.ok) throw new Error(js.error||'stop failed');
            clearRunning();
            if (document.getElementById('taskTimeTotal')) location.reload();
        }catch(err){ alert('Ошибка стопа таймера: '+err.message); }
    });

    box.addEventListener('click', ()=>{
        if (!running) return;
        window.location.href = abs(`boards/tasks/task_view.php?id=${running.task_id}`);
    });

    // Экспорт
    window.TaskTimer = {
        async start(taskId){
            try{
                const body = new URLSearchParams({ csrf: CSRF, task_id: String(taskId) });
                const r = await fetch(API.start, {
                    method:'POST',
                    body,
                    credentials:'same-origin'
                });
                const js = await parseJsonOrThrow(r);
                if (!js.ok) throw new Error(js.error||'start failed');
                setRunning({ task_id: js.task_id, task_title: js.task_title, started_ms: Date.parse(js.started_at) });
            }catch(e){ alert('Не удалось запустить таймер: '+e.message); }
        },
        async stop(){
            try{
                const body = new URLSearchParams({ csrf: CSRF });
                const r = await fetch(API.stop, { method:'POST', body, credentials:'same-origin' });
                await parseJsonOrThrow(r);
                clearRunning();
            }catch(e){ /* ignore */ }
        }
    };

    // Первый статус + периодический опрос
    if (document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', ()=>{ poll(); setInterval(poll,20000); }); }
    else { poll(); setInterval(poll,20000); }
})();
