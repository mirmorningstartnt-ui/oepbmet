<?php
/**
 * Tiny dependency-free PDF writer + BMET EC enrollment-card renderer.
 * Produces a single-page PDF (560 x 792 pt) that reproduces the layout of the
 * official sample card (ec-card/enrollment-card/MD-I-2026-09890023.pdf):
 *   - front panel  : title, BMET ID / Clearance ID, photo, personal details,
 *                    passport / issue-date / RL-ID columns, clearance date
 *   - verify panel : QR code (links to the public verify page) + instructions
 * Uses only PDF core fonts (Helvetica / Helvetica-Bold / Times-Bold), vector
 * drawing and JPEG (DCTDecode) photo embedding => works on any plain cPanel
 * PHP without composer or external libraries.
 */

class EcPdf {
    /** @var string content-stream operators */
    private $ops = '';
    private $images = [];   // key => ['id'=>obj#, 'w'=>, 'h'=>, 'data'=>]
    private $pageW = 560.0;
    private $pageH = 792.0;

    /* ── AFM widths (units/1000) for the core fonts we use ── */
    private static $W = [
        'H' => [ // Helvetica
            ' '=>278,'!'=>278,'"'=>355,'#'=>556,'$'=>556,'%'=>889,'&'=>667,"'"=>222,'('=>333,')'=>333,'*'=>389,'+'=>584,','=>278,'-'=>333,'.'=>278,'/'=>278,
            '0'=>556,'1'=>556,'2'=>556,'3'=>556,'4'=>556,'5'=>556,'6'=>556,'7'=>556,'8'=>556,'9'=>556,':'=>278,';'=>278,'<'=>584,'='=>584,'>'=>584,'?'=>556,'@'=>1015,
            'A'=>667,'B'=>667,'C'=>722,'D'=>722,'E'=>667,'F'=>611,'G'=>778,'H'=>722,'I'=>278,'J'=>500,'K'=>667,'L'=>556,'M'=>833,'N'=>722,'O'=>778,'P'=>667,'Q'=>778,'R'=>722,'S'=>667,'T'=>611,'U'=>722,'V'=>667,'W'=>944,'X'=>667,'Y'=>667,'Z'=>611,
            '['=>278,'\\'=>278,']'=>278,'^'=>469,'_'=>556,'`'=>333,
            'a'=>556,'b'=>556,'c'=>500,'d'=>556,'e'=>556,'f'=>278,'g'=>556,'h'=>556,'i'=>222,'j'=>222,'k'=>500,'l'=>222,'m'=>833,'n'=>556,'o'=>556,'p'=>556,'q'=>556,'r'=>333,'s'=>500,'t'=>278,'u'=>556,'v'=>500,'w'=>722,'x'=>500,'y'=>500,'z'=>500,
            '{'=>334,'|'=>260,'}'=>334,'~'=>584,
        ],
        'HB' => [ // Helvetica-Bold
            ' '=>278,'!'=>333,'"'=>474,'#'=>556,'$'=>556,'%'=>889,'&'=>722,"'"=>278,'('=>333,')'=>333,'*'=>389,'+'=>584,','=>278,'-'=>333,'.'=>278,'/'=>278,
            '0'=>556,'1'=>556,'2'=>556,'3'=>556,'4'=>556,'5'=>556,'6'=>556,'7'=>556,'8'=>556,'9'=>556,':'=>333,';'=>333,'<'=>584,'='=>584,'>'=>584,'?'=>611,'@'=>975,
            'A'=>722,'B'=>722,'C'=>722,'D'=>722,'E'=>667,'F'=>611,'G'=>778,'H'=>722,'I'=>278,'J'=>556,'K'=>722,'L'=>611,'M'=>833,'N'=>722,'O'=>778,'P'=>667,'Q'=>778,'R'=>722,'S'=>667,'T'=>611,'U'=>722,'V'=>667,'W'=>944,'X'=>667,'Y'=>667,'Z'=>611,
            '['=>333,'\\'=>278,']'=>333,'^'=>584,'_'=>556,'`'=>333,
            'a'=>556,'b'=>611,'c'=>556,'d'=>611,'e'=>556,'f'=>333,'g'=>611,'h'=>611,'i'=>278,'j'=>278,'k'=>556,'l'=>278,'m'=>889,'n'=>611,'o'=>611,'p'=>611,'q'=>611,'r'=>389,'s'=>556,'t'=>333,'u'=>611,'v'=>556,'w'=>778,'x'=>556,'y'=>556,'z'=>500,
            '{'=>389,'|'=>280,'}'=>389,'~'=>584,
        ],
        'TB' => [ // Times-Bold
            ' '=>250,'!'=>333,'"'=>555,'#'=>500,'$'=>500,'%'=>1000,'&'=>778,"'"=>333,'('=>333,')'=>333,'*'=>500,'+'=>570,','=>250,'-'=>333,'.'=>250,'/'=>278,
            '0'=>500,'1'=>500,'2'=>500,'3'=>500,'4'=>500,'5'=>500,'6'=>500,'7'=>500,'8'=>500,'9'=>500,':'=>333,';'=>333,'<'=>570,'='=>570,'>'=>570,'?'=>500,'@'=>930,
            'A'=>722,'B'=>667,'C'=>722,'D'=>722,'E'=>667,'F'=>611,'G'=>778,'H'=>778,'I'=>389,'J'=>500,'K'=>722,'L'=>611,'M'=>944,'N'=>722,'O'=>778,'P'=>611,'Q'=>778,'R'=>722,'S'=>556,'T'=>667,'U'=>778,'V'=>722,'W'=>1000,'X'=>722,'Y'=>778,'Z'=>667,
            '['=>333,'\\'=>278,']'=>333,'^'=>570,'_'=>500,'`'=>333,
            'a'=>500,'b'=>556,'c'=>444,'d'=>556,'e'=>444,'f'=>333,'g'=>500,'h'=>556,'i'=>278,'j'=>278,'k'=>500,'l'=>278,'m'=>833,'n'=>556,'o'=>500,'p'=>556,'q'=>556,'r'=>444,'s'=>389,'t'=>333,'u'=>556,'v'=>500,'w'=>722,'x'=>500,'y'=>500,'z'=>444,
            '{'=>334,'|'=>260,'}'=>334,'~'=>570,
        ],
    ];

    private static function font_key($font) { return $font === 'HB' ? 'HB' : ($font === 'TB' ? 'TB' : 'H'); }

    public function text_width($text, $size, $font = 'H') {
        $w = 0;
        $t = self::$W[self::font_key($font)];
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];
            $w += $t[$ch] ?? 556;
        }
        return $w * $size / 1000.0;
    }

    private function esc($s) {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    /** color helper: hex '#rrggbb' -> rgb operators */
    private function rgb($hex) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        return sprintf('%.3f %.3f %.3f', $r, $g, $b);
    }

    /** text at (x, y) with y measured from TOP of page (design coords) */
    public function text($x, $yTop, $size, $color, $font, $text) {
        $y = $this->pageH - $yTop;
        $this->ops .= sprintf("BT /%s %s Tf %s rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            self::font_key($font), $size, $this->rgb($color), $x, $y, $this->esc($text));
    }

    public function text_centered($cx, $yTop, $size, $color, $font, $text) {
        $w = $this->text_width($text, $size, $font);
        $this->text($cx - $w / 2, $yTop, $size, $color, $font, $text);
    }

    public function rect($x, $yTop, $w, $h, $fill = null, $stroke = null, $lw = 0.8) {
        $y = $this->pageH - $yTop - $h;
        $op = '';
        if ($fill)   $op .= $this->rgb($fill) . " rg ";
        if ($stroke) $op .= $this->rgb($stroke) . " RG " . $lw . " w ";
        $op .= sprintf('%.2f %.2f %.2f %.2f re ', $x, $y, $w, $h);
        $op .= ($fill && $stroke) ? 'B' : ($fill ? 'f' : 'S');
        $this->ops .= $op . "\n";
    }

    public function round_rect($x, $yTop, $w, $h, $r, $fill = null, $stroke = null, $lw = 0.8) {
        $y = $this->pageH - $yTop - $h;
        $k = 0.5523 * $r;
        $o = '';
        if ($fill)   $o .= $this->rgb($fill) . " rg ";
        if ($stroke) $o .= $this->rgb($stroke) . " RG " . $lw . " w ";
        $o .= sprintf("%.2f %.2f m ", $x + $r, $y);
        $o .= sprintf("%.2f %.2f l ", $x + $w - $r, $y);
        $o .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c ", $x + $w - $r + $k, $y, $x + $w, $y + $r - $k, $x + $w, $y + $r);
        $o .= sprintf("%.2f %.2f l ", $x + $w, $y + $h - $r);
        $o .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c ", $x + $w, $y + $h - $r + $k, $x + $w - $r + $k, $y + $h, $x + $w - $r, $y + $h);
        $o .= sprintf("%.2f %.2f l ", $x + $r, $y + $h);
        $o .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c ", $x + $r - $k, $y + $h, $x, $y + $h - $r + $k, $x, $y + $h - $r);
        $o .= sprintf("%.2f %.2f l ", $x, $y + $r);
        $o .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c ", $x, $y + $r - $k, $x + $r - $k, $y, $x + $r, $y);
        $o .= 'h ';
        $o .= ($fill && $stroke) ? 'B' : ($fill ? 'f' : 'S');
        $this->ops .= $o . "\n";
    }

    public function line($x1, $y1Top, $x2, $y2Top, $color, $lw = 0.8, $dash = null) {
        $o = $this->rgb($color) . " RG " . $lw . " w ";
        $o .= $dash ? "[{$dash}] 0 d " : "[] 0 d ";
        $o .= sprintf("%.2f %.2f m %.2f %.2f l S [] 0 d\n", $x1, $this->pageH - $y1Top, $x2, $this->pageH - $y2Top);
        $this->ops .= $o;
    }

    public function circle($cx, $cyTop, $r, $fill = null, $stroke = null, $lw = 0.8) {
        $y = $this->pageH - $cyTop;
        $k = 0.5523 * $r;
        $o = '';
        if ($fill)   $o .= $this->rgb($fill) . " rg ";
        if ($stroke) $o .= $this->rgb($stroke) . " RG " . $lw . " w ";
        $o .= sprintf("%.2f %.2f m ", $cx + $r, $y);
        $o .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c ", $cx + $r, $y + $k, $cx + $k, $y + $r, $cx, $y + $r);
        $o .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c ", $cx - $k, $y + $r, $cx - $r, $y + $k, $cx - $r, $y);
        $o .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c ", $cx - $r, $y - $k, $cx - $k, $y - $r, $cx, $y - $r);
        $o .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c ", $cx + $k, $y - $r, $cx + $r, $y - $k, $cx + $r, $y);
        $o .= 'h ' . (($fill && $stroke) ? 'B' : ($fill ? 'f' : 'S'));
        $this->ops .= $o . "\n";
    }

    /** embed a JPEG file; returns key */
    public function add_jpeg($path) {
        $key = md5($path);
        if (isset($this->images[$key])) return $key;
        $data = file_get_contents($path);
        if ($data === false) throw new Exception('Cannot read image: ' . $path);
        $info = @getimagesizefromstring($data);
        if (!$info || $info[2] !== IMAGETYPE_JPEG) throw new Exception('Embedded PDF photos must be JPEG.');
        $this->images[$key] = ['w' => $info[0], 'h' => $info[1], 'data' => $data];
        return $key;
    }

    public function draw_image($key, $x, $yTop, $w, $h) {
        $y = $this->pageH - $yTop - $h;
        $this->ops .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Im%s Do Q\n", $w, $h, $x, $y, $key);
    }

    /** draw image clipped to a rectangle (all coords top-origin) */
    public function draw_image_clipped($key, $x, $yTop, $w, $h, $cx, $cyTop, $cw, $ch) {
        $y = $this->pageH - $yTop - $h;
        $cy = $this->pageH - $cyTop - $ch;
        $this->ops .= sprintf("q %.2f %.2f %.2f %.2f re W n %.2f 0 0 %.2f %.2f %.2f cm /Im%s Do Q\n",
            $cx, $cy, $cw, $ch, $w, $h, $x, $y, $key);
    }

    /* ─────────── document assembly ─────────── */
    public function output() {
        $objects = [];   // 1-based object bodies (without "N 0 obj")
        $nImg = count($this->images);
        // obj 1 catalog, 2 pages, 3 page, 4 content, 5 F-H, 6 F-HB, 7 F-TB, then images
        $imgKeys = array_keys($this->images);
        $imgFirst = 8;
        $res = '<< /Font << /H 5 0 R /HB 6 0 R /TB 7 0 R >>';
        if ($nImg) {
            $res .= ' /XObject << ';
            foreach ($imgKeys as $i => $k) $res .= "/Im{$k} " . ($imgFirst + $i) . " 0 R ";
            $res .= '>>';
        }
        $res .= ' >>';
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0F %.0F] /Resources %s /Contents 4 0 R >>", $this->pageW, $this->pageH, $res);
        $objects[4] = "<< /Length " . strlen($this->ops) . " >>\nstream\n" . $this->ops . "endstream";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        $objects[7] = "<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>";
        foreach ($imgKeys as $i => $k) {
            $im = $this->images[$k];
            $objects[$imgFirst + $i] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $im['w'], $im['h'], strlen($im['data']), $im['data']
            );
        }
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        $count = max(array_keys($objects)) + 1;
        for ($i = 1; $i < $count; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . ($objects[$i] ?? "<< >>") . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . $count . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $pdf .= "trailer\n<< /Size " . $count . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $pdf;
    }
}

/**
 * Render the enrollment card PDF for one card record.
 * @param array $c  card row (assoc)
 * @param string $jpeg_path|null  photo (JPEG) or null
 * @param string $qr_url  verification URL encoded in the QR code
 * @return string PDF bytes
 */
function ec_card_pdf_bytes(array $c, $jpeg_path, $qr_url) {
    require_once __DIR__ . '/qrcode.php';

    $pdf = new EcPdf();
    $MONTHS = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    $fmt = function ($date) use ($MONTHS) {
        $ts = strtotime((string)$date);
        if (!$ts) return (string)$date;
        return sprintf('%02d %s %d', date('j', $ts), $MONTHS[(int)date('n', $ts) - 1], date('Y', $ts));
    };

    // page background
    $pdf->rect(0, 0, 560, 792, '#f5f6fa');

    $cx = 280.0;            // horizontal centre
    $cardW = 240.0;
    $cardX = $cx - $cardW / 2;
    $cardR = $cardX + $cardW;

    /* ══ FRONT PANEL ══ */
    $top1 = 63.5; $h1 = 182.0;
    $pdf->round_rect($cardX, $top1, $cardW, $h1, 9, '#f5f5f3', '#d8d8d8', 0.9);

    $pdf->text($cardX + 7, 82, 12.5, '#222222', 'TB', 'BMET EC Card');
    $pdf->text($cardX + 7, 92.5, 8, '#555555', 'H', 'BMET ID:');
    $pdf->text($cardX + 7 + $pdf->text_width('BMET ID:', 8, 'H') + 4, 92.5, 8, '#111111', 'HB', (string)$c['bmet_no']);
    $pdf->text($cardX + 7, 102, 8, '#555555', 'H', 'Clearance ID:');
    $pdf->text($cardX + 7 + $pdf->text_width('Clearance ID:', 8, 'H') + 2, 102, 8, '#111111', 'HB', (string)$c['ec_no']);

    // logos (vector stand-ins for the BD emblem + BMET seal)
    $lx = $cardR - 24; $ly = $top1 + 20;
    $pdf->circle($lx - 20, $ly, 9.5, '#006a4e');
    $pdf->circle($lx - 20, $ly, 6.0, '#f42a41');
    $pdf->circle($lx - 20, $ly, 1.8, '#ffd400');
    $pdf->circle($lx, $ly, 9.5, '#2456a8');
    for ($a = 0; $a < 8; $a++) {
        $ang = deg2rad($a * 45);
        $pdf->circle($lx + 8.4 * cos($ang), $ly + 8.4 * sin($ang), 1.5, '#2456a8');
    }
    $pdf->circle($lx, $ly, 6.2, '#ffffff');
    $pdf->circle($lx, $ly, 3.4, '#2456a8');

    $pdf->line($cardX + 7, 107.5, $cardR - 7, 107.5, '#333333', 0.9);

    // photo box
    $px = $cardX + 9; $py = 111.5; $pw = 61.5; $ph = 79.5;
    $pdf->rect($px, $py, $pw, $ph, '#e6e6e6', '#bbbbbb', 0.7);
    if ($jpeg_path) {
        try {
            $key = $pdf->add_jpeg($jpeg_path);
            // cover-fit inside the box
            $im = @getimagesize($jpeg_path);
            $iw = $im[0]; $ih = $im[1];
            $scale = max($pw / $iw, $ph / $ih);
            $dw = $iw * $scale; $dh = $ih * $scale;
            // clip to box
            $pdf->rect($px, $py, $pw, $ph, '#e6e6e6');
            $pdf->draw_image_clipped($key, $px + ($pw - $dw) / 2, $py + ($ph - $dh) / 2, $dw, $dh, $px, $py, $pw, $ph);
        } catch (Exception $e) { /* keep placeholder */ }
    }

    // right column
    $rx = $cardX + 82;
    $rows = [
        ['Name', (string)$c['name']],
        ["Father's Name", (string)$c['fathers_name']],
        ["Mother's Name", (string)($c['mothers_name'] ?? '')],
        ['Destination Country', (string)$c['country']],
    ];
    $y = 118.5;
    foreach ($rows as $r) {
        $pdf->text($rx, $y, 7.5, '#555555', 'H', $r[0]);
        $pdf->text($rx, $y + 9.5, 8.5, '#111111', 'HB', ec_pdf_fit($pdf, $r[1], 8.5, 'HB', $cardR - 8 - $rx));
        $y += 19.8;
    }

    $pdf->line($cardX + 7, 194, $cardR - 7, 194, '#bbbbbb', 0.7, '2,2');

    // three columns
    $cols = [
        [$cx - 80, 'Passport Number', (string)$c['passport_no']],
        [$cx + 13, 'Passport Issue Date', $fmt($c['passport_issue'])],
        [$cx + 82, 'RL ID', (string)$c['agency_license']],
    ];
    foreach ($cols as $col) {
        $lw = $pdf->text_width($col[1], 7.5, 'H');
        $pdf->text($col[0] - $lw / 2, 199.5, 7.5, '#555555', 'H', $col[1]);
        $vw = $pdf->text_width($col[2], 8.5, 'HB');
        $pdf->text($col[0] - $vw / 2, 211, 8.5, '#111111', 'HB', $col[2]);
    }

    $lbl = 'Clearance Date:';
    $val = '  ' . $fmt($c['ec_date']);
    $tw = $pdf->text_width($lbl, 8, 'H') + $pdf->text_width($val, 8.5, 'HB');
    $sx = $cx - $tw / 2;
    $pdf->text($sx, 221.5, 8, '#555555', 'H', $lbl);
    $pdf->text($sx + $pdf->text_width($lbl, 8, 'H'), 221.5, 8.5, '#111111', 'HB', $val);

    /* ══ VERIFY PANEL ══ */
    $top2 = 258.5; $h2 = 128.0;
    $pdf->round_rect($cardX, $top2, $cardW, $h2, 9, '#f5f5f3', '#d8d8d8', 0.9);
    $pdf->text_centered($cx, 275, 11, '#222222', 'TB', 'Verify this card');

    // QR code
    $matrix = qr_encode($qr_url);
    $qn = count($matrix);
    $qs = 59.0;                       // drawn size
    $qx = $cardX + 11.5; $qy = 281.0;
    $pdf->rect($qx - 2, $qy - 2, $qs + 4, $qs + 4, '#ffffff');
    $mod = $qs / ($qn + 4);           // quiet zone of 2 modules inside
    $ox = $qx + $mod * 2; $oy = $qy + $mod * 2;
    $ops = '';
    for ($r = 0; $r < $qn; $r++) {
        for ($cc = 0; $cc < $qn; $cc++) {
            if ($matrix[$r][$cc]) {
                $pdf->rect($ox + $cc * $mod, $oy + $r * $mod, $mod + 0.05, $mod + 0.05, '#111111');
            }
        }
    }

    $tx = $cardX + 82; $tw2 = $cardR - 8 - $tx;
    $pdf->text($tx, 288, 8, '#333333', 'H', '1. Scan QR code >> Visit the url.');
    $pdf->text_centered($tx + $tw2 / 2, 302, 8, '#333333', 'HB', 'OR');
    $host = (string)preg_replace('#^https?://#', '', $qr_url);
    $host = explode('/', $host)[0];
    $para = "2. {$host} >> Click 'Verify BMET EC card' >> Enter passport no. >> Submit & Verify your card";
    $ly2 = 315;
    foreach (ec_pdf_wrap($pdf, $para, 8, 'H', $tw2) as $ln) {
        $pdf->text($tx, $ly2, 8, '#333333', 'H', $ln);
        $ly2 += 10;
    }

    $note = 'This card holder is under insurance coverage & welfare services';
    $ny = 366;
    foreach (ec_pdf_wrap($pdf, $note, 8, 'H', $cardW - 30) as $ln) {
        $pdf->text_centered($cx, $ny, 8, '#444444', 'H', $ln);
        $ny += 10;
    }

    return $pdf->output();
}

/** shrink text (ellipsis) so it fits $maxw */
function ec_pdf_fit($pdf, $text, $size, $font, $maxw) {
    if ($pdf->text_width($text, $size, $font) <= $maxw) return $text;
    while (strlen($text) > 1 && $pdf->text_width($text . '...', $size, $font) > $maxw) $text = substr($text, 0, -1);
    return $text . '...';
}

/** greedy word wrap */
function ec_pdf_wrap($pdf, $text, $size, $font, $maxw) {
    $words = preg_split('/\s+/', $text);
    $lines = []; $cur = '';
    foreach ($words as $w) {
        $try = $cur === '' ? $w : $cur . ' ' . $w;
        if ($pdf->text_width($try, $size, $font) > $maxw && $cur !== '') { $lines[] = $cur; $cur = $w; }
        else $cur = $try;
    }
    if ($cur !== '') $lines[] = $cur;
    return $lines;
}
