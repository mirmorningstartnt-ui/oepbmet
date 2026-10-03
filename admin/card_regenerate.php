<?php
require_once __DIR__ . '/includes/bootstrap.php';
ec_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
ec_csrf_check();
$id = (int)($_POST['id'] ?? 0);
$card = ec_card_get($id);
if (!$card) { ec_flash('Card not found.', 'err'); header('Location: index.php'); exit; }
try {
    ec_publish_card($card);
    ec_flash('Published files regenerated for ' . $card['ec_no'] . '.');
} catch (Exception $e) {
    ec_flash('Regenerate failed: ' . $e->getMessage(), 'err');
}
header('Location: index.php');
exit;
