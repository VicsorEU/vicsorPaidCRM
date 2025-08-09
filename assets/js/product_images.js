(function(){
    const grid = document.getElementById('imageGrid');
    const fileInput = document.getElementById('imageUpload');
    if (!grid || !fileInput) return;

    const panel  = document.getElementById('attrPanel') || document.body;
    const CSRF   = (panel.getAttribute('data-csrf') || document.querySelector('meta[name="csrf-token"]')?.content || '');
    const productId = Number(panel.getAttribute('data-product-id')) || Number(document.querySelector('input[name="id"]')?.value || 0);
    const API_UP   = (window.APP_BASE_URL ? window.APP_BASE_URL + '/' : '/') + 'boards/products/api/product_image_upload.php';
    const API_DEL  = (window.APP_BASE_URL ? window.APP_BASE_URL + '/' : '/') + 'boards/products/api/product_image_delete.php';
    const API_PRIM = (window.APP_BASE_URL ? window.APP_BASE_URL + '/' : '/') + 'boards/products/api/product_image_primary.php';

    fileInput.addEventListener('change', async () => {
        const files = fileInput.files;
        if (!files || !files.length) return;

        const fd = new FormData();
        fd.append('csrf', CSRF);
        fd.append('product_id', String(productId));
        for (const f of files) fd.append('files[]', f);

        const r = await fetch(API_UP, { method:'POST', body: fd, credentials:'same-origin' });
        const js = await r.json();
        if (!js.ok) { alert(js.error||'Ошибка загрузки'); return; }

        (js.files||[]).forEach(addThumb);
        fileInput.value = '';
    });

    grid.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-act]');
        if (!btn) return;
        const item = btn.closest('.image-item'); if (!item) return;
        const id = Number(item.dataset.id);
        const act = btn.getAttribute('data-act');

        if (act === 'delete') {
            if (!confirm('Удалить изображение?')) return;
            const fd = new FormData(); fd.append('csrf', CSRF); fd.append('id', String(id));
            const r = await fetch(API_DEL, { method:'POST', body: fd, credentials:'same-origin' });
            const js = await r.json();
            if (!js.ok) { alert(js.error||'Ошибка'); return; }
            item.remove();
        }

        if (act === 'primary') {
            const fd = new FormData(); fd.append('csrf', CSRF); fd.append('id', String(id));
            const r = await fetch(API_PRIM, { method:'POST', body: fd, credentials:'same-origin' });
            const js = await r.json();
            if (!js.ok) { alert(js.error||'Ошибка'); return; }
            grid.querySelectorAll('.image-item .tag').forEach(t=>t.remove());
            grid.querySelectorAll('.image-item [data-act="primary"]').forEach(b=>{
                b.outerHTML = '<button class="btn sm" data-act="primary">Сделать обложкой</button>';
            });
            const tag = document.createElement('span'); tag.className='tag'; tag.textContent='Обложка';
            item.querySelector('.image-actions').prepend(tag);
            item.querySelector('[data-act="primary"]')?.remove();
        }
    });

    function addThumb(f){
        const div = document.createElement('div');
        div.className = 'image-item';
        div.dataset.id = f.id;
        div.innerHTML = `
      <img src="${f.url}" alt="">
      <div class="image-actions">
        ${f.is_primary ? '<span class="tag">Обложка</span>' : '<button class="btn sm" data-act="primary">Сделать обложкой</button>'}
        <button class="btn sm danger" data-act="delete">Удалить</button>
      </div>`;
        grid.appendChild(div);
    }
})();
