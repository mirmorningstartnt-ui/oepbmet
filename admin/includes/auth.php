<?php
/** Session auth + CSRF helpers for the admin dashboard. */

function ec_session_start() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('BMETADMIN');
        // pick a session save path we can actually write to (real write probe)
        foreach ([session_save_path(), sys_get_temp_dir(), dirname(__DIR__) . '/data'] as $p) {
            if ($p === '') continue;
            if (!@is_dir($p)) @mkdir($p, 0700, true);
            $f = rtrim($p, '/') . '/swt_' . uniqid() . '.tmp';
            if (@file_put_contents($f, '1') !== false) {
                @unlink($f);
                session_save_path($p);
                break;
            }
        }
        session_start();
    }
}

function ec_current_admin() {
    ec_session_start();
    return $_SESSION['admin'] ?? null;
}

function ec_require_login() {
    $a = ec_current_admin();
    if (!$a) {
        header('Location: login.php');
        exit;
    }
    return $a;
}

function ec_login($username, $password) {
    $st = ec_db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $admin = $st->fetch();
    if (!$admin || !password_verify($password, $admin['password_hash'])) return null;
    ec_session_start();
    session_regenerate_id(true);
    $_SESSION['admin'] = ['id' => (int)$admin['id'], 'username' => $admin['username']];
    return $_SESSION['admin'];
}

function ec_logout() {
    ec_session_start();
    $_SESSION = [];
    session_destroy();
}

function ec_csrf_token() {
    ec_session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function ec_csrf_field() {
    return '<input type="hidden" name="csrf" value="' . ec_e(ec_csrf_token()) . '" />';
}

function ec_csrf_check() {
    ec_session_start();
    $sent = $_POST['csrf'] ?? '';
    if (!$sent || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Invalid security token. Go back, refresh and try again.');
    }
}

function ec_flash($msg = null, $type = 'ok') {
    ec_session_start();
    if ($msg === null) {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
