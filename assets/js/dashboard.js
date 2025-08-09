// Рисуем графики на canvas без внешних библиотек
(function () {
    // ---- палитра объявлена до использования ----
    const defaultColors = ['#2563eb','#10b981','#f59e0b','#ef4444','#8b5cf6','#14b8a6','#f97316','#dc2626','#0ea5e9','#22c55e'];
    function pickColor(i){ return defaultColors[i % defaultColors.length]; }

    function parseJSONAttr(el, name, fallback) {
        try { return JSON.parse(el.getAttribute(name) || ''); } catch { return fallback; }
    }

    // ---------- Продажи: линия ----------
    const salesCv = document.getElementById('salesChart');
    if (salesCv) {
        const series = parseJSONAttr(salesCv, 'data-series', []);
        const labels = series.map(r => r.day);
        const data   = series.map(r => Number(r.sum || 0));
        makeLineChart(salesCv, {
            labels,
            datasets: [{ label: 'Выручка', data }],
            legendEl: null
        });
    }

    // ---------- Задачи по доскам: много линий ----------
    const tasksCv = document.getElementById('tasksChart');
    if (tasksCv) {
        const boards = parseJSONAttr(tasksCv, 'data-boards', []);
        const labels = boards.length ? boards[0].series.map(p => p.day) : [];
        const datasets = boards.map((b, idx) => ({
            label: b.name,
            data: (b.series || []).map(p => Number(p.cnt || 0)),
            color: b.color || pickColor(idx)
        }));
        const legendEl = document.getElementById('tasksLegend');
        makeLineChart(tasksCv, {
            labels,
            datasets,
            yIntegerTicks: true,
            legendEl
        });
    }

    // ---------- Утилиты отрисовки ----------
    function makeLineChart(canvas, { labels, datasets, yIntegerTicks=false, legendEl=null }) {
        const dpi = window.devicePixelRatio || 1;
        const padding = { top: 12, right: 12, bottom: 24, left: 36 };
        let W, H, ctx;

        // Легенда (DOM)
        if (legendEl) {
            legendEl.innerHTML = '';
            datasets.forEach(ds => {
                const item = document.createElement('span');
                item.style.display = 'inline-flex';
                item.style.alignItems = 'center';
                item.style.gap = '6px';
                const box = document.createElement('i');
                box.style.display = 'inline-block';
                box.style.width = '12px';
                box.style.height = '12px';
                box.style.borderRadius = '3px';
                box.style.background = ds.color || pickColor(0);
                const label = document.createElement('span');
                label.textContent = ds.label || '';
                label.style.color = '#334155';
                item.appendChild(box);
                item.appendChild(label);
                legendEl.appendChild(item);
            });
        }

        function resize() {
            const box = canvas.getBoundingClientRect();
            W = Math.max(320, Math.floor(box.width));
            H = Math.max(160, Math.floor(box.height || 220));
            canvas.width  = Math.floor(W * dpi);
            canvas.height = Math.floor(H * dpi);
            canvas.style.width  = W + 'px';
            canvas.style.height = H + 'px';
            ctx = canvas.getContext('2d');
            ctx.setTransform(dpi, 0, 0, dpi, 0, 0);
            draw();
        }

        function niceTicks(min, max, count) {
            if (min === max) { max = min + 1; }
            const span = max - min;
            const stepBase = Math.pow(10, Math.floor(Math.log10(span / Math.max(1, count))));
            const err = (count * stepBase) / span;
            let mult = 1;
            if      (err <= 0.15) mult = 5;
            else if (err <= 0.35) mult = 2;
            else                  mult = 1;
            const step = stepBase * mult;
            const tMin = Math.floor(min / step) * step;
            const tMax = Math.ceil (max / step) * step;
            const arr = [];
            for (let v = tMin; v <= tMax + 1e-9; v += step) arr.push(v);
            return arr;
        }

        function draw() {
            ctx.clearRect(0,0,W,H);

            // Диапазон по всем данным
            let all = [];
            datasets.forEach(ds => all = all.concat(ds.data));
            let yMin = Math.min(0, ...all);
            let yMax = Math.max(1, ...all);
            if (yIntegerTicks) { yMin = Math.floor(yMin); yMax = Math.ceil(yMax); }

            const plotW = W - padding.left - padding.right;
            const plotH = H - padding.top  - padding.bottom;
            const x0 = padding.left, y0 = H - padding.bottom;
            const n = Math.max(1, labels.length - 1);

            // Ось X
            ctx.strokeStyle = '#e5e7eb';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(x0, y0 + 0.5);
            ctx.lineTo(x0 + plotW, y0 + 0.5);
            ctx.stroke();

            // Тики Y + сетка
            const ticks = niceTicks(yMin, yMax, 5);
            ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
            ticks.forEach(t => {
                const y = y0 - (t - yMin) / (yMax - yMin) * plotH;
                ctx.strokeStyle = '#f3f4f6';
                ctx.beginPath();
                ctx.moveTo(x0, y + 0.5);
                ctx.lineTo(x0 + plotW, y + 0.5);
                ctx.stroke();
                ctx.fillStyle = '#6b7280';
                const txt = yIntegerTicks ? String(Math.round(t)) : String(Math.round(t));
                ctx.fillText(txt, 2, y - 2);
            });

            // Метки X (редко)
            const stepX = Math.max(1, Math.ceil(labels.length / 6));
            ctx.fillStyle = '#6b7280';
            for (let i = 0; i < labels.length; i += stepX) {
                const x = x0 + (i / n) * plotW;
                ctx.fillText((labels[i] || '').slice(5), x - 10, y0 + 16);
            }

            // Линии + точки
            datasets.forEach((ds, idx) => {
                const color = ds.color || pickColor(idx);
                ctx.strokeStyle = color;
                ctx.lineWidth = 2;
                ctx.beginPath();
                ds.data.forEach((v, i) => {
                    const x = x0 + (i / n) * plotW;
                    const y = y0 - ((v - yMin) / (yMax - yMin)) * plotH;
                    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                });
                ctx.stroke();

                ctx.fillStyle = color;
                ds.data.forEach((v, i) => {
                    const x = x0 + (i / n) * plotW;
                    const y = y0 - ((v - yMin) / (yMax - yMin)) * plotH;
                    ctx.beginPath(); ctx.arc(x, y, 2.5, 0, Math.PI * 2); ctx.fill();
                });
            });
        }

        const ro = new ResizeObserver(resize);
        ro.observe(canvas);
        resize();
    }
})();
