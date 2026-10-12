# PHP 8.4 Upgrade — Fasa 4 Cutover Staging

Tarikh pelaksanaan: 7 Oktober 2026

Repositori: `/var/www/iqs-framework`

Persekitaran: staging (`AppsStagingv1`)

Versi aplikasi: `1.10.0`

Runtime sasaran: PHP `8.4.26`

## Status

**Selesai dan diterima.** Trafik web IQS Framework kini menggunakan pool PHP-FPM 8.4 khusus melalui:

```text
/run/php/iqs-framework84.sock
```

PHP 8.3-FPM tidak dihentikan supaya rollback segera masih tersedia sepanjang tempoh pemerhatian.

## Artifak cutover

- Template Nginx PHP 8.4: `docs/php84/iqs-framework.nginx-php84.conf`
- Template probe runtime sementara: `docs/php84/runtime-version-probe.php`
- Skrip cutover dan auto-rollback: `tools/php84-cutover-iqs-framework.sh`
- Konfigurasi rollback:
  `/var/www/_migration_backups/iqs-framework-php84-20261007-2115/nginx/iqs-framework.phase4-before`

Probe runtime menggunakan nama rawak, dipasang hanya sepanjang pengesahan, dan dipadam oleh cleanup skrip selepas selesai atau gagal.

## Rekod pelaksanaan

Percubaan awal menerima PHP `8.3.35` sejurus selepas `systemctl reload nginx`. Skrip melaksanakan rollback automatik kepada konfigurasi PHP 8.3 dan `nginx -t` kekal lulus.

Semakan log menunjukkan cutover reload dan rollback reload berlaku dalam jarak satu saat. Puncanya ialah pengesahan dijalankan sebelum graceful/asynchronous Nginx reload selesai menggantikan worker lama; ia bukan kegagalan PHP 8.4.

Skrip kemudian diperkukuh untuk:

1. memastikan konfigurasi aktif Nginx mengandungi kedua-dua rujukan socket PHP 8.4;
2. membuat sambungan HTTP/1.1 baharu tanpa cache;
3. menunggu dan memeriksa runtime sehingga 30 saat;
4. menerima cutover hanya apabila probe memulangkan tepat `8.4.26`;
5. menjalankan HTTP login smoke test selepas runtime disahkan;
6. mengekalkan rollback automatik bagi sebarang kegagalan.

Pelaksanaan semula selesai dengan output:

```text
SUCCESS: IQS Framework kini menggunakan PHP 8.4.26.
ROLLBACK CONFIG: /var/www/_migration_backups/iqs-framework-php84-20261007-2115/nginx/iqs-framework.phase4-before
```

## Pengesahan pasca-cutover

| Pemeriksaan | Keputusan |
|---|---|
| Nginx handler utama | `/run/php/iqs-framework84.sock` |
| Nginx custom 404 handler | `/run/php/iqs-framework84.sock` |
| `php8.4-fpm` | `active` |
| `nginx` | `active` |
| Pemilik socket | `www-data:www-data` |
| Mode socket | `0660` |
| Runtime probe ketika cutover | `8.4.26` |
| Login page | HTTP 200 |
| Login page HTML | Tajuk halaman ditemui |

## Acceptance dan observation

Pentadbir mengesahkan semua feature acceptance test berjaya selepas cutover. Pemeriksaan pasca-cutover turut mengesahkan:

- tiada entri baharu dalam Nginx error log IQS Framework selepas cutover berjaya;
- tiada fatal error, deprecation, warning aplikasi, `TypeError`, atau `ValueError` dikesan dalam log yang boleh dibaca;
- Nginx dan PHP 8.4-FPM kekal aktif;
- PHP 8.3-FPM kekal aktif sebagai laluan rollback;
- baseline README dikemas kini kepada PHP 8.4.26 selepas penerimaan staging.

Keenam-enam regression suite turut dijalankan semula pada PHP 8.3.35 dan PHP 8.4.26. Kedua-dua runtime lulus:

- Additional database: 36/36;
- OneID SSO: 34/34;
- password reset flow;
- tetapan sistem save-sync;
- custom theme;
- PHP 8.4 compatibility guard.

Acceptance staging ditutup sebagai **lulus** berdasarkan ujian feature oleh pentadbir dan pengesahan operasi di atas.

## Kedudukan rollback

Rollback masih boleh dilakukan dengan memulihkan konfigurasi yang direkodkan di atas, diikuti:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Selepas rollback, runtime probe perlu mengesahkan PHP 8.3 dan login smoke test perlu diulang. Jangan hentikan PHP 8.3-FPM sehingga observation window dan acceptance test integrasi selesai.

## Pemerhatian berterusan

Sepanjang observation window, pantau khususnya:

- login, logout, session dan OneID SSO;
- MySQL, Sybase DBLIB/ODBC dan additional database;
- SMTP, password reset dan template e-mel;
- upload/download fail dan penjanaan PDF;
- custom theme dan System Settings;
- log bagi fatal error, `TypeError`, `ValueError`, deprecation dan warning aplikasi.

Cutover ini hanya melibatkan IQS Framework staging. Runtime downstream tidak diubah.
