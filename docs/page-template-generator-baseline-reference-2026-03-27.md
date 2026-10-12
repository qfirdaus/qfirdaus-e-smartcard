# Page Template Generator Baseline Reference

Tarikh: 2026-03-27

## Tujuan

Dokumen ini menetapkan page rujukan sebenar dalam `e-Base` yang akan dijadikan baseline untuk stub generator fasa awal.

Objektif dokumen ini:

- elakkan ambiguity semasa membina stub
- tentukan dengan jelas “bahagian mana” yang patut dicontohi
- pastikan hasil generator ikut standard visual dan struktur semasa

## Baseline 1: Blank Page

### Rujukan

- [dashboard.php](/var/www/app\e-base\public\pages\dashboard.php)

### Scope yang perlu diambil

Untuk template `Blank Page`, **bukan keseluruhan dashboard** yang perlu dijadikan contoh.

Yang perlu diambil hanyalah shell page standard:

- title header
- breadcrumb
- standard layout include
- content container utama

### Yang perlu ada dalam stub `Blank Page`

1. `require_once` / init standard
2. session/login guard
3. page title variable
4. include topbar/sidebar/footer melalui shell semasa
5. content wrapper standard
6. page header standard:
   - title
   - breadcrumb
7. body content placeholder kosong
8. optional JS/CSS page include jika dipilih
9. language key usage terus, bukan hardcoded text

### Yang tidak perlu dibawa dari dashboard

- KPI cards
- quick actions
- announcements
- health widgets
- system resources
- dashboard-specific query logic

### Maksud praktikal

`Blank Page` generator patut melahirkan page yang:

- nampak seperti page native `e-Base`
- tetapi hanya mempunyai:
  - header
  - breadcrumb
  - content card/panel kosong

Ini akan jadi starting point paling neutral untuk team developer.

## Baseline 2: DataTable Page

### Rujukan

- [senarai-pengguna.php](/var/www/app\e-base\public\pages\senarai-pengguna.php)

### Scope yang perlu diambil

Untuk template `DataTable Page`, rujukan yang dipilih ialah:

- **hanya kandungan dalam tab `Akses Staf`**

Ini bermaksud template datatable **tidak** perlu mewarisi keseluruhan complexity page `Senarai Pengguna`.

### Yang perlu diambil daripada `Akses Staf`

1. layout page standard
2. card/content shell untuk list page
3. DataTable standard pattern
4. top controls standard:
   - length selector
   - search box
   - top-right action area
5. table structure standard
6. bottom row standard:
   - info
   - pagination
7. page-specific JS hook untuk DataTable init
8. translation pattern untuk table labels

### Yang boleh dianggap optional dalam Phase 1

- group filter dropdown
- sync button
- add button
- extra roles flow
- edit/delete button actions
- modal add/edit
- student/public scope handling

Saya syorkan untuk stub datatable Phase 1:

- table ready
- search/length/info/pagination ready
- top-right action area placeholder wujud
- tetapi tiada business logic kompleks

### Yang tidak perlu dibawa dari `senarai-pengguna.php`

- tiga tab `Staf / Pelajar / Umum`
- student mode handling
- sync logic
- add staff/add student flow
- modal add/edit user
- delete user flow
- extra roles flow
- AJAX-specific user management logic
- operational mode enforcement

### Maksud praktikal

Template `DataTable Page` patut menghasilkan:

- satu page list standard
- satu table standard
- satu JS file standard untuk DataTable init
- satu controller skeleton yang sedia untuk bekalkan data

Bukan clone penuh `Senarai Pengguna`.

## Ringkasan Baseline Decision

### Blank Page

Rujuk:

- `dashboard.php`

Ambil:

- page shell
- title header
- breadcrumb

Buang:

- semua widget/dashboard logic

### DataTable Page

Rujuk:

- `senarai-pengguna.php`

Ambil:

- semua content dalam `Akses Staf` dari sudut structure/pattern

Buang:

- semua business logic user management
- semua multi-tab complexity
- semua student/public complexity

## Cadangan untuk Stub Builder

Semasa membina stub nanti, jangan cuba copy page reference bulat-bulat.

Sebaliknya:

### Stub `Blank Page`

Perlu jadi:

- distilled shell dari `dashboard.php`

### Stub `DataTable Page`

Perlu jadi:

- distilled list-page shell dari `senarai-pengguna.php` tab `Akses Staf`

Itu akan menghasilkan output yang:

- konsisten dengan sistem semasa
- tetapi masih ringan dan maintainable

## Final Recommendation

Baseline yang anda pilih adalah tepat untuk fasa awal:

- `dashboard.php` untuk `Blank Page`
- `senarai-pengguna.php` tab `Akses Staf` untuk `DataTable Page`

Ini cukup kuat untuk menghasilkan template yang benar-benar terasa seperti modul `e-Base`, tanpa membawa masuk complexity yang tidak perlu ke dalam generator.
