# Sybase Multi-Domain Legacy Cleanup

Dokumen ini merangkum baki komponen `single active Sybase` yang masih dikekalkan sebagai compatibility layer selepas Fasa 5.

## Status Semasa

Runtime utama sistem kini sudah berasaskan:

- `SYBASE_ENVIRONMENT`
- `SYBASE_OPERATIONAL_MODE`
- resolver domain:
  - `Database::pdoSybaseStaff()`
  - `Database::pdoSybaseStudent()`

Model lama masih tinggal untuk keserasian sementara:

- `SYBASE_ACTIVE_BASE`
- `Database::pdoSybaseActive()`
- `get_active_sybase_pdo()`
- `configuration/config_db_active.json`

## Baki Legacy Yang Masih Wujud

### 1. Compatibility Layer Runtime

- `app/includes/init.php`
  - masih define `SYBASE_ACTIVE_BASE`
  - masih baca `config_db_active.json` sebagai fallback

- `app/includes/functions-db.php`
  - `get_active_sybase_key()`
  - `get_active_sybase_pdo()`
  - helper lama `getSybaseEHRMDB()` / `getSybaseEHRMDB_DEV()` masih bergantung pada cache flag lama

- `app/classes/Database.php`
  - `getInstance('sybase_active')`
  - `Database::pdoSybaseActive()`

### 2. Config / Persistence Layer

- `app/classes/Config.php`
  - `setSybaseActiveBase()`
  - `getSybaseActiveBase()`

- `app/controllers/TetapanSistemController.php`
  - `activateSybaseBase()`
  - sync ke `SYBASE_ACTIVE_BASE`
  - baca / tulis `config_db_active.json`

- `app/configuration/config_db_active.json`
  - masih wujud untuk fallback lama

### 3. Surface Lama Yang Sudah Dikurangkan

- `app/includes/topbar.php`
  - tidak lagi papar `SYBASE_ACTIVE_BASE`

- `app/controllers/LoginController.php`
  - tidak lagi menulis `$_SESSION['SYBASE_ACTIVE_BASE']`

## Cadangan Cleanup Akhir

### Langkah A: Retire JSON active-db

Buang kebergantungan kepada:

- `app/configuration/config_db_active.json`
- `$GLOBALS['sybase_active']`

Prasyarat:

- `Tetapan Sistem > Database` telah stabil dengan `Environment` + `Operational Mode`
- tiada lagi flow yang bergantung pada flag `ehrmdb/ehrmdb_dev`

### Langkah B: Retire active-base persistence

Buang secara berperingkat:

- `Config::setSybaseActiveBase()`
- `Config::getSybaseActiveBase()`
- `TetapanSistemController::activateSybaseBase()`
- penulisan `SYBASE_ACTIVE_BASE` ke DB/session

Prasyarat:

- semua caller penting sudah guna resolver domain

### Langkah C: Retire active Sybase resolver

Buang:

- `Database::pdoSybaseActive()`
- `get_active_sybase_pdo()`
- `getInstance('sybase_active')`

Prasyarat:

- tiada lagi caller code yang baca `sybase_active`

### Langkah D: Retire legacy staff alias jika perlu

Jika mahu architecture lebih bersih, anda boleh pertimbang buang alias:

- `sybase_ehrmdb_*`
- `sybase_ehrmdb_dev_*`

dan kekalkan hanya:

- `sybase_staff_prod_*`
- `sybase_staff_dev_*`
- `sybase_student_prod_*`
- `sybase_student_dev_*`

Ini optional. Alias lama masih berguna jika anda mahu migration lebih lembut.

## Risiko Jika Cleanup Dibuat Terlalu Awal

- dashboard staf boleh gagal load
- sync staf / add user boleh rosak jika ada caller legacy tertinggal
- detection development mode boleh mengelirukan jika masih bergantung pada `SYBASE_ACTIVE_BASE`

## Cadangan Susunan Kerja Seterusnya

1. Kekalkan architecture semasa sebagai stable baseline.
2. Pilih sama ada mahu:
   - `A. Cleanup akhir legacy`, atau
   - `B. Bina feature pelajar production sebenar dahulu`
3. Jika pilih cleanup akhir:
   - mula dengan Langkah A
   - verify
   - baru ke Langkah B dan C

