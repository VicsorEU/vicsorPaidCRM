// Мини-компонент "поисковый селект" для товаров (без сторонних библиотек)
(function(){
    const API_ATTR = 'data-api';
    const table = document.getElementById('itemsTable');
    if (!table) return;

    const apiUrl = table.getAttribute(API_ATTR);
    if (!apiUrl) return;

    // Публичная инициализация для уже существующих строк
    window.initProductPicker = function(container){
        container.querySelectorAll('.product-picker').forEach(setupPicker);
    };

    // Инициализация для таблицы (делегирование)
    initProductPicker(table);

    function setupPicker(box){
        if (box.__inited) return;
        box.__inited = true;

        const input = box.querySelector('.product-search');
        const list  = box.querySelector('.picker-list');
        const hidId = box.querySelector('input[name="item_product_id[]"]');
        const hidNm = box.querySelector('input[name="item_name[]"]');
        const hidSku= box.querySelector('input[name="item_sku[]"]');
        const skuOut= box.querySelector('.sku-out');
        const unitSel= box.closest('tr').querySelector('select[name="item_unit[]"]');
        const priceInput = box.closest('tr').querySelector('input[name="item_price[]"]');

        let lastQuery = '';
        let idx = -1;

        input.addEventListener('input', debounce(async ()=>{
            const q = (input.value || '').trim();
            lastQuery = q;
            if (q.length === 0) { hide(); return; }
            const items = await fetchJson(apiUrl + '?q=' + encodeURIComponent(q) + '&limit=20');
            renderList(items || []);
        }, 200));

        input.addEventListener('focus', ()=>{
            if (input.value.trim().length > 0) input.dispatchEvent(new Event('input'));
        });

        input.addEventListener('keydown', (e)=>{
            const options = list.querySelectorAll('.picker-item');
            if (!options.length) return;

            if (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(options.length-1, idx+1); highlight(options, idx); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); idx = Math.max(0, idx-1); highlight(options, idx); }
            else if (e.key === 'Enter') {
                if (idx >= 0) { e.preventDefault(); options[idx].click(); }
            }
        });

        document.addEventListener('click', (e)=>{
            if (!box.contains(e.target)) hide();
        });

        function renderList(items){
            list.innerHTML = '';
            idx = -1;
            if (!items.length) { hide(); return; }

            items.forEach(p=>{
                const el = document.createElement('div');
                el.className = 'picker-item';
                el.innerHTML = `
          <div class="pi-top"><b>${escapeHtml(p.sku || '—')}</b> — ${escapeHtml(p.name)}</div>
          <div class="pi-sub">Ед.: ${escapeHtml(p.unit || 'шт')} · Цена: ${Number(p.price||0).toFixed(2)}</div>
        `;
                el.addEventListener('click', ()=>{
                    // Заполняем поля
                    hidId.value  = p.id;
                    hidNm.value  = p.name;
                    hidSku.value = p.sku || '';
                    input.value  = `${p.sku || '—'} — ${p.name}`;
                    if (skuOut) skuOut.textContent = p.sku || '—';

                    // Установим единицу и цену
                    if (unitSel) selectIfExists(unitSel, p.unit || 'шт');
                    if (priceInput && (!priceInput.value || Number(priceInput.value) === 0)) {
                        priceInput.value = Number(p.price || 0).toFixed(2);
                        priceInput.dispatchEvent(new Event('input', {bubbles:true}));
                    }

                    hide();
                });
                list.appendChild(el);
            });
            show();
        }

        function show(){ list.hidden = false; box.classList.add('open'); }
        function hide(){ list.hidden = true; box.classList.remove('open'); }
    }

    function selectIfExists(sel, value){
        const v = String(value || '');
        const found = Array.from(sel.options).some(o => o.value === v);
        if (!found) {
            const o = document.createElement('option'); o.value = v; o.textContent = v;
            sel.appendChild(o);
        }
        sel.value = v;
    }

    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
    function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
    async function fetchJson(url){
        const r = await fetch(url, {credentials:'same-origin'});
        if (!r.ok) return [];
        return r.json();
    }
})();
