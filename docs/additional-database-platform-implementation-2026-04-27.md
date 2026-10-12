# Additional Database Platform Implementation

Tarikh: `2026-04-27`

## Status Ringkas

Platform additional database untuk sistem ini sudah siap pada tahap **platform-ready**.

Sudah siap:
- Main runtime kekal terlindung untuk `mysql main`, `sybase staff`, dan `sybase student`
- `MAIN_DB_ENVIRONMENT` untuk MySQL main
- Resolver pusat untuk main dan additional connections
- Auto resolve Windows/Linux untuk variant driver yang berkaitan
- Registry backend untuk additional connections
- UI `Additional Connections` dalam `Tetapan Sistem > Database`
- CRUD metadata additional connection
- `Enable/Disable`
- `Test Connection`
- `Inspect`
- `Schema Preview`
- `Data Preview`
- Runtime usage melalui:
  - `DatabaseManager->additional($code, $environment = null)`
  - `Database::pdoAdditional($code, $environment = null)`
  - `Database::getInstance('dbx_xxx')->getConnection()`

Belum dibuat:
- Integrasi kepada modul perniagaan sebenar
- Report/lookup/sync production use-case yang bergantung pada additional connection tertentu

Sebab belum dibuat:
- Tiada modul sasaran sebenar yang dipilih buat masa ini

## Prinsip Reka Bentuk

Sistem ini menggunakan model:
- `protected core`
- `extensible additional registry`

Maksudnya:
- 3 database utama sistem tidak boleh diganggu oleh registry tambahan
- additional connections hanya untuk feature sokongan, reference, report, integration, atau transaksi khas
- failure pada additional connection tidak sepatutnya merosakkan bootstrap sistem utama

## API Runtime

### Main runtime

```php
$mysql = (new DatabaseManager())->mainMysql();
$staff = (new DatabaseManager())->mainSybaseStaff();
$student = (new DatabaseManager())->mainSybaseStudent();
```

### Additional runtime

```php
$pdo = (new DatabaseManager())->additional('dbx_mysql_reporting');
```

Atau:

```php
$pdo = Database::pdoAdditional('dbx_mysql_reporting');
```

Atau:

```php
$pdo = Database::getInstance('dbx_mysql_reporting')->getConnection();
```

## Rules Penting

- Jangan guna additional connection untuk gantikan `mysql main`
- Jangan sambungkan login/auth/bootstrap kepada additional connection
- Guna additional connection melalui service/repository khusus, bukan tabur panggilan DB terus dalam banyak controller
- Tambah guard pada feature:
  - jika connection disabled
  - jika connection test gagal
  - jika preview/query gagal
- Untuk feature baharu, fail hanya pada feature itu, bukan seluruh sistem

## Source of Truth

### Main runtime

Main runtime masih bergantung kepada:
- `public/configuration/db_config.php`
- `Config`
- runtime constants/session/env

### Additional registry

Additional connection registry dibaca daripada:
- `tbl_m_db_connection`
- `tbl_m_db_connection_env`

Resolver runtime akan memuatkan registry tambahan ini secara automatik melalui `DatabaseManager`.

## OS Behavior

### MySQL

- lazimnya OS-neutral
- menggunakan PDO MySQL

### Sybase

- resolve variant ikut OS dan driver availability
- Windows biasanya cuba `odbc` dahulu jika tersedia
- Linux biasanya cuba `dblib` dahulu jika tersedia

### MSSQL

- support asas sudah disediakan dalam resolver
- boleh pilih `sqlsrv`, `odbc`, atau `dblib` ikut config/OS/driver availability

## Feature Yang Sudah Guna Additional Runtime

Semua ini berada di `Tetapan Sistem > Database > Additional Connections`:

- `Test Connection`
  - buka connection sebenar dan jalankan `select 1`
- `Inspect`
  - baca metadata sambungan hidup
- `Schema Preview`
  - baca senarai objek schema read-only
- `Data Preview`
  - baca sehingga 20 rekod pertama dari object yang dipilih

Ini bermaksud additional connection bukan sekadar disimpan dalam config, tetapi sudah digunakan oleh feature runtime sebenar.

## Fail Utama Yang Terlibat

Core runtime:
- `public/classes/Database.php`
- `public/classes/DatabaseManager.php`
- `public/classes/DatabaseRuntimeConfig.php`
- `public/classes/DatabaseConnectionDefinition.php`
- `public/classes/DatabaseConnectionRegistry.php`
- `public/classes/DatabaseConnectionResolver.php`
- `public/classes/DatabaseConnectionFactory.php`

Registry backend:
- `public/classes/DatabaseConnectionRepository.php`
- `public/classes/DatabaseConnectionValidator.php`
- `public/controllers/TetapanSistemController.php`

UI:
- `public/pages/tetapan-sistem.php`
- `public/pages/partials/tetapan-sistem/tab-database.php`
- `public/assets/js/pages/tetapan-sistem.js`
- `public/assets/css/pages/tetapan-sistem.css`

Migration:
- `updates/20260426_database_connection_registry.sql`

## Cadangan Bila Mahu Guna Pada Modul Sebenar

Urutan terbaik:

1. Pilih satu modul read-only dahulu
2. Buat repository/service khusus
3. Panggil `DatabaseManager->additional('dbx_xxx')`
4. Tangani error pada boundary feature sahaja
5. Uji pada Windows dan Linux jika deployment dua-dua OS digunakan

## Nota Penutupan

Pada tahap semasa:
- platform additional DB sudah siap dan boleh digunakan
- tetapi belum ada modul perniagaan tetap yang bergantung padanya

Itu adalah keadaan yang dijangka, bukan defect.
