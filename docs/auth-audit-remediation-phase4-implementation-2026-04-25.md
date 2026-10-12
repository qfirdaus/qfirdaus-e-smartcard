# Auth Audit Remediation Phase 4 Implementation Plan

Tarikh: 2026-04-25

Dokumen ini ialah pecahan task implementasi sebenar untuk Fasa 4 daripada roadmap:
- [auth-audit-remediation-roadmap-2026-04-25.md](/var/www/app/iqs-framework/docs/auth-audit-remediation-roadmap-2026-04-25.md)

Skop Fasa 4:
- policy simplification pada boundary auth
- kurangkan enumeration dan policy oracle untuk unknown account
- hentikan penggunaan category guessing sebagai asas keputusan auth kritikal
- kekalkan audit dalaman yang kaya tanpa menjadikan UI terlalu spesifik

Status legend:
- `[ ]` belum mula
- `[~]` sedia untuk implementasi
- `[x]` siap dan disahkan

## Sasaran Fasa 4

Hasil yang dikehendaki selepas Fasa 4:
- keputusan auth untuk unknown account tidak lagi bergantung pada bentuk `loginID`
- `normalizeLoginCategory()` tidak lagi menjadi policy gate untuk unknown account
- mesej user untuk branch unauthenticated menjadi lebih generik dan lebih sukar dieksploit untuk enumeration
- audit backend masih menyimpan sebab sebenar untuk troubleshooting dan forensik

## Prinsip Pelaksanaan Fasa 4

Fasa ini perlu dibuat dengan berhati-hati kerana ia menyentuh mesej dan policy branch yang user nampak.

Prinsip:
- jangan ubah login success path
- jangan ubah rule sebenar untuk account yang sah
- kurangkan specificity hanya pada boundary unauthenticated yang berisiko menjadi oracle
- kekalkan specificity di audit/backend, bukan semestinya di UI
- gunakan source authoritative sahaja untuk category:
  - `tbl_m_user.f_categoryUser`
  - validated SSO source / handoff

## Task 1: Hentikan Category Guessing Untuk Unknown Manual Account

Status:
- `[~]`

Fail terlibat:
- `public/controllers/LoginController.php`

Tujuan:
- mengelakkan keputusan auth awal yang bergantung pada bentuk identifier yang dihantar oleh pengguna

Perubahan yang dicadangkan:
- kenal pasti branch dalam `authenticate()` yang memanggil `normalizeLoginCategory(null, $loginID)` selepas user lookup gagal
- hentikan penggunaan result itu sebagai asas policy untuk unknown manual user
- untuk unknown manual user, pulangkan result atau exception yang lebih generik

Checklist implementasi:
- `[ ]` cari semua branch `user not found` dalam `authenticate()`
- `[ ]` cari semua tempat `normalizeLoginCategory(null, $loginID)` digunakan untuk unknown account
- `[ ]` keluarkan category guessing daripada policy branch unknown manual user
- `[ ]` semak kesan pada exception:
  - `SSO_FIRST_LOGIN_REQUIRED`
  - `MANUAL_ACCOUNT_NOT_READY`
- `[ ]` pastikan account yang benar-benar wujud masih mengikuti policy sebenar berdasarkan DB row

Acceptance criteria:
- unknown manual account tidak lagi dikelaskan kepada `STAF` / `PELAJAR` / `UMUM` hanya berdasarkan bentuk identifier
- decision branch untuk unknown manual account menjadi lebih generik

Verification:
- cuba login manual dengan identifier tidak wujud yang menyerupai:
  - staf
  - pelajar
  - email umum
- pastikan mesej akhir tidak berubah mengikut bentuk identifier semata-mata

## Task 2: Hadkan `normalizeLoginCategory()` Kepada Helper Sokongan

Status:
- `[~]`

Fail terlibat:
- `public/controllers/LoginController.php`

Tujuan:
- menurunkan fungsi ini daripada policy gate kepada helper sokongan yang hanya digunakan apabila source category sudah cukup kuat

Perubahan yang dicadangkan:
- audit semua penggunaan `normalizeLoginCategory()`
- klasifikasikan penggunaan kepada:
  - policy-critical
  - reporting/audit
  - fallback UI/helper
- keluarkan penggunaan policy-critical yang tiada source authoritative

Checklist implementasi:
- `[ ]` cari semua call site `normalizeLoginCategory()`
- `[ ]` tandakan mana yang masih sah digunakan
- `[ ]` pastikan fungsi hanya menerima:
  - row user sebenar
  - source SSO yang telah validated
  - fallback reporting yang tidak mempengaruhi auth outcome
- `[ ]` semak fallback default `STAF` dan tentukan sama ada ia masih wajar untuk helper non-critical

Acceptance criteria:
- `normalizeLoginCategory()` tidak lagi mempengaruhi keputusan auth kritikal untuk unknown user
- fungsi ini hanya tinggal sebagai helper sokongan

Verification:
- audit semua call site selepas perubahan
- pastikan tiada branch auth kritikal yang masih bergantung pada guessed category

## Task 3: Generikkan Mesej User Untuk Unknown / Not-Ready Boundary

Status:
- `[~]`

Fail terlibat:
- `public/login.php`
- `public/controllers/LoginController.php`
- translation keys berkaitan jika perlu

Tujuan:
- mengurangkan enumeration surface pada boundary auth tanpa membutakan operasi backend

Perubahan yang dicadangkan:
- semak exception dan alert untuk branch unauthenticated
- kelompokkan response user-facing yang terlalu spesifik
- kekalkan reason code penuh di audit/backend

Checklist implementasi:
- `[ ]` semak branch exception di `login.php`
- `[ ]` kenal pasti branch yang terlalu spesifik untuk user belum authenticated
- `[ ]` tentukan response generik yang sesuai untuk:
  - unknown account
  - account belum sedia
  - route login salah
- `[ ]` pastikan translation key UI tidak terlalu mendedahkan policy dalaman
- `[ ]` kekalkan reason code sebenar di log/audit

Acceptance criteria:
- user-facing response pada boundary unauthenticated menjadi lebih seragam
- audit masih boleh membezakan sebab sebenar
- account sah yang berjaya login tidak terkesan

Verification:
- cuba identifier tidak wujud
- cuba account yang memang belum sedia
- banding mesej UI dan audit backend

## Task 4: Bezakan Internal Reason Dan User-Facing Reason

Status:
- `[~]`

Fail terlibat:
- `public/controllers/LoginController.php`
- `public/login.php`

Tujuan:
- memastikan backend boleh audit dengan terperinci tanpa menjadikan UI policy oracle

Perubahan yang dicadangkan:
- semak semua tempat controller melempar exception policy/auth
- tentukan lapisan mana yang patut guna:
  - internal reason code
  - user-facing message group
- jika perlu, perkenalkan mapping reason dalaman ke kategori mesej awam

Checklist implementasi:
- `[ ]` inventori semua reason/exception yang dilempar oleh `LoginController`
- `[ ]` inventori semua alert branch di `login.php`
- `[ ]` kelompokkan reason dalaman kepada kategori mesej awam
- `[ ]` pastikan audit menggunakan reason dalaman
- `[ ]` pastikan UI menggunakan kategori mesej yang telah digenerikkan

Acceptance criteria:
- reason dalaman tetap kaya untuk audit
- mesej awam lebih ringkas dan lebih sukar digunakan untuk enumeration

Verification:
- trigger beberapa branch auth fail
- bandingkan reason dalam audit/log dengan mesej yang dipapar kepada user

## Task 5: Semak Semula Branch `SSO` Unknown / Not Provisioned

Status:
- `[~]`

Fail terlibat:
- `public/controllers/LoginController.php`
- `public/login.php`

Tujuan:
- memastikan flow `SSO` masih boleh memberi maklumat operasi yang cukup, tetapi tidak menjadi oracle kepada pihak luar

Perubahan yang dicadangkan:
- semak branch:
  - `SSO_ACCOUNT_NOT_PROVISIONED`
  - `SSO_DEFAULT_GROUP_INVALID`
  - `SSO_SOURCE_UNAVAILABLE`
  - `SSO_AUTO_PROVISION_FAILED`
- tentukan mana yang perlu kekal spesifik di UI dan mana yang patut digenerikkan
- jika reason datang daripada handoff sah dan internal-only context, specificity yang lebih tinggi mungkin masih boleh diterima

Checklist implementasi:
- `[ ]` semak semua exception khusus `SSO`
- `[ ]` klasifikasikan mengikut risiko enumeration
- `[ ]` tentukan branch yang masih perlu mesej spesifik
- `[ ]` tentukan branch yang patut digenerikkan kepada mesej auth/SSO umum
- `[ ]` kekalkan semua reason penuh di audit backend

Acceptance criteria:
- flow `SSO` masih boleh ditroubleshoot oleh admin melalui audit
- UI tidak mendedahkan lebih daripada yang diperlukan kepada pihak luar

Verification:
- uji handoff valid tetapi account tidak provisioned
- uji source unavailable
- uji auto-provision gagal
- banding UI dengan audit backend

## Task 6: Semak Semula Unknown-User Path Supaya Konsisten Antara `MANUAL` Dan `SSO`

Status:
- `[~]`

Fail terlibat:
- `public/controllers/LoginController.php`
- `public/login.php`

Tujuan:
- memastikan behavior unknown identity tidak bercanggah antara dua auth method

Perubahan yang dicadangkan:
- semak path manual unknown user
- semak path SSO unknown user / not found / not provisioned
- pastikan kedua-duanya konsisten dari sudut:
  - guardrail behavior
  - audit behavior
  - UI specificity

Checklist implementasi:
- `[ ]` banding unknown manual path vs unknown SSO path
- `[ ]` selaraskan taxonomy reason code jika perlu
- `[ ]` selaraskan user-facing behavior setakat yang sesuai
- `[ ]` pastikan perbezaan yang tinggal benar-benar diperlukan oleh business rule

Acceptance criteria:
- unknown identity path antara `MANUAL` dan `SSO` lebih konsisten
- perbezaan yang tinggal hanya yang benar-benar berasaskan policy sah

Verification:
- uji manual unknown user
- uji SSO unknown / unprovisioned user
- banding hasil akhir

## Task 7: Kemas Kini Checklist QA Untuk Enumeration Dan Policy Oracle

Status:
- `[~]`

Fail / artifak terlibat:
- dokumen ujian manual
- dokumen remedi auth

Tujuan:
- memastikan selepas implementasi, perubahan ini benar-benar mengurangkan kebocoran maklumat pada boundary auth

Perubahan yang dicadangkan:
- sediakan set ujian yang membandingkan output untuk identifier pelbagai bentuk
- uji beza antara:
  - unknown user
  - user salah password
  - user not-ready
  - user blocked
  - wrong login route

Checklist implementasi:
- `[ ]` senaraikan test cases enumeration utama
- `[ ]` tentukan expected UI response set
- `[ ]` tentukan expected audit response set
- `[ ]` dokumentasikan branch yang memang sengaja kekal spesifik atas sebab operasi

Acceptance criteria:
- test cases utama enumeration mempunyai output yang boleh dinilai
- risiko policy oracle dikurangkan berbanding baseline sebelum Fasa 4

Verification:
- jalankan test matrix selepas implementasi

## Susunan Implementasi Fasa 4

Urutan yang disyorkan:
1. Task 1: hentikan category guessing untuk unknown manual account
2. Task 2: hadkan `normalizeLoginCategory()` kepada helper sokongan
3. Task 3: generikkan mesej user untuk unknown/not-ready boundary
4. Task 4: bezakan internal reason dan user-facing reason
5. Task 5: semak semula branch `SSO` unknown/not provisioned
6. Task 6: selaraskan unknown-user path antara `MANUAL` dan `SSO`
7. Task 7: kemas kini checklist QA enumeration

Rasional:
- category guessing mesti dikeluarkan dahulu
- selepas itu barulah mesej dan reason mapping boleh dikemaskan
- alignment `SSO` dan `MANUAL` dibuat selepas behavior asas menjadi lebih bersih

## Checklist Siap Fasa 4

Semua item berikut perlu `[x]` sebelum Fasa 4 dianggap selesai:
- `[ ]` category guessing tidak lagi digunakan untuk unknown manual account
- `[ ]` `normalizeLoginCategory()` tidak lagi menjadi policy gate untuk unknown account
- `[ ]` user-facing message untuk boundary unauthenticated lebih generik
- `[ ]` internal reason dan user-facing reason telah dipisahkan
- `[ ]` branch `SSO` unknown/not provisioned telah disemak dan diselaraskan
- `[ ]` checklist QA enumeration telah disediakan dan diluluskan

## Acceptance Criteria Fasa 4

Fasa 4 dianggap lulus jika:
- bentuk identifier tidak lagi cukup untuk mempengaruhi response policy pada unknown account
- risiko enumeration dan policy oracle berkurang berbanding baseline audit
- audit backend masih menyimpan sebab sebenar yang cukup untuk troubleshooting
- login success path manual dan SSO tidak berubah

## Cadangan Handover Selepas Fasa 4

Selepas Fasa 4 siap dan disahkan:
- teruskan ke Fasa 5 untuk mengetatkan kontrak dan replay resistance `SSO`
- gunakan baseline QA Fasa 4 untuk memastikan tightening `SSO` tidak membuka semula enumeration yang telah dikurangkan
