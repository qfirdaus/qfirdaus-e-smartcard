# Page Template Generator Folder Structure

Tarikh: 2026-03-27

## Tujuan

Dokumen ini menetapkan struktur folder sebenar yang dicadangkan untuk membina ciri `Page Template Generator` dalam repo `e-Base` semasa.

Objektif utama struktur ini:

- padan dengan susun atur repo semasa
- tidak mengganggu folder runtime yang sudah wujud
- jelas membezakan antara:
  - template runtime
  - stub generator
  - output generated files

## Pemerhatian Repo Semasa

Struktur utama semasa:

- `docs/`
- `public/`
  - `ajax/`
  - `assets/`
  - `classes/`
  - `controllers/`
  - `includes/`
  - `lang/`
  - `pages/`
  - `templates/`

Pemerhatian penting:

1. `public/templates/` sudah wujud dan sedang digunakan untuk runtime template seperti mail:
   - `public/templates/mail/`

2. `public/assets/js/pages/` dan `public/assets/css/pages/` sudah menjadi lokasi semasa untuk asset page-specific.

3. `public/pages/`, `public/controllers/`, `public/lang/` ialah lokasi output sebenar yang patut digunakan oleh generator.

## Keputusan Struktur

### Prinsip

Jangan campurkan stub generator dengan `public/templates/` yang sedang berfungsi sebagai runtime template folder.

### Keputusan yang dicadangkan

Letakkan semua template generator di **root-level `templates/`**, bukan di dalam `public/`.

Sebab:

- lebih bersih dari sudut architecture
- tidak expose stub generator di bawah web root
- tidak mengelirukan dengan mail/runtime templates
- lebih mudah diurus sebagai asset pembangunan platform

## Struktur Folder Yang Dicadangkan

```text
/var/www/app\e-base\
  docs\
  public\
    ajax\
    assets\
      css\
        pages\
      js\
        pages\
    classes\
    controllers\
    includes\
    lang\
    pages\
    templates\
      mail\
  templates\
    generator\
      page-generator\
      registry\
        templates.json
        features.json
      stubs\
        page\
          blank\
            page.stub.php
            controller.stub.php
            js.stub.js
            css.stub.css
            lang.ms.stub.php
            lang.en.stub.php
            meta.json
          datatable\
            page.stub.php
            controller.stub.php
            js.stub.js
            css.stub.css
            lang.ms.stub.php
            lang.en.stub.php
            meta.json
        partials\
          page\
            layout.stub.php
            datatable.stub.php
            filterbar.stub.php
            swal.stub.php
          controller\
            base-controller.stub.php
            datatable-loader.stub.php
          lang\
            page-common.ms.stub.php
            page-common.en.stub.php
      examples\
        blank-sample.md
        datatable-sample.md
```

## Fungsi Setiap Folder

### `public/templates/`

Kekal untuk runtime template semasa.

Contoh:

- mail HTML/text templates

Folder ini **bukan** tempat untuk stub generator baru.

### `templates/generator/page-generator/registry/`

Simpan metadata generator:

- `templates.json`
- `features.json`

Tujuan:

- source of truth untuk template yang available
- source of truth untuk feature yang boleh dipilih

### `templates/generator/page-generator/stubs/page/`

Simpan stub ikut template type.

Contoh awal:

- `blank`
- `datatable`

Setiap template ada fail sendiri supaya senang versioning dan maintain.

### `templates/generator/page-generator/stubs/partials/`

Simpan blok reusable.

Contoh:

- datatable markup
- base layout include
- standard JS block
- swal block

Ini penting supaya generator tidak bergantung pada giant template tunggal.

### `templates/generator/page-generator/examples/`

Optional tetapi berguna.

Tujuan:

- tunjuk contoh hasil generation
- bantu review template output
- bantu onboarding platform team

## Lokasi Output Fail Generated

Output sebenar generator patut kekal pada struktur semasa `e-Base`:

- page:
  - `public/pages/<slug>.php`
- controller:
  - `public/controllers/<ClassName>Controller.php`
- js:
  - `public/assets/js/pages/<slug>.js`
- css:
  - `public/assets/css/pages/<slug>.css`
- translation:
  - `public/lang/ms.php`
  - `public/lang/en.php`

Optional phase kemudian:

- AJAX endpoints:
  - `public/ajax/<slug>-*.php`

## Kenapa Bukan Dalam `public/templates/`

Walaupun folder itu sudah wujud, saya tidak syorkan guna semula untuk generator kerana:

1. ia sudah membawa maksud `runtime view template`
2. stub generator bukan artefak runtime
3. generator stub lebih baik dianggap sebagai asset pembangunan, bukan asset web
4. pencampuran ini akan mengelirukan team kemudian

Ringkasnya:

- `public/templates/` = runtime templates
- `templates/generator/page-generator/` = generator stubs

## Struktur Fail Registry Yang Dicadangkan

### `templates.json`

Patut menyimpan:

- template key
- label
- description
- version
- generated files
- supported options

### `features.json`

Untuk Phase 1, file ini boleh sangat minimum atau kosong jika belum digunakan penuh.

Tetapi saya syorkan tetap diwujudkan awal supaya architecture tidak perlu berubah besar kemudian.

## Struktur Naming Template

### Template key

Cadangan:

- `blank`
- `datatable`
- `crud-modal`
- `form`
- `dashboard`

### Version

Setiap template patut ada `meta.json`:

- `template_key`
- `template_version`
- `description`
- `generated_files`

Ini akan bantu audit kemudian.

## Struktur Service Yang Padan Dengan Folder Ini

Untuk folder structure ini, service yang dicadangkan nanti boleh resolve path seperti berikut:

- registry path:
  - `/var/www/app\e-base\templates\generator\page-generator\registry\templates.json`
- template root:
  - `/var/www/app\e-base\templates\generator\page-generator\stubs\page\`
- partial root:
  - `/var/www/app\e-base\templates\generator\page-generator\stubs\partials\`

Ini lebih kemas berbanding hardcode banyak path dalam controller.

## Cadangan Practical Start

Saya syorkan mula dengan folder ini dahulu:

```text
templates\
  generator\
    page-generator\
      registry\
        templates.json
      stubs\
        page\
          blank\
          datatable\
        partials\
          page\
          controller\
          lang\
```

Folder lain seperti `examples/` dan `features.json` boleh ditambah sejurus selepas baseline jalan.

## Final Recommendation

Untuk repo `e-Base` semasa, struktur folder terbaik ialah:

- kekalkan `public/templates/` untuk runtime template sedia ada
- bina `templates/generator/page-generator/` di root repo untuk semua stub generator baru

Ini paling bersih, paling selamat, dan paling mudah diselenggara dalam jangka panjang.
