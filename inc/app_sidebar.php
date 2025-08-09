<?php
/** @var string $active */
?>
<aside class="sidebar">
    <div class="brand">
        <img class="logo" src="<?= url('assets/img/vicsor_logo.svg') ?>" alt=""><span>VicsorCRM</span>
    </div>
    <nav class="nav">
        <a<?= active_link($active,'dashboard') ?> href="<?= url('index.php') ?>">
            <svg class="ico" viewBox="0 0 24 24" fill="none"><path d="M3 12L12 3l9 9v8a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1v-8Z" stroke="currentColor" stroke-width="1.6"/></svg>
            <span>Дашборд</span>
        </a>
        <a<?= active_link($active,'orders') ?> href="<?= url('boards/order/orders.php') ?>">
            <svg class="ico" viewBox="0 0 24 24" fill="none"><path d="M3 5h18M3 12h18M3 19h18" stroke="currentColor" stroke-width="1.6"/></svg>
            <span>Заказы</span>
        </a>
        <a<?= active_link($active,'products') ?> href="<?= url('boards/products/products.php') ?>">
            <svg class="ico" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.6"/></svg>
            <span>Товары</span>
        </a>
        <a<?= active_link($active,'inventory') ?> href="<?= url('boards/inventory/movements.php') ?>">
            <svg class="ico" viewBox="0 0 24 24" fill="none"><path d="M12 3v3M12 18v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M3 12h3M18 12h3" stroke="currentColor" stroke-width="1.6"/></svg>
            <span>Приход/расход</span>
        </a>
        <a<?= active_link($active,'warehouses') ?> href="<?= url('boards/warehouses/warehouses.php') ?>">
            <svg class="ico" viewBox="0 0 24 24" fill="none">
                <path d="M3 9l9-6 9 6v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V9z" stroke="currentColor" stroke-width="1.6"/>
            </svg>
            <span>Склады</span>
        </a>
        <a<?= active_link($active,'warehouses') ?> href="<?= url('boards/products/categories.php') ?>">
            <svg class="ico" viewBox="0 0 24 24" fill="none">
                <path d="M3 9l9-6 9 6v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V9z" stroke="currentColor" stroke-width="1.6"/>
            </svg>
            <span>Категории</span>
        </a>
        <a<?= active_link($active,'warehouses') ?> href="<?= url('boards/products/attributes.php') ?>">
            <svg class="ico" viewBox="0 0 24 24" fill="none">
                <path d="M3 9l9-6 9 6v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V9z" stroke="currentColor" stroke-width="1.6"/>
            </svg>
            <span>Атрибуты</span>
        </a>
        <a<?= active_link($active,'customers') ?> href="<?= url('boards/customer/customers.php') ?>">
            <svg class="ico" viewBox="0 0 24 24" fill="none"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"/><path d="M20 21a8 8 0 1 0-16 0" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
            <span>Клиенты</span>
        </a>
        <a<?= active_link($active,'tasks') ?> href="<?= url('boards/tasks/kanban.php') ?>">
            <svg class="ico" viewBox="0 0 24 24" fill="none">
                <path d="M5 6h14M5 12h14M5 18h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            <span>Задачи</span>
        </a>
        <a<?= active_link($active,'analytics') ?> href="#">
            <svg class="ico" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M7 12h10M12 7v10" stroke="currentColor" stroke-width="1.6"/></svg>
            <span>Аналитика</span>
        </a>
        <a<?= active_link($active,'integrations') ?> href="#">
            <svg class="ico" viewBox="0 0 24 24" fill="none"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="1.6"/><path d="M19.4 15a7.97 7.97 0 0 0 0-6m-14.8 6a7.97 7.97 0 0 1 0-6" stroke="currentColor" stroke-width="1.6"/></svg>
            <span>Интеграции</span>
        </a>
        <a<?= active_link($active,'settings') ?> href="#">
            <svg class="ico" viewBox="0 0 24 24" fill="none"><path d="M12 3v3M12 18v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M3 12h3M18 12h3" stroke="currentColor" stroke-width="1.6"/></svg>
            <span>Настройки</span>
        </a>
    </nav>
    <div class="foot">
        <button id="collapseSidebar" class="collapse-btn"><span>Свернуть меню</span></button>
    </div>
</aside>

