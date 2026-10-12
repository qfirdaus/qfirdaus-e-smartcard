# Page Template Generator Batch 1 Services

Tarikh: 2026-03-27

## Tujuan

Dokumen ini menerangkan:

1. struktur kandungan `templates.json`
2. tanggungjawab setiap service dalam Batch 1
3. checklist kerja implementasi untuk setiap komponen

Dokumen ini menjadi rujukan terus semasa mula membina generator.

## Struktur Kandungan `templates.json`

Untuk Batch 1, `templates.json` tidak perlu kompleks.

Ia hanya perlu menyimpan metadata minimum untuk dua template:

- `blank`
- `datatable`

### Struktur logik yang dicadangkan

Setiap entri patut ada medan berikut:

- `key`
- `label`
- `description`
- `version`
- `page_stub`
- `controller_stub`
- `meta_stub`
- `outputs`

### Maksud setiap medan

#### `key`

Identifier dalaman template.

Contoh:

- `blank`
- `datatable`

#### `label`

Nama paparan dalam UI.

Contoh:

- `Blank Page`
- `DataTable Page`

#### `description`

Penerangan ringkas untuk developer.

Contoh:

- `Page asas dengan title, breadcrumb, dan content shell`
- `Page senarai standard dengan struktur DataTable`

#### `version`

Versi template.

Untuk Batch 1:

- `1`

#### `page_stub`

Path relatif ke fail page stub.

#### `controller_stub`

Path relatif ke fail controller stub.

#### `meta_stub`

Path relatif ke `meta.json` template.

#### `outputs`

Senarai fail yang template ini akan hasilkan.

Untuk Batch 1:

- `page`
- `controller`

### Contoh isi logik

#### Template `blank`

- `key`: `blank`
- `label`: `Blank Page`
- `description`: `Page asas dengan title, breadcrumb, dan content shell`
- `version`: `1`
- `page_stub`: `stubs/page/blank/page.stub.php`
- `controller_stub`: `stubs/page/blank/controller.stub.php`
- `meta_stub`: `stubs/page/blank/meta.json`
- `outputs`: `page`, `controller`

#### Template `datatable`

- `key`: `datatable`
- `label`: `DataTable Page`
- `description`: `Page senarai standard dengan DataTable baseline`
- `version`: `1`
- `page_stub`: `stubs/page/datatable/page.stub.php`
- `controller_stub`: `stubs/page/datatable/controller.stub.php`
- `meta_stub`: `stubs/page/datatable/meta.json`
- `outputs`: `page`, `controller`

## Meta Template (`meta.json`)

Setiap template juga patut ada `meta.json` sendiri.

Tujuan:

- simpan info tambahan per template
- ruang untuk scale tanpa menambah complexity awal pada registry

### Medan minimum

- `template_key`
- `template_version`
- `baseline_reference`
- `notes`

### Contoh logik

#### Blank

- `template_key`: `blank`
- `template_version`: `1`
- `baseline_reference`: `public/pages/dashboard.php`
- `notes`: `gunakan shell header + breadcrumb sahaja`

#### DataTable

- `template_key`: `datatable`
- `template_version`: `1`
- `baseline_reference`: `public/pages/senarai-pengguna.php`
- `notes`: `ambil structure content dari tab Akses Staf sahaja`

## Service Responsibilities

## 1. `TemplateRegistryService.php`

### Tujuan

Menjadi pintu masuk untuk membaca senarai template yang tersedia.

### Tanggungjawab

- baca `templates.json`
- parse metadata template
- return semua template
- return satu template ikut `key`
- reject key yang tidak wujud

### Tidak patut buat

- render stub
- generate fail
- normalize nama page

### Checklist implementasi

- define template root/registry path
- load file registry
- validate file wujud
- validate JSON sah
- expose method:
  - `getAllTemplates()`
  - `getTemplate(string $key)`

## 2. `TemplateResolverService.php`

### Tujuan

Resolve metadata template kepada fail stub sebenar.

### Tanggungjawab

- terima template key
- ambil record dari registry
- resolve full path untuk:
  - page stub
  - controller stub
  - meta stub
- validate fail stub wujud
- return satu object/array resolved template

### Tidak patut buat

- generate fail output
- write file

### Checklist implementasi

- gunakan `TemplateRegistryService`
- build path penuh dari root:
  - `/var/www/app\e-base\templates\generator\page-generator\`
- semak semua stub wajib wujud
- return payload resolved

## 3. `FileGenerationService.php`

### Tujuan

Enjin utama untuk generate fail output.

### Tanggungjawab

- normalize nama page
- derive slug
- derive controller class name
- derive key prefix
- derive target output path
- semak collision fail
- baca stub content
- replace placeholder
- tulis fail output
- return summary result

### Tidak patut buat

- baca UI request secara terus
- render page generator

### Checklist implementasi

- helper `normalizeSlug()`
- helper `buildControllerClassName()`
- helper `buildKeyPrefix()`
- helper `buildOutputPaths()`
- collision checker
- stub renderer
- file writer
- generation summary result

## 4. `TemplateGeneratorController.php`

### Tujuan

Hubungkan UI generator dengan service backend.

### Tanggungjawab

- render page generator
- sediakan template list ke UI
- terima request preview
- terima request generate
- panggil service yang sesuai
- return response ringkas dan jelas

### Tidak patut buat

- logic stub parsing yang berat
- direct file generation logic

### Checklist implementasi

- load available templates dari registry
- bind form input
- validate input minimum
- action:
  - `preview`
  - `generate`

## Placeholder Strategy for Batch 1

Untuk Batch 1, placeholder boleh sangat minimum.

### Page stub

Placeholder minimum:

- `{{PAGE_TITLE_MS}}`
- `{{PAGE_TITLE_EN}}`
- `{{PAGE_SLUG}}`
- `{{PAGE_KEY_PREFIX}}`
- `{{CONTROLLER_CLASS}}`

### Controller stub

Placeholder minimum:

- `{{CONTROLLER_CLASS}}`
- `{{PAGE_SLUG}}`
- `{{PAGE_KEY_PREFIX}}`

Ini sudah cukup untuk baseline awal.

## Output Path Rules

### Derived output

Input:

- `Senarai Pelajar`

Derived:

- slug: `senarai-pelajar`
- controller class: `SenaraiPelajarController`
- key prefix: `senarai_pelajar`

Output:

- `public/pages/senarai-pelajar.php`
- `public/controllers/SenaraiPelajarController.php`

## Cadangan Urutan Implementasi

Urutan terbaik untuk service layer:

1. `TemplateRegistryService.php`
2. `TemplateResolverService.php`
3. `FileGenerationService.php`
4. `TemplateGeneratorController.php`

Sebab:

- registry jadi source of truth
- resolver bergantung pada registry
- generator bergantung pada resolver
- controller bergantung pada semuanya

## Definition of Done untuk Batch 1 Service Layer

Batch 1 service layer dianggap siap apabila:

1. template `blank` dan `datatable` boleh dibaca dari registry
2. stub path boleh diresolve dengan betul
3. nama page boleh dinormalisasi dengan konsisten
4. fail output page + controller boleh dihasilkan dengan selamat
5. controller UI boleh preview dan generate menggunakan service layer ini

## Final Recommendation

Untuk Batch 1, jangan jadikan service terlalu generic.

Bina service yang:

- kecil
- jelas tanggungjawab
- fokus pada 2 template sahaja

Selepas flow ini stabil, barulah tambah:

- JS/CSS generation
- lang skeleton generation
- feature manifests
- CRUD template
