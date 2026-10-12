# Audit Naik Taraf IQS Framework ke PHP 8.4.26

Tarikh audit: 7 Oktober 2026

Repositori: `/var/www/iqs-framework`

Commit yang diaudit: `bc753b2`

Versi aplikasi: `1.10.0`

Skop sasaran: PHP 8.3.x kepada PHP 8.4.26

## 1. Ringkasan Eksekutif

IQS Framework boleh dinaik taraf kepada PHP 8.4.26 melalui rollout terkawal. Tiada fatal incompatibility, parse error, atau architectural blocker ditemui dalam kod milik aplikasi.

Walau bagaimanapun, pertukaran production tidak disyorkan dilakukan sebelum dua isu compatibility kecil dibetulkan:

1. penggunaan constant `E_STRICT` yang deprecated dalam PHP 8.4;
2. panggilan fungsi CSV tanpa parameter `escape` yang eksplisit.

TCPDF 6.4.4 pula ialah dependency lama yang mengandungi API deprecated. Ia masih berjaya menghasilkan PDF asas pada PHP 8.4.26, tetapi laluan PDF yang menggunakan remote image, cURL, XML, atau SVG perlu diuji atau library tersebut dinaik taraf.

Kesimpulan audit:

| Perkara | Keputusan |
|---|---|
| Keserasian syntax aplikasi | Lulus |
| Regression suite pada PHP 8.4.26 | Lulus |
| Login page melalui PHP 8.4.26 | HTTP 200 |
| Kesetaraan extension PHP 8.3/8.4 | Lulus |
| Blocker seni bina | Tiada |
| Pembaikan kod sebelum cutover | Ada, kecil |
| Boleh uji di staging PHP 8.4.26 | Ya |
| Sesuai terus tukar production tanpa pembaikan | Tidak disyorkan |

Tahap kesiapsiagaan keseluruhan: **baik, dengan risiko rendah hingga sederhana**.

## 2. Penjelasan Versi Runtime

Perancangan awal menyebut PHP 8.3.33. Pemeriksaan runtime semasa audit menunjukkan:

```text
PHP 8.3.35 (cli)
PHP 8.4.26 (cli)
```

Oleh itu, baseline sebenar pada host semasa ialah PHP 8.3.35. Kesimpulan compatibility tetap terpakai kepada downstream yang masih menggunakan PHP 8.3.33 kerana pembaikan yang dicadangkan tidak menggunakan syntax atau feature khusus PHP 8.4.

PHP 8.4.26 CLI dan PHP-FPM telah dipasang pada host. Kedua-dua servis PHP-FPM 8.3 dan 8.4 dilaporkan aktif dan enabled semasa audit.

## 3. Skop Audit

Audit meliputi:

- kod PHP milik aplikasi dalam `public/`, `tests/`, dan `tools/`;
- library bundled PHPMailer dan TCPDF;
- PHP CLI 8.3.35 dan PHP CLI 8.4.26;
- extension yang tersedia pada kedua-dua runtime;
- konfigurasi Nginx yang berkaitan dengan IQS Framework;
- semua regression test yang tersedia dalam `tests/`;
- HTTP rendering login page menggunakan PHP 8.4.26;
- API deprecated dan backward-incompatible changes utama PHP 8.4;
- keserasian kod bersama untuk downstream yang kekal pada PHP 8.3.x.

Audit ini bersifat read-only. Tiada fail aplikasi, konfigurasi Nginx, konfigurasi PHP-FPM, service, atau pangkalan data diubah.

## 4. Kaedah Audit

Kaedah yang digunakan:

1. Inventori runtime dan extension melalui `php8.3` dan `php8.4`.
2. Perbandingan module list antara kedua-dua runtime.
3. PHP lint dengan PHP 8.4.26 terhadap kod aplikasi dan vendor bundle.
4. Carian statik untuk API deprecated, removed API, implicit nullability, CSV behavior, MySQLi, session, cURL, XML, DOM, Reflection, dan serialization.
5. Pelaksanaan regression suite dengan `error_reporting=E_ALL` pada PHP 8.4.26.
6. Smoke test PHPMailer MIME generation.
7. Smoke test TCPDF basic PDF generation.
8. HTTP request terhadap login page melalui PHP 8.4.26 built-in server.
9. Pemeriksaan PHP-FPM service, Nginx socket, ODBC, PDO DBLIB, dan PDO drivers.
10. Semakan audit terdahulu `docs/php84-readiness-audit-2026-04-23.md` terhadap keadaan release 1.10.0 semasa.

## 5. Keputusan Syntax dan Regression

### 5.1 PHP lint

Keputusan PHP 8.4.26 lint:

| Kumpulan | Bilangan fail | Keputusan |
|---|---:|---|
| Kod aplikasi/test/tool | 313 | Lulus |
| PHPMailer dan TCPDF | 295 | Lulus |

Tiada parse error atau fatal compilation error ditemui.

Lint sahaja tidak menjamin semua behavior runtime kerana function body tertentu hanya menghasilkan deprecation apabila dipanggil. Oleh itu, regression dan runtime probes turut dijalankan.

### 5.2 Regression suite

Semua regression suite sedia ada dijalankan menggunakan PHP 8.4.26 dengan `E_ALL`:

| Suite | Keputusan |
|---|---|
| Additional database regression | 36/36 lulus |
| OneID SSO flow regression | 34/34 lulus |
| Password reset flow regression | Lulus |
| Tetapan sistem save-sync regression | Lulus |
| Custom theme regression | Lulus |

Ini memberikan keyakinan terhadap database configuration, MySQL/Sybase driver resolution, encryption envelope, SSO identity handling, password recovery, system configuration, dan custom theme behavior.

### 5.3 HTTP login smoke test

Login page dijalankan terus melalui PHP 8.4.26 dan memberi respons:

```text
HTTP/1.1 200 OK
X-Powered-By: PHP/8.4.26
```

HTML login page berjaya dirender. Satu deprecation daripada `E_STRICT` direkodkan dalam server log, seperti dihuraikan dalam penemuan utama.

## 6. Kesetaraan Extension PHP

Module list PHP 8.3.35 dan PHP 8.4.26 adalah setara pada host audit. Extension kritikal yang tersedia termasuk:

- Core, PDO dan mysqlnd;
- `pdo_mysql` dan `mysqli`;
- `odbc` dan `PDO_ODBC`;
- `pdo_dblib` dengan FreeTDS;
- `curl` dan `openssl`;
- `mbstring`, `intl`, `iconv`, dan `gettext`;
- `dom`, `SimpleXML`, `xml`, `xmlreader`, `xmlwriter`, dan `xsl`;
- `gd`, `fileinfo`, `zip`, dan `zlib`;
- `session`, `sodium`, dan `Zend OPcache`.

PDO drivers PHP 8.4.26 yang dilaporkan:

```text
dblib, mysql, odbc
```

FreeTDS driver juga tersedia melalui unixODBC. Ini bermaksud tiada jurang extension yang jelas antara runtime 8.3 dan 8.4 pada host semasa.

Walau bagaimanapun, kewujudan extension tidak membuktikan credential, DSN, firewall, TLS, atau query production berfungsi. Sambungan MySQL dan Sybase masih perlu diuji end-to-end dalam pool PHP-FPM 8.4 sebenar.

## 7. Penemuan Utama

### 7.1 `E_STRICT` deprecated dalam PHP 8.4

Lokasi:

```text
public/includes/init.php:597
```

Kod semasa:

```php
error_reporting($__IS_DEV ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);
```

PHP 8.4 telah membuang error level `E_STRICT` dan mendeprecate constant tersebut. Runtime probe dan HTTP login smoke test menghasilkan:

```text
PHP Deprecated: Constant E_STRICT is deprecated
```

Impak:

- aplikasi masih boleh berjalan;
- production biasanya tidak memaparkan mesej kerana `display_errors` dimatikan;
- log PHP akan menerima deprecation pada request yang memuatkan `init.php`;
- volume log boleh meningkat dengan cepat.

Pembaikan yang dicadangkan:

```php
error_reporting($__IS_DEV ? E_ALL : E_ALL & ~E_DEPRECATED);
```

Keutamaan: **wajib sebelum production cutover**.

Keserasian PHP 8.3: pembaikan ini selamat pada PHP 8.3.33, 8.3.35, dan PHP 8.4.26.

### 7.2 Parameter `escape` CSV perlu eksplisit

Lokasi milik aplikasi:

```text
public/classes/AuditLogger.php:50
public/ajax/audit-center-export.php:177
public/ajax/audit-center-export.php:178
public/ajax/audit-center-export.php:183
public/ajax/audit-center-export.php:197
```

Fungsi terlibat:

- `str_getcsv()`;
- `fputcsv()`.

PHP 8.4 mendeprecate penggunaan default parameter `escape` kerana default tersebut akan berubah pada versi akan datang. Runtime probe mengesahkan kedua-dua fungsi mengeluarkan `E_DEPRECATED` jika parameter tidak diberi.

Impak:

- eksport CSV masih berfungsi pada PHP 8.4;
- deprecation akan masuk ke log semasa Audit Center export;
- behavior escaping tidak dinyatakan dengan jelas untuk versi PHP akan datang.

Cadangan:

- tambah parameter `escape` secara eksplisit;
- gunakan `''` jika format CSV tidak memerlukan proprietary backslash escaping;
- sahkan fail eksport boleh dibuka dengan Excel/LibreOffice dan kandungan quote, comma, newline, serta apostrophe kekal betul.

Keutamaan: **wajib sebelum production cutover**.

Keserasian PHP 8.3: perubahan ini selamat pada PHP 8.3.x dan PHP 8.4.x.

### 7.3 TCPDF 6.4.4 ialah compatibility watchpoint

Versi bundled:

```text
tecnickcom/tcpdf 6.4.4
```

Penggunaan deprecated yang ditemui dalam bundle:

```text
CURLOPT_BINARYTRANSFER
xml_set_object()
```

Basic PDF smoke test berjaya pada PHP 8.4.26 dan menghasilkan dokumen dalam memory. Namun ujian asas tidak melalui semua cabang TCPDF.

Risiko tertumpu pada:

- remote image atau resource melalui cURL;
- XML/SVG parsing;
- font conversion;
- digital signature;
- barcode dan advanced PDF features.

Carian kod aplikasi tidak menemukan penggunaan aktif `new TCPDF` di luar bundle vendor. Oleh itu, TCPDF bukan blocker langsung bagi IQS Framework core semasa. Downstream mungkin mempunyai penggunaan sendiri dan mesti diperiksa secara berasingan.

Cadangan:

1. inventori penggunaan TCPDF dalam setiap downstream;
2. naik taraf kepada release TCPDF yang masih menyokong PHP 8.3 dan PHP 8.4; atau
3. jika upgrade dependency ditangguhkan, smoke-test semua laluan PDF sebenar dan pantau deprecation log.

Keutamaan: **tinggi jika PDF digunakan; sederhana jika bundle dormant**.

### 7.4 PHPMailer 6.10.0

Versi bundled:

```text
PHPMailer 6.10.0
```

PHPMailer lint dan basic MIME-generation smoke test berjaya pada PHP 8.4.26 tanpa deprecation yang dikesan.

Risiko sebenar untuk mail lebih berkait dengan:

- SMTP authentication;
- TLS certificate negotiation;
- provider policy;
- DNS/network/firewall;
- sender domain configuration.

Oleh itu, penghantaran email sebenar masih perlu menjadi acceptance test wajib walaupun library-level smoke test lulus.

### 7.5 Tiada root dependency manifest

Repositori tidak mempunyai root `composer.json` atau `composer.lock`. PHPMailer dan TCPDF disimpan sebagai bundled source.

Impak:

- `composer check-platform-reqs` tidak boleh digunakan;
- dependency version tidak dikunci melalui satu manifest pusat;
- security dan compatibility upgrade perlu diurus secara manual;
- sukar memastikan downstream membawa library version yang sama.

Ini bukan blocker PHP 8.4, tetapi merupakan technical debt yang patut dimasukkan dalam roadmap dependency management.

## 8. Perubahan PHP 8.4 yang Disemak

Tiada penggunaan berisiko ditemui dalam kod aplikasi bagi kategori berikut:

- implicitly nullable typed parameters;
- `mysqli_ping()`, `mysqli_kill()`, dan `mysqli_refresh()`;
- explicit mode lama pada `mysqli_store_result()`;
- `session_set_save_handler()` signature lebih daripada dua parameter;
- one-argument `ReflectionMethod::__construct()` yang deprecated;
- deprecated `DatePeriod` ISO constructor;
- `trigger_error(..., E_USER_ERROR)`;
- `stream_context_set_option()` signature dua argumen;
- `xml_set_object()` dalam kod aplikasi;
- `CURLOPT_BINARYTRANSFER` dalam kod aplikasi;
- `DOM_PHP_ERR`;
- `SOAP_FUNCTIONS_ALL`;
- `lcg_value()`;
- legacy `Serializable` implementation;
- `utf8_encode()` dan `utf8_decode()`;
- `create_function()`;
- removed MySQLi constants;
- invalid use of `exit()` atau `die()`;
- indirect readonly-property modification dalam `__clone()`.

Perubahan PDO MySQL boolean attributes tidak menunjukkan incompatibility dalam regression suite, tetapi database connection behavior perlu tetap diuji melalui PHP-FPM 8.4.

## 9. Nginx dan PHP-FPM

Konfigurasi Nginx IQS Framework masih menunjuk kepada:

```text
/run/php/php8.3-fpm.sock
```

Lokasi berkaitan ditemui dalam:

```text
/etc/nginx/sites-available/iqs-framework
```

PHP 8.4.26 sudah dipasang dan servis `php8.4-fpm` aktif, tetapi IQS Framework belum menggunakan pool tersebut melalui Nginx.

Perubahan Nginx kepada socket 8.4 mesti dianggap sebagai deployment change, bukan code change. Sebelum reload:

1. sahkan pool/socket PHP 8.4 wujud dan boleh diakses oleh Nginx;
2. semak ownership dan permission socket;
3. pastikan FPM 8.4 mempunyai environment variables yang sama;
4. jalankan `nginx -t`;
5. reload, bukan restart keras, jika sesuai;
6. semak `PHP_VERSION` melalui request web sebenar;
7. simpan konfigurasi socket 8.3 sebagai rollback.

Percubaan `php-fpm8.3 -t` dan `php-fpm8.4 -t` daripada akaun audit tidak dapat menyelesaikan post-processing kerana akaun tersebut tidak mempunyai permission menulis/membuka fail log FPM di `/var/log`. Ini bukan bukti konfigurasi FPM rosak; kedua-dua servis dilaporkan aktif. Config test rasmi perlu dijalankan dengan authority sistem yang sesuai semasa deployment.

## 10. Keserasian Downstream PHP 8.3

IQS Framework boleh berjalan pada PHP 8.4.26 sementara downstream kekal pada PHP 8.3.33 atau PHP 8.3.35, dengan syarat shared code mengekalkan syntax baseline PHP 8.3.

Model peralihan yang disokong:

```text
iqs-framework -> PHP 8.4.26
downstream    -> PHP 8.3.x
shared code   -> syntax dan API yang serasi PHP 8.3 + PHP 8.4
```

Pembaikan `E_STRICT` dan CSV adalah dual-runtime compatible. Ia boleh disync ke downstream sebelum downstream menukar runtime.

Sepanjang tempoh mixed runtime, jangan masukkan feature eksklusif PHP 8.4 ke core shared code, termasuk syntax atau behavior yang tidak boleh diparse oleh PHP 8.3. Contohnya, elakkan menggunakan property hooks atau asymmetrical property visibility dalam fail yang akan disync kepada downstream PHP 8.3.

Cadangan compatibility policy sementara:

- minimum runtime: PHP 8.3;
- tested runtime: PHP 8.3.33/8.3.35 dan PHP 8.4.26;
- shared code mesti lulus lint menggunakan kedua-dua interpreter;
- CI/regression perlu dijalankan pada kedua-dua runtime sehingga semua downstream selesai migrasi.

## 11. Risiko Mengikut Komponen

| Komponen | Risiko kod | Risiko operasi | Catatan |
|---|---|---|---|
| Bootstrap/login page | Rendah | Rendah | HTTP 200 pada PHP 8.4.26 |
| Session/auth | Rendah | Sederhana | Perlu login/logout browser test |
| OneID SSO | Rendah | Tinggi | Kod regression lulus; external handoff wajib diuji |
| Password recovery | Rendah | Sederhana | Regression lulus; email delivery wajib diuji |
| PHPMailer | Rendah | Sederhana | MIME smoke test lulus; SMTP sebenar belum diuji |
| MySQL | Rendah | Sederhana | Driver tersedia; transaction/query perlu diuji |
| Sybase DBLIB | Rendah | Tinggi | Driver tersedia; network dan query sebenar perlu diuji |
| Sybase ODBC | Rendah | Tinggi | Driver tersedia; DSN dan driver behavior perlu diuji |
| Audit CSV | Sederhana | Rendah | Menghasilkan deprecation sehingga dibaiki |
| TCPDF | Sederhana | Sederhana/Tinggi | Bergantung kepada penggunaan downstream |
| Upload/manual | Rendah | Sederhana | Temp filename berubah; test upload/download diperlukan |
| OPcache | Rendah | Sederhana | Perlu reset/restart FPM selepas cutover |

## 12. Pembaikan Minimum Sebelum Cutover

### Wajib

- buang penggunaan `E_STRICT` dalam runtime error reporting;
- tambah parameter `escape` eksplisit bagi semua penggunaan `fputcsv()` dan `str_getcsv()`;
- tambah regression assertion supaya penggunaan tersebut tidak kembali;
- lint kod menggunakan PHP 8.3 dan PHP 8.4;
- jalankan regression suite menggunakan kedua-dua runtime.

### Sangat disyorkan

- inventori TCPDF dalam semua downstream;
- uji penghantaran SMTP sebenar;
- uji MySQL dan Sybase menggunakan PHP-FPM 8.4;
- aktifkan temporary PHP 8.4 staging pool dengan `E_ALL` logging;
- pantau error log untuk `Deprecated`, `TypeError`, `ValueError`, dan fatal errors;
- kemas kini README runtime baseline hanya selepas cutover disahkan.

### Boleh dibuat kemudian

- wujudkan root Composer dependency management;
- tambah automated PHP compatibility scanner;
- bina CI matrix PHP 8.3/8.4;
- keluarkan bundled vendor examples/tools daripada deployment jika tidak diperlukan.

## 13. Pelan Pelaksanaan Disyorkan

### Fasa 1: Remediasi kod dual-runtime

1. Baiki `E_STRICT`.
2. Baiki CSV escape parameter.
3. Tambah regression coverage.
4. Jalankan PHP 8.3 dan PHP 8.4 lint/test.
5. Release dan sync perubahan ke downstream semasa downstream masih PHP 8.3.

Expected result: satu codebase yang bersih pada kedua-dua runtime.

### Fasa 2: IQS Framework staging pada PHP 8.4

1. Sediakan pool/socket PHP 8.4 khusus.
2. Arahkan hanya IQS Framework staging kepada socket tersebut.
3. Jangan ubah downstream.
4. Jalankan acceptance checklist penuh.
5. Pantau log sekurang-kurangnya sepanjang satu kitaran ujian operasi.

### Fasa 3: IQS Framework production cutover

1. Ambil salinan konfigurasi Nginx dan PHP-FPM.
2. Pastikan tiada deployment lain sedang berjalan.
3. Tukar `fastcgi_pass` IQS Framework kepada socket PHP 8.4.
4. Jalankan config test.
5. Reload Nginx dan reset/restart pool yang berkaitan.
6. Jalankan immediate smoke test.
7. Pantau log dan rollback jika acceptance gagal.

### Fasa 4: Downstream berperingkat

Untuk setiap downstream:

1. pastikan repo bersih dan sepadan dengan remote;
2. pastikan compatibility fixes telah disync;
3. audit project-specific dependencies dan custom PHP code;
4. lint/test dengan PHP 8.4;
5. tukar satu project pada satu masa;
6. pantau sebelum bergerak ke project berikutnya.

## 14. Acceptance Checklist PHP 8.4

### Runtime

- [ ] Web request melaporkan PHP 8.4.26.
- [ ] Nginx config test lulus.
- [ ] PHP-FPM 8.4 pool aktif dan socket boleh dicapai.
- [ ] Extension list sama dengan baseline.
- [ ] OPcache aktif.
- [ ] Tiada deprecation daripada core application bootstrap.

### Authentication

- [ ] Login manual berjaya.
- [ ] Login gagal memberi feedback yang betul.
- [ ] Logout memusnahkan session dengan betul.
- [ ] Session timeout berfungsi.
- [ ] Role/group access kekal betul.
- [ ] View As/impersonation berfungsi jika digunakan.

### OneID SSO

- [ ] Staff SSO berjaya.
- [ ] Student SSO berjaya.
- [ ] Existing linked account berjaya.
- [ ] Invalid token menghasilkan controlled failure.
- [ ] SSO callback tidak loop.
- [ ] TLS verification kekal aktif.
- [ ] Log tidak merekod token sensitif.

### Password dan email

- [ ] Forgot password manual account berjaya.
- [ ] SSO-managed account kekal tidak menggunakan local reset.
- [ ] Reset token valid berjaya.
- [ ] Expired/invalid token ditolak.
- [ ] Forced password change berjaya.
- [ ] SMTP test email diterima.
- [ ] Email template rendering kekal betul.

### Database

- [ ] MySQL connection berjaya.
- [ ] MySQL read/write transaction berjaya.
- [ ] Sybase DBLIB connection berjaya jika digunakan.
- [ ] Sybase ODBC connection berjaya jika digunakan.
- [ ] Additional database inspection berjaya.
- [ ] Error redaction tidak mendedahkan credential.

### UI dan file flow

- [ ] Dashboard dan System Settings dimuatkan.
- [ ] Custom theme save/load berfungsi.
- [ ] Language switch berfungsi.
- [ ] File upload berjaya.
- [ ] Manual view/download berjaya.
- [ ] Audit CSV export berjaya tanpa deprecation.
- [ ] PDF generation berjaya bagi setiap use case sebenar.

### Logging

- [ ] Tiada `Fatal error`.
- [ ] Tiada unexpected `TypeError` atau `ValueError`.
- [ ] Tiada app-owned PHP 8.4 deprecation.
- [ ] Tiada SMTP/TLS regression.
- [ ] Tiada DB driver atau DSN regression.

## 15. Rollback Plan

Rollback perlu tersedia sebelum cutover:

1. simpan salinan Nginx site config asal;
2. kekalkan PHP 8.3-FPM aktif sepanjang observation window;
3. jika berlaku regression, pulihkan `fastcgi_pass` kepada `/run/php/php8.3-fpm.sock`;
4. jalankan `nginx -t`;
5. reload Nginx;
6. reset OPcache/restart pool jika perlu;
7. sahkan `PHP_VERSION` kembali kepada 8.3.x;
8. jalankan login, database, dan email smoke test;
9. simpan log PHP 8.4 untuk root-cause analysis.

Database rollback tidak sepatutnya diperlukan jika upgrade runtime tidak disertai schema migration. Elakkan menggabungkan PHP cutover dengan perubahan schema atau release feature besar supaya punca kegagalan mudah dikenal pasti.

## 16. Go/No-Go Criteria

### Go untuk staging

- dua pembaikan wajib selesai;
- PHP 8.3 dan PHP 8.4 lint lulus;
- regression suite lulus pada kedua-dua runtime;
- pool PHP 8.4 mempunyai extension parity;
- rollback socket PHP 8.3 tersedia.

### Go untuk production

- staging acceptance checklist lengkap;
- tiada app-owned deprecation dalam log;
- SMTP, SSO, MySQL dan Sybase sebenar disahkan;
- semua PDF flow yang digunakan disahkan;
- config test dan rollback rehearsal berjaya;
- observation window staging mencukupi.

### No-Go

- fatal error, unexplained TypeError atau ValueError;
- missing extension atau driver;
- SSO callback failure/loop;
- SMTP atau password recovery gagal;
- MySQL/Sybase connection regression;
- PDF business flow gagal;
- log dipenuhi deprecation aplikasi;
- tiada rollback yang telah disahkan.

## 17. Rujukan

Rujukan rasmi PHP:

- PHP 8.4 migration guide: <https://www.php.net/manual/en/migration84.php>
- PHP 8.4 backward-incompatible changes: <https://www.php.net/manual/en/migration84.incompatible.php>
- PHP 8.4 deprecated features: <https://www.php.net/manual/en/migration84.deprecated.php>
- PHP 8.4 release overview: <https://www.php.net/releases/8.4/en.php>

Rujukan dalaman:

- `docs/php84-readiness-audit-2026-04-23.md` — audit sejarah sebelum runtime berpindah daripada Docker/Apache kepada WSL/Nginx/PHP-FPM.
- `README.md` — baseline runtime dan extension semasa.
- `tests/` — regression suite yang digunakan dalam audit.

## 18. Keputusan Akhir

IQS Framework release 1.10.0 secara asasnya serasi dengan PHP 8.4.26. Bukti syntax, regression, extension parity, library smoke test, dan HTTP rendering menyokong permulaan controlled staging migration.

Production cutover tidak patut dibuat secara terus dalam keadaan kod semasa kerana `E_STRICT` dan CSV calls akan menghasilkan deprecation. Kedua-duanya ialah pembaikan kecil dan backward-compatible dengan PHP 8.3, maka ia patut diselesaikan dan disync kepada downstream sebelum migration runtime dilakukan.

Selepas dua pembaikan tersebut, risiko terbesar bukan lagi syntax PHP tetapi integrasi operasi: SMTP, OneID, MySQL, Sybase, file handling, dan mana-mana PDF flow downstream. Upgrade terbaik ialah mengekalkan shared code pada baseline PHP 8.3-compatible, menjalankan IQS Framework dahulu pada PHP 8.4.26, kemudian memigrasikan downstream satu per satu selepas project-specific verification.
