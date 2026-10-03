# BMET EC Card Manager (PHP + MySQL, cPanel-ready)

A ready-to-deploy add-on for the static BMET LMS site in this repository.
It adds a small **admin dashboard** that creates and manages **Emigration Clearance (EC) cards**
and automatically publishes every card as:

1. a public verification page — `ec-card/verify/<EC-No>.html`
2. a printable enrollment card PDF — `ec-card/enrollment-card/<EC-No>.pdf`
   (same layout as the uploaded sample `ec-card/enrollment-card/MD-I-2026-09890023.pdf`,
   including a scannable QR code that points to the verification page)
3. a `MOCK_DB` line inside `pdo-certificate.html` **and** `pdo-enrollment-card.html`,
   so the static passport-search pages keep working without PHP

Only plain HTML / CSS / JS / PHP / MySQL are used — **no composer, no external PHP libraries**.
The PDF writer and the QR-code encoder are self-contained PHP classes
(`admin/includes/pdf.php`, `admin/includes/qrcode.php`).

---

## Deployment on Hostinger (cPanel / hPanel)

1. **Upload** the whole project folder into `public_html` (File Manager → Upload, or FTP).
2. **Create a MySQL database** in cPanel → *MySQL Databases*:
   create database, create user, assign the user to the database with *ALL PRIVILEGES*.
3. Open **`https://yourdomain.com/install.php`** in your browser.
   * enter the database host/name/user/password (host is usually `localhost`),
   * choose the administrator username + password,
   * leave *Site URL* empty to auto-detect it (this URL is encoded in the card QR codes),
   * click **Install now**.
   The installer creates the tables (`admins`, `ec_cards`), writes
   `admin/includes/config.php` and creates the admin account.
4. **Delete `install.php`** from the server (important!).
5. Log in at **`https://yourdomain.com/admin/`** and start creating cards.

Requirements: PHP 7.4+ with `pdo_mysql` (always present on Hostinger);
`gd` is used only to normalise uploaded photos (PNG → JPG) and is present on Hostinger by default.

---

## Using the dashboard

* **Cards list** — search by name / EC No / BMET No / passport / NID / country; open the public
  verify page or the PDF; edit; regenerate published files; delete (also removes every published file).
* **Add / edit card** — fields: Name, EC Date, Father's Name, Mother's Name, Birth Date, Gender,
  NID, Blood Group, Passport No + issue/expire, Visa No + issue/expire, Referral No, Employer,
  Country, Recruiting Agency Name / License No / Phone, Permanent Address
  (House-Vill-Road, Post Office, Police Station, Upazilla, District, Division) and an optional photo.
* **Generated numbers**
  * **EC No** = `CC-I-YEAR-PASSPORTDIGITS` — e.g. `MD-I-2026-09890023`:
    `MD` = 2-letter country code (Moldova), `I` = default series,
    `2026` = year of the EC date, `09890023` = numeric part of the passport number.
    The form shows a live preview while you type.
  * **BMET No** = `CPM` + year + 7-digit sequence + check letter (e.g. `CPM20260000001L`).

Every save re-publishes the verify page, the PDF and the two `MOCK_DB` lines automatically.

## Public pages

* `pdo-certificate.html` — unchanged; searches its `MOCK_DB` and opens `ec-card/verify/<EC>.html`.
* `pdo-enrollment-card.html` — redesigned search: resolves the passport through
  `admin/api/lookup.php` when PHP is available (falls back to its `MOCK_DB` on pure static
  hosting) and opens the enrollment card PDF `ec-card/enrollment-card/<EC>.pdf`.
* `index.html` and `ec-card/verify/MD-I-2026-09890023.html` — untouched sample pages.

## Project structure

```
index.html                     public login page (untouched)
pdo-certificate.html           certificate search (MOCK_DB lines appended at runtime)
pdo-enrollment-card.html       enrollment-card search (redesigned)
install.php                    one-time installer  →  DELETE AFTER INSTALL
admin/
  index.php                    dashboard / card list
  login.php / logout.php       session auth (password_hash, CSRF protected)
  card_form.php                add / edit card (+ photo upload)
  card_delete.php              delete card + published files
  card_regenerate.php          re-publish verify page / PDF / MOCK_DB
  api/lookup.php               public JSON passport → EC lookup
  assets/admin.css, admin.js   dashboard UI + live EC-No preview
  includes/                    PHP core (db, auth, pdf, qrcode, templates) — web-denied
ec-card/
  verify/<EC>.html             generated verification pages
  enrollment-card/<EC>.pdf     generated enrollment cards
  uploads/<EC>.jpg             stored photos
```

Runtime-generated files (`admin/includes/config.php`, `admin/data/`, photos and generated
cards) are excluded from Git via `.gitignore`; only the sample card is tracked.
