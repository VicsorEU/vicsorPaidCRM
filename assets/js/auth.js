// Показать/скрыть пароль
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-toggle="password"]');
    if (!btn) return;
    const input = btn.parentElement.querySelector('input[type="password"], input[type="text"]');
    if (!input) return;
    const nowType = input.type === 'password' ? 'text' : 'password';
    input.type = nowType;
    btn.innerHTML = nowType === 'text' ? eyeOffSVG : eyeSVG;
});

const eyeSVG = `<svg viewBox="0 0 24 24" fill="none">
  <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/>
  <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
</svg>`;

const eyeOffSVG = `<svg viewBox="0 0 24 24" fill="none">
  <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.6"/>
  <path d="M10.6 10.6A3 3 0 0012 15a3 3 0 002.4-4.4M9 5.3C9.9 5.1 10.9 5 12 5c6.5 0 10 7 10 7a18.6 18.6 0 01-3.6 4.5M6.2 7.2A18.5 18.5 0 002 12s3.5 7 10 7c1.9 0 3.6-.4 5.1-1" stroke="currentColor" stroke-width="1.6"/>
</svg>`;
