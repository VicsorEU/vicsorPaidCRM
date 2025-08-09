(function(){
    const wrap = document.getElementById('catTree');
    if (!wrap) return;

    const API_SAVE = wrap.getAttribute('data-api-save');
    const API_DEL  = wrap.getAttribute('data-api-del');
    const CSRF     = wrap.getAttribute('data-csrf');

    const slugify = s => (s||'').toString()
        .toLowerCase()
        .replace(/[а-яё]/g, ch => ({'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'e','ж':'zh','з':'z','и':'i','й':'j','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'c','ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'}[ch]||ch))
        .replace(/[^\w]+/g,'-').replace(/-+/g,'-').replace(/(^-|-$)/g,'');

    document.getElementById('addRootBtn')?.addEventListener('click', async () => {
        const name = prompt('Название категории:');
        if (!name) return;
        const slug = prompt('Слаг (для URL):', slugify(name)) || '';
        const desc = prompt('Описание (необязательно):', '') || '';
        await createCat(null, name, slug, desc);
    });

    wrap.addEventListener('click', async (e) => {
        const add = e.target.closest('.add');
        const ren = e.target.closest('.rename');
        const del = e.target.closest('.del');

        if (add) {
            const id = add.dataset.id;
            const name = prompt('Название подкатегории:');
            if (!name) return;
            const slug = prompt('Слаг (для URL):', slugify(name)) || '';
            const desc = prompt('Описание (необязательно):', '') || '';
            await createCat(id, name, slug, desc);
        }
        if (ren) {
            const li = ren.closest('li[data-id]');
            const id = Number(li.dataset.id);
            const titleEl = li.querySelector('.node-title');
            const currName = titleEl?.textContent?.trim() || '';
            const currSlug = li.dataset.slug || '';
            const currDesc = li.dataset.desc || '';
            const name = prompt('Новое имя:', currName); if (!name) return;
            const slug = prompt('Слаг:', currSlug || slugify(name)) || '';
            const desc = prompt('Описание:', currDesc) || '';
            await editCat(id, name, slug, desc);
            titleEl.textContent = name;
            li.dataset.slug = slug; li.dataset.desc = desc;
        }
        if (del) {
            const id = del.dataset.id;
            if (!confirm('Удалить категорию и все подкатегории?')) return;
            await removeCat(id);
            wrap.querySelector(`li[data-id="${id}"]`)?.remove();
        }
    });

    async function createCat(parentId, name, slug, desc) {
        const fd = new FormData();
        fd.append('csrf', CSRF);
        fd.append('action','create');
        fd.append('name', name);
        fd.append('slug', slug);
        fd.append('description', desc);
        fd.append('parent_id', parentId ?? '');
        const r = await fetch(API_SAVE, { method:'POST', body: fd, credentials:'same-origin' });
        const js = await r.json();
        if (!js.ok) { alert(js.error||'Ошибка'); return; }
        const li = document.createElement('li');
        li.setAttribute('data-id', js.id);
        li.setAttribute('data-slug', js.slug||'');
        li.setAttribute('data-desc', js.description||'');
        li.innerHTML = `
      <div class="node">
        <span class="drag-handle">⋮⋮</span>
        <span class="node-title"></span>
        <span class="node-actions">
          <button class="btn sm add" data-id="${js.id}">+ Подкатегория</button>
          <button class="btn sm rename" data-id="${js.id}">Редактировать</button>
          <button class="btn sm danger del" data-id="${js.id}">Удалить</button>
        </span>
      </div>
    `;
        li.querySelector('.node-title').textContent = name;

        if (parentId) {
            let parentLi = wrap.querySelector(`li[data-id="${parentId}"]`);
            if (!parentLi) return location.reload();
            let ul = parentLi.querySelector(':scope > ul.cat-tree');
            if (!ul) { ul = document.createElement('ul'); ul.className='cat-tree'; parentLi.appendChild(ul); }
            ul.appendChild(li);
        } else {
            let root = wrap.querySelector(':scope > ul.cat-tree');
            if (!root) { root = document.createElement('ul'); root.className='cat-tree'; wrap.appendChild(root); }
            root.appendChild(li);
        }
    }

    async function editCat(id, name, slug, desc) {
        const fd = new FormData();
        fd.append('csrf', CSRF);
        fd.append('action','edit');
        fd.append('id', String(id));
        fd.append('name', name);
        fd.append('slug', slug);
        fd.append('description', desc);
        const r = await fetch(API_SAVE, { method:'POST', body: fd, credentials:'same-origin' });
        const js = await r.json();
        if (!js.ok) alert(js.error||'Ошибка');
    }

    async function removeCat(id) {
        const fd = new FormData();
        fd.append('csrf', CSRF);
        fd.append('id', id);
        const r = await fetch(API_DEL, { method:'POST', body: fd, credentials:'same-origin' });
        const js = await r.json();
        if (!js.ok) alert(js.error||'Ошибка');
    }
})();
