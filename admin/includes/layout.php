<?php
/** Shared HTML shell for admin pages. */

function adm_head($title, $topbar = true) {
    $me = ec_current_admin();
    ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo ec_e($title); ?> · BMET EC Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&family=Lato:wght@400;700;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="assets/admin.css" />
</head>
<body>
<?php if ($topbar): ?>
  <div class="adm-topbar">
    <div class="adm-topbar-inner">
      <a class="adm-brand" href="index.php">
        <img src="http://training.oep.gov.bd/uploadfiles/4Z0Zwp08PK5yC1i2y3w7Iolq7ezoPmcaVYJFVJSm.png" alt="BMET Logo" onerror="this.style.display='none'" />
        <strong>BMET EC Dashboard</strong>
      </a>
      <div class="adm-top-actions">
        <a class="btn btn-sm" href="index.php"><i class="fas fa-list"></i> Cards</a>
        <a class="btn btn-sm btn-primary" href="card_form.php"><i class="fas fa-plus"></i> Add New Card</a>
        <a class="btn btn-sm" href="../index.html" target="_blank"><i class="fas fa-globe"></i> View Site</a>
        <?php if ($me): ?>
          <span class="adm-user"><i class="fas fa-user-shield"></i> <?php echo ec_e($me['username']); ?></span>
          <a class="btn btn-sm btn-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
<div class="adm-body">
<?php
    $f = ec_flash();
    if ($f) echo '<div class="alert alert-' . ($f['type'] === 'ok' ? 'ok' : 'err') . '">' . ec_e($f['msg']) . '</div>';
}

function adm_foot() {
    ?>
</div>
<div class="adm-foot">BMET Emigration Clearance Card Manager &copy; <?php echo date('Y'); ?></div>
</body>
</html>
<?php
}
