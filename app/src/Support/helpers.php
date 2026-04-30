<?php

/*
function base_url($path = '') {
    global $config;
    return rtrim($config['app']['base_url'], '/') . '/' . ltrim($path,'/');
}


function view($template, $vars = []) {
    // Torna o $config disponível em todas as views/layouts
    global $config;
    // Evita sobrescrever se vier em $vars
    if (!isset($vars['config'])) {
        $vars['config'] = $config;
    }

    extract($vars);
    require __DIR__ . '/../../views/' . $template;
}


function redirect($path) {
    header('Location: ' . base_url($path));
    exit;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    echo '<input type="hidden" name="csrf" value="'.$t.'">';
}

function check_csrf() {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(400);
        exit('CSRF token inválido.');
    }
}

function is_post() { return strtoupper($_SERVER['REQUEST_METHOD']) === 'POST'; }



function render_page(string $viewPath, array $vars = []) {
    global $config;
    if (!isset($vars['config'])) {
        $vars['config'] = $config;
    }

    // Renderiza a parcial para buffer
    ob_start();
    extract($vars);
    require __DIR__ . '/../../views/' . $viewPath;
    $captured = ob_get_clean();

    // Closure para o layout usar como $content()
    $content = function() use ($captured) {
        echo $captured;
    };

    // Chama o layout principal apenas uma vez
    require __DIR__ . '/../../views/layouts/adminlte.php';
}
*/


function base_url($path = '') {
    global $config;
    return rtrim($config['app']['base_url'], '/') . '/' . ltrim($path,'/');
}

function view($template, $vars = []) {
    // Torna o $config disponível em todas as views/layouts
    global $config;
    // Evita sobrescrever se vier em $vars
    if (!isset($vars['config'])) {
        $vars['config'] = $config;
    }

    extract($vars);
    require __DIR__ . '/../../views/' . $template;
}

function redirect($path) {
    header('Location: ' . base_url($path));
    exit;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/* =========================
 * 👇 NOVO: helpers de escape
 * ========================= */
if (!function_exists('e')) {
    /**
     * Escape HTML seguro (converte qualquer valor para string).
     * Usa ENT_QUOTES + ENT_SUBSTITUTE para evitar erros com bytes inválidos.
     */
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('h')) {
    /** Alias curto de e() — útil em views. */
    function h($value): string
    {
        return e($value);
    }
}

/* =========================
 * Ajuste para usar o e()
 * ========================= */
function csrf_field() {
    $t = e(csrf_token());
    echo '<input type="hidden" name="csrf" value="'.$t.'">';
}

function check_csrf() {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(400);
        exit('CSRF token inválido.');
    }
}

function is_post() { return strtoupper($_SERVER['REQUEST_METHOD']) === 'POST'; }

function render_page(string $viewPath, array $vars = []) {
    global $config;
    if (!isset($vars['config'])) {
        $vars['config'] = $config;
    }

    // Renderiza a parcial para buffer
    ob_start();
    extract($vars);
    require __DIR__ . '/../../views/' . $viewPath;
    $captured = ob_get_clean();

    // Closure para o layout usar como $content()
    $content = function() use ($captured) {
        echo $captured;
    };

    // Chama o layout principal apenas uma vez
    require __DIR__ . '/../../views/layouts/adminlte.php';
}

// ─── Server-side pagination (Massey) ────────

if (!function_exists('totalPages')) {
    function totalPages(int $total, int $pp): int {
        $pp = max(1, $pp);
        return max(1, (int)ceil($total / $pp));
    }
}

if (!function_exists('buildPageUrl')) {
    function buildPageUrl(string $pattern, int $page, string $absBase): string {
        $qs = sprintf($pattern, $page);
        return htmlspecialchars($absBase . '?' . $qs);
    }
}

if (!function_exists('pageControls')) {
    /**
     * Returns array of page URLs/metadata for paginator nav
     *
     * @param  array  $meta     ['total', 'p', 'pp', 'qsPattern']
     * @param  string $absBase  Absolute base URL (no query string)
     * @return array  ['first','prev','next','last','pages','p']
     */
    function pageControls(array $meta, string $absBase): array {
        $total = (int)$meta['total'];
        $p     = (int)$meta['p'];
        $pp    = (int)$meta['pp'];
        $pages = totalPages($total, $pp);
        return [
            'first' => buildPageUrl($meta['qsPattern'], 1,                   $absBase),
            'prev'  => buildPageUrl($meta['qsPattern'], max(1, $p - 1),      $absBase),
            'next'  => buildPageUrl($meta['qsPattern'], min($pages, $p + 1), $absBase),
            'last'  => buildPageUrl($meta['qsPattern'], $pages,              $absBase),
            'pages' => $pages,
            'p'     => $p,
        ];
    }
}

if (!function_exists('ppOptions')) {
    /**
     * <option> HTML per-page size selector.
     */
    function ppOptions(int $current): string {
        $sizes = [25, 100, 1000, 5000];
        $out   = '';
        foreach ($sizes as $s) {
            $sel  = ($s === $current) ? ' selected' : '';
            $out .= "<option value=\"{$s}\"{$sel}>{$s}</option>";
        }
        return $out;
    }
}

if (!function_exists('filterHiddenInputs')) {
    /**
     *
     * @param  array $filters  [name => value]
     * @return string
     */
    function filterHiddenInputs(array $filters): string {
        $out = '';
        foreach ($filters as $k => $v) {
            $v    = htmlspecialchars((string)$v);
            $out .= "<input type=\"hidden\" name=\"{$k}\" value=\"{$v}\">";
        }
        return $out;
    }
}

