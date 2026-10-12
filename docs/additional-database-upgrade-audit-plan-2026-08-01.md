# Additional Database Upgrade Audit and Plan

Tarikh audit: `2026-08-01`
Projek utama: `iqs-framework`
Versi ketika audit: `1.8.5`
Status dokumen: Audit dan perancangan sebelum pelaksanaan

## 1. Tujuan

Dokumen ini merekodkan:

- fungsi semasa platform Additional Database;
- kontrak runtime yang telah digunakan oleh projek downstream;
- dapatan teknikal dan keselamatan;
- risiko perubahan core kepada downstream;
- hasil semakan read-only terhadap `e-hepa-uat` sebagai projek downstream rujukan;
- pelan naik taraf berperingkat tanpa memerlukan perubahan kod downstream;
- syarat ujian, deployment, rollback, dan penerimaan bagi setiap release.

Dokumen ini tidak menandakan bahawa naik taraf telah dilaksanakan. Pada tarikh audit, tiada perubahan database, konfigurasi runtime, credential, atau kod Additional Database dibuat.

## 2. Skop Audit

Audit utama meliputi implementasi Additional Database dalam IQS Framework, khususnya:

- `public/classes/Database.php`
- `public/classes/DatabaseManager.php`
- `public/classes/DatabaseRuntimeConfig.php`
- `public/classes/DatabaseConnectionDefinition.php`
- `public/classes/DatabaseConnectionRegistry.php`
- `public/classes/DatabaseConnectionResolver.php`
- `public/classes/DatabaseConnectionFactory.php`
- `public/classes/DatabaseConnectionRepository.php`
- `public/classes/DatabaseConnectionValidator.php`
- `public/controllers/TetapanSistemController.php`
- `public/pages/tetapan-sistem.php`
- `public/pages/partials/tetapan-sistem/tab-database.php`
- `public/assets/js/pages/tetapan-sistem.js`
- `public/setting/helper/security_helper.php`
- dokumentasi Additional Database sedia ada.

Satu audit read-only berasingan telah dibenarkan terhadap `e-hepa-uat` untuk menilai kesan downstream. Tiada fail, database, konfigurasi, atau operasi Git yang mengubah state dilakukan dalam audit tersebut.

## 3. Fungsi Semasa Additional Database

Additional Database ialah registry sambungan database sokongan yang diurus secara berasingan daripada database utama sistem.

Kegunaan yang disasarkan:

- reporting;
- lookup atau reference data;
- integrasi dengan sistem luar;
- batch dan data synchronization;
- transaksi sokongan yang tidak patut digabungkan dengan main runtime.

Ia tidak sepatutnya digunakan untuk menggantikan:

- Main MySQL;
- Sybase staff;
- Sybase student;
- login, authorization, atau bootstrap utama aplikasi.

### 3.1 Public Runtime API

API utama untuk programmer ialah:

```php
$pdo = Database::pdoAdditional('dbx_code', 'production');
```

Kontrak semasa:

```php
Database::pdoAdditional(
    string $code,
    ?string $environment = null
): PDO
```

API sokongan dalaman yang turut tersedia:

```php
$pdo = (new DatabaseManager())->additional($code, $environment);
```

Programmer downstream sepatutnya menggunakan public API dan tidak:

- membina DSN sendiri;
- hardcode host, username, atau password;
- membaca jadual registry secara terus;
- memanggil repository, resolver, atau factory dalaman secara terus.

### 3.2 Registry Database

Registry menggunakan:

- `tbl_m_db_connection` untuk metadata connection;
- `tbl_m_db_connection_env` untuk target environment, OS, driver, host, credential, dan options;
- `tbl_a_db_connection_test` sebagai audit test connection yang bersifat optional dalam implementasi semasa.

Satu connection boleh mempunyai beberapa env row berdasarkan kombinasi:

- `production` atau `development`;
- `any`, `windows`, atau `linux`;
- driver yang sesuai dengan family database.

Family dan driver yang disokong:

| Family | Driver |
|---|---|
| MySQL | `mysql` |
| Sybase | `odbc`, `dblib` |
| MSSQL | `sqlsrv`, `odbc`, `dblib` |

### 3.3 Resolver dan Connection Factory

Resolver memilih konfigurasi berdasarkan:

1. connection code;
2. status enabled;
3. environment yang diminta atau environment default;
4. OS runtime;
5. driver mode;
6. driver PDO yang tersedia.

Factory kemudian membina PDO dengan:

- exception error mode;
- associative fetch mode;
- native prepares jika disokong;
- pilihan khusus MySQL, ODBC, DBLIB, atau SQL Server.

PDO yang berjaya dibina dicache mengikut resolved key untuk runtime/request yang sama.

### 3.4 Fungsi Pentadbiran

Tetapan Sistem menyediakan:

- senarai dan carian connection;
- create dan update metadata;
- enable atau disable;
- konfigurasi production/development;
- konfigurasi driver dan OS;
- Test Connection menggunakan `SELECT 1`;
- Inspect runtime;
- Schema Preview;
- Data Preview maksimum 20 row;
- sample code untuk programmer.

Tiada fungsi delete connection ditemui. Connection boleh dinyahaktifkan tetapi tidak dipadam melalui UI semasa.

## 4. Kawalan Keselamatan Semasa

Kawalan yang telah tersedia:

- halaman dan action Tetapan Sistem dihadkan kepada Super Admin;
- action POST/AJAX menjalankan validasi CSRF;
- reserved connection codes tidak boleh digunakan sebagai additional connection;
- connection disabled tidak boleh ditest, inspect, atau preview;
- payload divalidasi mengikut family, driver, OS, environment, port, dan username;
- password tidak dipulangkan dalam senarai biasa;
- nama object untuk Data Preview divalidasi dan di-quote;
- kegagalan registry tidak sepatutnya menjatuhkan main runtime;
- create, update, toggle, test, inspect, dan preview mempunyai audit action.

## 5. Dapatan Audit

### 5.1 Platform Telah Digunakan Oleh Downstream

Dokumentasi lama menyatakan platform belum digunakan oleh modul perniagaan. Audit downstream membuktikan kenyataan itu tidak lagi tepat secara keseluruhan. Sekurang-kurangnya `e-hepa-uat` menggunakan Additional Database dalam modul sebenar.

Implikasi:

- Additional Database kini merupakan public compatibility surface;
- perubahan core perlu dianggap sebagai perubahan library yang digunakan production;
- public API, resolver behavior, schema, driver, dan credential format tidak boleh diubah secara breaking.

### 5.2 Encryption Credential Perlu Diperkukuh

Implementasi semasa menggunakan `AES-256-ECB` dengan key yang hardcoded dalam `security_helper.php`.

Risiko:

- ECB bukan authenticated encryption;
- key disimpan bersama source code;
- ciphertext tidak mempunyai version atau key identifier;
- perubahan cipher atau key secara terus akan menyebabkan credential lama gagal dibaca;
- repository semasa mempunyai fallback yang boleh mengembalikan nilai asal jika proses encode/decode tidak menghasilkan nilai yang dijangka.

Keperluan naik taraf:

- format credential versioned;
- authenticated encryption;
- key daripada environment deployment;
- dual-read bagi format legacy dan baharu;
- tiada plaintext fallback;
- migrasi credential dilakukan secara terkawal, bukan automatik ketika page dibuka.

### 5.3 Migration Registry Tidak Lengkap Dalam Repository

Dokumentasi merujuk:

```text
updates/20260426_database_connection_registry.sql
```

Fail tersebut tidak ditemui ketika audit. Definisi `CREATE TABLE` yang lengkap bagi registry juga tidak ditemui dalam repository.

Implikasi:

- deployment baharu tidak mempunyai source-of-truth migration yang jelas;
- schema downstream mungkin berbeza antara deployment;
- core baharu boleh gagal membaca registry jika mengharapkan column yang belum tersedia.

Migration yang dipulihkan mesti additive, idempotent, dan tidak memadam data sedia ada.

### 5.4 Data Preview Boleh Mendedahkan Data Sensitif

Data Preview menjalankan query read-only tetapi menggunakan `SELECT *` sehingga 20 row.

Risiko:

- password, token, secret, nombor pengenalan, data peribadi, atau binary boleh dipaparkan;
- tiada masking column sensitif ditemui;
- keselamatan bergantung kepada akses Super Admin dan permission credential database.

Naik taraf perlu menambah masking, truncation, binary handling, timeout, dan pilihan untuk mematikan preview bagi connection tertentu.

### 5.5 Permission Database Tidak Dihadkan Oleh Registry

Public API memulangkan PDO penuh. Jika credential mempunyai permission write, modul downstream boleh menjalankan `INSERT`, `UPDATE`, atau `DELETE`.

Registry tidak boleh menjamin akaun read-only. Oleh itu:

- credential reporting/lookup patut menggunakan user read-only;
- hak write hanya diberi untuk use-case yang diluluskan;
- diagnostics boleh memberi amaran tetapi tidak boleh menganggap semua connection read-only.

### 5.6 Failure Isolation Utama Adalah Baik

`DatabaseManager` menangkap kegagalan ketika bootstrap registry dan mengekalkan main runtime. Ini melindungi login dan bootstrap daripada kerosakan Additional Database.

Walau bagaimanapun, feature downstream yang membuka Additional Database dalam constructor masih boleh gagal sepenuhnya jika connection tidak dapat di-resolve.

### 5.7 Tiada Automated Compatibility Coverage Yang Jelas

Audit tidak menemui test suite khusus yang mencukupi untuk melindungi:

- public API;
- environment resolution;
- OS/driver selection;
- registry failure isolation;
- legacy credential compatibility;
- preview safety;
- schema compatibility.

Automated regression tests perlu dibuat sebelum perubahan keselamatan atau schema.

## 6. Audit Downstream Rujukan: e-HEPA

Audit read-only terhadap `e-hepa-uat` mendapati penggunaan langsung berikut.

### 6.1 Connection Yang Digunakan

| Connection code | Environment | Kegunaan |
|---|---|---|
| `dbx_mysql_istaddb` | `production` | Data ISTAD seperti kegiatan, jawatan, program, dan rekod berkaitan |
| `dbx_sybase_asramadb` | `production` | Maklumat penginapan pelajar |

### 6.2 Direct Consumer

Empat controller menggunakan public API:

- `public/controllers/ListMohoniStarFacController.php`
- `public/controllers/PenglibatanTNCController.php`
- `public/controllers/PenglibatanController.php`
- `public/controllers/PeribadiController.php`

Corak penggunaan:

```php
$pdo = Database::pdoAdditional('connection_code', 'production');
```

Dapatan positif:

- environment ditetapkan secara eksplisit;
- return value digunakan sebagai PDO;
- tiada pembacaan registry table secara terus oleh application code;
- tiada penggunaan resolver, repository, atau factory dalaman oleh application code;
- tiada DSN atau credential yang dibina sendiri;
- sembilan fail core Additional Database yang dibandingkan adalah sama dengan IQS Framework versi `1.8.5`;
- tiada customization core khusus e-HEPA ditemui pada fail yang dibandingkan.

### 6.3 Risiko Kepada e-HEPA

Connection dibuka dalam constructor controller tanpa isolation di sekeliling panggilan `pdoAdditional()`.

Jika registry, driver, environment, atau credential gagal selepas upgrade, page berkaitan boleh gagal sebelum model digunakan. Feature yang berisiko:

- modul Penglibatan;
- modul Penglibatan TNC;
- senarai permohonan iStar berkaitan;
- maklumat penginapan dalam profil/peribadi.

Kesimpulan e-HEPA:

- dependency langsung: ya;
- perubahan kod downstream diperlukan: tidak, jika core kekal backward-compatible;
- migration/config deployment mungkin diperlukan;
- smoke test terhadap dua connection code wajib sebelum rollout lebih luas.

## 7. Kontrak Compatibility Yang Mesti Dikunci

Perkara berikut dianggap public contract mulai sekarang:

1. Method ini kekal tersedia:

   ```php
   Database::pdoAdditional(string $code, ?string $environment = null): PDO
   ```

2. Return type kekal `PDO`.
3. Parameter environment kekal optional.
4. Nilai `production` dan `development` dikekalkan.
5. Default environment behavior semasa tidak berubah tanpa migration path.
6. Connection code sedia ada tidak dinamakan semula secara automatik.
7. Driver sedia ada tidak dibuang dalam release naik taraf ini.
8. Schema lama kekal boleh dibaca sepanjang tempoh migration.
9. Ciphertext legacy kekal boleh dibaca sepanjang tempoh migration.
10. Additional Database tidak menjadi dependency kepada login atau bootstrap.
11. Exception baharu, jika diperkenalkan, mesti kekal boleh ditangkap sebagai `RuntimeException`.

## 8. Pelan Naik Taraf

### Fasa 1: Dokumentasi Kontrak

Skop:

- isytiharkan public API dan behavior yang dilindungi;
- dokumentasikan responsibility antara core dan downstream;
- kemas kini kenyataan lama bahawa Additional Database belum digunakan oleh modul sebenar.

Kesan database: tiada.
Kesan kod downstream: tiada.

Cadangan commit:

```text
docs: dokumentasikan kontrak additional database
```

### Fasa 2: Automated Regression Tests

Tambah ujian bagi:

- signature dan return contract public API;
- explicit dan default environment;
- production/development resolution;
- MySQL, Sybase, dan MSSQL driver selection;
- Windows/Linux resolution;
- enabled/disabled/not-found behavior;
- registry tidak tersedia tanpa menjatuhkan main runtime;
- PDO cache;
- legacy credential read;
- reserved code validation;
- schema dan data preview safety;
- payload lama kekal diterima.

Gunakan fake/mock factory untuk unit test. Live database connection diletakkan dalam smoke test berasingan.

Kesan database: tiada.
Kesan kod downstream: tiada.

Cadangan commit:

```text
test: tambah ujian regresi additional database
```

### Fasa 3: Migration Registry Idempotent

Sediakan source-of-truth migration bagi:

- `tbl_m_db_connection`;
- `tbl_m_db_connection_env`;
- `tbl_a_db_connection_test` jika dijadikan sebahagian rasmi platform.

Syarat:

- `CREATE TABLE IF NOT EXISTS` atau pemeriksaan setara;
- tiada drop/rename column dalam release ini;
- column baharu nullable atau mempunyai default;
- tiada delete/update terhadap row connection sedia ada;
- boleh dijalankan berulang kali;
- diuji pada schema kosong dan schema sedia ada;
- mempunyai verification query dan rollback/recovery notes.

Kesan database: hanya apabila migration dijalankan.
Kesan kod downstream: tiada; operasi deployment diperlukan.

Cadangan commit:

```text
fix: tambah migration idempotent registry additional database
```

### Fasa 4: Diagnostics Read-Only

Tambah diagnostics untuk:

- kewujudan jadual dan column;
- env row aktif;
- driver PDO tersedia;
- resolved environment, OS, driver, dan key;
- target host/database tanpa password;
- credential legacy atau version baharu;
- production/development menuju target sama;
- driver atau env row tidak lengkap;
- timeout dan resolution error yang jelas.

Diagnostics tidak membuka semua connection ketika page load. Live connection hanya berlaku melalui Test atau Inspect.

Kesan database: read-only.
Kesan kod downstream: tiada.

Cadangan commit:

```text
feat: tambah diagnostik additional database
```

### Fasa 5: Versioned Dual-Read Encryption

Perkenalkan format credential baharu, contohnya:

```text
v2:<key-id>:<nonce>:<ciphertext>:<tag>
```

Gunakan authenticated encryption seperti Sodium `secretbox` atau AES-256-GCM.

Read path:

1. credential dengan prefix/version baharu dibaca menggunakan provider baharu;
2. credential tanpa version dibaca menggunakan legacy decoder;
3. kegagalan decrypt menghasilkan explicit error;
4. ciphertext tidak boleh digunakan sebagai plaintext password.

Write path:

1. password baharu atau yang dikemas kini ditulis sebagai format baharu;
2. password legacy yang tidak diubah dibiarkan kekal;
3. key diperoleh daripada environment deployment;
4. password, key, dan plaintext tidak masuk log.

Kesan database: credential baharu/dikemas kini mempunyai format baharu.
Kesan kod downstream: tiada; environment key diperlukan.

Cadangan commit:

```text
security: tambah enkripsi credential database berperingkat
```

### Fasa 6: Controlled Credential Migration Tool

Tambah CLI tool dengan:

- dry-run;
- pilihan satu connection/environment atau semua;
- backup ciphertext sebelum perubahan;
- legacy decrypt;
- v2 encrypt;
- connection test;
- commit hanya selepas test berjaya;
- rollback row jika test gagal;
- laporan tanpa secret.

Tool tidak dijalankan secara automatik ketika deployment atau page load.

Kesan database: ciphertext berubah hanya apabila tool dijalankan.
Kesan kod downstream: tiada; operasi migration diperlukan.

Cadangan commit:

```text
feat: tambah tool migrasi credential database
```

### Fasa 7: Schema dan Data Preview Hardening

Tambah:

- query timeout;
- masking column sensitif seperti `password`, `secret`, `token`, dan `api_key`;
- truncation nilai panjang;
- handling selamat untuk BLOB/binary;
- option untuk disable data preview per connection;
- audit connection code, environment, dan object name tanpa kandungan row;
- had 30 objek dan 20 row dikekalkan atau diperketatkan.

Kesan database: read-only query.
Kesan kod downstream: tiada.

Cadangan commit:

```text
security: lindungi data sensitif dalam database preview
```

### Fasa 8: Error Handling dan Observability

Standardkan kategori error:

- connection tidak ditemui;
- connection disabled;
- environment tidak dikonfigurasi;
- driver tidak tersedia;
- credential gagal didecrypt;
- authentication gagal;
- timeout;
- schema registry tidak compatible.

Jika exception khusus diperkenalkan, ia mesti mewarisi `RuntimeException` untuk mengekalkan compatibility downstream.

Log tidak boleh mengandungi password, key, full credential DSN, atau data preview.

Kesan database: tiada.
Kesan kod downstream: tiada jika exception inheritance dikekalkan.

Cadangan commit:

```text
refactor: standardkan ralat additional database
```

## 9. Strategi Release

Perubahan tidak digabungkan dalam satu release besar.

### Release A: Compatibility Foundation

Kandungan:

- Fasa 1: dokumentasi kontrak;
- Fasa 2: automated tests;
- Fasa 3: migration schema;
- Fasa 4: diagnostics read-only.

Acceptance gate:

- semua tests lulus;
- public API tidak berubah;
- resolver menghasilkan target yang sama untuk fixture lama;
- migration lulus pada schema kosong dan schema sedia ada;
- tiada credential atau data registry diubah oleh diagnostics.

### Release B: Credential Security

Kandungan:

- Fasa 5: versioned dual-read encryption;
- Fasa 6: credential migration tool.

Acceptance gate:

- legacy credential masih boleh connect;
- v2 credential boleh connect;
- key salah atau tiada menghasilkan error yang jelas;
- tiada plaintext fallback;
- tiada secret dalam log;
- dry-run tidak mengubah database;
- migration boleh rollback apabila connection test gagal.

### Release C: Operational Hardening

Kandungan:

- Fasa 7: preview hardening;
- Fasa 8: error handling dan observability.

Acceptance gate:

- query downstream tidak berubah;
- Test, Inspect, Schema Preview, dan Data Preview berfungsi;
- sensitive column dimask;
- additional connection failure tidak menjejaskan main runtime;
- exception lama masih boleh ditangkap melalui `RuntimeException`.

## 10. Deployment Downstream

Cadangan urutan bagi setiap projek downstream:

1. inventori semua penggunaan `pdoAdditional()` dan connection code;
2. backup registry database;
3. rekod resolved environment, driver, host, dan database sebelum upgrade;
4. sync Release A;
5. jalankan migration additive;
6. Test Connection bagi setiap connection code;
7. smoke-test modul sebenar;
8. pasang encryption key baharu;
9. sync Release B;
10. sahkan credential legacy masih berfungsi;
11. migrasikan satu credential pada satu masa;
12. ulang Test Connection dan module smoke test;
13. sync Release C;
14. pantau error log dan audit trail;
15. rollout ke projek seterusnya hanya selepas projek semasa stabil.

`e-hepa-uat` sesuai dijadikan projek perintis UAT kerana penggunaan connection code dan feature yang terlibat telah dikenal pasti.

## 11. Smoke Test Minimum

Untuk setiap downstream:

- login dan dashboard berfungsi;
- Main MySQL tidak terjejas;
- Sybase staff/student tidak terjejas;
- setiap connection code additional boleh di-resolve;
- explicit production/development menuju target yang betul;
- Test Connection berjaya;
- query read sebenar modul berjaya;
- query write diuji hanya jika modul memang memerlukannya;
- disable connection hanya menggagalkan feature berkaitan;
- registry failure tidak menjatuhkan aplikasi utama;
- tiada password atau data sensitif muncul dalam log.

Smoke test tambahan e-HEPA:

- `dbx_mysql_istaddb` berjaya;
- `dbx_sybase_asramadb` berjaya;
- modul Penglibatan berfungsi;
- modul Penglibatan TNC berfungsi;
- senarai permohonan iStar berkaitan berfungsi;
- maklumat penginapan pelajar berfungsi.

## 12. Rollback Principle

Setiap release perlu boleh diundur secara berasingan.

- Release A tidak memadam schema atau data lama.
- Release B mengekalkan legacy decoder sepanjang tempoh transition.
- Sebelum credential migration, ciphertext lama dibackup.
- Jika v2 gagal, rollback credential row dan core Release B tanpa mengubah application code.
- Release C tidak mengubah runtime contract atau query business module.
- Jangan buang legacy decoder sehingga semua downstream disahkan telah bermigrasi dan satu tempoh stabil selesai.

## 13. Perubahan Yang Dilarang Dalam Naik Taraf Semasa

- membuang atau rename `Database::pdoAdditional()`;
- menukar return type daripada PDO;
- mewajibkan parameter environment;
- menukar default environment secara senyap;
- rename connection code sedia ada;
- drop/rename column registry;
- membuang driver sedia ada;
- membuang legacy credential decoder terlalu awal;
- menukar key dan re-encrypt secara automatik;
- menghubungkan Additional Database kepada login/bootstrap;
- membuka semua connection secara automatik ketika page load;
- memigrasikan semua downstream serentak tanpa projek perintis.

## 14. Cadangan Pelaksanaan Pertama

Mulakan dengan Release A sahaja:

1. kunci kontrak compatibility;
2. tambah automated regression tests;
3. sediakan migration idempotent;
4. tambah diagnostics read-only;
5. uji tanpa mengubah database deployment;
6. review keputusan sebelum memulakan perubahan credential.

Ini menyediakan safety net sebelum perubahan yang lebih sensitif dibuat dalam Release B.

## 15. Status Semasa

Pada tarikh dokumen ini:

- audit core selesai;
- audit read-only e-HEPA selesai;
- risiko utama dikenal pasti;
- pelan release berperingkat tersedia;
- baseline regression suite telah ditambah di `tests/additional-database-regression.php`;
- baseline regression suite lulus `30/30` checks pada PHP CLI `8.3.32`;
- suite boleh dijalankan dengan `php tests/additional-database-regression.php`;
- regression suite tidak membuka connection database sebenar;
- source-of-truth migration idempotent telah ditambah sebagai `docs/additional-database-registry-migration-2026-08-01.sql`;
- migration hanya disediakan sebagai fail dan belum dijalankan pada mana-mana database;
- diagnostics read-only registry telah ditambah tanpa auto-connect ke Additional Database;
- diagnostics dikemas kini semula selepas Refresh, Create, Update, Enable, atau Disable;
- tiada migration dijalankan;
- tiada credential diubah;
- tiada database diubah;
- tiada naik taraf kod Additional Database dilaksanakan;
- komponen pembangunan Release A telah disediakan dan memerlukan review serta smoke test UI;
- smoke test UI Release A telah disahkan oleh pemilik sistem;
- Release B dual-read encryption v2 telah ditambah tanpa migrasi automatik credential;
- credential legacy kekal boleh dibaca dan ditulis selagi key v2 belum dipasang;
- credential baharu ditulis sebagai v2 apabila `DB_ADDITIONAL_CREDENTIAL_KEY` tersedia;
- offline v2 credential smoke test tersedia melalui `tools/additional-database-credential-smoke.php`;
- offline v2 credential smoke test lulus menggunakan process-only staging key tanpa akses database;
- development-registry v2 integration smoke tersedia melalui `tools/additional-database-registry-v2-smoke.php` dan menggunakan transaction rollback;
- development-registry v2 integration smoke lulus pada `iqsframeworkdb` dan verification mengesahkan `0` fixture tertinggal;
- controlled credential migration tool telah dibina dengan dry-run sebagai default;
- apply mode memerlukan explicit scope, confirmation token, ciphertext backup, connection test, dan transaction rollback;
- dry-run `dbx_mysql_test` development lulus dan mengesahkan tiada row berubah;
- satu credential `dbx_mysql_test` development telah dimigrasikan daripada legacy kepada v2 dengan key ID `development-2026`;
- pre-update dan post-update live connection test bagi `dbx_mysql_test` lulus;
- post-migration observation menjalankan tiga public-API runtime checks berturut-turut dan semuanya lulus dengan resolved target yang konsisten;
- post-migration registry diagnostics bagi `dbx_mysql_test` berstatus `healthy` dengan `0` error dan `0` warning;
- respons registry biasa selepas migrasi mengesahkan password kekal tidak terdedah;
- ciphertext backup telah diwujudkan dengan permission `0600` untuk recovery terkawal;
- connection production lain belum dimigrasikan;
- Release C Data Preview hardening telah ditambah: sensitive masking, text truncation, binary summarization, row limit metadata, dan best-effort query timeout;
- Release C error handling telah distandardkan menggunakan `AdditionalDatabaseException` yang kekal mewarisi `RuntimeException`; mesej exception sedia ada dikekalkan bagi compatibility downstream;
- mesej gagal Additional Database kini melalui redaction berpusat sebelum dipaparkan pada UI, disimpan sebagai keputusan ujian, dimasukkan ke audit payload, atau ditulis oleh fallback audit logger;
- redaction melindungi password/secret/token/key, credential URI, environment key, dan keseluruhan envelope credential v2 sambil mengekalkan konteks driver dan host untuk diagnosis;
- controlled error-redaction smoke test bagi `dbx_mysql_test` development telah lulus: baseline dan post-smoke `SELECT 1` berjaya, credential salah mencetuskan ralat, dan marker rahsia tidak terdapat dalam output yang telah disaring;
- smoke test tersebut tidak mengubah registry atau credential tersimpan, tidak menyimpan keputusan ujian, dan tidak menyentuh mana-mana connection production;
- Release C observability menambah `error_category` ber-cardinality rendah pada audit metadata untuk kegagalan Create, Update, Toggle, Test, Inspect, Schema Preview, dan Data Preview;
- classifier hanya membaca jenis exception dan tidak wrap, mengganti, atau mengubah exception contract downstream;
- final core release-readiness check lulus: regression `30/30`, core-file protection audit `PASS`, PHP syntax bagi semua kelas/controller/tool berkaitan lulus, JavaScript syntax lulus, dan `git diff --check` bersih;
- repository secret scan tidak menemui stable development key atau ciphertext sebenar dalam tracked/untracked source; padanan yang ditemui hanyalah placeholder arahan dan fixture regression yang disengajakan;
- release readiness ini tidak menjalankan migration, tidak mengubah database, dan tidak melakukan Git commit atau push;
- final downstream compatibility audit read-only pada `e-hepa-uat` mengesahkan empat consumer sebenar masih memanggil signature yang disokong `Database::pdoAdditional(string, ?string): PDO`; semua consumer/controller/model berkaitan lulus PHP syntax dan tidak bergantung pada kelas exception baharu;
- live read-only `PDO + SELECT 1` bagi `dbx_mysql_istaddb` production lulus;
- live connection baseline bagi `dbx_sybase_asramadb` production gagal sebelum rollout core pada stage connect kerana ODBC DSN runtime tidak ditemui (`dsn_not_found`); active registry row ialah `production/any/odbc` dan extension PDO ODBC tersedia;
- kegagalan Sybase tersebut ialah isu konfigurasi ODBC/DSN downstream sedia ada, bukan regression API upgrade; tiada perubahan code downstream diperlukan, tetapi smoke test Sybase tidak boleh dianggap lulus sehingga DSN runtime diperbetulkan di luar repository core;
- audit downstream tidak mengubah fail, registry, credential, atau data dan hanya menjalankan `SELECT 1` pada connection yang berjaya dibuka;
- pelaksanaan seterusnya memerlukan arahan berasingan.

## 16. Audit Platform-aware Registry (Linux/Windows)

### 16.1 Masalah operasi yang disahkan

Registry sebenarnya sudah mempunyai key variant `(connection_code, environment, os_family, driver)`, tetapi penggunaan semasa masih cenderung menyimpan satu active row `os_family=any`. Apabila Windows memerlukan ODBC dan Linux memerlukan DBLIB, kedua-dua runtime berkongsi dan menukar row yang sama. Ini menghasilkan konfigurasi last-writer-wins dan kegagalan berselang antara workstation development dan Linux/staging.

Snapshot read-only registry development `iqs-framework` ketika audit:

- `dbx_mssql_upnm20`: `production / any / dblib`, global `driver_mode=dblib`;
- `dbx_mysql_test`: `development / any / mysql`, global `driver_mode=auto`;
- `dbx_sybase_asramadb`: `production / any / dblib`, global `driver_mode=auto`.

Tiada credential, host, DSN, atau data diubah ketika snapshot ini diambil.

### 16.2 Tempat lain yang mempunyai risiko serupa

1. `f_driver_mode` disimpan pada master connection dan digunakan untuk semua OS serta environment. Nilai selain `auto` boleh memaksa preference Windows ke Linux atau sebaliknya.
2. Resolver explicit driver mode kini mencari current OS, kemudian boleh mengambil variant OS lain. Ini boleh memilih Windows ODBC pada Linux atau Linux DBLIB pada Windows walaupun konfigurasi itu machine-specific.
3. Row `os_family=any` dikembangkan kepada Windows dan Linux. Jika ia wujud bersama row OS-specific untuk driver sama, precedence bergantung pada pembinaan map dan tidak dinyatakan sebagai contract yang jelas.
4. DSN ODBC ialah konfigurasi mesin/runtime, bukan konfigurasi database sahaja. Satu `f_dsn_name` shared tidak semestinya wujud pada semua workstation atau server.
5. Validator memaksa MySQL kepada `os_family=any`. Ini menghalang host/port berbeza mengikut OS, contohnya Docker hostname pada Linux dan localhost/host alias pada Windows.
6. UI Test/Inspect/Schema/Data Preview mempunyai fallback yang akhirnya boleh memilih active row daripada OS atau environment lain. Ujian boleh memberi false positive terhadap target yang bukan diminta.
7. `saveTestResult()` menapis environment dan OS tetapi tidak driver; satu ujian boleh overwrite status beberapa driver variant serentak.
8. Diagnostics menilai driver row OS lain sebagai error pada runtime semasa. Windows-only `sqlsrv` boleh dilaporkan sebagai error di Linux walaupun Linux mempunyai DBLIB variant yang sihat.
9. Update UI menghantar semua `env_rows`; repository memadam semua row kemudian insert semula. Dua pengguna mengedit variant berlainan masih boleh saling menimpa kerana tiada optimistic locking/revision check.
10. Password dipelihara berdasarkan tuple environment/OS/driver. Apabila driver atau OS ditukar, ia dianggap variant baharu dan credential mungkin perlu dimasukkan semula; ini mendorong pengguna mengubah row lama berbanding menambah variant baharu.

Setting seperti enabled/disabled connection, purpose, family dan supports production/development memang bersifat global dan wajar kekal shared. Host, port, database, DSN, username, credential, charset dan extra driver options wajar kekal pada variant row.

### 16.3 Penyelesaian menyeluruh yang dicadangkan

Gunakan model **satu logical connection, banyak runtime variants**:

```text
dbx_sybase_asramadb / production
  windows / odbc / DSN khusus Windows
  linux   / dblib / host:port khusus Linux
```

Downstream terus memanggil API yang sama:

```php
Database::pdoAdditional('dbx_sybase_asramadb', 'production');
```

Resolver memilih variant secara deterministik:

1. environment yang diminta sahaja; jangan fallback silang production/development;
2. exact runtime OS dahulu, kemudian `any` hanya jika tiada exact OS;
3. driver yang tersedia pada runtime sahaja;
4. `driver_mode=auto` menggunakan preference matrix per OS/family;
5. explicit driver mode hanya digunakan pada OS yang sepadan dan tidak boleh mengambil row OS lain;
6. fallback mesti datang daripada OS yang sama atau row `any`, serta direkod dalam diagnostics/audit.

Satu row DBLIB sahaja tidak boleh secara umum menjadi ODBC pada Windows kerana ODBC memerlukan DSN/driver manager configuration tersendiri. Kedua-dua variant perlu disimpan serentak. Alternatif DSN-less ODBC hanya boleh digunakan jika nama ODBC driver dan connection-string contract distandardkan; ia tidak selamat untuk diandaikan secara automatik.

### 16.4 Perubahan core yang dicadangkan

- resolver: deterministic exact-OS selection dan larang cross-OS/cross-environment fallback;
- registry: precedence explicit OS mengatasi `any`, bebas daripada urutan row DB;
- validator: kesan overlap `any` + OS-specific, duplicate active target dan variant yang tidak lengkap;
- test/inspect/preview: wajib memilih tuple tepat environment/OS/driver atau menggunakan resolver runtime sebenar;
- test result: update berdasarkan exact `(code, environment, os_family, driver)`;
- diagnostics: nilai kesihatan runtime semasa secara berasingan daripada coverage matrix semua platform;
- UI: sediakan butang/preset `Windows ODBC` dan `Linux DBLIB`, paparkan resolution preview, dan elakkan arahan “tukar driver” pada row shared;
- concurrency: tambah revision/updated-at guard atau endpoint update-per-variant supaya edit Windows tidak memadam perubahan Linux;
- credential UX: fungsi clone variant yang menyalin field selamat dan meminta explicit confirmation untuk reuse credential tanpa mendedahkan plaintext;
- audit: rekod requested tuple, resolved tuple, fallback reason dan runtime OS tanpa menyimpan credential.

### 16.5 Pelan pelaksanaan selamat

1. Tambah regression matrix Windows/Linux untuk Sybase, MSSQL dan MySQL tanpa mengubah API awam.
2. Betulkan resolver/registry precedence serta exact tuple test-result update.
3. Ketatkan controller supaya tiada fallback silang environment/OS.
4. Tingkatkan diagnostics dan UI variant editor.
5. Jalankan dry-run audit yang menyenaraikan row `any`, global forced driver mode dan overlap; jangan auto-migrate.
6. Untuk `dbx_sybase_asramadb`, tukar row shared kepada dua variant melalui controlled migration: Linux DBLIB dan Windows ODBC. DSN Windows perlu disahkan oleh team downstream.
7. Smoke-test setiap variant pada OS masing-masing, kemudian public API regression pada downstream.
8. Rollout core dahulu; migrasi data variant dibuat berasingan dengan backup dan rollback.

Perubahan ini tidak memerlukan perubahan pada code consumer downstream selagi signature dan return type `Database::pdoAdditional(string, ?string): PDO` dikekalkan.

## 17. Database dan Downstream Rollout Prerequisites

Pengesahan read-only pada database development menunjukkan ketiga-tiga table registry serta semua column runtime yang diperlukan sudah tersedia. Source migration SQL disediakan secara idempotent tetapi tidak pernah dijalankan oleh Codex dalam siri kerja ini.

Perubahan data sebenar yang telah dibuat ialah satu credential `dbx_mysql_test / development / any / mysql` telah dimigrasikan daripada legacy kepada envelope v2. `dbx_mssql_upnm20` dan `dbx_sybase_asramadb` masih legacy.

Implikasi rollout:

- semua runtime downstream yang membaca, menguji, atau mengedit row credential v2 mesti mempunyai nilai `DB_ADDITIONAL_CREDENTIAL_KEY` yang sama dan key ID yang sesuai;
- key sebenar tidak boleh dimasukkan ke Git, source code, migration SQL, atau package deployment; ia mesti dipasang melalui secret/environment setiap runtime;
- downstream yang hanya menggunakan connection legacy kekal serasi kerana core mengekalkan dual-read;
- jika downstream menggunakan database registry berasingan, jalankan schema readiness check dan hanya gunakan migration idempotent apabila table/column belum tersedia;
- jika semua downstream benar-benar berkongsi registry DB yang sama, schema migration hanya perlu disahkan sekali pada DB tersebut, tetapi secret key tetap perlu dipasang pada setiap runtime PHP yang membaca credential v2;
- sync core mesti merangkumi semua kelas baharu dan perubahan controller/JS/page, bukan hanya `Database.php` atau resolver;
- cadangan platform-aware dalam Seksyen 16 kemudiannya mula dilaksanakan sebagai Release D menggunakan schema sedia ada; status terperinci direkodkan dalam Seksyen 18;
- migrasi variant platform ialah perubahan data registry dan perlu backup, dry-run, exact-OS smoke test serta rollback, tetapi tidak memerlukan perubahan code consumer downstream.

Smoke test biasa mungkin menulis status/rekod audit ujian apabila dijalankan melalui UI. Controlled CLI redaction smoke dan downstream `SELECT 1` audit tidak mengubah registry atau credential.

## 18. Release D Platform-aware Implementation Status

Komponen berikut telah dilaksanakan tanpa mengubah public API downstream:

- regression matrix Windows/Linux, exact-OS precedence, `any` fallback dan cross-OS rejection;
- resolver memilih exact runtime OS dahulu, kemudian `any`, serta hanya driver PDO yang tersedia;
- Additional MySQL menyokong OS-specific host/port variant;
- validator menolak overlap ambiguous `any` dengan OS-specific bagi environment/driver sama;
- Test, Inspect, Schema Preview dan Data Preview tidak lagi fallback ke OS atau environment lain;
- keputusan connection test dikemas kini menggunakan exact tuple `(code, environment, os_family, driver)`;
- diagnostics memisahkan `runtime_health` daripada `platform_coverage` dan tidak menghukum Linux kerana driver Windows tidak tersedia;
- UI memaparkan platform coverage serta preset `Windows ODBC` dan `Linux DBLIB`;
- opaque registry revision ditambah untuk optimistic concurrency protection supaya borang stale tidak memadam variant platform lain;
- tool audit read-only tersedia di `tools/additional-database-platform-audit.php`;
- controlled migration tool dry-run-default tersedia di `tools/additional-database-platform-migrate.php`, dengan backup, transaction rollback, Linux `SELECT 1`, confirmation token dan Windows-test-pending guard.

Regression Release D lulus `36/36`; core-file protection audit, PHP syntax, JavaScript syntax dan `git diff --check` turut lulus.

Dry-run `dbx_sybase_asramadb` mengesan row `production / any / dblib` dan mencadangkan:

- `production / linux / dblib`, menggunakan konfigurasi sedia ada;
- `production / windows / odbc`, memerlukan DSN Windows yang sah;
- master `driver_mode=auto`.

Dry-run pertama tidak mengubah database. Selepas DSN Windows `ASRAMADB` disahkan oleh pemilik sistem, controlled apply telah dilaksanakan pada database development:

- row shared `production / any / dblib` ditukar kepada `production / linux / dblib`;
- row `production / windows / odbc` ditambah menggunakan DSN `ASRAMADB`;
- credential ciphertext sedia ada direuse tanpa dipaparkan atau ditukar kepada plaintext dalam output;
- master `driver_mode` diselaraskan kepada `auto`;
- Linux DBLIB `SELECT 1` lulus sebelum commit dan public API post-migration turut lulus;
- post-migration diagnostics melaporkan runtime Linux `healthy`, platform coverage `2/2`, dan `0` missing;
- audit tool post-migration melaporkan `Issues: none`;
- backup berjaya disimpan dengan permission `0600` di `_updates_archive/platform-backups/additional-db-platform-backup-20260801-052014-eb224326.json`.

Percubaan apply pertama dihentikan sebelum perubahan DB kerana deployed schema menggunakan primary key `id`; transaksi tidak mengubah row. Tool kemudian dibaiki supaya menyokong kedua-dua `id` dan `f_db_connection_env_id` tanpa memerlukan schema migration.

Windows ODBC smoke test kemudiannya disahkan lulus oleh programmer downstream pada runtime Windows: public API memilih `driver=odbc` dan `SELECT 1` memulangkan `1`. Dengan pengesahan Linux DBLIB dan Windows ODBC ini, Release D platform-aware dianggap lengkap.

Final automated UI integration smoke turut lulus selepas migrasi:

- butang preset dan handler `Windows ODBC` serta `Linux DBLIB` tersedia;
- hidden registry revision dan paparan platform coverage tersedia;
- payload form mengandungi exact tuple Linux/DBLIB dan Windows/ODBC serta lulus server validation;
- stale revision ditolak sebelum variant replacement dan registry revision kekal sama;
- test status Linux hanya mengemas kini exact Linux/DBLIB row dalam transaction, Windows/ODBC row tidak disentuh, kemudian semua perubahan smoke di-rollback;
- runtime Linux memilih DBLIB dan `SELECT 1` lulus;
- database kekal tidak berubah selepas smoke test.

Browser automation tidak tersedia dalam runtime repository, maka ujian ini ialah structural dan backend UI-integration smoke, bukan rakaman klik visual browser. Visual Windows/UI acceptance telah dilengkapkan secara berasingan melalui pengesahan programmer downstream terhadap ODBC runtime.

## 19. Release 1.9.0, Downstream Sync dan Pilot Validation

Release feature ini ditetapkan sebagai `1.9.0`. Public consumer contract kekal `Database::pdoAdditional(string, ?string): PDO`, maka modul downstream tidak perlu menukar code panggilan. Perubahan yang perlu diselaraskan ialah salinan core framework, readiness schema registry, data variant bagi setiap database development yang berasingan, dan secret v2 pada setiap runtime yang membacanya.

### 19.1 Rekod sync code downstream

Core Additional Database telah disebarkan ke branch `main` bagi semua project downstream yang diluluskan. Keadaan remote yang direkodkan selepas push ialah:

| Project | Commit |
|---|---|
| `apel` | `0bd0bb6` |
| `e-bdr` | `5b8726b` |
| `e-dqms` | `d130f69` |
| `e-facility-uat` | `37d893d` |
| `e-hepa-uat` | `837a0b9` |
| `e-pms-uat` | `c28e4a1` |
| `e-pr-uat` | `9ac7137` |
| `e-prestasi-uat` | `8ae1275` |
| `e-spk-uat` | `b8895e6` |
| `emadani` | `cca4852` |
| `sap-uat` | `9ff39fa` |
| `survey-uat` | `9b897da` |

Semua working tree dilaporkan bersih dan sepadan dengan remote `main` selepas operasi tersebut. Rekod ini hanya mengesahkan sync code; setiap project masih memerlukan validasi terhadap database development dan runtime OS masing-masing.

### 19.2 Pilot `e-hepa-uat`

Readiness check mengesahkan table dan column registry yang diperlukan telah tersedia; tiada `CREATE TABLE` atau `ALTER TABLE` dijalankan. Data `dbx_sybase_asramadb` kemudiannya dimigrasikan secara terkawal:

- row asal `production / any / odbc` dipisahkan kepada `production / linux / dblib` dan `production / windows / odbc`;
- DSN Windows dikekalkan sebagai `ASRAMADB`, master mode ditetapkan kepada `auto`, dan ciphertext sedia ada disalin tanpa mendedahkan plaintext;
- backup permission `0600` disimpan di `_updates_archive/platform-backups/additional-db-platform-backup-20260801-063033-f28d75ae.json` dalam repository `e-hepa-uat`;
- Linux public API memilih `dblib` dan `SELECT 1` berjaya;
- `dbx_mysql_istaddb` memilih `mysql` dan `SELECT 1` berjaya;
- fresh Windows ODBC smoke test selepas migrasi masih menunggu programmer Windows tersedia.

Runtime Linux `e-hepa-uat` telah dipasang dengan v2 key dan disahkan `configured=yes`, `write_format=v2`, serta key ID `e-hepa-development-2026`. Credential registry masih legacy. Runtime Windows yang berkongsi registry mesti menerima key sebenar yang sama dan fingerprint perlu dibandingkan sebelum sebarang Edit/Save yang boleh menulis semula credential sebagai v2.

### 19.3 Pilot `e-dqms`

Readiness check turut mengesahkan schema registry lengkap tanpa migration DDL. Lima connection MSSQL berikut telah dipisahkan daripada `production / any / dblib` kepada variant `production / linux / dblib` dan `production / windows / sqlsrv`:

- `db_sqlsrv_kpt` (disabled tetapi dimigrasikan untuk kegunaan masa depan);
- `dbx_sqlsrv_kpt_staff`;
- `dbx_sqlsrv_kpt_student`;
- `dbx_sqlsrv_upnm_staff`;
- `dbx_sqlsrv_upnm_student`.

Master mode setiap connection ditetapkan kepada `auto`; credential ciphertext legacy disalin kepada kedua-dua variant. Backup permission `0600` disimpan di `_updates_archive/platform-backups/additional-db-platform-backup-20260801-070051-7ad2a187.json` dalam repository `e-dqms`.

Semua connection aktif lulus Linux DBLIB `SELECT 1`; connection disabled kekal ditolak sebagai `disabled`, seperti contract asal. Diagnostics melaporkan platform coverage `10/10`. Amaran runtime yang tinggal ialah sepuluh credential variant masih menggunakan format legacy. Windows PDO SQLSRV smoke test dan pemasangan/penyelarasan v2 key masih belum selesai.

### 19.4 Kesan database sebenar

Feature core menggunakan column registry yang sudah diperkenalkan secara idempotent dalam source migration. Bagi tiga database development yang telah diperiksa dalam siri kerja ini, schema yang diperlukan sedia ada dan tiada DDL dijalankan.

Perubahan database sebenar setakat rekod ini ialah:

- database development `iqs-framework`: satu credential `dbx_mysql_test / development / any / mysql` dimigrasikan kepada v2, dan `dbx_sybase_asramadb` dipisahkan kepada Linux/DBLIB serta Windows/ODBC;
- database development `e-hepa-uat`: `dbx_sybase_asramadb` dipisahkan kepada dua variant platform; credential kekal legacy;
- database development `e-dqms`: lima connection MSSQL dipisahkan kepada sepuluh variant Linux/DBLIB dan Windows/SQLSRV; credential kekal legacy.

Oleh sebab setiap downstream menggunakan database development yang berlainan, sync code sahaja tidak mengubah database project tersebut. Setiap database mesti melalui urutan berasingan: schema readiness check, platform audit dry-run, backup, controlled apply bagi connection berkaitan, Linux/Windows smoke test, kemudian credential dry-run dan apply hanya selepas key semua runtime diselaraskan.

### 19.5 Baki kerja rollout

- Selesaikan fresh Windows ODBC smoke test untuk `e-hepa-uat` selepas memasang key v2 yang sama pada runtime Windows.
- Selesaikan Windows PDO SQLSRV `SELECT 1` untuk lima variant `e-dqms` yang berkaitan dan pasang key v2 secara konsisten pada semua runtime perkongsian registry.
- Audit dan migrasikan setiap database downstream lain secara satu demi satu; jangan anggap data registry sama walaupun code core sama.
- Simpan key di secret/environment runtime sahaja, bandingkan fingerprint tanpa memaparkan key, dan jangan commit `.env` atau key ke Git.
- Kekalkan credential legacy sehingga semua runtime perkongsian registry disahkan mempunyai key sama; selepas itu jalankan credential migration dalam dry-run sebelum controlled apply.
- Rekod backup, tuple yang diubah, keputusan diagnostics, driver PDO sebenar dan `SELECT 1` bagi setiap OS/project dalam dokumen operasi project berkenaan.

### 19.6 Ringkasan status release

Core `1.9.0` dan compatibility contract dianggap siap. Migrasi database bukan migration global dan tidak memerlukan perubahan code consumer downstream. Rollout keseluruhan hanya dianggap lengkap apabila setiap database development downstream telah melalui audit/migrasi tersendiri dan semua variant OS yang benar-benar digunakan telah lulus smoke test.
