// Свернуть/развернуть сайдбар
document.addEventListener('click', (e) => {
    if (e.target.closest('#burger') || e.target.closest('#collapseSidebar')) {
        document.querySelector('.app').classList.toggle('collapsed');
    }
});

// Примитивный линейный график на canvas без библиотек
(function(){
    const c = document.getElementById('salesChart');
    if (!c) return;
    const ctx = c.getContext('2d');

    // retina
    const dpr = window.devicePixelRatio || 1;
    const cssW = c.clientWidth, cssH = c.clientHeight;
    c.width = cssW * dpr; c.height = cssH * dpr;
    ctx.scale(dpr, dpr);

    // демо-данные
    const data = [18,22,20,27,25,31,28,33,36,40,38,44,46,42,48,52,50,57,55,60,64,62,68,72,75,73,78,80,85,92];

    const pad = {l:32,r:12,t:12,b:24};
    const w = cssW - pad.l - pad.r;
    const h = cssH - pad.t - pad.b;
    const min = Math.min(...data), max = Math.max(...data);
    const nx = (i)=> pad.l + (i/(data.length-1))*w;
    const ny = (v)=> pad.t + (1-(v-min)/(max-min))*h;

    // ось X/Y (минимал)
    ctx.strokeStyle = '#e6eaf0';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(pad.l, pad.t); ctx.lineTo(pad.l, pad.t+h); ctx.lineTo(pad.l+w, pad.t+h);
    ctx.stroke();

    // линия
    ctx.strokeStyle = '#009ef7';
    ctx.lineWidth = 2;
    ctx.beginPath();
    data.forEach((v,i)=>{ const x=nx(i), y=ny(v); i?ctx.lineTo(x,y):ctx.moveTo(x,y); });
    ctx.stroke();

    // заливка под кривой
    const grad = ctx.createLinearGradient(0,pad.t,0, pad.t+h);
    grad.addColorStop(0,'rgba(0,158,247,.22)');
    grad.addColorStop(1,'rgba(0,158,247,0)');
    ctx.fillStyle = grad;
    ctx.lineTo(pad.l+w, pad.t+h);
    ctx.lineTo(pad.l, pad.t+h);
    ctx.closePath();
    ctx.fill();
})();
