// Логика строк заказа: добавление/удаление + пересчёт итогов + инициализация пикера
(function(){
    const table = document.getElementById('itemsTable');
    if (!table) return;

    const addBtn = document.getElementById('addItem');

    // Слоты с данными
    const units = tryParseJson(table.getAttribute('data-units')) || ["шт","упак","кг","г","л","м","см","м2","м3","час","компл"];

    addBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        addRow();
        recalc();
    });

    table.addEventListener('input', (e) => {
        if (e.target.matches('input, select')) recalc();
    });

    table.addEventListener('click', (e) => {
        if (e.target.closest('[data-remove]')) {
            e.preventDefault();
            const tr = e.target.closest('tr');
            tr?.remove();
            recalc();
        }
    });

    // Инициализируем пикер для уже существующих строк (редактирование)
    if (window.initProductPicker) window.initProductPicker(table);

    function addRow(row){
        const tr = document.createElement('tr');
        tr.innerHTML = `
      <td class="product-picker">
        <input class="product-search" placeholder="Поиск товара (SKU/название)" autocomplete="off">
        <input type="hidden" name="item_product_id[]">
        <input type="hidden" name="item_name[]">
        <input type="hidden" name="item_sku[]">
        <div class="picker-list" hidden></div>
        <div class="muted" style="font-size:12px;color:#6b778c;margin-top:6px;">SKU: <b class="sku-out">—</b></div>
      </td>
      <td style="width:130px;">
        <select name="item_unit[]">${units.map(u=>`<option value="${escapeHtml(u)}">${escapeHtml(u)}</option>`).join('')}</select>
      </td>
      <td style="width:120px;"><input name="item_qty[]" type="number" step="0.001" min="0" value="1" required></td>
      <td style="width:140px;"><input name="item_price[]" type="number" step="0.01" min="0" value="0.00"></td>
      <td style="width:140px;" class="line-total">0.00</td>
      <td style="width:60px;"><a href="#" data-remove class="btn">×</a></td>
    `;
        table.querySelector('tbody').appendChild(tr);
        if (window.initProductPicker) window.initProductPicker(tr);
    }

    function recalc() {
        let sum = 0;
        table.querySelectorAll('tbody tr').forEach(tr => {
            const qty   = num(getVal(tr,'item_qty[]'));
            const price = num(getVal(tr,'item_price[]'));
            const total = Math.round(qty * price * 100) / 100;
            sum += total;
            const cell = tr.querySelector('.line-total');
            if (cell) cell.textContent = total.toFixed(2);
        });

        setVal('total_items', sum);
        const ship = numId('total_shipping');
        const disc = numId('total_discount');
        const tax  = numId('total_tax');
        const grand = Math.round((sum + ship - disc + tax) * 100) / 100;
        setVal('total_amount', grand);
        const gt = document.getElementById('grandTotal');
        if (gt) gt.textContent = grand.toFixed(2);
    }

    function getVal(tr, name){ return (tr.querySelector(`input[name="${name}"]`)?.value || '').replace(',','.'); }
    function setVal(id, v){ const el = document.getElementById(id); if (el) el.value = Number(v).toFixed(2); }
    function num(v){ const n = parseFloat(String(v || '').replace(',','.')); return isNaN(n)?0:n; }
    function numId(id){ const el = document.getElementById(id); return num(el?.value); }
    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
    function tryParseJson(s){ try { return JSON.parse(s || ''); } catch { return null; } }

    // Если это "new" и строк нет — добавим первую
    if (!table.querySelector('tbody tr')) addRow();
    recalc();
})();
