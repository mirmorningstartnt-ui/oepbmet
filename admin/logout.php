<?php
require_once __DIR__ . '/includes/bootstrap.php';
ec_logout();
header('Location: login.php');
exit;
