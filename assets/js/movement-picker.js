// assets/js/movement-picker.js
// Поисковый выбор товара в документах склада (приход/расход/перемещение/корректировка)
(function(){
    const table = document.getElementById('mvItems');
    if (!table) return;

    const mode = table.getAttribute('data-mode') || 'free'; // free (catalog) | pick (instock)
    const apiCatalog = table.getAttribute('data-api-catalog');
    const apiInstock = table.getAttribute('data-api-instock');
    const units = tryParseJson(table.getAttribute('data-units')) || ["шт","упак","кг","г","л","м","см","м2","м3","час","компл"];

    // кнопка "добавить"
    const addBtn = document.getElementById('addItem');
    addBtn?.addEventListener('click', (e)=>{ e.preventDefault(); addRow(); triggerRecalc(); });

    // делегирование: инпуты → пересчёт (передаст в movement.js)
    table.addEventListener('input', (e)=>{ if (e.target.matches('input,select')) triggerRecalc(); });
    table.addEventListener('click', (e)=>{
        if (e.target.closest('[data-remove]')) { e.preventDefault(); e.target.closest('tr')?.remove(); triggerRecalc(); }
    });

    // пересоздать списки при смене склада (важно для pick)
    document.addEventListener('change', (e)=>{
        if (mode !== 'pick') return;
        if (e.target.name === 'warehouse_id' || e.target.name === 'src_warehouse_id') {
            // очистим введённые значения поиска, чтобы пользователь заново выбрал из нового склада
            table.querySelectorAll('.product-picker').forEach(box=>{
                const inp = box.querySelector('.product-search'); if (inp) inp.value = '';
                const avail = box.querySelector('.avail'); if (avail) avail.textContent = '—';
                box.querySelector('input[name="item_product_id[]"]').value = '';
                box.querySelector('input[name="item_name[]"]').value = '';
                box.querySelector('input[name="item_sku[]"]').value = '';
            });
        }
    });

    // инициализация уже существующих строк (в редактировании)
    initPickers(table);

    // если строк нет — добавим одну
    if (!table.querySelector('tbody tr')) addRow();

    function addRow(){
        const tr = document.createElement('tr');
        tr.innerHTML = `
      <td class="product-picker">
        <input class="product-search" placeholder="${mode==='pick'?'Поиск по наличию':'Поиск по каталогу'} (SKU/название)" autocomplete="off">
        <input type="hidden" name="item_product_id[]">
        <input type="hidden" name="item_name[]">
        <input type="hidden" name="item_sku[]">
        <div class="picker-list" hidden></div>
        ${mode==='pick' ? '<div class="muted" style="font-size:12px;color:#6b778c;margin-top:6px;">Доступно: <b class="avail">—</b></div>' : '<div class="muted" style="font-size:12px;color:#6b778c;margin-top:6px;">SKU: <b class="sku-out">—</b></div>'}
      </td>
      <td style="width:130px;">
        <select name="item_unit[]">${units.map(u=>`<option value="${escapeHtml(u)}">${escapeHtml(u)}</option>`).join('')}</select>
      </td>
      <td style="width:120px;"><input name="qty[]" type="number" step="0.001" min="0" value="1" required></td>
      <td style="width:140px;"><input name="price[]" type="number" step="0.01" min="0" value="0.00"></td>
      <td style="width:140px;" class="line-total">0.00</td>
      <td style="width:60px;"><a href="#" class="btn" data-remove>×</a></td>
    `;
        table.querySelector('tbody').appendChild(tr);
        setupPicker(tr.querySelector('.product-picker'));
    }

    function initPickers(root){
        root.querySelectorAll('.product-picker').forEach(setupPicker);
    }

    function setupPicker(box){
        if (!box || box.__inited) return;
        box.__inited = true;

        const input = box.querySelector('.product-search');
        const list  = box.querySelector('.picker-list');
        const hidId = box.querySelector('input[name="item_product_id[]"]');
        const hidNm = box.querySelector('input[name="item_name[]"]');
        const hidSku= box.querySelector('input[name="item_sku[]"]');
        const skuOut= box.querySelector('.sku-out');
        const availOut = box.querySelector('.avail');
        const tr = box.closest('tr');
        const unitSel = tr.querySelector('select[name="item_unit[]"]');
        const priceInput = tr.querySelector('input[name="price[]"]');

        let idx = -1;

        input.addEventListener('input', debounce(async ()=>{
            const q = (input.value || '').trim();
            if (!q) { hide(); return; }

            let api = apiCatalog;
            if (mode === 'pick') {
                const wh = currentWarehouse();
                if (!wh) { list.innerHTML=''; hide(); return; }
                api = apiInstock + '?warehouse_id=' + encodeURIComponent(wh);
            }
            const url = api + (api.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q) + '&limit=20';
            const items = await fetchJson(url);
            renderList(items || []);
        }, 220));

        input.addEventListener('focus', ()=>{ if (input.value.trim()) input.dispatchEvent(new Event('input')); });

        input.addEventListener('keydown', (e)=>{
            const options = list.querySelectorAll('.picker-item');
            if (!options.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(options.length-1, idx+1); highlight(options, idx); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); idx = Math.max(0, idx-1); highlight(options, idx); }
            else if (e.key === 'Enter') { if (idx>=0) { e.preventDefault(); options[idx].click(); } }
        });

        document.addEventListener('click', (e)=>{ if (!box.contains(e.target)) hide(); });

        function renderList(items){
            list.innerHTML = '';
            idx = -1;
            if (!items.length) { hide(); return; }
            items.forEach(p=>{
                const el = document.createElement('div');
                el.className = 'picker-item';
                el.innerHTML = `
          <div class="pi-top"><b>${escapeHtml(p.sku || '—')}</b> — ${escapeHtml(p.name)}</div>
          <div class="pi-sub">
            Ед.: ${escapeHtml(p.unit || 'шт')} · Цена: ${Number(p.price||0).toFixed(2)}
            ${typeof p.qty !== 'undefined' ? ' · Доступно: ' + Number(p.qty).toFixed(3) : ''}
          </div>`;
                el.addEventListener('click', ()=>{
                    hidId.value = p.id;
                    hidNm.value = p.name;
                    hidSku.value = p.sku || '';
                    input.value  = `${p.sku || '—'} — ${p.name}`;
                    if (skuOut) skuOut.textContent = p.sku || '—';
                    if (availOut && typeof p.qty !== 'undefined') availOut.textContent = Number(p.qty).toFixed(3);

                    // Установим единицу и цену, если пусто/0
                    selectIfExists(unitSel, p.unit || 'шт');
                    if (priceInput && (!priceInput.value || Number(priceInput.value) === 0)) {
                        priceInput.value = Number(p.price || 0).toFixed(2);
                        priceInput.dispatchEvent(new Event('input', {bubbles:true}));
                    }
                    hide(); triggerRecalc();
                });
                list.appendChild(el);
            });
            show();
        }

        function show(){ list.hidden = false; box.classList.add('open'); }
        function hide(){ list.hidden = true; box.classList.remove('open'); }
    }

    function currentWarehouse(){
        // для transfer берём ИСТОЧНИК
        const src = document.querySelector('select[name="src_warehouse_id"]');
        const wh  = document.querySelector('select[name="warehouse_id"]');
        return (src && src.value) ? src.value : (wh ? wh.value : 0);
    }

    function selectIfExists(sel, value){
        if (!sel) return;
        const v = String(value || '');
        const found = Array.from(sel.options).some(o => o.value === v);
        if (!found) { const o = document.createElement('option'); o.value=v; o.textContent=v; sel.appendChild(o); }
        sel.value = v;
    }

    function triggerRecalc(){ document.getElementById('mvItems')?.dispatchEvent(new Event('input', {bubbles:true})); }

    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
    function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
    async function fetchJson(url){ const r = await fetch(url, {credentials:'same-origin'}); if (!r.ok) return []; return r.json(); }
    function tryParseJson(s){ try { return JSON.parse(s || ''); } catch { return null; } }
})();
