# Additional Database Smoke Test Checklist

Tarikh: `2026-04-27`

Tujuan dokumen ini ialah untuk semakan pantas selepas setup atau sebelum rollout.

## Prasyarat

- Migration `updates/20260426_database_connection_registry.sql` sudah dijalankan
- Admin boleh buka `Tetapan Sistem > Database > Additional Connections`
- Sekurang-kurangnya satu additional connection telah didaftarkan

## Checklist UI Registry

### 1. Refresh list

- Buka tab `Additional Connections`
- Klik `Refresh`
- Pastikan senarai dimuatkan tanpa ralat SQL atau JS

Keputusan lulus:
- table reload berjaya
- tiada popup ralat

### 2. Add connection

- Klik `Add Connection`
- Pastikan modal boleh diklik sepenuhnya
- Isi satu config minimum yang sah
- Simpan

Keputusan lulus:
- modal tidak tenggelam di belakang overlay
- rekod baharu muncul dalam table

### 3. Edit connection

- Edit nama atau notes
- Simpan semula

Keputusan lulus:
- perubahan dipaparkan dalam table

### 4. Edit tanpa tukar password

- Edit connection sedia ada
- kosongkan field password
- simpan
- buat `Test Connection`

Keputusan lulus:
- password lama masih digunakan
- test masih boleh berjaya

### 5. Enable/Disable

- Disable satu connection
- cuba `Test`
- kemudian enable semula

Keputusan lulus:
- bila disabled, action runtime block dengan mesej yang jelas
- bila enabled semula, action boleh digunakan lagi

## Checklist Validation

### 6. Validation kosong

- Buka `Add Connection`
- terus klik `Save`

Keputusan lulus:
- mesej validation muncul di atas modal
- mesej tidak tersembunyi di belakang overlay

### 7. Validation env mismatch

- aktifkan `Supports development`
- hanya tambah env row `production`
- klik `Save`

Keputusan lulus:
- validation menolak konfigurasi itu

### 8. Validation duplicate env row

- tambah dua env row dengan kombinasi sama:
  - environment sama
  - os sama
  - driver sama

Keputusan lulus:
- validation menolak kombinasi duplicate

## Checklist Runtime

### 9. Test Connection

- Klik `Test`

Keputusan lulus:
- action berjaya atau gagal dengan mesej yang tepat
- `Last Test` dikemas kini

### 10. Inspect

- Klik `Inspect`

Keputusan lulus:
- popup memaparkan:
  - code
  - family
  - environment
  - configured driver
  - active driver
  - current DB
  - current user
  - server time
  - ping

### 11. Schema Preview

- Klik `Schema Preview`

Keputusan lulus:
- popup memaparkan senarai object schema

### 12. Data Preview

- Dalam `Schema Preview`, klik `Preview` pada satu object

Keputusan lulus:
- popup memaparkan sehingga 20 rekod pertama
- tiada write/update berlaku

## Checklist Compatibility

### 13. Main runtime tidak terganggu

Selepas semua ujian di atas:
- login masih berjalan
- page utama masih berjalan
- MySQL main masih normal
- Sybase staff/student mode masih normal

Keputusan lulus:
- tiada regression pada flow utama

### 14. Windows/Linux readiness

Jika deployment ada dua OS:
- semak sekurang-kurangnya satu connection tambahan pada Windows
- semak sekurang-kurangnya satu connection tambahan pada Linux

Keputusan lulus:
- resolver memilih driver variant yang sesuai
- action `Test`, `Inspect`, `Schema Preview` masih berjaya

## Checklist Audit

- Semak audit event untuk action:
  - `CREATE`
  - `UPDATE`
  - `ENABLE`
  - `DISABLE`
  - `TEST`
  - `INSPECT`
  - `SCHEMA_PREVIEW`
  - `OBJECT_PREVIEW`

Keputusan lulus:
- action berjaya direkod sebagai `SUCCESS`
- action gagal direkod sebagai `FAILURE`

## Sign-off

Platform dianggap lulus smoke test jika:
- registry UI stabil
- validation stabil
- runtime preview stabil
- 3 main database tidak terkesan
