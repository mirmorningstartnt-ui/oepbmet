<?php
/**
 * Minimal QR Code model-2 encoder (byte mode, ECC level M, versions 1-6).
 * Pure PHP, no dependencies. Returns the module matrix as int[][].
 * Algorithm validated against the reference `qrcode` library + OpenCV decoding.
 */

function qr_gf_tables() {
    static $tables = null;
    if ($tables !== null) return $tables;
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) $x ^= 0x11D;
    }
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    $tables = [$exp, $log];
    return $tables;
}

function qr_gf_mul($a, $b) {
    if ($a == 0 || $b == 0) return 0;
    list($exp, $log) = qr_gf_tables();
    return $exp[$log[$a] + $log[$b]];
}

function qr_rs_generator($n) {
    $g = [1];
    for ($i = 0; $i < $n; $i++) {
        $ng = array_fill(0, count($g) + 1, 0);
        foreach ($g as $j => $c) {
            $ng[$j] ^= $c;
            $ng[$j + 1] ^= qr_gf_mul($c, qr_gf_tables()[0][$i]);
        }
        $g = $ng;
    }
    return $g;
}

function qr_rs_encode($data, $ec_len) {
    $g = qr_rs_generator($ec_len);
    $res = array_merge($data, array_fill(0, $ec_len, 0));
    $n = count($data);
    for ($i = 0; $i < $n; $i++) {
        $coef = $res[$i];
        if ($coef != 0) {
            for ($j = 1; $j < count($g); $j++) {
                $res[$i + $j] ^= qr_gf_mul($g[$j], $coef);
            }
        }
    }
    return array_slice($res, $n);
}

function qr_mask_fn($k, $r, $c) {
    switch ($k) {
        case 0: return (($r + $c) % 2) == 0;
        case 1: return ($r % 2) == 0;
        case 2: return ($c % 3) == 0;
        case 3: return (($r + $c) % 3) == 0;
        case 4: return ((intdiv($r, 2) + intdiv($c, 3)) % 2) == 0;
        case 5: return (($r * $c) % 2 + ($r * $c) % 3) == 0;
        case 6: return ((($r * $c) % 2 + ($r * $c) % 3) % 2) == 0;
        default: return ((($r + $c) % 2 + ($r * $c) % 3) % 2) == 0;
    }
}

function qr_format_bits($mask) {
    $d = (0 << 3) | $mask;           // ECC M = 0
    $rem = $d << 10;
    for ($i = 14; $i >= 10; $i--) {
        if ($rem & (1 << $i)) $rem ^= (0b10100110111 << ($i - 10));
    }
    return ((($d << 10) | $rem) ^ 0b101010000010010);
}

function qr_format_cells($size) {
    $copy1 = [];
    for ($i = 0; $i < 6; $i++) $copy1[] = [8, $i];
    $copy1[] = [8, 7]; $copy1[] = [8, 8]; $copy1[] = [7, 8];
    for ($i = 9; $i < 15; $i++) $copy1[] = [14 - $i, 8];
    $copy2 = [];
    for ($i = 0; $i < 7; $i++) $copy2[] = [$size - 1 - $i, 8];
    for ($i = 7; $i < 15; $i++) $copy2[] = [8, $size - 15 + $i];
    return [$copy1, $copy2];
}

/**
 * Encode a byte string into a QR matrix.
 * @return int[][] matrix (0/1), or throws Exception when too long (>106 bytes)
 */
function qr_encode($text) {
    static $RS = [
        1 => [26, 10, [16]],
        2 => [44, 16, [28]],
        3 => [70, 26, [44]],
        4 => [100, 18, [32, 32]],
        5 => [134, 24, [43, 43]],
        6 => [172, 16, [27, 27, 27, 27]],
    ];
    static $CAP = [1 => 14, 2 => 26, 3 => 42, 4 => 62, 5 => 84, 6 => 106];
    static $ALIGN = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34]];
    static $REM = [1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7];

    $bytes = array_values(unpack('C*', $text));
    $n = count($bytes);
    $ver = 0;
    for ($v = 1; $v <= 6; $v++) if ($CAP[$v] >= $n) { $ver = $v; break; }
    if ($ver === 0) throw new Exception('QR data too long (max 106 bytes).');
    $size = 17 + $ver * 4;
    list($_total, $ec_per_block, $blocks) = $RS[$ver];

    $bits = '0100' . str_pad(decbin($n), 8, '0', STR_PAD_LEFT);
    foreach ($bytes as $b) $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
    $data_cw_total = array_sum($blocks);
    $bits .= str_repeat('0', min(4, $data_cw_total * 8 - strlen($bits)));
    if (strlen($bits) % 8) $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
    $words = [];
    for ($i = 0; $i < strlen($bits); $i += 8) $words[] = bindec(substr($bits, $i, 8));
    $k = 0;
    while (count($words) < $data_cw_total) { $words[] = ($k % 2 === 0) ? 0xEC : 0x11; $k++; }

    $block_data = []; $idx = 0;
    foreach ($blocks as $bl) { $block_data[] = array_slice($words, $idx, $bl); $idx += $bl; }
    $block_ec = [];
    foreach ($block_data as $bd) $block_ec[] = qr_rs_encode($bd, $ec_per_block);

    $inter = [];
    $max_d = 0; foreach ($block_data as $bd) $max_d = max($max_d, count($bd));
    for ($i = 0; $i < $max_d; $i++)
        foreach ($block_data as $bd) if ($i < count($bd)) $inter[] = $bd[$i];
    for ($i = 0; $i < $ec_per_block; $i++)
        foreach ($block_ec as $be) $inter[] = $be[$i];

    $m = array_fill(0, $size, array_fill(0, $size, null));

    $finder = function ($r0, $c0) use (&$m, $size) {
        for ($dr = -1; $dr <= 7; $dr++) for ($dc = -1; $dc <= 7; $dc++) {
            $rr = $r0 + $dr; $cc = $c0 + $dc;
            if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) continue;
            if ($dr < 0 || $dr > 6 || $dc < 0 || $dc > 6) { $m[$rr][$cc] = 0; continue; }
            $on = ($dr === 0 || $dr === 6 || $dc === 0 || $dc === 6 || ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4));
            $m[$rr][$cc] = $on ? 1 : 0;
        }
    };
    $finder(0, 0); $finder(0, $size - 7); $finder($size - 7, 0);

    for ($i = 8; $i < $size - 8; $i++) {
        $m[6][$i] = ($i % 2) ? 0 : 1;
        $m[$i][6] = ($i % 2) ? 0 : 1;
    }

    $pos = $ALIGN[$ver];
    if ($pos) {
        $last = $pos[count($pos) - 1];
        foreach ($pos as $r) foreach ($pos as $c) {
            if (($r === 6 && $c === 6) || ($r === 6 && $c === $last) || ($r === $last && $c === 6)) continue;
            for ($dr = -2; $dr <= 2; $dr++) for ($dc = -2; $dc <= 2; $dc++)
                $m[$r + $dr][$c + $dc] = (max(abs($dr), abs($dc)) != 1) ? 1 : 0;
        }
    }

    list($copy1, $copy2) = qr_format_cells($size);
    foreach (array_merge($copy1, $copy2) as $cell) $m[$cell[0]][$cell[1]] = 0;
    $m[$size - 8][8] = 1; // dark module

    // function-module map BEFORE data placement
    $func = [];
    for ($r = 0; $r < $size; $r++)
        for ($c = 0; $c < $size; $c++)
            $func[$r][$c] = ($m[$r][$c] !== null);

    $all_bits = '';
    foreach ($inter as $b) $all_bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
    $all_bits .= str_repeat('0', $REM[$ver]);
    $bi = 0; $col = $size - 1; $upward = true;
    while ($col > 0) {
        if ($col === 6) $col--;
        if ($upward) { $rows = range($size - 1, 0); } else { $rows = range(0, $size - 1); }
        foreach ($rows as $r) {
            foreach ([0, -1] as $dc) {
                $c = $col + $dc;
                if ($m[$r][$c] === null) {
                    $m[$r][$c] = ($bi < strlen($all_bits)) ? (int)$all_bits[$bi] : 0;
                    $bi++;
                }
            }
        }
        $col -= 2; $upward = !$upward;
    }

    $apply_mask = function ($kk) use ($m, $func, $size) {
        $mm = $m;
        for ($r = 0; $r < $size; $r++) for ($c = 0; $c < $size; $c++)
            if (!$func[$r][$c] && qr_mask_fn($kk, $r, $c)) $mm[$r][$c] ^= 1;
        return $mm;
    };

    $place_format = function ($mm, $kk) use ($copy1, $copy2, $size) {
        $fb = qr_format_bits($kk);
        for ($i = 0; $i < 15; $i++) {
            $bit = ($fb >> (14 - $i)) & 1;
            $mm[$copy1[$i][0]][$copy1[$i][1]] = $bit;
            $mm[$copy2[$i][0]][$copy2[$i][1]] = $bit;
        }
        $mm[$size - 8][8] = 1;
        return $mm;
    };

    $penalty = function ($mm) use ($size) {
        $p = 0;
        for ($r = 0; $r < $size; $r++) {
            $run = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($mm[$r][$c] === $mm[$r][$c - 1]) $run++;
                else { if ($run >= 5) $p += 3 + $run - 5; $run = 1; }
            }
            if ($run >= 5) $p += 3 + $run - 5;
        }
        for ($c = 0; $c < $size; $c++) {
            $run = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($mm[$r][$c] === $mm[$r - 1][$c]) $run++;
                else { if ($run >= 5) $p += 3 + $run - 5; $run = 1; }
            }
            if ($run >= 5) $p += 3 + $run - 5;
        }
        for ($r = 0; $r < $size - 1; $r++) for ($c = 0; $c < $size - 1; $c++) {
            $v = $mm[$r][$c];
            if ($v === $mm[$r][$c + 1] && $v === $mm[$r + 1][$c] && $v === $mm[$r + 1][$c + 1]) $p += 3;
        }
        $pat1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $pat2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        for ($r = 0; $r < $size; $r++) for ($c = 0; $c <= $size - 11; $c++) {
            $seg = array_slice($mm[$r], $c, 11);
            if ($seg === $pat1 || $seg === $pat2) $p += 40;
        }
        for ($c = 0; $c < $size; $c++) for ($r = 0; $r <= $size - 11; $r++) {
            $seg = [];
            for ($i = 0; $i < 11; $i++) $seg[] = $mm[$r + $i][$c];
            if ($seg === $pat1 || $seg === $pat2) $p += 40;
        }
        $dark = 0;
        for ($r = 0; $r < $size; $r++) for ($c = 0; $c < $size; $c++) $dark += $mm[$r][$c];
        $p += 10 * intdiv(abs(intdiv($dark * 100, $size * $size) - 50), 5);
        return $p;
    };

    $best = null;
    for ($kk = 0; $kk < 8; $kk++) {
        $mm = $place_format($apply_mask($kk), $kk);
        $sc = $penalty($mm);
        if ($best === null || $sc < $best[0]) $best = [$sc, $mm];
    }
    return $best[1];
}
