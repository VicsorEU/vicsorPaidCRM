// assets/js/order.js
// Добавление/удаление строк и пересчёт сумм в заказе (new/edit)
(function(){
    const table = document.getElementById('itemsTable');
    if (!table) return;

    const addBtn = document.getElementById('addItem');
    addBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        addRow();
        recalc();
    });

    table.addEventListener('input', (e) => {
        if (e.target.matches('input')) recalc();
    });

    table.addEventListener('click', (e) => {
        if (e.target.closest('[data-remove]')) {
            e.preventDefault();
            const tr = e.target.closest('tr');
            tr?.remove();
            recalc();
        }
    });

    function addRow(row={name:'',sku:'',qty:'1',price:'0.00'}) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
      <td><input name="item_name[]" placeholder="Наименование" value="${escapeHtml(row.name)}" required></td>
      <td style="width:140px;"><input name="item_sku[]" placeholder="SKU" value="${escapeHtml(row.sku||'')}"></td>
      <td style="width:120px;"><input name="item_qty[]" type="number" step="0.001" min="0" value="${row.qty}" required></td>
      <td style="width:140px;"><input name="item_price[]" type="number" step="0.01" min="0" value="${row.price}"></td>
      <td style="width:140px;" class="line-total">0.00</td>
      <td style="width:60px;"><a href="#" data-remove class="btn">×</a></td>
    `;
        table.querySelector('tbody').appendChild(tr);
    }

    function recalc() {
        let sum = 0;
        table.querySelectorAll('tbody tr').forEach(tr => {
            const qty   = parseFloat(getVal(tr,'item_qty[]'))   || 0;
            const price = parseFloat(getVal(tr,'item_price[]')) || 0;
            const total = Math.round(qty * price * 100) / 100;
            sum += total;
            const cell = tr.querySelector('.line-total');
            if (cell) cell.textContent = total.toFixed(2);
        });

        setVal('total_items', sum);
        const ship = getInputVal('total_shipping');
        const disc = getInputVal('total_discount');
        const tax  = getInputVal('total_tax');
        const grand = Math.round((sum + ship - disc + tax) * 100) / 100;
        setVal('total_amount', grand);
        const gt = document.getElementById('grandTotal');
        if (gt) gt.textContent = grand.toFixed(2);
    }

    ['total_shipping','total_discount','total_tax'].forEach(id=>{
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', recalc);
    });

    function getVal(tr, name){
        const el = tr.querySelector(`input[name="${name}"]`);
        return (el?.value || '').replace(',','.');
    }
    function getInputVal(id){
        const el = document.getElementById(id);
        return parseFloat((el?.value || '').replace(',','.')) || 0;
    }
    function setVal(id, v){
        const el = document.getElementById(id);
        if (el) el.value = Number(v).toFixed(2);
    }
    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

    // если таблица была пустой (new) — добавим первую строку
    if (!table.querySelector('tbody tr')) addRow();
    recalc();
})();
