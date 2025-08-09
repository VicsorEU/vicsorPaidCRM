(function(){
    const panel = document.getElementById('attrPanel');
    if (!panel) return;

    const fetchUrl = panel.getAttribute('data-fetch');
    const saveUrl  = panel.getAttribute('data-save');
    const productId= Number(panel.getAttribute('data-product-id'));
    const CSRF     = panel.getAttribute('data-csrf');

    const attrForm = document.getElementById('attrForm');
    const attrFields = document.getElementById('attrFields');
    const hint = document.getElementById('attrHint');
    const addSelect = document.getElementById('attrAddSelect');
    const addBtn    = document.getElementById('attrAddBtn');

    let ALL = [];         // все атрибуты (справочник)
    let VALUES = {};      // текущие значения у товара {attrId: {...}}

    function option(label, val){ const o=document.createElement('option'); o.textContent=label; o.value=val; return o; }
    function esc(s){ return (s??'').toString().replace(/[&<>"]/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c])); }

    async function init(){
        const r = await fetch(`${fetchUrl}?product_id=${productId}`, {credentials:'same-origin'});
        const js = await r.json();
        if (!js.ok){ alert(js.error||'Не удалось загрузить атрибуты'); return; }
        ALL = js.attributes||[];
        VALUES = js.values||{};

        // Заполним селект для добавления
        addSelect.innerHTML = '';
        addSelect.appendChild(option('— выберите атрибут —',''));
        ALL.forEach(a => addSelect.appendChild(option(`${a.name}${a.code?` (${a.code})`:''}`, a.id)));

        // Предзаполним строки по тем атрибутам, у которых есть значения
        Object.keys(VALUES).forEach(k => addRow(findAttr(Number(k)), VALUES[k]));
        toggleForm();
    }

    function findAttr(id){ return ALL.find(a => Number(a.id)===Number(id)); }

    function toggleForm(){
        const hasRows = !!attrFields.querySelector('.attr-row');
        hint.style.display = hasRows ? 'none' : '';
        attrForm.style.display = hasRows ? '' : 'none';
    }

    addBtn?.addEventListener('click', ()=>{
        const id = Number(addSelect.value||0);
        const a = findAttr(id);
        if (!a) return;
        // если уже есть — просто сфокусируем
        const exists = attrFields.querySelector(`.attr-row[data-id="${id}"]`);
        if (exists) { exists.scrollIntoView({behavior:'smooth',block:'center'}); exists.classList.add('blink'); setTimeout(()=>exists.classList.remove('blink'),700); return; }
        addRow(a, null);
        toggleForm();
    });

    function addRow(a, preset){
        const wrap = document.createElement('div');
        wrap.className = 'attr-row';
        wrap.dataset.id = a.id;
        wrap.dataset.type = a.type;

        const title = document.createElement('div');
        title.style.fontWeight='600';
        title.style.display='flex'; title.style.alignItems='center'; title.style.gap='8px';
        title.innerHTML = `<span>${esc(a.name)}</span>${a.code?`<code style="color:#64748b;background:#f1f5f9;border-radius:4px;padding:2px 4px">${esc(a.code)}</code>`:''}`;
        wrap.appendChild(title);

        let control = null;

        if (a.type === 'text' || a.type === 'number' || a.type === 'date') {
            control = document.createElement('input');
            control.type = a.type === 'number' ? 'number' : (a.type === 'date' ? 'date' : 'text');
            control.value = preset?.value ?? '';
            control.className = 'input';
            control.style.maxWidth='360px';
        } else if (a.type === 'bool') {
            control = document.createElement('input');
            control.type = 'checkbox';
            control.checked = !!(preset?.value);
        } else if (a.type === 'select') {
            control = document.createElement('select');
            (a.options||[]).forEach(o=>{
                const opt = document.createElement('option');
                opt.value = o.id; opt.textContent = o.value;
                if (Number(preset?.value||0)===Number(o.id)) opt.selected = true;
                control.appendChild(opt);
            });
        } else if (a.type === 'multiselect') {
            control = document.createElement('select');
            control.multiple = true;
            control.size = Math.min(6, (a.options||[]).length || 3);
            const set = new Set(preset?.values||[]);
            (a.options||[]).forEach(o=>{
                const opt = document.createElement('option');
                opt.value = o.id; opt.textContent = o.value;
                if (set.has(Number(o.id))) opt.selected = true;
                control.appendChild(opt);
            });
        }

        control.classList.add('attr-input');
        wrap.appendChild(control);

        const del = document.createElement('button');
        del.type='button'; del.className='btn danger'; del.textContent='Удалить';
        del.style.marginLeft='8px';
        del.addEventListener('click', ()=>{ wrap.remove(); toggleForm(); });
        wrap.appendChild(del);

        attrFields.appendChild(wrap);
    }

    attrForm?.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const payload = [];
        attrFields.querySelectorAll('.attr-row').forEach(row=>{
            const id = Number(row.dataset.id);
            const type = row.dataset.type;
            const el = row.querySelector('.attr-input');

            if (type === 'multiselect') {
                const values = Array.from(el.selectedOptions).map(o=>Number(o.value));
                payload.push({id, type, values});
            } else if (type === 'bool') {
                payload.push({id, type, value: el.checked ? 1 : 0});
            } else if (type === 'number') {
                payload.push({id, type, value: el.value === '' ? null : Number(el.value)});
            } else {
                payload.push({id, type, value: el.value});
            }
        });

        const fd = new FormData();
        fd.append('csrf', CSRF);
        fd.append('product_id', String(productId));
        fd.append('attrs', JSON.stringify(payload));

        const r = await fetch(saveUrl, { method:'POST', body: fd, credentials:'same-origin' });
        const js = await r.json();
        if (!js.ok) { alert(js.error||'Не удалось сохранить атрибуты'); return; }
        alert('Атрибуты сохранены');
    });

    init();
})();
