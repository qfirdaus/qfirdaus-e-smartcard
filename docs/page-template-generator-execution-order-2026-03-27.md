# Page Template Generator Execution Order

Tarikh: 2026-03-27

## Tujuan

Dokumen ini menyusun urutan implementasi sebenar untuk membina `Page Template Generator` dalam `e-Base`.

Fokus dokumen ini:

- fail mana patut dibina dahulu
- kerja mana jadi blocker
- apa yang boleh ditangguh
- bagaimana team boleh bergerak secara praktikal dari hari pertama

Dokumen ini bukan roadmap besar, tetapi `working order` untuk execution sebenar.

## Prinsip Execution

Untuk Batch 1, urutan kerja terbaik ialah:

1. bina source of truth dahulu
2. bina stub dahulu
3. bina backend service dahulu
4. bina UI terakhir

Sebab:

- UI akan lebih mudah bila backend contract sudah stabil
- backend lebih mudah bila stub dan registry sudah jelas
- stub lebih mudah dibina bila baseline sudah dibekukan

## Execution Order

## Step 1: Create Template Root

### Objective

Wujudkan struktur folder generator yang rasmi.

### Action

Wujudkan folder ini dahulu:

- `templates/generator/page-generator/registry/`
- `templates/generator/page-generator/stubs/page/blank/`
- `templates/generator/page-generator/stubs/page/datatable/`
- `templates/generator/page-generator/stubs/partials/page/`
- `templates/generator/page-generator/stubs/partials/controller/`
- `templates/generator/page-generator/stubs/partials/lang/`

### Kenapa langkah ini dahulu

Sebab semua kerja lain akan bergantung pada path ini.

## Step 2: Build `templates.json`

### Objective

Tetapkan 2 template yang disokong:

- `blank`
- `datatable`

### Action

Wujudkan:

- `templates/generator/page-generator/registry/templates.json`

### Output

Template registry minimum yang backend dan UI boleh baca.

### Blocker status

Ini blocker kepada:

- `TemplateRegistryService`
- preview UI

## Step 3: Build Blank Template Stubs

### Objective

Sediakan baseline paling mudah untuk generator.

### Action

Wujudkan:

- `blank/page.stub.php`
- `blank/controller.stub.php`
- `blank/meta.json`

### Reference

- `public/pages/dashboard.php`

### Scope

Ambil hanya:

- title header
- breadcrumb
- page shell

### Kenapa step ini awal

Blank template paling mudah untuk validate end-to-end flow generation.

## Step 4: Build DataTable Template Stubs

### Objective

Sediakan template kedua yang lebih bernilai untuk team.

### Action

Wujudkan:

- `datatable/page.stub.php`
- `datatable/controller.stub.php`
- `datatable/meta.json`

### Reference

- `public/pages/senarai-pengguna.php`
- hanya content dalam tab `Akses Staf`

### Scope

Ambil:

- list page shell
- DataTable structure
- top controls pattern
- bottom pagination/info pattern

Jangan ambil:

- tabs
- add/edit/delete user logic
- sync logic
- student/public logic

## Step 5: Build `TemplateRegistryService`

### Objective

Boleh baca template list dari registry.

### Action

Wujudkan:

- `public/classes/TemplateRegistryService.php`

### Scope

Method minimum:

- `getAllTemplates()`
- `getTemplate(string $key)`

### Dependency

Bergantung pada:

- `templates.json`

## Step 6: Build `TemplateResolverService`

### Objective

Resolve template key kepada fail stub sebenar.

### Action

Wujudkan:

- `public/classes/TemplateResolverService.php`

### Scope

Service ini mesti boleh:

- resolve page stub path
- resolve controller stub path
- resolve meta path
- validate fail wujud

### Dependency

Bergantung pada:

- `TemplateRegistryService`
- struktur folder stub

## Step 7: Build `FileGenerationService`

### Objective

Sediakan enjin generation sebenar.

### Action

Wujudkan:

- `public/classes/FileGenerationService.php`

### Scope

Function minimum:

- normalize page name
- derive slug
- derive controller class
- derive output path
- collision check
- render stub placeholder
- generate `page + controller`

### Dependency

Bergantung pada:

- `TemplateResolverService`

### Kenapa ini langkah kritikal

Selepas fail ini siap, generator sudah boleh diuji walaupun UI belum cantik.

## Step 8: Build `TemplateGeneratorController`

### Objective

Hubungkan backend service dengan page UI.

### Action

Wujudkan:

- `public/controllers/TemplateGeneratorController.php`

### Scope

Action minimum:

- load available templates
- handle preview request
- handle generate request

### Dependency

Bergantung pada:

- service layer siap

## Step 9: Build Minimal Generator UI Page

### Objective

Beri team satu screen usable untuk preview dan generate.

### Action

Wujudkan:

- `public/pages/template-generator.php`

### Scope

Input minimum:

- page name
- title MS
- title EN
- template type

Output minimum:

- preview fail yang akan dicipta
- generate button

### Dependency

Bergantung pada:

- controller siap

## Step 10: Add Page JS

### Objective

Polish interaksi form dan preview.

### Action

Wujudkan:

- `public/assets/js/pages/template-generator.js`

### Scope

Function minimum:

- refresh preview bila input berubah
- submit preview
- submit generate
- handle success/error alert

### Status

Ini penting, tetapi bukan blocker untuk backend flow asas.

## Step 11: Add Page CSS

### Objective

Buat UI generator lebih kemas dan selari dengan e-Base.

### Action

Wujudkan:

- `public/assets/css/pages/template-generator.css`

### Status

Ini polish layer, bukan blocker.

## Step 12: Verification

### Objective

Pastikan baseline betul-betul boleh dipakai.

### Action

Uji dua generation:

1. `Blank Page`
2. `DataTable Page`

### Verify

- nama fail betul
- controller class betul
- fail ditolak jika sudah wujud
- output boleh syntax check
- output page boleh load

## Working Sequence by Day

### Day 1

- create folder structure
- create `templates.json`
- create `blank` stubs

### Day 2

- create `datatable` stubs
- create `TemplateRegistryService`
- create `TemplateResolverService`

### Day 3

- create `FileGenerationService`
- test generate `page + controller` secara manual/backend

### Day 4

- create `TemplateGeneratorController`
- create `template-generator.php`

### Day 5

- create `template-generator.js`
- create `template-generator.css`
- verification pass

## Blockers to Watch

### Blocker 1

Kalau baseline stub belum dibekukan, service layer akan berubah-ubah.

### Blocker 2

Kalau naming convention belum dikunci, output path dan controller naming akan jadi inconsistent.

### Blocker 3

Kalau generator terus cuba support terlalu banyak feature awal-awal, Batch 1 akan lambat siap.

## What Can Wait

Perkara ini boleh ditangguh selepas Batch 1:

- `features.json` yang lebih kompleks
- js/css/lang auto-generation penuh
- CRUD modal template
- ajax endpoint bundle
- auto menu registration
- template preview code diff

## Final Recommendation

Urutan implementasi paling practical untuk team sekarang ialah:

1. folder structure
2. template registry
3. blank stub
4. datatable stub
5. registry service
6. resolver service
7. file generation service
8. generator controller
9. generator UI
10. JS/CSS polish

Kalau team ikut urutan ini, generator boleh mula hidup dengan risiko paling rendah dan tanpa banyak rework.
