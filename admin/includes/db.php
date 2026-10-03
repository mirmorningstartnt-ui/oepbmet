<?php
/**
 * Database layer (PDO). MySQL/MariaDB on production (Hostinger cPanel);
 * SQLite is supported as a zero-config fallback for local testing only.
 */

function ec_db() {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $c = function_exists('ec_config') ? ec_config() : null;
    if (!$c) return null;
    $driver = $c['driver'] ?? 'mysql';
    if ($driver === 'sqlite') {
        $path = $c['sqlite_path'] ?? (dirname(__DIR__) . '/data/app.sqlite');
        if (!is_dir(dirname($path))) @mkdir(dirname($path), 0775, true);
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL');
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $c['host'] ?? 'localhost', (int)($c['port'] ?? 3306), $c['name'] ?? '');
    $pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

/** CREATE TABLE statements for the given driver */
function ec_schema_sql($driver = 'mysql') {
    if ($driver === 'sqlite') {
        return [
            "CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(60) NOT NULL UNIQUE,
                email VARCHAR(120) DEFAULT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS ec_cards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ec_no VARCHAR(40) NOT NULL UNIQUE,
                bmet_no VARCHAR(40) NOT NULL UNIQUE,
                ec_date VARCHAR(10) NOT NULL,
                name VARCHAR(120) NOT NULL,
                fathers_name VARCHAR(120) DEFAULT '',
                mothers_name VARCHAR(120) DEFAULT '',
                birth_date VARCHAR(10) DEFAULT NULL,
                gender VARCHAR(20) DEFAULT '',
                nid VARCHAR(40) DEFAULT '',
                blood_group VARCHAR(10) DEFAULT '',
                passport_no VARCHAR(40) NOT NULL,
                passport_issue VARCHAR(10) DEFAULT NULL,
                passport_expire VARCHAR(10) DEFAULT NULL,
                visa_no VARCHAR(60) DEFAULT '',
                visa_issue VARCHAR(10) DEFAULT NULL,
                visa_expire VARCHAR(10) DEFAULT NULL,
                referral_no VARCHAR(60) DEFAULT '',
                employer VARCHAR(200) DEFAULT '',
                country VARCHAR(120) DEFAULT '',
                country_code VARCHAR(2) DEFAULT '',
                agency_name VARCHAR(200) DEFAULT '',
                agency_license VARCHAR(60) DEFAULT '',
                agency_phone VARCHAR(40) DEFAULT '',
                addr_house VARCHAR(200) DEFAULT '',
                addr_post VARCHAR(120) DEFAULT '',
                addr_ps VARCHAR(120) DEFAULT '',
                addr_upazila VARCHAR(120) DEFAULT '',
                addr_district VARCHAR(120) DEFAULT '',
                addr_division VARCHAR(120) DEFAULT '',
                photo VARCHAR(255) DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
        ];
    }
    return [
        "CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(60) NOT NULL UNIQUE,
            email VARCHAR(120) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS ec_cards (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            ec_no VARCHAR(40) NOT NULL UNIQUE,
            bmet_no VARCHAR(40) NOT NULL UNIQUE,
            ec_date DATE NOT NULL,
            name VARCHAR(120) NOT NULL,
            fathers_name VARCHAR(120) DEFAULT '',
            mothers_name VARCHAR(120) DEFAULT '',
            birth_date DATE DEFAULT NULL,
            gender VARCHAR(20) DEFAULT '',
            nid VARCHAR(40) DEFAULT '',
            blood_group VARCHAR(10) DEFAULT '',
            passport_no VARCHAR(40) NOT NULL,
            passport_issue DATE DEFAULT NULL,
            passport_expire DATE DEFAULT NULL,
            visa_no VARCHAR(60) DEFAULT '',
            visa_issue DATE DEFAULT NULL,
            visa_expire DATE DEFAULT NULL,
            referral_no VARCHAR(60) DEFAULT '',
            employer VARCHAR(200) DEFAULT '',
            country VARCHAR(120) DEFAULT '',
            country_code VARCHAR(2) DEFAULT '',
            agency_name VARCHAR(200) DEFAULT '',
            agency_license VARCHAR(60) DEFAULT '',
            agency_phone VARCHAR(40) DEFAULT '',
            addr_house VARCHAR(200) DEFAULT '',
            addr_post VARCHAR(120) DEFAULT '',
            addr_ps VARCHAR(120) DEFAULT '',
            addr_upazila VARCHAR(120) DEFAULT '',
            addr_district VARCHAR(120) DEFAULT '',
            addr_division VARCHAR(120) DEFAULT '',
            photo VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_passport (passport_no),
            KEY idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

/** All writable card fields (form name => db column) */
function ec_card_fields() {
    return ['ec_no','bmet_no','ec_date','name','fathers_name','mothers_name','birth_date','gender','nid',
        'blood_group','passport_no','passport_issue','passport_expire','visa_no','visa_issue','visa_expire',
        'referral_no','employer','country','country_code','agency_name','agency_license','agency_phone',
        'addr_house','addr_post','addr_ps','addr_upazila','addr_district','addr_division','photo'];
}

function ec_card_get($id) {
    $st = ec_db()->prepare('SELECT * FROM ec_cards WHERE id = ?');
    $st->execute([(int)$id]);
    return $st->fetch() ?: null;
}

function ec_card_by_ec($ec) {
    $st = ec_db()->prepare('SELECT * FROM ec_cards WHERE ec_no = ?');
    $st->execute([$ec]);
    return $st->fetch() ?: null;
}

function ec_card_find_by_passport($passport) {
    $st = ec_db()->prepare('SELECT * FROM ec_cards WHERE UPPER(passport_no) = UPPER(?) ORDER BY id DESC LIMIT 1');
    $st->execute([$passport]);
    return $st->fetch() ?: null;
}

function ec_cards_list($search = '', $limit = 500) {
    $search = trim((string)$search);
    if ($search !== '') {
        $like = '%' . $search . '%';
        $st = ec_db()->prepare('SELECT * FROM ec_cards WHERE ec_no LIKE ? OR bmet_no LIKE ? OR name LIKE ? OR passport_no LIKE ? OR nid LIKE ? OR country LIKE ? ORDER BY id DESC LIMIT ' . (int)$limit);
        $st->execute([$like, $like, $like, $like, $like, $like]);
    } else {
        $st = ec_db()->prepare('SELECT * FROM ec_cards ORDER BY id DESC LIMIT ' . (int)$limit);
        $st->execute();
    }
    return $st->fetchAll();
}

function ec_cards_count() {
    return (int)ec_db()->query('SELECT COUNT(*) FROM ec_cards')->fetchColumn();
}

function ec_card_insert(array $d) {
    $cols = ec_card_fields();
    $vals = [];
    foreach ($cols as $c) $vals[] = $d[$c] ?? null;
    $sql = 'INSERT INTO ec_cards (' . implode(',', $cols) . ') VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')';
    ec_db()->prepare($sql)->execute($vals);
    return (int)ec_db()->lastInsertId();
}

function ec_card_update($id, array $d) {
    $cols = ec_card_fields();
    $set = [];
    $vals = [];
    foreach ($cols as $c) { $set[] = "$c = ?"; $vals[] = $d[$c] ?? null; }
    $vals[] = (int)$id;
    ec_db()->prepare('UPDATE ec_cards SET ' . implode(',', $set) . ' WHERE id = ?')->execute($vals);
}

function ec_card_delete($id) {
    ec_db()->prepare('DELETE FROM ec_cards WHERE id = ?')->execute([(int)$id]);
}

/** next free BMET sequence number for a year */
function ec_next_bmet_seq($year) {
    $prefix = 'CPM' . $year;
    $st = ec_db()->prepare("SELECT bmet_no FROM ec_cards WHERE bmet_no LIKE ?");
    $st->execute([$prefix . '%']);
    $max = 0;
    foreach ($st->fetchAll() as $row) {
        $body = substr($row['bmet_no'], strlen($prefix));
        if (preg_match('/^(\d{7})/', $body, $m)) $max = max($max, (int)$m[1]);
    }
    return $max + 1;
}
