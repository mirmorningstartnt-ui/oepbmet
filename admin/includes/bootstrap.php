<?php
/** Common bootstrap for every admin page. */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** url helper relative to the admin folder */
function ec_url($path = '') {
    return rtrim($path, '/');
}

/** root-relative link helper (admin folder may sit in a subdirectory) */
function ec_admin_url($path = '') {
    return $path;
}
