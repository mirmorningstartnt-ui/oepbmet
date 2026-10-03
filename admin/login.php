<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if (!ec_installed()) {
    header('Location: ../install.php');
    exit;
}
if (ec_current_admin()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ec_csrf_check();
    $u = trim($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    $admin = ($u !== '' && $p !== '') ? ec_login($u, $p) : null;
    if ($admin) {
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid username or password.';
}

adm_head('Login', false);
?>
  <div class="login-wrap">
    <div class="login-box">
      <h1>BMET EC Dashboard</h1>
      <p class="sub">Sign in with the administrator credentials created by the installer.</p>
      <?php if ($error): ?><div class="alert alert-err"><?php echo ec_e($error); ?></div><?php endif; ?>
      <div class="adm-card">
        <form method="post" action="login.php" novalidate>
          <?php echo ec_csrf_field(); ?>
          <div class="form-group" style="margin-bottom:16px;">
            <label class="form-label" for="username">Username <span class="req">*</span></label>
            <input class="form-control" id="username" name="username" autocomplete="username" required />
          </div>
          <div class="form-group" style="margin-bottom:20px;">
            <label class="form-label" for="password">Password <span class="req">*</span></label>
            <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required />
          </div>
          <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>
      </div>
      <p class="hint mt" style="text-align:center;"><a href="../index.html">&larr; Back to site</a></p>
    </div>
  </div>
<?php adm_foot(); ?>
