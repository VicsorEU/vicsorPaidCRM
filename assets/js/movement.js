// assets/js/movement.js
// Поддерживает два режима таблицы позиций:
// data-mode="free"  → ручной ввод SKU/названия
// data-mode="pick"  → выбор из остатков выбранного склада (API boards/api/instock.php)
//
// Требования к разметке:
// <table id="mvItems" data-mode="pick|free" data-api-instock="/.../boards/api/instock.php" data-doc-type="in|out|transfer|adjust">
//   <tbody>...</tbody>
// </table>
// Кнопка добавления: #addItem
//
(function(){
    const tbl = document.getElementById('mvItems');
    if (!tbl) return;

    const mode = tbl.dataset.mode || 'free';
    const api  = tbl.dataset.apiInstock || '';
    const docType = tbl.dataset.docType || 'in';

    const addBtn = document.getElementById('addItem');

    addBtn && addBtn.addEventListener('click', (e)=>{ e.preventDefault(); addRow(); recalc(); });

    tbl.addEventListener('input', (e)=>{ if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') recalc(); });

    tbl.addEventListener('click', (e)=>{
        if (e.target.closest('[data-remove]')) {
            e.preventDefault();
            e.target.closest('tr')?.remove();
            recalc();
        }
    });

    function getWhForPick(){
        if (docType === 'transfer') {
            return document.querySelector('select[name="src_warehouse_id"]')?.value || 0;
        }
        return document.querySelector('select[name="warehouse_id"]')?.value || 0;
    }

    function addRow(row) {
        if (mode === 'pick') {
            const tr = document.createElement('tr');
            const selValue = row?.product_id || '';
            const qty = row?.qty || '1';
            const price = row?.price || '0.00';
            const name = row?.name || '';
            const sku  = row?.sku  || '';

            tr.innerHTML = `
        <td>
          <select class="prodsel" name="item_product_id[]" data-selected="${selValue}" required></select>
          <input type="hidden" name="item_name[]" value="${escapeHtml(name)}">
          <input type="hidden" name="item_sku[]"  value="${escapeHtml(sku)}">
          <div class="muted" style="font-size:12px;color:#6b778c;margin-top:6px;">Доступно: <b class="avail">—</b></div>
        </td>
        <td style="width:120px;"><input name="item_qty[]" type="number" step="0.001" min="0" value="${qty}"></td>
        <td style="width:140px;"><input name="item_price[]" type="number" step="0.01" min="0" value="${price}"></td>
        <td style="width:140px;" class="line-total">0.00</td>
        <td style="width:60px;"><a href="#" data-remove class="btn">×</a></td>
      `;
            tbl.querySelector('tbody').appendChild(tr);
            populateSelect(tr);
        } else {
            const tr = document.createElement('tr');
            const qty = row?.qty || '1';
            const price = row?.price || '0.00';
            const name = row?.name || '';
            const sku  = row?.sku  || '';

            tr.innerHTML = `
        <td style="width:140px;"><input name="item_sku[]" placeholder="SKU" value="${escapeHtml(sku)}"></td>
        <td><input name="item_name[]" placeholder="Наименование" value="${escapeHtml(name)}" required></td>
        <td style="width:120px;"><input name="item_qty[]" type="number" step="0.001" min="0" value="${qty}" required></td>
        <td style="width:140px;"><input name="item_price[]" type="number" step="0.01" min="0" value="${price}"></td>
      <td style="width:140px;" class="line-total">0.00</td>
        <td style="width:60px;"><a href="#" data-remove class="btn">×</a></td>
      `;
            tbl.querySelector('tbody').appendChild(tr);
        }
    }

    async function populateSelect(tr){
        const wh = parseInt(getWhForPick(), 10);
        const sel = tr.querySelector('.prodsel');
        if (!sel) return;
        sel.innerHTML = '<option value="">— загружаем… —</option>';

        if (!api || !wh) { sel.innerHTML = '<option value="">— выберите склад —</option>'; return; }

        const url = api + '?warehouse_id=' + encodeURIComponent(wh) + '&q=';
        try {
            const res = await fetch(url, {credentials: 'same-origin'});
            const list = await res.json();

            sel.innerHTML = '<option value="">— выберите товар —</option>';
            for (const p of list) {
                const o = document.createElement('option');
                o.value = p.id;
                o.textContent = `${p.sku} — ${p.name} (есть: ${Number(p.qty).toFixed(3)} ${p.unit})`;
                o.dataset.sku = p.sku;
                o.dataset.name = p.name;
                o.dataset.qty = p.qty;
                sel.appendChild(o);
            }

            const selectedId = sel.dataset.selected;
            if (selectedId) {
                sel.value = selectedId;
                const opt = sel.options[sel.selectedIndex];
                tr.querySelector('input[name="item_sku[]"]').value  = opt?.dataset.sku || '';
                tr.querySelector('input[name="item_name[]"]').value = opt?.dataset.name || '';
                tr.querySelector('.avail').textContent = opt?.dataset.qty ? Number(opt.dataset.qty).toFixed(3) : '—';
            }

            sel.addEventListener('change', ()=>{
                const opt = sel.options[sel.selectedIndex];
                tr.querySelector('input[name="item_sku[]"]').value  = opt?.dataset.sku || '';
                tr.querySelector('input[name="item_name[]"]').value = opt?.dataset.name || '';
                tr.querySelector('.avail').textContent = opt?.dataset.qty ? Number(opt.dataset.qty).toFixed(3) : '—';
            });

        } catch(e){
            sel.innerHTML = '<option value="">— ошибка загрузки —</option>';
            console.error(e);
        }
    }

    function recalc() {
        let sum = 0;
        tbl.querySelectorAll('tbody tr').forEach(tr => {
            const qty   = parseFloat(getVal(tr,'item_qty[]')) || 0;
            const price = parseFloat(getVal(tr,'item_price[]')) || 0;
            const total = Math.round(qty * price * 100) / 100;
            sum += total;
            const cell = tr.querySelector('.line-total');
            if (cell) cell.textContent = total.toFixed(2);
        });
        const el = document.getElementById('docTotal');
        if (el) el.textContent = sum.toFixed(2);
    }

    function getVal(tr, name){
        const el = tr.querySelector(`input[name="${name}"]`);
        return (el?.value || '').replace(',','.');
    }
    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

    // при смене склада — обновить селекты (в pick-режиме)
    document.addEventListener('change', (e)=>{
        if (mode !== 'pick') return;
        if (e.target?.name === 'warehouse_id' || e.target?.name === 'src_warehouse_id') {
            tbl.querySelectorAll('tbody tr').forEach(populateSelect);
        }
    });

    // Авто-строка, если пусто
    if (!tbl.querySelector('tbody tr')) addRow();

    // Пересчёт изначально
    recalc();

    // Экспортируем в window для отладки (не обязательно)
    window._mv = { addRow, recalc };
})();
