(function(){
    const box = document.getElementById('productCats');
    if (!box) return;

    // Каскад: родитель ↔ дети, плюс обратная связь "дитя → предки"
    box.addEventListener('change', (e)=>{
        if (!e.target.matches('input[name="cat_ids[]"]')) return;
        const li = e.target.closest('li');
        if (!li) return;

        // вниз: чек родителя -> чек всем детям; снятие -> снять всем детям
        const setChildren = (node, checked) => {
            node.querySelectorAll(':scope ul input[name="cat_ids[]"]').forEach(i=>{ i.checked = checked; });
        };
        setChildren(li, e.target.checked);

        // вверх: если у узла чекнули — чекаем всех предков; если сняли — проверяем, остались ли отмеченные дети у предка
        const setParents = (node) => {
            let parentLi = node.parentElement.closest('li');
            while (parentLi) {
                const parentCb = parentLi.querySelector(':scope > label > input[name="cat_ids[]"], :scope > input[name="cat_ids[]"]') ||
                    parentLi.querySelector(':scope input[name="cat_ids[]"]');
                if (parentCb) {
                    if (e.target.checked) {
                        parentCb.checked = true;
                    } else {
                        // если у предка нет отмеченных потомков — снимаем
                        const anyChecked = parentLi.querySelectorAll(':scope ul input[name="cat_ids[]"]:checked').length > 0;
                        if (!anyChecked) parentCb.checked = false;
                    }
                }
                parentLi = parentLi.parentElement.closest('li');
            }
        };
        setParents(li);
    });

    // Поиск
    const search = document.getElementById('catSearch');
    if (search) {
        const labels = Array.from(box.querySelectorAll('label'));
        const liAll  = Array.from(box.querySelectorAll('li'));
        search.addEventListener('input', ()=>{
            const q = search.value.trim().toLowerCase();
            if (!q) {
                liAll.forEach(li => li.style.display='');
                return;
            }
            // Скрываем все, потом показываем те, где метка содержит q и их предков
            liAll.forEach(li => li.style.display='none');
            labels.forEach(lab=>{
                const text = lab.textContent.trim().toLowerCase();
                if (text.includes(q)) {
                    let li = lab.closest('li');
                    while (li) {
                        li.style.display = '';
                        li = li.parentElement.closest('li');
                    }
                }
            });
        });
    }
})();
