<header class="topbar">
    <button id="burger" class="burger" aria-label="Меню">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="1.8"/></svg>
    </button>

    <div class="search">
        <svg class="s-ico" viewBox="0 0 24 24" fill="none"><path d="M11 19a8 8 0 1 1 5.3-14.1A8 8 0 0 1 11 19Zm9-1-4-4" stroke="currentColor" stroke-width="1.6"/></svg>
        <input type="text" placeholder="Быстрый поиск…">
        <span class="k">/</span>
    </div>

    <div class="top-actions">
        <button class="icon-btn" title="Уведомления">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 8a6 6 0 1 1 12 0v5l2 3H4l2-3V8Z" stroke="currentColor" stroke-width="1.6"/><path d="M10 19a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.6"/></svg>
        </button>
        <div class="user">
            <img src="<?= url('assets/img/avatar.jpg') ?>" alt="">
            <span class="name">Пользователь #<?= htmlspecialchars((string)($_SESSION['user_id'] ?? '')) ?></span>
        </div>
    </div>
</header>
