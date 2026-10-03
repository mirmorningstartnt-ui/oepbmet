<?php
/**
 * Core application helpers: config, EC/BMET number generation, static-file
 * publishing (verify page, enrollment-card PDF, MOCK_DB lines) and uploads.
 */

require_once __DIR__ . '/countries.php';
require_once __DIR__ . '/pdf.php';

function ec_root() { return dirname(__DIR__, 2); }

function ec_config() {
    static $cfg = false;
    if ($cfg !== false) return $cfg;
    $f = __DIR__ . '/config.php';
    $cfg = is_file($f) ? (require $f) : null;
    return $cfg;
}

function ec_installed() { return is_file(__DIR__ . '/config.php'); }

/** Public base URL of the site (no trailing slash) */
function ec_base_url() {
    $c = ec_config();
    if (!empty($c['base_url'])) return rtrim($c['base_url'], '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    // strip /admin/... or /install.php to get the site root
    $path = preg_replace('#/admin/.*$#', '', $script);
    $path = preg_replace('#/[^/]*$#', '', $path);
    return $scheme . '://' . $host . rtrim($path, '/');
}

function ec_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ─────────────── number generation ─────────────── */

/**
 * Emigration Clearance number:  CC-I-YEAR-PASSPORTDIGITS
 * e.g. MD-I-2026-09890023  (MD = Moldova, I = default series,
 * 2026 = EC year, 09890023 = numeric part of the passport number)
 */
function ec_make_ec_no($country, $ec_date, $passport_no) {
    $cc = ec_country_code($country);
    $year = date('Y');
    if ($ec_date && ($ts = strtotime($ec_date))) $year = date('Y', $ts);
    $digits = preg_replace('/\D/', '', (string)$passport_no);
    if ($digits === '') $digits = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$passport_no));
    return strtoupper($cc) . '-I-' . $year . '-' . $digits;
}

/** BMET registration number: CPM + year + 7-digit sequence + check letter */
function ec_make_bmet_no($ec_date, $seq) {
    $year = date('Y');
    if ($ec_date && ($ts = strtotime($ec_date))) $year = date('Y', $ts);
    $body = sprintf('%07d', (int)$seq);
    $sum = 0;
    foreach (str_split($year . $body) as $ch) $sum += (int)$ch;
    $check = chr(65 + ($sum % 26));
    return 'CPM' . $year . $body . $check;
}

function ec_fmt_dmy($iso) {
    $ts = strtotime((string)$iso);
    return $ts ? date('d/M/Y', $ts) : (string)$iso;
}

/* ─────────────── MOCK_DB maintenance ─────────────── */

function ec_mockdb_files() {
    return [ec_root() . '/pdo-certificate.html', ec_root() . '/pdo-enrollment-card.html'];
}

/** append one { passport: ..., ec: ... } entry to the MOCK_DB array of a static page */
function ec_mockdb_append($file, $passport, $ec) {
    $src = @file_get_contents($file);
    if ($src === false) return false;
    ec_mockdb_remove($file, $ec);
    $src = @file_get_contents($file);
    $p = ec_js_str($passport);
    $e = ec_js_str($ec);
    $ok = preg_replace_callback('/const\s+MOCK_DB\s*=\s*\[(.*?)\]\s*;/s', function ($m) use ($p, $e) {
        $body = rtrim($m[1]);
        if ($body !== '') $body = rtrim($body, ',') . ',';
        return "const MOCK_DB = [" . $body . "\n      { passport: '" . $p . "', ec: '" . $e . "' }\n    ];";
    }, $src, 1, $count);
    if (!$count) return false;
    return @file_put_contents($file, $ok) !== false;
}

/** remove the MOCK_DB entry (line) that references the given EC number */
function ec_mockdb_remove($file, $ec) {
    $src = @file_get_contents($file);
    if ($src === false) return false;
    $e = ec_js_str($ec);
    $ok = preg_replace_callback('/const\s+MOCK_DB\s*=\s*\[(.*?)\]\s*;/s', function ($m) use ($e) {
        $body = $m[1];
        $body = preg_replace("/[ \t]*\{[^{}\n]*ec:\s*'" . preg_quote($e, '/') . "'[^{}\n]*\},?[ \t]*\r?\n?/", '', $body);
        // tidy: strip a dangling comma left behind by the removed entry
        $t = rtrim($body);
        if ($t !== '' && substr($t, -1) === ',') $t = substr($t, 0, -1);
        $body = ($t !== '') ? $t . "\n    " : "\n    ";
        return 'const MOCK_DB = [' . $body . '];';
    }, $src, 1);
    return @file_put_contents($file, $ok) !== false;
}

function ec_js_str($s) {
    return str_replace(['\\', "'"], ['\\\\', "\\'"], (string)$s);
}

/* ─────────────── published static files ─────────────── */

function ec_verify_file($ec) { return ec_root() . '/ec-card/verify/' . $ec . '.html'; }
function ec_pdf_file($ec)   { return ec_root() . '/ec-card/enrollment-card/' . $ec . '.pdf'; }
function ec_photo_file($ec) { return ec_root() . '/ec-card/uploads/' . $ec . '.jpg'; }

/** Build the public verification page HTML for a card (same design as the sample page) */
function ec_verify_html(array $c) {
    $ec = $c['ec_no'];
    $map = [
        '{{EC}}'        => ec_e($ec),
        '{{PHOTO}}'     => ec_e('../uploads/' . $ec . '.jpg'),
        '{{PDF}}'       => ec_e('../enrollment-card/' . $ec . '.pdf'),
        '{{NAME}}'      => ec_e($c['name']),
        '{{EC_DATE}}'   => ec_e(ec_fmt_dmy($c['ec_date'])),
        '{{BIRTH}}'     => ec_e($c['birth_date']),
        '{{BLOOD}}'     => ec_e($c['blood_group'] ?? ''),
        '{{PASSPORT}}'  => ec_e($c['passport_no']),
        '{{P_ISSUE}}'   => ec_e($c['passport_issue']),
        '{{P_EXPIRE}}'  => ec_e($c['passport_expire']),
        '{{VISA}}'      => ec_e($c['visa_no']),
        '{{V_ISSUE}}'   => ec_e($c['visa_issue']),
        '{{V_EXPIRE}}'  => ec_e($c['visa_expire']),
        '{{REFERRAL}}'  => ec_e($c['referral_no']),
        '{{EMPLOYER}}'  => ec_e($c['employer']),
        '{{COUNTRY}}'   => ec_e($c['country']),
        '{{AG_NAME}}'   => ec_e($c['agency_name']),
        '{{AG_LIC}}'    => ec_e($c['agency_license']),
        '{{AG_PHONE}}'  => ec_e($c['agency_phone']),
        '{{BMET}}'      => ec_e($c['bmet_no']),
        '{{GENDER}}'    => ec_e($c['gender']),
        '{{NID}}'       => ec_e($c['nid']),
        '{{A_HOUSE}}'   => ec_e($c['addr_house']),
        '{{A_POST}}'    => ec_e($c['addr_post']),
        '{{A_PS}}'      => ec_e($c['addr_ps']),
        '{{A_UPA}}'     => ec_e($c['addr_upazila']),
        '{{A_DIST}}'    => ec_e($c['addr_district']),
        '{{A_DIV}}'     => ec_e($c['addr_division']),
    ];
    $map['{{STYLE}}'] = file_get_contents(__DIR__ . '/verify_style.css');
    $tpl = file_get_contents(__DIR__ . '/verify_template.html');
    return strtr($tpl, $map);
}

/**
 * (Re)generate every published artifact for a card:
 *  - ec-card/verify/<EC>.html
 *  - ec-card/enrollment-card/<EC>.pdf
 *  - MOCK_DB line in pdo-certificate.html + pdo-enrollment-card.html
 */
function ec_publish_card(array $c) {
    $ec = $c['ec_no'];
    @mkdir(ec_root() . '/ec-card/uploads', 0775, true);
    @mkdir(ec_root() . '/ec-card/verify', 0775, true);
    @mkdir(ec_root() . '/ec-card/enrollment-card', 0775, true);

    file_put_contents(ec_verify_file($ec), ec_verify_html($c));

    $photo = ec_photo_file($ec);
    $qr_url = ec_base_url() . '/ec-card/verify/' . $ec . '.html';
    file_put_contents(ec_pdf_file($ec), ec_card_pdf_bytes($c, is_file($photo) ? $photo : null, $qr_url));

    foreach (ec_mockdb_files() as $f) ec_mockdb_append($f, $c['passport_no'], $ec);
    return true;
}

/** remove published artifacts of a card */
function ec_unpublish_card($ec) {
    @unlink(ec_verify_file($ec));
    @unlink(ec_pdf_file($ec));
    @unlink(ec_photo_file($ec));
    foreach (ec_mockdb_files() as $f) ec_mockdb_remove($f, $ec);
}

/**
 * Store an uploaded photo as ec-card/uploads/<EC>.jpg (JPEG).
 * PNG is converted with GD when available. Returns true on success.
 */
function ec_save_photo(array $upload, $ec) {
    if (!isset($upload['error']) || $upload['error'] === UPLOAD_ERR_NO_FILE) return false;
    if ($upload['error'] !== UPLOAD_ERR_OK) throw new Exception('Photo upload failed (code ' . $upload['error'] . ').');
    if (($upload['size'] ?? 0) > 8 * 1024 * 1024) throw new Exception('Photo must be smaller than 8 MB.');
    $info = @getimagesize($upload['tmp_name']);
    if (!$info) throw new Exception('Uploaded photo is not a valid image.');
    $dest = ec_photo_file($ec);
    @mkdir(dirname($dest), 0775, true);
    if ($info[2] === IMAGETYPE_JPEG) {
        // re-encode through GD when possible to normalise/strip oddities
        if (function_exists('imagecreatefromstring')) {
            $im = @imagecreatefromstring(file_get_contents($upload['tmp_name']));
            if ($im) { imagejpeg($im, $dest, 88); imagedestroy($im); return true; }
        }
        return move_uploaded_file($upload['tmp_name'], $dest) || copy($upload['tmp_name'], $dest);
    }
    if ($info[2] === IMAGETYPE_PNG && function_exists('imagecreatefromstring')) {
        $im = @imagecreatefromstring(file_get_contents($upload['tmp_name']));
        if (!$im) throw new Exception('Could not read the PNG photo.');
        $bg = imagecreatetruecolor(imagesx($im), imagesy($im));
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
        imagejpeg($bg, $dest, 88);
        imagedestroy($bg); imagedestroy($im);
        return true;
    }
    throw new Exception('Photo must be JPG or PNG.');
}
