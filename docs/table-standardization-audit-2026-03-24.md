# Table Standardization Audit

Date: 2026-03-24

## Ringkasan

Standardisasi jadual dalam sistem ini sukar bukan kerana CSS global tidak cukup, tetapi kerana setiap modul besar membina "mini design system" jadual sendiri. Pada masa ini terdapat tiga lapisan yang bertindih:

1. Baseline global dalam `assets/css/app.css`
2. Override per-page dalam fail page/CSS page
3. Struktur DataTables dan row content custom dalam JS per-page

Selagi tiga lapisan ini hidup serentak, perubahan pada satu lapisan tidak akan nampak benar-benar seragam di semua page.

## Modul Jadual Yang Ketara

### 1. Rujukan Paling Stabil

- `pages/kumpulan-pengguna.php`

Ini modul yang paling sesuai dijadikan rujukan utama kerana:
- shell jadual jelas
- wrapper DataTables kemas
- header, body, hover, info, pagination selari
- interaksi top controls terkawal

Status semasa:
- preset rasmi shared telah dikunci berdasarkan rupa semasa `kumpulan-pengguna.php`
- fail preset rasmi:
  - `assets/css/datatables-standard.css`
  - `assets/js/helpers/datatables-standard.js`

### 2. Modul Jadual Dengan Override Tinggi

- `pages/senarai-pengguna.php`
- `pages/manage-manuals.php`
- `pages/access-matrix.php`
- `pages/carian-pelajar.php`
- `assets/css/pages/profile.css`

Masalah utama modul ini:
- selector terlalu spesifik (`#userDT`, `#groupTable`, dsb.)
- header/body/hover ditulis berulang
- DataTables DOM custom berbeza-beza
- top controls diubah suai secara manual selepas init
- row content lebih berat daripada rujukan

### 3. Jadual Bukan DataTables Tetapi Visual Custom

- `pages/partials/tetapan-sistem/tab-database.php`
- `pages/partials/tetapan-sistem/tab-language.php`
- sebahagian jadual dalam `dashboard.php`

Masalah utama:
- jadual ini tidak patuh baseline DataTables
- guna shell khas (`db-settings-table`, `lang-settings-table`, dsb.)
- semantic table betul, tetapi visual rhythm dan density terpisah daripada standard page data

## Punca Teknikal Kenapa Standardisasi Susah

### A. Duplicate CSS Tokens

Contoh pattern yang berulang:
- `thead th`
- `tbody td`
- `tbody tr:hover`
- `.dataTables_filter input`
- `.dataTables_length select`
- `.dataTables_info`
- `.dataTables_paginate`

Pattern ini wujud serentak dalam:
- `app.css`
- `kumpulan-pengguna.php`
- `senarai-pengguna.php`
- `manage-manuals.php`
- `access-matrix.php`
- `profile.css`
- `carian-pelajar.php`

### B. DOM/DataTables Custom Tidak Seragam

Setiap page cenderung ada DOM string sendiri, contohnya:
- `dt-top-left`
- `dt-top-right`
- `dt-bottom-row`
- penambahan filter custom
- penambahan button custom

Akibatnya, walaupun CSS header/body sama, spacing controls atas/bawah masih berbeza antara page.

### C. Row Content Density Tidak Sama

`kumpulan-pengguna` lebih ringan kerana banyak sel guna ikon ringkas.

`senarai-pengguna` lebih berat kerana satu row mengandungi:
- teks lebih panjang
- chip/badge
- tooltip/info icon
- action buttons lebih padat

Jadi menyamakan `shell` sahaja tidak cukup. Perlu ada standard untuk `cell content density`.

### D. Behaviour JS Masih Menimpa Visual

Contoh yang sudah berlaku:
- `senarai-pengguna.php` pernah mewarna row melalui JS selepas DataTables render
- beberapa page ubah kelas/top controls selepas init secara manual

Ini menyebabkan CSS standard nampak "tak menjadi" walaupun sebenarnya ditimpa selepas render.

## Penemuan Paling Penting

### 1. `kumpulan-pengguna.php` patut jadi template rasmi

Bukan sekadar rujukan visual, tetapi patut dijadikan:
- rujukan DOM DataTables
- rujukan shell CSS
- rujukan spacing controls
- rujukan pagination/info layout

### 2. `senarai-pengguna.php` tidak akan pernah jadi `exact same` jika hanya ubah shell

Sebab row content dia lebih berat. Untuk benar-benar seragam, perlu bezakan:
- standard `table shell`
- standard `cell content`

### 3. `app.css` sekarang terlalu banyak cuba menjadi global table standard, tetapi page masih override terlalu banyak

Jadi model terbaik bukan terus tambah lagi CSS dalam `app.css`, tetapi kurangkan override page dan pindahkan ke preset shared.

## Solusi Yang Paling Relevan

## Fasa A: Jadikan `kumpulan-pengguna` sebagai preset rasmi

Bina satu preset bersama, contohnya:
- `assets/css/datatables-standard.css`
- `assets/js/datatables-standard.js`

Kandungan minimum preset:
- shell `.table-responsive`
- `thead th`
- `tbody td`
- `tbody tr:hover`
- `dataTables_filter`
- `dataTables_length`
- `dataTables_info`
- `dataTables_paginate`
- dark mode variant

JS preset:
- DOM layout standard
- placeholder search standard
- styling `length select`
- helper append custom controls

## Fasa B: Pisahkan 2 kategori jadual

### Kategori 1: Data tables standard

Contoh:
- kumpulan pengguna
- senarai pengguna
- manuals
- access
- login activity
- audit trail

Standard yang patut sama:
- wrapper
- controls atas
- info/pagination bawah
- row hover
- header density

### Kategori 2: Settings/selection tables

Contoh:
- database settings
- language settings
- runtime summary tables

Ini tak perlu ikut DataTables penuh, tetapi patut ikut family surface yang sama:
- header tone
- border
- row padding
- hover

## Fasa C: Kurangkan override per-page

Prioriti tertinggi:
1. `senarai-pengguna.php`
2. `manage-manuals.php`
3. `access-matrix.php`
4. `profile.css`
5. `carian-pelajar.php`

Kaedah:
- buang override table shell dari page
- tinggal hanya override untuk kandungan unik page itu
- semua header/body/hover/info/pagination ambil dari preset bersama

## Fasa D: Standardkan `cell content density`

Wujudkan komponen bersama:
- `table-chip`
- `table-status-chip`
- `table-icon-action`
- `table-inline-meta`

Tanpa ini, page seperti `senarai-pengguna` akan sentiasa rasa lain walaupun jadual luar sama.

## Cadangan Implement Paling Praktikal

### Langkah 1

Bina preset CSS/JS standard berasaskan `kumpulan-pengguna`.

### Langkah 2

Migrasi page ini dahulu:
- `senarai-pengguna.php`
- `manage-manuals.php`
- `access-matrix.php`

### Langkah 3

Migrasi page sokongan:
- `profile`
- `carian-pelajar`

### Langkah 4

Samakan jadual settings (`tetapan-sistem`) sebagai kategori berasingan, bukan paksa jadi DataTables.

## Kesimpulan

Masalah utama bukan "table susah distandardkan", tetapi:
- terlalu banyak CSS bertindih
- terlalu banyak DOM/DataTables setup berbeza
- terlalu banyak presentation row yang dicipta semula per-page

Penyelesaian terbaik bukan tweak rawak pada setiap page, tetapi:

1. satu preset table rasmi berasaskan `kumpulan-pengguna`
2. satu preset JS DataTables rasmi
3. komponen content dalam cell yang juga standard
4. migration pass per-page untuk buang override lama
