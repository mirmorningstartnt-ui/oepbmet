/* Live EC-No preview on the card form: CC-I-YEAR-PASSPORTDIGITS */
(function () {
  var codes = window.EC_COUNTRY_CODES || {};
  var el = document.getElementById('ec_preview');
  if (!el) return;
  function val(id) { var n = document.getElementById(id); return n ? n.value.trim() : ''; }
  function compute() {
    var country = val('country');
    var date = val('ec_date');
    var pass = val('passport_no');
    var cc = codes[country];
    if (!cc) cc = (country.replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase() || 'XX');
    var year = date ? date.slice(0, 4) : String(new Date().getFullYear());
    var digits = pass.replace(/\D/g, '') || pass.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    el.value = cc + '-I-' + year + '-' + (digits || '_________');
  }
  ['country', 'ec_date', 'passport_no'].forEach(function (id) {
    var n = document.getElementById(id);
    if (!n) return;
    n.addEventListener('input', compute);
    n.addEventListener('change', compute);
  });
})();
