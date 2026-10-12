# PHP 8.4 Upgrade — Fasa 1 hingga 3

Tarikh pelaksanaan: 7 Oktober 2026

Repositori: `/var/www/iqs-framework`

Baseline commit: `bc753b2450448990175a690a8a21e3fec26e63eb`

Versi aplikasi: `1.10.0`

## Status

| Fasa | Status | Catatan |
|---|---|---|
| 1 — Baseline dan rollback | Selesai | Bukti dan salinan konfigurasi disimpan di luar repo |
| 2 — Remediasi dual-runtime | Selesai | PHP 8.3 dan PHP 8.4 lint/test lulus |
| 3 — Pool PHP-FPM 8.4 khusus | Selesai | Pool aktif dan socket tersedia; Nginx masih menggunakan PHP 8.3 |

## Fasa 1 — Baseline dan rollback

Backup disimpan di:

```text
/var/www/_migration_backups/iqs-framework-php84-20261007-2115
```

Kandungan:

- konfigurasi Nginx IQS Framework sebelum cutover;
- PHP 8.3 `php.ini` dan `www.conf`;
- PHP 8.4 `php.ini` dan `www.conf`;
- commit dan version baseline;
- module list PHP 8.3 dan PHP 8.4;
- service state;
- SHA-256 konfigurasi yang disalin.

Trafik IQS Framework masih menggunakan:

```text
/run/php/php8.3-fpm.sock
```

PHP 8.3-FPM tidak dihentikan atau diubah.

## Fasa 2 — Remediasi kod

Perubahan:

1. `E_STRICT` dibuang daripada `error_reporting()`.
2. Semua panggilan CSV yang dikenal pasti kini menetapkan separator, enclosure, dan escape secara eksplisit.
3. Regression test `tests/php84-compat-regression.php` ditambah.

Keputusan bagi PHP 8.3.35 dan PHP 8.4.26:

- 314 fail aplikasi/test/tool lulus lint bagi setiap runtime;
- Additional database: 36/36;
- OneID SSO: 34/34;
- Password reset: lulus;
- Tetapan sistem: lulus;
- Custom theme: lulus;
- PHP 8.4 compatibility: lulus;
- login page PHP 8.4: HTTP 200;
- tiada `Deprecated`, warning, parse error, atau fatal error dalam HTTP smoke test selepas remediasi.

## Fasa 3 — Pool PHP 8.4 khusus

Template:

```text
docs/php84/iqs-framework84.pool.conf
```

Socket yang akan diwujudkan:

```text
/run/php/iqs-framework84.sock
```

Pool tersebut mengekalkan baseline penting PHP 8.3:

- memory limit 512 MB;
- execution time 120 saat;
- upload/post limit 100 MB;
- timezone Asia/Kuala_Lumpur;
- session lifetime 28,800 saat;
- secure dan HttpOnly session cookie;
- `exec`, `passthru`, `shell_exec`, `system`, `proc_open`, dan `popen` disabled;
- `E_ALL` logging dengan display errors dimatikan.

Template telah lulus:

```text
php-fpm8.4 configuration test is successful
```

Pemasangan telah dilaksanakan oleh pentadbir menggunakan:

```bash
sudo install -o root -g root -m 0644 \
  /var/www/iqs-framework/docs/php84/iqs-framework84.pool.conf \
  /etc/php/8.4/fpm/pool.d/iqs-framework84.conf

sudo php-fpm8.4 -t
sudo systemctl reload php8.4-fpm
sudo systemctl is-active php8.4-fpm
sudo test -S /run/php/iqs-framework84.sock
```

Verifikasi selepas pemasangan:

```text
/etc/php/8.4/fpm/pool.d/iqs-framework84.conf  root:root       0644
/run/php/iqs-framework84.sock                 www-data:www-data 0660
POOL_TEMPLATE_MATCH                           YES
php8.4-fpm                                    active
iqs-framework84 workers                       2
```

Fasa 3 selesai sepenuhnya tetapi trafik masih kekal pada PHP 8.3 kerana Nginx belum diubah.

## Checkpoint sebelum Fasa 4

Jangan ubah Nginx sehingga perkara berikut disahkan:

- pool config terpasang;
- `php-fpm8.4 -t` lulus dengan authority root;
- `/run/php/iqs-framework84.sock` wujud;
- socket dimiliki `www-data:www-data` dan mode `0660`;
- PHP 8.4-FPM aktif;
- backup Fasa 1 masih tersedia.

Fasa 4 kelak hanya perlu menukar dua `fastcgi_pass` dalam site IQS Framework kepada socket khusus tersebut, menjalankan `nginx -t`, dan reload Nginx. Downstream tidak terlibat.
