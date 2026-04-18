<?php
function require_login() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
    }
}

function redirect($path, $msg = '', $type = 'success') {
    if ($msg) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
    }
    header("Location: " . BASE_URL . $path);
    exit;
}

function flash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $cls = $f['type'] === 'error' ? 'alert-error' : ($f['type'] === 'warning' ? 'alert-warning' : 'alert-success');
        echo "<div class=\"alert $cls\">" . htmlspecialchars($f['msg']) . "</div>";
    }
}
