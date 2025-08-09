(function(){
    const typeSel = document.getElementById('attrType');
    const block   = document.getElementById('optionsBlock');
    const addBtn  = document.getElementById('addOptBtn');

    if (typeSel) {
        const toggle = () => {
            const t = typeSel.value;
            block.style.display = (t === 'select' || t === 'multiselect') ? '' : 'none';
        };
        typeSel.addEventListener('change', toggle);
        toggle();
    }

    addBtn?.addEventListener('click', () => {
        const wrap = block.querySelector('.opts');
        const row = document.createElement('div');
        row.className = 'opt-row';
        row.innerHTML = `
      <input type="hidden" name="opt_id[]" value="0">
      <input type="text" name="opt_value[]" placeholder="Значение">
      <input type="number" name="opt_position[]" value="0" style="width:100px" placeholder="Порядок">
      <button class="btn danger del-opt" type="button">Удалить</button>
    `;
        wrap.appendChild(row);
    });

    block?.addEventListener('click', (e) => {
        const del = e.target.closest('.del-opt');
        if (!del) return;
        del.closest('.opt-row')?.remove();
    });
})();
