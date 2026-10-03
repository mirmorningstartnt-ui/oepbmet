<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if (!ec_installed()) {
    header('Location: ../install.php');
    exit;
}
$me = ec_require_login();

$q = trim($_GET['q'] ?? '');
try {
    $cards = ec_cards_list($q);
    $total = ec_cards_count();
    $db_error = null;
} catch (Exception $e) {
    $cards = [];
    $total = 0;
    $db_error = $e->getMessage();
}

adm_head('Dashboard');
?>

  <div class="adm-stats">
    <div class="stat"><div class="n"><?php echo (int)$total; ?></div><div class="l">EC Cards Issued</div></div>
    <div class="stat"><div class="n"><?php echo count($cards); ?></div><div class="l"><?php echo $q !== '' ? 'Matching Cards' : 'Shown'; ?></div></div>
    <div class="stat"><div class="n"><?php echo ec_e(date('Y')); ?></div><div class="l">Current Year</div></div>
  </div>

  <div class="adm-card">
    <div class="adm-toolbar">
      <h2>Emigration Clearance Cards</h2>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <form class="adm-search" method="get" action="index.php">
          <input class="form-control" type="text" name="q" value="<?php echo ec_e($q); ?>" placeholder="Search name / EC No / passport / NID…" />
          <button class="btn" type="submit"><i class="fas fa-search"></i></button>
          <?php if ($q !== ''): ?><a class="btn" href="index.php">Clear</a><?php endif; ?>
        </form>
        <a class="btn btn-primary" href="card_form.php"><i class="fas fa-plus"></i> Add New Card</a>
      </div>
    </div>

    <?php if ($db_error): ?>
      <div class="alert alert-err">Database error: <?php echo ec_e($db_error); ?></div>
    <?php endif; ?>

    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th></th>
            <th>Name</th>
            <th>EC No</th>
            <th>BMET No</th>
            <th>Passport</th>
            <th>Country</th>
            <th>EC Date</th>
            <th style="min-width:250px;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$cards): ?>
          <tr><td colspan="8" class="muted" style="text-align:center;padding:26px;">
            No cards found<?php echo $q !== '' ? ' for this search' : ' yet'; ?>.
            <a href="card_form.php">Create the first EC card &rarr;</a>
          </td></tr>
        <?php endif; ?>
        <?php foreach ($cards as $c): ?>
          <tr>
            <td>
              <?php if (!empty($c['photo']) && is_file(ec_root() . '/' . $c['photo'])): ?>
                <img class="thumb" src="../<?php echo ec_e($c['photo']); ?>" alt="" />
              <?php else: ?>
                <span class="thumb" style="display:inline-block;"></span>
              <?php endif; ?>
            </td>
            <td><strong><?php echo ec_e($c['name']); ?></strong><br><span class="small muted">NID <?php echo ec_e($c['nid'] ?: '—'); ?></span></td>
            <td class="mono"><?php echo ec_e($c['ec_no']); ?></td>
            <td class="mono"><?php echo ec_e($c['bmet_no']); ?></td>
            <td class="mono"><?php echo ec_e($c['passport_no']); ?></td>
            <td><?php echo ec_e($c['country']); ?><br><span class="small muted"><?php echo ec_e($c['country_code']); ?></span></td>
            <td><?php echo ec_e($c['ec_date']); ?></td>
            <td>
              <div class="row-actions">
                <a class="btn btn-sm" target="_blank" href="../ec-card/verify/<?php echo ec_e($c['ec_no']); ?>.html" title="Public verification page"><i class="fas fa-id-card"></i> Verify</a>
                <a class="btn btn-sm" target="_blank" href="../ec-card/enrollment-card/<?php echo ec_e($c['ec_no']); ?>.pdf" title="Enrollment card PDF"><i class="fas fa-file-pdf"></i> PDF</a>
                <a class="btn btn-sm" href="card_form.php?id=<?php echo (int)$c['id']; ?>" title="Edit"><i class="fas fa-pen"></i> Edit</a>
                <form method="post" action="card_regenerate.php">
                  <?php echo ec_csrf_field(); ?>
                  <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>" />
                  <button class="btn btn-sm" type="submit" title="Regenerate verify page, PDF and MOCK_DB lines"><i class="fas fa-rotate"></i></button>
                </form>
                <form method="post" action="card_delete.php" onsubmit="return confirm('Delete card <?php echo ec_e($c['ec_no']); ?> and all published files?');">
                  <?php echo ec_csrf_field(); ?>
                  <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>" />
                  <button class="btn btn-sm btn-danger" type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint mt">Every card automatically publishes <span class="ec-preview">ec-card/verify/&lt;EC-No&gt;.html</span>, <span class="ec-preview">ec-card/enrollment-card/&lt;EC-No&gt;.pdf</span> and a MOCK_DB line in <span class="ec-preview">pdo-certificate.html</span> / <span class="ec-preview">pdo-enrollment-card.html</span>.</p>
  </div>

<?php adm_foot(); ?>
