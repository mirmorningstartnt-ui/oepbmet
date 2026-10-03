<?php
require_once __DIR__ . '/includes/bootstrap.php';
ec_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
ec_csrf_check();
$id = (int)($_POST['id'] ?? 0);
$card = ec_card_get($id);
if (!$card) { ec_flash('Card not found.', 'err'); header('Location: index.php'); exit; }
try {
    ec_unpublish_card($card['ec_no']);
    ec_card_delete($id);
    ec_flash('Card ' . $card['ec_no'] . ' deleted together with its published files.');
} catch (Exception $e) {
    ec_flash('Delete failed: ' . $e->getMessage(), 'err');
}
header('Location: index.php');
exit;
