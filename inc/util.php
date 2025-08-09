<?php
// inc/util.php
require_once __DIR__ . '/auth.php'; // тянет config/session

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token()).'">';
}
function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
        if (!$ok) { http_response_code(400); exit('Bad CSRF'); }
    }
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg];
}
function flash_render(): string {
    $out = '';
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $f) {
            $cls = $f['t'] === 'ok' ? 'ok' : 'error';
            $out .= '<div class="alert '.$cls.'">'.htmlspecialchars($f['m']).'</div>';
        }
        unset($_SESSION['flash']);
    }
    return $out;
}

function active_link(string $current, string $name): string {
    return $current === $name ? ' class="active"' : '';
}

function qparam(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

function paginate(int $total, int $page, int $perPage, string $baseUrl): string {
    $pages = max(1, (int)ceil($total / $perPage));
    if ($pages <= 1) return '';
    $html = '<div class="pagination">';
    for ($p=1; $p <= $pages; $p++) {
        $cls = $p === $page ? ' class="current"' : '';
        $html .= '<a'.$cls.' href="'.htmlspecialchars($baseUrl.(str_contains($baseUrl,'?')?'&':'?').'page='.$p).'">'.$p.'</a>';
    }
    $html .= '</div>';
    return $html;
}

function url(string $path): string {
    return rtrim(APP_BASE_URL, '/') . '/' . ltrim($path, '/');
}

function order_statuses_map(): array {
    return [
        'new'      => 'Новый',
        'pending'  => 'Ожидает',
        'paid'     => 'Оплачен',
        'shipped'  => 'Отправлен',
        'canceled' => 'Отменён',
        'refunded' => 'Возврат',
    ];
}