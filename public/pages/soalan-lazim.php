<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// pages/soalan-lazim.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

/* ================= Session / UI ================= */
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
$lang         = $_SESSION['lang']          ?? 'ms';
$sidebarTheme = $_SESSION['theme.menu'] ?? $_SESSION['theme.sidebar'] ?? 'dark';
$version      = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));

/* GET page biasa — boleh lepaskan lock */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
  session_write_close();
}

/* ================= Helpers ================= */
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function tx(string $key, string $fallback): string {
  $v = __($key);
  return ($v === $key || $v === null || $v === '') ? $fallback : (string)$v;
}

$PAGE_TITLE = tx('faq_title', 'Soalan Lazim (FAQ)');

/* ================= Data: Kategori + FAQ =================
 *  - Setiap item wajib ada: cat (kategori), q (soalan), a (jawapan), tags (opsyen)
 *  - Jika nak tambah, ikut struktur yang sama.
 */
/*
  Each FAQ now supports an optional 'audience' key:
   - 'all' (default) : visible to everyone
   - string like 'Super Admin', 'Admin HR', 'Admin Kewangan' : visible only to that group
   - array of strings : visible to any matching group
*/
$faqs = [
  // Akaun & Akses
  ['cat'=>tx('faq_cat_account_access','Akaun & Akses'),'q'=>tx('faq_item_01_q','Bagaimana cara log masuk ke sistem?'),'a'=>tx('faq_item_01_a','Gunakan <b>ID Staf</b> dan <b>kata laluan</b> anda pada halaman log masuk. Jika ini kali pertama, sila ikut arahan yang dipaparkan pada halaman login atau hubungi pentadbir sistem untuk bantuan.'),'tags'=>'akaun login id pengguna kata laluan masuk'],
  ['cat'=>tx('faq_cat_account_access','Akaun & Akses'),'q'=>tx('faq_item_02_q','Kenapa saya tidak boleh log masuk?'),'a'=>tx('faq_item_02_a','Punca biasa ialah kata laluan tidak tepat, akaun disekat, atau akses kumpulan belum ditetapkan. Semak semula ID/kata laluan anda. Jika masih gagal, hubungi pentadbir sistem.'),'tags'=>'login gagal akaun sesi akses'],

  // Navigasi & Penggunaan
  ['cat'=>tx('faq_cat_navigation','Navigasi & Penggunaan'),'q'=>tx('faq_item_04_q','Di mana saya boleh lihat maklumat ringkas sistem?'),'a'=>tx('faq_item_04_a','Gunakan halaman <b>Dashboard</b> untuk paparan ringkas. Ia membantu anda faham status semasa dan navigasi ke modul utama dengan lebih cepat.'),'tags'=>'dashboard halaman utama ringkasan sistem'],
  ['cat'=>tx('faq_cat_navigation','Navigasi & Penggunaan'),'q'=>tx('faq_item_06_q','Bagaimana cara cepat cari fungsi dalam sistem?'),'a'=>tx('faq_item_06_a','Gunakan menu modul di sidebar dan pilih halaman berkaitan. Untuk halaman jadual, gunakan carian di bahagian atas jadual untuk tapis data dengan cepat.'),'tags'=>'carian jadual navigasi modul fungsi'],

  // Profil & Tetapan
  ['cat'=>tx('faq_cat_profile_settings','Profil & Tetapan'),'q'=>tx('faq_item_07_q','Bagaimana saya kemaskini tetapan bahasa?'),'a'=>tx('faq_item_07_a','Anda boleh menukar bahasa melalui topbar atau halaman <b>Profil</b>. Pilihan bahasa akan disimpan untuk akaun anda.'),'tags'=>'bahasa language profil'],
  ['cat'=>tx('faq_cat_profile_settings','Profil & Tetapan'),'q'=>tx('faq_item_08_q','Bagaimana saya ubah tema paparan?'),'a'=>tx('faq_item_08_a','Pergi ke halaman <b>Profil</b> untuk menukar tetapan tema seperti mode paparan dan warna antaramuka. Perubahan akan digunakan pada sesi anda.'),'tags'=>'tema dark light profil'],
  ['cat'=>tx('faq_cat_profile_settings','Profil & Tetapan'),'q'=>tx('faq_item_09_q','Apa kandungan Jejak Audit di halaman Profil?'),'a'=>tx('faq_item_09_a','Jejak Audit memaparkan rekod aktiviti penting seperti kemaskini data dan tindakan sistem. Ia membantu semakan dan pemantauan keselamatan.'),'tags'=>'audit profil sesi aktiviti akaun'],

  // Data & Rekod
  ['cat'=>tx('faq_cat_user_management','Data & Rekod'),'q'=>tx('faq_item_10_q','Bagaimana saya menyemak atau mengurus data yang dipaparkan dalam jadual?'),'a'=>tx('faq_item_10_a','Pentadbir boleh menggunakan halaman <b>Senarai Pengguna</b> untuk menambah pengguna, menukar kumpulan, dan mengawal status akses. Setiap perubahan perlu ikut polisi dalaman organisasi.'),'tags'=>'jadual data semakan rekod tindakan'],
  ['cat'=>tx('faq_cat_user_management','Data & Rekod'),'q'=>tx('faq_item_11_q','Apakah maksud status akses pengguna?'),'a'=>tx('faq_item_11_a','Status akses menentukan sama ada pengguna dibenarkan masuk ke sistem. Jika status disekat, pengguna tidak boleh log masuk sehingga status diaktifkan semula oleh pentadbir.'),'tags'=>'status label lencana rekod maklumat'],
  ['cat'=>tx('faq_cat_user_management','Data & Rekod'),'q'=>tx('faq_item_12_q','Apa fungsi halaman Kumpulan Pengguna?'),'a'=>tx('faq_item_12_a','Halaman ini digunakan untuk mengurus struktur kumpulan, warna identiti kumpulan, serta akses modul/menu bagi setiap kumpulan. Ini memudahkan pengurusan hak capaian secara berpusat.'),'tags'=>'ubah data semak rekod borang kemaskini'],

  // Keselamatan & Privasi
  ['cat'=>tx('faq_cat_group_management','Keselamatan & Privasi'),'q'=>tx('faq_item_13_q','Bolehkah kumpulan dipadam?'),'a'=>tx('faq_item_13_a','Kumpulan hanya boleh dipadam jika tiada akses modul/menu yang aktif dan tiada pengguna yang masih ditetapkan pada kumpulan tersebut. Ini untuk elak gangguan operasi.'),'tags'=>'keselamatan privasi kata laluan sesi data'],

  // Sokongan & Bantuan
  ['cat'=>tx('faq_cat_support','Sokongan & Bantuan'),'q'=>tx('faq_item_14_q','Apa perlu dibuat jika berlaku ralat sistem?'),'a'=>tx('faq_item_14_a','Catat mesej ralat, masa kejadian, dan tindakan semasa ralat berlaku. Hantar maklumat tersebut kepada pentadbir sistem untuk semakan lanjut.'),'tags'=>'ralat sistem bantuan sokongan tangkapan skrin'],
  ['cat'=>tx('faq_cat_support','Sokongan & Bantuan'),'q'=>tx('faq_item_15_q','Siapa perlu dihubungi untuk isu akses atau konfigurasi?'),'a'=>tx('faq_item_15_a','Hubungi pentadbir sistem dalaman organisasi anda. Isu akses, kumpulan pengguna, dan tetapan sistem biasanya memerlukan kebenaran pentadbir.'),'tags'=>'bantuan support helpdesk sokongan panduan'],
];

/* ================= Kategori ================= */
// Semua FAQ dipaparkan (tiada penapisan berdasarkan role di page)
$cats = array_values(array_unique(array_map(fn($x)=> (string)$x['cat'], $faqs)));
array_unshift($cats, __('faq_cat_semua') ?: 'Semua');
$defaultCat = $cats[0];
?>
<!doctype html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php
    $NEED_DATERANGE  = false;
    $NEED_VECTORMAP  = false;
    $NEED_DATATABLES = false;
    $NEED_SELECT2    = false;
    $INCLUDE_I18N_PRESTASI = true;
    include __DIR__ . '/../includes/head.php';
  ?>
  <style>
    body { font-size:.95rem }
    .faq-muted { color: var(--bs-secondary-color) }
    .faq-lead { max-width:820px; margin:0 auto }
    .faq-hero-card{
      border: 0;
      border-radius: 8px;
      overflow: hidden;
      background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #2563eb 100%);
      box-shadow: 0 10px 28px rgba(15,23,42,.18);
    }
    .faq-card {
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 8px;
      box-shadow: 0 4px 14px rgba(15,23,42,.06);
    }

    /* Left category list - Professional styling */
    .faq-cat .list-group-item{
      cursor:pointer; 
      user-select:none;
      border-left: 3px solid transparent;
      transition: all 0.24s ease;
      padding: 0.75rem 1rem;
      color: var(--bs-body-color);
      border-radius: 8px;
      margin-bottom: .25rem;
    }
    .faq-cat .list-group-item:hover:not(.active) {
      background: var(--bs-primary-bg-subtle);
      border-left-color: var(--bs-primary);
      padding-left: 1.25rem;
      color: var(--bs-primary-text-emphasis) !important; /* Ensure text is visible on hover */
    }
    .faq-cat .list-group-item.active,
    .faq-cat .list-group-item.active:hover,
    .faq-cat .list-group-item.active:focus {
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
      border-left-color: #0d6efd !important;
      color: #ffffff !important;
      font-weight: 600;
      box-shadow: 0 3px 10px rgba(15,23,42,.18);
    }
    .faq-cat .list-group-item.active:hover {
      background: linear-gradient(135deg, #0b5ed7 0%, #084298 100%) !important; /* Darker on hover */
    }
    /* Ensure text inside active item is always white */
    .faq-cat .list-group-item.active * {
      color: #ffffff !important;
    }

    /* Right content - Enhanced accordion */
    .accordion-button { 
      text-decoration:none;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    .accordion-button:not(.collapsed) {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      color: var(--bs-primary);
    }
    .acc-item {
      border-radius: 8px;
      overflow:hidden;
      border: 1px solid #e9ecef;
      box-shadow: 0 2px 8px rgba(15,23,42,.05);
      transition: all 0.3s ease;
    }
    .acc-item:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      transform: translateY(-2px);
    }
    .acc-item + .acc-item { margin-top:1rem }

    .no-result { 
      display:none;
      border-radius: 8px;
      border-left: 4px solid var(--bs-warning);
    }
    mark { 
      padding:.15em .35em; 
      border-radius:.3rem;
      background: linear-gradient(135deg, #fff3cd 0%, #ffc107 100%);
      font-weight: 600;
    }

    /* Search box enhancement */
    #faqSearch {
      border-radius: 8px;
      border: 2px solid #e9ecef;
      transition: all 0.3s ease;
    }
    #faqSearch:focus {
      border-color: var(--bs-primary);
      box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
    }

    /* Count badge */
    #faqCount {
      background: #eff6ff;
      color: #1d4ed8;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-weight: 600;
    }
  </style>
</head>
<body id="body-layout"
  data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
  data-menu-color="<?= h($_SESSION['theme.menu'] ?? 'light') ?>"
  data-layout="vertical" data-sidebar-size="default" class="loading">

<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid">

        <!-- Tajuk -->
        <div class="row mb-2"><div class="col-12">
          <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
            <h4 class="page-title"><i class="ri-questionnaire-line me-1"></i> <?= __('faq_title') ?: 'Soalan Lazim (FAQ)' ?></h4>
            <div class="page-title-right">
              <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><i class="ri-home-4-line align-middle me-1"></i> <?= __('sidebar_dashboard') ?></li>
                <li class="breadcrumb-item active"><?= __('faq_title') ?: 'Soalan Lazim (FAQ)' ?></li>
              </ol>
            </div>
          </div>
        </div></div>

        <!-- Intro -->
        <div class="row"><div class="col-12">
          <div class="card faq-hero-card">
            <div class="card-body text-center faq-lead text-white py-4">
              <div class="mb-3">
                <i class="ri-question-answer-line" style="font-size: 3rem; opacity: 0.9;"></i>
              </div>
              <h3 class="mt-1 mb-3 fw-bold"><?= h(__('faq_heading') ?: 'Soalan Lazim Sistem') ?></h3>
              <p class="mb-0" style="opacity: 0.95;">
                <?= h(__('faq_intro') ?: 'Rujuk panduan umum penggunaan sistem. Pilih kategori atau gunakan carian untuk jawapan yang berkaitan.') ?>
              </p>
            </div>
          </div>
        </div></div>

        <!-- Layout: Left Categories | Right Accordion -->
        <div class="row g-3">
          <!-- LEFT: Kategori -->
          <div class="col-xl-3">
            <div class="card faq-card">
              <div class="card-header bg-gradient bg-primary text-white py-3">
                <i class="ri-folder-2-line me-2"></i> <strong><?= h(__('faq_label_category') ?: 'Kategori') ?></strong>
              </div>
              <div class="card-body p-2 faq-cat">
                <div id="catList" class="list-group list-group-flush">
                  <?php foreach ($cats as $i => $c): ?>
                    <button type="button"
                            class="list-group-item list-group-item-action<?= $i===0 ? ' active':'' ?>"
                            data-cat="<?= h($c) ?>">
                      <?= h($c) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- RIGHT: Carian + Accordion -->
          <div class="col-xl-9">
            <div class="card faq-card">
              <div class="card-body">
                <!-- Carian -->
                <div class="row align-items-center g-2 mb-2">
                  <div class="col-md-8">
                    <div class="input-group">
                      <span class="input-group-text"><i class="ri-search-line"></i></span>
                      <input id="faqSearch" type="search" class="form-control"
                             placeholder="<?= h(__('faq_placeholder_cari') ?: 'Cari dalam kategori terpilih…') ?>"
                             autocomplete="off">
                    </div>
                  </div>
                  <div class="col-md-4 text-md-end">
                    <span id="faqCount" class="small faq-muted"></span>
                  </div>
                </div>

                <!-- Accordion -->
                <div id="faqContainer" class="accordion"></div>

                <div id="faqNoResult" class="alert alert-warning no-result mt-3">
                  <i class="ri-information-line me-1"></i> <?= __('faq_tiada_padamu') ?: 'Tiada padanan ditemui. Cuba kata kunci lain.' ?>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /.container-fluid -->
    </div><!-- /.content -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div><!-- /.content-page -->
</div><!-- /.wrapper -->

<?php
  $NEED_JQUERY     = true;
  $NEED_SWEETALERT = false;
  include __DIR__ . '/../includes/script.php';
?>

<script>
(function(){
  'use strict';

  /* ==========================================================
   *  FAQ dengan Kategori (Left) & Accordion (Right)
   *  - Data PHP → JSON (render client-side)
   *  - Penapisan ikut kategori + carian (di kategori aktif)
   *  - Highlight kata kunci dalam tajuk & jawapan
   * ========================================================== */

  // ---------- Data dari PHP ----------
  const FAQS = <?= json_encode($faqs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  const DEFAULT_CAT = <?= json_encode($defaultCat, JSON_UNESCAPED_UNICODE) ?>;

  // ---------- Elemen ----------
  const $  = (s, r=document)=> r.querySelector(s);
  const $$ = (s, r=document)=> Array.from(r.querySelectorAll(s));
  const catList      = $('#catList');
  const faqSearch    = $('#faqSearch');
  const faqContainer = $('#faqContainer');
  const noResult     = $('#faqNoResult');
  const faqCount     = $('#faqCount');

  // ---------- State ----------
  let activeCat = DEFAULT_CAT;     // "Semua" atau nama kategori lain
  let searchTerm = '';

  // ---------- Utils ----------
  const norm = s => String(s||'').toLowerCase();

  function makeId(prefix, idx){ return prefix + String(idx+1); }

  function escapeRe(s){ return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

  // Safely set innerHTML using DOMPurify when available, else DOMParser fallback
  function setSafeInnerHTML(el, html) {
    if (!el) return;
    if (!html) { el.innerHTML = ''; return; }
    if (window.DOMPurify && typeof DOMPurify.sanitize === 'function') {
      el.innerHTML = DOMPurify.sanitize(html);
      return;
    }
    try {
      const doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html');
      doc.querySelectorAll('script').forEach(s => s.remove());
      doc.querySelectorAll('*').forEach(n => {
        Array.from(n.attributes).forEach(a => {
          if (/^on/i.test(a.name)) n.removeAttribute(a.name);
          if ((a.name === 'src' || a.name === 'href') && /^javascript:/i.test(a.value)) n.removeAttribute(a.name);
        });
      });
      el.innerHTML = doc.body.firstChild ? doc.body.firstChild.innerHTML : '';
    } catch (e) {
      el.innerHTML = html;
    }
  }

  /** Highlight term dalam elemen (innerHTML). Simpan salinan asal pada data-raw. */
  function highlightIn(el, term){
    const t = norm(term).trim();
    const raw = el.getAttribute('data-raw') || el.innerHTML;
    if (!el.hasAttribute('data-raw')) el.setAttribute('data-raw', raw);
    if (!t) { setSafeInnerHTML(el, raw); return; }
    const rx = new RegExp('(' + escapeRe(t) + ')', 'ig');
    setSafeInnerHTML(el, raw.replace(rx, '<mark>$1</mark>'));
  }

  /** Kira & papar jumlah paparan */
  function updateCount(n, total){
    if (!faqCount) return;
    const countText = <?= json_encode(__('faq_count_display') ?: 'daripada', JSON_UNESCAPED_UNICODE) ?>;
    const soalanText = <?= json_encode(__('faq_count_soalan') ?: 'soalan dipaparkan', JSON_UNESCAPED_UNICODE) ?>;
    faqCount.textContent = n < total
      ? (n + ' ' + countText + ' ' + total + ' ' + soalanText)
      : (total + ' ' + soalanText);
  }

  // ---------- Render ----------
  /** Render accordion mengikut kategori aktif & carian */
  function renderFAQs(){
    const term = norm(searchTerm);
    const cat  = String(activeCat||'').trim();

    // Saring ikut kategori (gunakan translation untuk "Semua")
    const semuaText = <?= json_encode(__('faq_cat_semua') ?: 'Semua', JSON_UNESCAPED_UNICODE) ?>;
    let list = FAQS.filter(x => (cat === semuaText || x.cat === cat));

    // Saring ikut carian
    if (term){
      list = list.filter(x => {
        const hay = norm(x.q + ' ' + (x.a||'').replace(/<[^>]*>/g,'') + ' ' + (x.tags||''));
        return hay.indexOf(term) !== -1;
      });
    }

    // Kosongkan container
    faqContainer.innerHTML = '';

    if (!list.length){
      noResult.style.display = '';
      updateCount(0, 0);
      return;
    }
    noResult.style.display = 'none';
    updateCount(list.length, list.length);

    // Bina item accordion
    list.forEach((item, idx) => {
      const wrap  = document.createElement('div');
      wrap.className = 'accordion-item acc-item';
      wrap.dataset.cat  = item.cat;
      wrap.dataset.tags = norm(item.tags||'');

      const cid = makeId('faqC', idx);
      wrap.innerHTML = `
        <div class="accordion-header">
          <a href="#" class="accordion-button bg-light fw-medium text-dark" data-bs-toggle="collapse" data-bs-target="#${cid}" aria-expanded="false" aria-controls="${cid}">
            <span class="faq-q">${h(item.q)}</span>
          </a>
        </div>
        <div id="${cid}" class="collapse" data-bs-parent="#faqContainer">
          <div class="p-3">
            <div class="faq-a"><?= '' ?></div>
          </div>
        </div>
      `;

      // Masukkan jawapan sebagai HTML (dari server) dengan selamat (kandungan kita kawal)
      setSafeInnerHTML(wrap.querySelector('.faq-a'), item.a || '');

      // Highlight (tajuk & jawapan)
      highlightIn(wrap.querySelector('.accordion-button'), term);
      highlightIn(wrap.querySelector('.faq-a'), term);

      faqContainer.appendChild(wrap);
    });
  }

  // ---------- Interaksi ----------
  // Klik kategori (left)
  catList?.addEventListener('click', function(e){
    const btn = e.target.closest('[data-cat]');
    if (!btn) return;
    // Tukar aktif
    $$('.list-group-item', catList).forEach(x => x.classList.remove('active'));
    btn.classList.add('active');
    activeCat = btn.getAttribute('data-cat') || 'Semua';
    // Reset carian bila tukar kategori (pilihan: kekalkan — tukar ikut preferensi)
    // faqSearch.value = '';
    // searchTerm = '';
    renderFAQs();
  });

  // Carian (right)
  faqSearch?.addEventListener('input', function(){
    searchTerm = this.value || '';
    renderFAQs();
  });

  // ---------- Init ----------
  window.addEventListener('load', function(){
    // Preselect active cat dari butang yang ada kelas .active (fallback ke DEFAULT_CAT)
    const preset = $('[data-cat].active', catList);
    activeCat = preset ? (preset.getAttribute('data-cat') || DEFAULT_CAT) : DEFAULT_CAT;
    renderFAQs();
  });

  // ---------- Helper escape untuk template literal (PHP h() analog) ----------
  function h(s){
    return String(s ?? '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#39;');
  }
})();
</script>
</body>
</html>
