<?php
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
