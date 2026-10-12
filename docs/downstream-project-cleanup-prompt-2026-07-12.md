# Prompt Cleanup Projek Downstream IQS Framework

Dokumen ini menyimpan prompt rujukan untuk mengaudit dan membersihkan projek downstream yang menggunakan IQS Framework. Gunakan prompt ini secara berasingan bagi setiap projek dan gantikan nilai `PROJEK_AKTIF` terlebih dahulu.

## Prompt untuk Codex

```text
PROJEK_AKTIF = NAMA_REPOSITORY_DOWNSTREAM

Arahan skop:

- Semua operasi hanya dibenarkan dalam `/var/www/app/${PROJEK_AKTIF}` dan subfoldernya.
- Jangan membaca, mencari, mengubah, commit, push atau mencadangkan perubahan kepada repository lain.
- Sebelum membuat perubahan, sahkan semua fail sasaran berada di bawah root projek tersebut.
- Jangan ubah konfigurasi global WSL, Ubuntu, Nginx, PHP atau Git.
- Jangan jalankan git commit, push, pull, merge atau rebase tanpa arahan jelas.
- Jika penyelesaian memerlukan perubahan di luar repository aktif, hentikan dan laporkan dahulu.
- Pelihara semua perubahan sedia ada yang tidak berkaitan dalam working tree.

Objektif:

Audit projek downstream ini untuk cleanup yang sama seperti IQS Framework. Jangan terus memadam fail. Jalankan audit read-only dahulu, terangkan kegunaan semasa setiap fail, cari semua rujukan dalam repository, nilai kesan pemadaman, dan bahagikan dapatan kepada:

1. Selamat dibuang.
2. Perlu dikekalkan.
3. Memerlukan pengesahan kerana masih digunakan atau telah disesuaikan.

Selepas laporan audit, tunggu kelulusan saya sebelum membuat sebarang perubahan.

Skop audit cleanup:

1. Docker lama

Semak sama ada projek masih menggunakan Docker, Docker Compose, Apache container, PHP container atau sijil SSL development berikut:

- `.dockerignore`
- `Dockerfile`
- `docker-compose.yml`
- `docker/apache/iqs-framework.dev.conf`
- `docker/apache/servername.conf`
- `docker/php.ini`
- `docker/ssl/iqs-framework.dev.crt`
- `docker/ssl/iqs-framework.dev.key`

Cari rujukan Docker dalam semua kod, skrip dan dokumentasi repository. Jika projek kini berjalan terus melalui WSL + Nginx/PHP-FPM dan Docker benar-benar tidak digunakan, cadangkan pemadaman fail tersebut. Kenal pasti juga rujukan path/container yang perlu dikemas kini kepada runtime semasa. Jangan buang Docker jika projek downstream masih menggunakannya.

2. Fail metadata runtime lama

Audit:

- `.file_hashes`
- `.last_collect_time`
- `.last_update_check`

`.file_hashes` dan `.last_collect_time` boleh dibuang hanya jika tiada skrip aktif menggunakannya. `.last_update_check` digunakan oleh `update-files.sh`, jadi jangan padam secara fizikal jika skrip itu masih aktif; nilai sama ada ia patut kekal sebagai fail runtime yang tidak dijejak Git dan dimasukkan ke `.gitignore`.

3. Konfigurasi VS Code

Audit `.vscode/launch.json` dan seluruh folder `.vscode`. Jika ia hanya menyediakan konfigurasi PHP/Xdebug yang tidak digunakan, cadangkan pemadaman dan tambah `.vscode/` ke `.gitignore`. Kekalkan jika pasukan downstream masih berkongsi konfigurasi debug tersebut.

4. Konfigurasi npm lama

Audit:

- `package.json`
- `package-lock.json`
- `node_modules/`
- konfigurasi Tailwind, PostCSS, Vite atau Webpack
- arahan `npm`, `npx` atau Node dalam CI, deployment, dokumentasi dan skrip projek

Jika `package.json` masih membawa metadata projek lama, hanya mempunyai skrip ujian placeholder, tiada build pipeline dan tiada penggunaan npm sebenar, cadangkan pemadaman `package.json` dan `package-lock.json` bersama-sama. Jangan padam jika projek downstream mempunyai build frontend aktif.

5. Nama runtime environment

Semak baris komen pada `.env` dan `.env.example`. Jika masih tertulis:

`# e-Base Runtime Environment`

cadangkan perubahan kepada:

`# IQS-Framework Runtime Environment`

Jangan paparkan nilai rahsia daripada `.env` dalam laporan atau output. Pastikan `.env` kekal tidak dijejak Git.

6. Audit `.gitignore`

Bandingkan `.gitignore` dengan keseluruhan tree, status Git dan fail runtime sebenar. Pertimbangkan struktur berikut, tetapi sesuaikan dengan penggunaan projek dan jangan gantikan secara membuta tuli:

# Dependencies
/vendor/
node_modules/

# Environment and secrets
.env
.env.*
!.env.example
!*.env.example

# Runtime files
*.log
*.sqlite
/.last_update_check
app/cache/
public/cache/

# User uploads
/public/uploads/**
!/public/uploads/**/
!/public/uploads/**/.htaccess

# Deployment staging
updates/
_updates_archive/
public_backup_*

# IDE and workspace
.idea/
.vscode/
*.code-workspace

# OS files
.DS_Store
Thumbs.db
Desktop.ini

# Temporary and backup files
*.swp
*.swo
*~
*.bak
*.backup
*.tmp
*.temp
*.orig
*.rej

# Test and coverage artifacts
.phpunit.result.cache
.phpunit.cache/
coverage/

Pastikan `*.env.example` kekal boleh dijejak. Pastikan kandungan upload pengguna diabaikan tetapi fail keselamatan `.htaccess` boleh dijejak. Laporkan fail yang sudah dijejak tetapi kini sepadan dengan ignore rules kerana menambah `.gitignore` sahaja tidak mengeluarkannya daripada Git index. Jangan jalankan `git rm --cached` tanpa kelulusan jelas.

7. Audit `.sync-update-ignore`

Kekalkan pengecualian berikut jika projek downstream menggunakan dashboard tersuai dan tidak mahu sync framework menindihnya:

- `public/pages/dashboard.php`
- `public/controllers/DashboardController.php`
- `public/assets/js/pages/demo.dashboard.js`

Buang rujukan workspace lapuk jika ada:

- `iqs-framework.code-workspace`
- `iqs-framework.code-workspcae`

Sahkan `public/lang/custom/*` masih dilindungi secara automatik oleh skrip sync. Jangan anggap fail dashboard boleh dikemas kini secara global kerana programmer downstream mungkin telah menyesuaikannya.

8. Log sync yang tidak digunakan

Audit `sync-updates.sh` dan fail berkaitan untuk rujukan kepada log lama seperti `sync.log` atau `conflict.log`. Jika skrip tidak lagi menghasilkan atau menggunakan log tersebut, cadangkan membuang pembolehubah, cleanup atau rujukan lapuk sahaja. Jangan mengubah tingkah laku sync yang masih aktif.

9. Dokumentasi dan rujukan lapuk

Selepas menentukan fail yang selamat dibuang, cari semua rujukan kepada Docker, npm, VS Code, metadata lama dan runtime lama dalam `README.md`, `CHANGELOG.md`, `docs/`, skrip serta kod. Cadangkan kemas kini hanya apabila rujukan itu benar-benar menjadi tidak tepat selepas cleanup. Jangan menyalin perubahan dokumentasi IQS Framework secara membuta tuli ke projek downstream.

Apabila saya meluluskan pelaksanaan:

- Buat hanya perubahan yang telah diluluskan.
- Gunakan pemadaman biasa untuk fail source/config yang disahkan tidak digunakan.
- Jangan padam data upload fizikal tanpa arahan khusus.
- Jangan keluarkan fail tracked daripada Git index tanpa arahan khusus.
- Semak semula semua rujukan selepas pemadaman.
- Jalankan pemeriksaan sintaks atau ujian berkaitan dalam skop projek.
- Jangan commit secara automatik.

Selepas perubahan, wajib paparkan:

- Senarai penuh fail diubah dan dipadam.
- Ringkasan perubahan.
- Pemeriksaan atau ujian yang dijalankan.
- Risiko atau tindakan susulan yang masih diperlukan.
- Cadangan mesej commit Git yang jelas dan ringkas.
```

## Rekod cleanup pada IQS Framework

Prompt di atas dirumus daripada cleanup berikut yang telah dibuat pada repository IQS Framework:

- Membuang setup Docker development yang tidak lagi digunakan selepas runtime berpindah kepada WSL + Nginx/PHP-FPM.
- Membuang `.file_hashes` dan `.last_collect_time` yang tidak lagi digunakan.
- Membersihkan rujukan log sync lama dalam `sync-updates.sh`.
- Membuang `.vscode/launch.json` untuk konfigurasi PHP/Xdebug yang tidak digunakan.
- Menukar label environment daripada e-Base kepada IQS-Framework dalam `.env` dan `.env.example`; hanya `.env.example` dijejak Git.
- Membuang `package.json` dan `package-lock.json` kerana tiada build pipeline npm yang aktif.
- Melengkapkan `.gitignore` untuk environment, runtime, upload, IDE, OS, fail sementara dan artefak ujian.
- Membuang pengecualian workspace lapuk daripada `.sync-update-ignore` sambil mengekalkan tiga fail dashboard sebagai pengecualian sync yang disengajakan.

Setiap projek downstream mesti diaudit secara berasingan kerana penggunaan Docker, npm, dashboard dan fail upload mungkin berbeza.
