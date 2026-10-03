<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
ec_require_login();

$id = (int)($_GET['id'] ?? 0);
$card = $id ? ec_card_get($id) : null;
if ($id && !$card) { ec_flash('Card not found.', 'err'); header('Location: index.php'); exit; }

$errors = [];
$v = [
    'name' => '', 'fathers_name' => '', 'mothers_name' => '', 'ec_date' => date('Y-m-d'),
    'birth_date' => '', 'gender' => 'Male', 'nid' => '', 'blood_group' => '',
    'passport_no' => '', 'passport_issue' => '', 'passport_expire' => '',
    'visa_no' => '', 'visa_issue' => '', 'visa_expire' => '', 'referral_no' => '',
    'employer' => '', 'country' => '', 'agency_name' => '', 'agency_license' => '',
    'agency_phone' => '', 'addr_house' => '', 'addr_post' => '', 'addr_ps' => '',
    'addr_upazila' => '', 'addr_district' => '', 'addr_division' => '',
];
if ($card) foreach ($v as $k => $_unused) $v[$k] = $card[$k] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ec_csrf_check();
    foreach ($v as $k => $_u) $v[$k] = trim((string)($_POST[$k] ?? ''));
    foreach (['name', 'passport_no', 'ec_date', 'country'] as $req) {
        if ($v[$req] === '') $errors[] = ucfirst(str_replace('_', ' ', $req)) . ' is required.';
    }
    foreach (['ec_date', 'birth_date', 'passport_issue', 'passport_expire', 'visa_issue', 'visa_expire'] as $d) {
        if ($v[$d] !== '' && !strtotime($v[$d])) $errors[] = ucfirst(str_replace('_', ' ', $d)) . ' is not a valid date.';
    }

    $country_code = ec_country_code($v['country']);
    $ec_no = ec_make_ec_no($v['country'], $v['ec_date'], $v['passport_no']);
    $existing = ec_card_by_ec($ec_no);
    if ($existing && (int)$existing['id'] !== $id) {
        $errors[] = "EC No $ec_no already exists (card id {$existing['id']}).";
    }

    if (!$errors) {
        try {
            $old_ec = $card['ec_no'] ?? null;
            if ($card) {
                $bmet_no = $card['bmet_no'];
                $photo = $card['photo'];
            } else {
                $year = date('Y', strtotime($v['ec_date']));
                $seq = ec_next_bmet_seq($year);
                do {
                    $bmet_no = ec_make_bmet_no($v['ec_date'], $seq);
                    $st = ec_db()->prepare('SELECT id FROM ec_cards WHERE bmet_no = ?');
                    $st->execute([$bmet_no]);
                    if ($st->fetch()) { $seq++; continue; }
                    break;
                } while (true);
                $photo = null;
            }

            // photo upload
            if (!empty($_FILES['photo']['name'])) {
                ec_save_photo($_FILES['photo'], $ec_no);
                $photo = 'ec-card/uploads/' . $ec_no . '.jpg';
            } elseif ($old_ec && $old_ec !== $ec_no && is_file(ec_photo_file($old_ec))) {
                @rename(ec_photo_file($old_ec), ec_photo_file($ec_no));
                $photo = 'ec-card/uploads/' . $ec_no . '.jpg';
            }

            $data = array_merge($v, [
                'ec_no' => $ec_no,
                'bmet_no' => $bmet_no,
                'country_code' => $country_code,
                'photo' => $photo,
            ]);

            if ($card) {
                ec_card_update($id, $data);
                $new_id = $id;
                if ($old_ec && $old_ec !== $ec_no) ec_unpublish_card($old_ec);
            } else {
                $new_id = ec_card_insert($data);
            }
            ec_publish_card(ec_card_get($new_id));
            ec_flash(($card ? 'Card updated' : 'Card created') . " — $ec_no published (verify page + PDF + MOCK_DB).");
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$preview_ec = $card ? $card['ec_no'] : ec_make_ec_no($v['country'] ?: 'Moldova', $v['ec_date'], $v['passport_no'] ?: '_________');
adm_head($card ? 'Edit Card' : 'Add New Card');
?>

  <div class="adm-card">
    <h2><?php echo $card ? 'Edit EC Card — ' . ec_e($card['ec_no']) : 'Add New EC Card'; ?></h2>
    <p class="adm-sub">Fields marked <span style="color:var(--danger);">*</span> are required. The EC No and BMET No are generated automatically.</p>

    <?php if ($errors): ?>
      <div class="alert alert-err"><?php foreach ($errors as $er) echo ec_e($er) . '<br>'; ?></div>
    <?php endif; ?>

    <form method="post" action="card_form.php<?php echo $id ? '?id=' . $id : ''; ?>" enctype="multipart/form-data" novalidate>
      <?php echo ec_csrf_field(); ?>

      <fieldset class="adm-fieldset">
        <legend>Generated Numbers</legend>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">EC No (generated)</label>
            <input class="form-control" id="ec_preview" value="<?php echo ec_e($preview_ec); ?>" readonly />
            <span class="hint">Format: <b>CC-I-YEAR-PASSPORT#</b> — country code, default series “I”, EC year, passport digits.</span>
          </div>
          <div class="form-group">
            <label class="form-label">BMET No (generated)</label>
            <input class="form-control" value="<?php echo ec_e($card['bmet_no'] ?? 'saved on create'); ?>" readonly />
          </div>
          <div class="form-group">
            <label class="form-label" for="ec_date">EC Date <span class="req">*</span></label>
            <input class="form-control" type="date" id="ec_date" name="ec_date" value="<?php echo ec_e($v['ec_date']); ?>" required data-ec="date" />
          </div>
        </div>
      </fieldset>

      <fieldset class="adm-fieldset">
        <legend>Personal Information</legend>
        <div class="form-grid">
          <div class="form-group"><label class="form-label" for="name">Name <span class="req">*</span></label>
            <input class="form-control" id="name" name="name" value="<?php echo ec_e($v['name']); ?>" required /></div>
          <div class="form-group"><label class="form-label" for="fathers_name">Fathers Name</label>
            <input class="form-control" id="fathers_name" name="fathers_name" value="<?php echo ec_e($v['fathers_name']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="mothers_name">Mothers Name</label>
            <input class="form-control" id="mothers_name" name="mothers_name" value="<?php echo ec_e($v['mothers_name']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="birth_date">Birth Date</label>
            <input class="form-control" type="date" id="birth_date" name="birth_date" value="<?php echo ec_e($v['birth_date']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="gender">Gender</label>
            <select class="form-control" id="gender" name="gender">
              <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                <option value="<?php echo $g; ?>" <?php echo $v['gender'] === $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="form-group"><label class="form-label" for="nid">NID</label>
            <input class="form-control" id="nid" name="nid" value="<?php echo ec_e($v['nid']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="blood_group">Blood Group</label>
            <input class="form-control" id="blood_group" name="blood_group" value="<?php echo ec_e($v['blood_group']); ?>" placeholder="e.g. O+" /></div>
          <div class="form-group"><label class="form-label" for="photo">Photo (JPG/PNG)</label>
            <input class="form-control" type="file" id="photo" name="photo" accept="image/jpeg,image/png" />
            <?php if (!empty($card['photo'])): ?>
              <img class="photo-preview" src="../<?php echo ec_e($card['photo']); ?>" alt="current photo" />
            <?php endif; ?>
          </div>
        </div>
      </fieldset>

      <fieldset class="adm-fieldset">
        <legend>Passport &amp; Visa</legend>
        <div class="form-grid">
          <div class="form-group"><label class="form-label" for="passport_no">Passport No <span class="req">*</span></label>
            <input class="form-control" id="passport_no" name="passport_no" value="<?php echo ec_e($v['passport_no']); ?>" required data-ec="passport" /></div>
          <div class="form-group"><label class="form-label" for="passport_issue">Passport Issue Date</label>
            <input class="form-control" type="date" id="passport_issue" name="passport_issue" value="<?php echo ec_e($v['passport_issue']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="passport_expire">Passport Expire Date</label>
            <input class="form-control" type="date" id="passport_expire" name="passport_expire" value="<?php echo ec_e($v['passport_expire']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="visa_no">Visa No</label>
            <input class="form-control" id="visa_no" name="visa_no" value="<?php echo ec_e($v['visa_no']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="visa_issue">Visa Issue Date</label>
            <input class="form-control" type="date" id="visa_issue" name="visa_issue" value="<?php echo ec_e($v['visa_issue']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="visa_expire">Visa Expire Date</label>
            <input class="form-control" type="date" id="visa_expire" name="visa_expire" value="<?php echo ec_e($v['visa_expire']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="referral_no">Referral No</label>
            <input class="form-control" id="referral_no" name="referral_no" value="<?php echo ec_e($v['referral_no']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="employer">Employer</label>
            <input class="form-control" id="employer" name="employer" value="<?php echo ec_e($v['employer']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="country">Country <span class="req">*</span></label>
            <select class="form-control" id="country" name="country" required data-ec="country">
              <option value="">— select —</option>
              <?php foreach (ec_countries() as $cname => $ccode): ?>
                <option value="<?php echo ec_e($cname); ?>" <?php echo strcasecmp($v['country'], $cname) === 0 ? 'selected' : ''; ?>><?php echo ec_e($cname . ' (' . $ccode . ')'); ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
      </fieldset>

      <fieldset class="adm-fieldset">
        <legend>Recruiting Agency</legend>
        <div class="form-grid">
          <div class="form-group"><label class="form-label" for="agency_name">Recruiting Agency Name</label>
            <input class="form-control" id="agency_name" name="agency_name" value="<?php echo ec_e($v['agency_name']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="agency_license">License No</label>
            <input class="form-control" id="agency_license" name="agency_license" value="<?php echo ec_e($v['agency_license']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="agency_phone">Phone</label>
            <input class="form-control" id="agency_phone" name="agency_phone" value="<?php echo ec_e($v['agency_phone']); ?>" /></div>
        </div>
      </fieldset>

      <fieldset class="adm-fieldset">
        <legend>Permanent Address</legend>
        <div class="form-grid">
          <div class="form-group full"><label class="form-label" for="addr_house">House / Vill / Road</label>
            <input class="form-control" id="addr_house" name="addr_house" value="<?php echo ec_e($v['addr_house']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="addr_post">Post Office</label>
            <input class="form-control" id="addr_post" name="addr_post" value="<?php echo ec_e($v['addr_post']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="addr_ps">Police Station</label>
            <input class="form-control" id="addr_ps" name="addr_ps" value="<?php echo ec_e($v['addr_ps']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="addr_upazila">Upazilla</label>
            <input class="form-control" id="addr_upazila" name="addr_upazila" value="<?php echo ec_e($v['addr_upazila']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="addr_district">District</label>
            <input class="form-control" id="addr_district" name="addr_district" value="<?php echo ec_e($v['addr_district']); ?>" /></div>
          <div class="form-group"><label class="form-label" for="addr_division">Division</label>
            <input class="form-control" id="addr_division" name="addr_division" value="<?php echo ec_e($v['addr_division']); ?>" /></div>
        </div>
      </fieldset>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> <?php echo $card ? 'Save Changes' : 'Create Card & Publish'; ?></button>
        <a class="btn" href="index.php">Cancel</a>
      </div>
    </form>
  </div>

  <script>
    window.EC_COUNTRY_CODES = <?php echo json_encode(ec_countries()); ?>;
    window.EC_EDIT_MODE = <?php echo $card ? 'true' : 'false'; ?>;
  </script>
  <script src="assets/admin.js"></script>
<?php adm_foot(); ?>
