# User, Auth, and SSO Roadmap

Tarikh: 2026-03-25

## Tujuan

Dokumen ini merumuskan roadmap implementasi berfasa untuk model pengguna `STAFF`, `STUDENT`, dan `PUBLIC` dalam sistem `e-Base`, dengan mengambil kira bahawa integrasi SSO `OneID` belum wujud lagi dan patut dimasukkan pada fasa akhir.

Prinsip utama roadmap ini:

- bina dahulu `user registry` dan `authorization`
- kekalkan sistem boleh beroperasi dengan login lokal semasa development
- jadikan SSO sebagai lapisan `authentication provider`, bukan asas access control
- elakkan overengineering dan kekalkan migrasi yang selamat

## Sasaran Akhir

Di hujung roadmap ini, `e-Base` patut mempunyai:

- satu model pengguna yang seragam untuk `STAFF`, `STUDENT`, dan `PUBLIC`
- pemisahan jelas antara `authentication` dan `authorization`
- access control yang tidak bergantung penuh kepada SSO
- user management UI yang sesuai untuk 3 kategori pengguna
- sistem setting yang boleh mengawal kategori login dan mode auth
- asas yang stabil untuk integrasi `OneID` pada fasa terakhir

## Prinsip Reka Bentuk

1. `SSO validates identity, e-Base validates access`
2. `tbl_m_user` menjadi sumber utama authorization
3. STAFF dan STUDENT guna model `hybrid`
   - identity datang dari external source / SSO
   - access control datang dari local registry
4. PUBLIC kekal local-only
5. semua flow login akhirnya melalui service authorization yang sama

## Fasa Implementasi

## Fasa 1: Baseline User Registry

### Objektif

Sediakan model data pengguna yang menyokong 3 kategori tanpa bergantung kepada SSO.

### Skop

- tambah kategori pengguna dalam `tbl_m_user`
- tambah medan auth source dan status akaun
- kemas kini access logic supaya tidak lagi staff-only
- kekalkan compatibility dengan flow semasa

### Cadangan perubahan data

Medan minimum yang perlu diwujudkan atau distandardkan:

- `user_category`
  - `STAFF | STUDENT | PUBLIC`
- `auth_source`
  - `LOCAL | ONEID | HYBRID`
- `account_status`
  - `ACTIVE | BLOCKED | INACTIVE`
- `is_login_allowed`
- `staff_id`
- `matric_no`
- `username`
- `last_login_at`
- `last_sync_at`

Jika perlu untuk legacy compatibility:

- kekalkan `f_flag` buat sementara waktu
- map `f_flag` kepada `is_login_allowed` / `account_status`

### Impak database

- migration pada `tbl_m_user`
- backfill user sedia ada sebagai `STAFF`
- review uniqueness:
  - `staff_id`
  - `matric_no`
  - `username`

### Impak UI

- belum perlu ubah banyak UI
- fokus kepada kestabilan data dan service layer

### Risiko

- data lama mungkin tidak konsisten
- field legacy seperti `f_flag` mungkin dipakai di banyak tempat

### Mitigasi

- buat migration additive dahulu
- jangan buang field legacy pada fasa ini
- buat compatibility mapping di service/controller

### Deliverable

- `tbl_m_user` menyokong 3 kategori
- semua user sedia ada dipetakan dengan jelas
- asas authorization seragam tersedia

## Fasa 2: Authorization Service Unification

### Objektif

Satukan semua keputusan “boleh login atau tidak” ke dalam satu service / layer logik.

### Skop

- refactor flow login supaya semua kategori masuk ke checker yang sama
- semak:
  - kategori
  - status akaun
  - login allowed
  - group/role
- standardkan reason code untuk deny access

### Cadangan flow

Semua login flow, tak kira local atau SSO, mesti melalui urutan ini:

1. resolve identity
2. resolve category
3. load user dari `tbl_m_user`
4. semak policy global kategori
5. semak status akaun
6. semak login allowed
7. semak group/role
8. create session

### Impak database

- tiada perubahan besar tambahan

### Impak UI

- mesej deny access jadi lebih jelas dan konsisten
- audit login lebih mudah dibina

### Risiko

- logic lama mungkin masih tersebar dalam page atau include tertentu

### Mitigasi

- buat satu authorization service sebagai source of truth
- page/login handler lama hanya jadi caller

### Deliverable

- satu aliran authorization yang seragam untuk semua kategori user

## Fasa 3: Local Login by Category

### Objektif

Pastikan sistem development boleh berfungsi penuh tanpa SSO.

### Skop

- local login untuk STAFF
- local login untuk STUDENT
- local login untuk PUBLIC
- policy per kategori boleh dihidupkan/dimatikan melalui config

### Cadangan login mode

- STAFF:
  - current: `staff_id + password`
- STUDENT:
  - current: `matric_no + password`
- PUBLIC:
  - current: `username + password`

### Impak database

- password hash diperlukan untuk PUBLIC
- password hash untuk STAFF/STUDENT boleh kekal sementara semasa dev

### Impak UI

- login page perlu jelas tunjuk pilihan:
  - local login
  - future OneID button placeholder

### Risiko

- login form boleh jadi terlalu kompleks jika semua dipaparkan serentak

### Mitigasi

- guna tab atau selector kategori login
- atau satu form dengan `identifier + password + category`

### Deliverable

- sistem berjalan penuh tanpa SSO
- semua business rule login boleh diuji awal

## Fasa 4: User Management UI

### Objektif

Jadikan user registry boleh diurus secara jelas oleh admin.

### Skop

- `Senarai Pengguna` 3 tab:
  - `Staff`
  - `Student`
  - `Public`
- action per kategori:
  - add
  - sync
  - block/unblock
  - assign group

### Cadangan UI

Tab 3 kategori adalah sesuai, dengan filter tambahan:

- status akaun
- auth source
- group
- allowed/blocked

### Behaviour cadangan

#### Staff tab

- add staff dari source staf
- sync staff data

#### Student tab

- add student dari source pelajar
- sync student data

#### Public tab

- create local account
- reset password

### Impak database

- mungkin perlu audit semula index dan uniqueness

### Impak UI

- page `Senarai Pengguna` jadi pusat user governance

### Risiko

- jika cuba satukan semua logic terus dalam satu page monolith, maintenance akan mahal

### Mitigasi

- guna controller/service mengikut kategori
- shared table preset dan shared modal pattern

### Deliverable

- admin boleh urus semua kategori user tanpa bergantung pada SSO

## Fasa 5: Sync and Provisioning Rules

### Objektif

Tetapkan dengan jelas bagaimana STAFF dan STUDENT dimasukkan atau dikemaskini dari sumber luar.

### Cadangan model

Gunakan model `hybrid controlled provisioning`:

- external source beri identity/profile
- local registry beri access

### Rules cadangan

- STAFF tidak auto-allowed hanya kerana wujud di source luar
- STUDENT tidak auto-allowed hanya kerana wujud di source luar
- user mesti tetap wujud dalam `tbl_m_user`

### Cadangan operation

- `Add Staff`:
  - pilih dari source staf aktif
- `Add Student`:
  - pilih dari source pelajar aktif
- `Sync`:
  - update profile/status dari source luar
  - jangan auto-create user jika polisi belum benarkan

### Impak database

- medan sync metadata digunakan lebih aktif

### Impak UI

- perlu beza jelas antara:
  - `exists in source`
  - `allowed in e-Base`

### Risiko

- sync boleh overwrite data yang sepatutnya dikawal local

### Mitigasi

- takrifkan field mana:
  - authoritative dari source luar
  - authoritative dari local system

### Deliverable

- provisioning dan sync behaviour yang jelas, predictable, dan audit-friendly

## Fasa 6: Configuration and Policy Layer

### Objektif

Sediakan layer settings yang boleh mengawal mode auth dan kategori login.

### Setting minimum yang dicadangkan

#### Authentication mode

- `auth.sso_enabled`
- `auth.local_staff_enabled`
- `auth.local_student_enabled`
- `auth.local_public_enabled`

#### Category access

- `auth.allow_staff_login`
- `auth.allow_student_login`
- `auth.allow_public_login`

#### Provisioning policy

- `auth.staff_auto_provision`
- `auth.student_auto_provision`

Cadangan default:

- `staff_auto_provision = false`
- `student_auto_provision = false`

### Impak database

- tambah config keys dalam config/settings storage sedia ada

### Impak UI

- perlu tambah seksyen auth/access policy dalam `Tetapan Sistem`

### Risiko

- admin mungkin keliru antara “login method enabled” dan “category access allowed”

### Mitigasi

- pisahkan wording dan grouping UI dengan jelas
- contoh:
  - `Login Method`
  - `Allowed User Categories`

### Deliverable

- policy auth boleh diurus tanpa ubah code

## Fasa 7: SSO Preparation

### Objektif

Sediakan struktur dalaman supaya integrasi OneID nanti masuk dengan gangguan minimum.

### Skop

- tetapkan contract identity resolution
- tentukan mapping identifier
  - staff_id
  - matric_no
- sediakan interface auth provider
- audit session lifecycle

### Nota penting

Pada fasa ini, SSO belum semestinya terus dihidupkan.

Fokus ialah:

- `SSO-ready architecture`
- bukan `SSO fully live`

### Deliverable

- e-Base sudah sedia menerima SSO integration tanpa refactor besar lagi

## Fasa 8: OneID / SSO Integration

### Objektif

Masukkan OneID sebagai channel authentication rasmi.

### Skop

- portal initiated login
- system initiated login
- local session bridging
- deny access jika user tiada dalam `tbl_m_user`

### Prinsip

Walaupun SSO sah:

- jika user tiada dalam `tbl_m_user`
- atau kategori tidak dibenarkan
- atau akaun disekat

login tetap mesti ditolak

### Deliverable

- integrasi OneID penuh
- access control kekal ditentukan oleh e-Base

## Urutan Kerja Yang Disyorkan

Urutan paling praktikal:

1. Fasa 1
2. Fasa 2
3. Fasa 3
4. Fasa 4
5. Fasa 5
6. Fasa 6
7. Fasa 7
8. Fasa 8

## Keputusan Penting Yang Perlu Dikunci Awal

Sebelum implement besar dimulakan, keputusan ini perlu dimuktamadkan:

1. `tbl_m_user` kekal satu jadual utama
2. `user_category` menjadi field wajib
3. STAFF dan STUDENT mesti wujud dalam local registry untuk dibenarkan login
4. PUBLIC kekal local-only
5. SSO datang terakhir, bukan awal

## Risiko Utama Keseluruhan

1. Legacy field dan logic lama staff-only mungkin bercampur dengan model baru
2. UI boleh menjadi terlalu kompleks jika kategori tidak dipisahkan dengan jelas
3. Sync external boleh mengganggu data local jika ownership field tidak didefinisi awal
4. Jika SSO dimasukkan terlalu awal, architecture akan jadi bercampur dan mahal untuk dibetulkan

## Strategi Mitigasi Keseluruhan

1. buat migration additive dahulu
2. perkenal service authorization bersama sebelum ubah banyak UI
3. guna feature flag / config gating
4. rollout kategori `STUDENT` dan `PUBLIC` secara terkawal
5. audit dan test setiap fasa sebelum bergerak ke fasa seterusnya

## Ringkasan Akhir

Roadmap terbaik untuk `e-Base` ialah:

- bina dahulu model user dan access control dalaman
- hidupkan semua business rule melalui login lokal
- siapkan user management dan policy settings
- masukkan `OneID` hanya pada hujung roadmap

Pendekatan ini memberi:

- risiko lebih rendah
- testing lebih mudah
- rework lebih sedikit
- asas yang lebih sesuai untuk sistem universiti berskala besar
