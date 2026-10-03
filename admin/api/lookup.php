<?php
/**
 * Public JSON lookup used by the static search pages when PHP is available.
 * Falls back gracefully (the pages keep their local MOCK_DB otherwise).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$passport = trim((string)($_GET['passport'] ?? ''));
if (!ec_installed() || $passport === '') {
    echo json_encode(['found' => false]);
    exit;
}
try {
    $card = ec_card_find_by_passport($passport);
} catch (Exception $e) {
    echo json_encode(['found' => false]);
    exit;
}
if (!$card) { echo json_encode(['found' => false]); exit; }
echo json_encode([
    'found' => true,
    'ec'    => $card['ec_no'],
    'verify'=> 'ec-card/verify/' . $card['ec_no'] . '.html',
    'pdf'   => 'ec-card/enrollment-card/' . $card['ec_no'] . '.pdf',
]);
