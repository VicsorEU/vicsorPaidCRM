// assets/js/movement.js
// Подсчёт сумм в документах склада (приход/расход/перемещение/корректировка)
// и лёгкая валидация количества для режима "из наличия" (data-mode="pick")
(function () {
    const table = document.getElementById('mvItems');
    if (!table) return;

    const mode = table.getAttribute('data-mode') || 'free'; // 'free' | 'pick'

    function toNumber(v) {
        if (v == null) return 0;
        const n = parseFloat(String(v).replace(',', '.'));
        return Number.isFinite(n) ? n : 0;
    }

    function round2(n) {
        return Math.round((+n || 0) * 100) / 100;
    }

    function recalc() {
        let sum = 0;

        table.querySelectorAll('tbody tr').forEach((tr) => {
            const qtyInput = tr.querySelector('input[name="qty[]"]');
            const priceInput = tr.querySelector('input[name="price[]"]');
            const lineCell = tr.querySelector('.line-total');

            const qty = toNumber(qtyInput?.value);
            const price = toNumber(priceInput?.value);
            const total = round2(qty * price);
            sum += total;

            if (lineCell) lineCell.textContent = total.toFixed(2);

            // Мягкая проверка остатков в режиме pick (расход/перемещение)
            if (mode === 'pick' && qtyInput) {
                const availEl = tr.querySelector('.avail'); // текстовое поле "Доступно: <b class=avail>…</b>"
                const avail = toNumber(availEl?.textContent);
                const warn = Number.isFinite(avail) && qty > avail + 1e-9;

                qtyInput.classList.toggle('warn', warn);
                if (warn) {
                    qtyInput.setAttribute('title', 'Недостаточно остатка на складе');
                    if (availEl?.parentElement) availEl.parentElement.style.color = '#c00';
                } else {
                    qtyInput.removeAttribute('title');
                    if (availEl?.parentElement) availEl.parentElement.style.color = '';
                }
            }
        });

        const totalEl = document.getElementById('docTotal');
        if (totalEl) totalEl.textContent = sum.toFixed(2);
    }

    // Пересчёт при вводе
    table.addEventListener('input', (e) => {
        if (e.target.matches('input, select')) recalc();
    });

    // Пересчёт при добавлении/удалении строк
    const tbody = table.querySelector('tbody');
    if (tbody) {
        const mo = new MutationObserver(() => recalc());
        mo.observe(tbody, { childList: true, subtree: false });
    }

    // Первичный пересчёт
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', recalc);
    } else {
        recalc();
    }
})();
