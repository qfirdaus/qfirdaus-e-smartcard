# Page Template Generator Phase 1 Backlog

Tarikh: 2026-03-27

## Tujuan Phase 1

Phase 1 memberi fokus kepada versi minimum yang benar-benar usable untuk team developer.

Sasaran Phase 1:

- wujud satu page generator dalam `e-Base`
- boleh generate `Blank Page`
- boleh generate `DataTable Page`
- hasil generation terus ikut baseline `e-Base`
- page yang dijana boleh diubah suai bebas oleh developer selepas generation

Phase ini sengaja tidak memasukkan:

- CRUD modal penuh
- AJAX bundle kompleks
- auto-create menu/module/database record
- overwrite automation
- template sync/regeneration

## Scope Phase 1

Hasil minimum yang patut siap pada hujung phase:

1. UI generator page
2. registry asas untuk 2 template
3. stub fail untuk:
   - page
   - controller
   - js
   - css
   - lang skeleton
4. service generation dengan collision check
5. dry-run / preview sebelum create
6. audit log ringkas untuk generation event

## Deliverables

### 1. Generator Admin Page

Fail/komponen yang perlu diwujudkan:

- page admin generator
- controller untuk page generator
- js page untuk interaksi form/preview
- optional css page jika perlu

Function minimum:

- isi nama page
- isi title MS
- isi title EN
- pilih template:
  - `blank`
  - `datatable`
- pilih checkbox:
  - generate js
  - generate css
  - generate lang keys
- preview output
- klik generate

### 2. Template Registry Asas

Registry minimum yang perlu diwujudkan:

- senarai template yang disokong
- label template
- template version
- generated files list

Phase 1 belum perlu feature manifest yang kompleks.

Cukup jika registry boleh jawab:

- template apa available
- fail apa akan dijana
- stub mana perlu digunakan

### 3. Stub Files

Template minimum yang perlu disediakan:

#### Blank Page

- `page.stub.php`
- `controller.stub.php`
- `js.stub.js`
- `css.stub.css`
- `lang.stub.php`

#### DataTable Page

- `page.stub.php`
- `controller.stub.php`
- `js.stub.js`
- `css.stub.css`
- `lang.stub.php`

### 4. File Generation Service

Service ini perlu handle:

- normalize nama page
- derive class/controller name
- resolve target path
- semak fail sudah wujud atau tidak
- generate content dari stub
- tulis fail ke lokasi sebenar

Policy default:

- abort jika fail sudah wujud

### 5. Preview / Dry Run

Sebelum create sebenar, sistem mesti boleh tunjuk:

- page filename
- controller filename
- js filename
- css filename
- lang keys prefix
- template yang dipilih

### 6. Audit Ringkas

Setiap generation event patut sekurang-kurangnya simpan:

- siapa generate
- bila
- template apa
- page slug apa
- fail apa dicipta

## Work Breakdown

## A. Discovery & Standard Freeze

### Objective

Bekukan standard semasa `e-Base` yang akan dijadikan baseline generator.

### Task

1. audit satu page ringkas yang dianggap paling “standard”
2. audit satu page DataTable yang dianggap paling kemas
3. senaraikan include wajib:
   - topbar
   - sidebar
   - footer
   - script include
4. senaraikan helper yang patut direuse
5. tetapkan naming convention rasmi

### Output

- dokumen reference baseline
- keputusan template standard awal

Rujukan baseline yang telah dipilih:

- `Blank Page` -> `public/pages/dashboard.php` (header + breadcrumb sahaja)
- `DataTable Page` -> `public/pages/senarai-pengguna.php` (hanya content dalam tab `Akses Staf`)

## B. Backlog UI Generator

### Objective

Bina page generator yang minimum tetapi jelas untuk developer.

### Task

1. wujudkan page `Page Template Generator`
2. wujudkan form config asas
3. bina section:
   - basic info
   - template type
   - options
   - output preview
4. tambah validation client-side minimum
5. tambah action:
   - preview
   - generate

### Acceptance

- developer boleh isi form tanpa keliru
- preview fail boleh dilihat sebelum generate

## C. Backlog Registry & Stub

### Objective

Sediakan struktur template yang maintainable.

### Task

1. wujudkan folder template generator
2. wujudkan registry file asas
3. wujudkan stub untuk `blank`
4. wujudkan stub untuk `datatable`
5. tetapkan placeholder standard

### Acceptance

- generator boleh resolve template dari registry
- stub tidak hardcoded dalam controller/service

## D. Backlog Generation Engine

### Objective

Bina enjin generation yang selamat.

### Task

1. validate page name input
2. normalize slug
3. derive controller class name
4. derive target file paths
5. check collision fail
6. render stub dengan placeholder
7. tulis fail ke disk
8. return summary result

### Acceptance

- fail dijana pada lokasi betul
- tiada overwrite silent
- naming convention konsisten

## E. Backlog Language Skeleton

### Objective

Pastikan page generated terus multilingual-ready.

### Task

1. tentukan key prefix berdasarkan page slug
2. generate skeleton key untuk `ms`
3. generate skeleton key untuk `en`
4. pastikan page stub terus guna `__('key')`

### Acceptance

- generated page tidak bergantung pada text literal
- lang skeleton ditambah konsisten

## F. Backlog Verification

### Objective

Pastikan output Phase 1 benar-benar boleh dipakai.

### Task

1. test generate `Blank Page`
2. test generate `DataTable Page`
3. semak syntax output
4. semak include path
5. semak page load minimum
6. semak lang key integration

### Acceptance

- output boleh dibuka tanpa fatal error
- developer boleh sambung ubah suai manual selepas generate

## Prioriti Kerja

Urutan yang saya syorkan:

1. standard freeze
2. naming & path rules
3. stub folder structure
4. registry minimum
5. generation service
6. generator UI
7. preview
8. language skeleton
9. verification

## Suggested Task List

### Epic 1: Baseline Standard

- pilih page blank baseline
- pilih page datatable baseline
- freeze include pattern
- freeze controller skeleton

### Epic 2: Template Infrastructure

- create template folder
- create registry file
- create blank stubs
- create datatable stubs

### Epic 3: Generation Engine

- create name normalizer
- create class name resolver
- create collision checker
- create stub renderer
- create file writer

### Epic 4: Generator UI

- create generator page
- create generator controller
- create preview panel
- create generate action

### Epic 5: Multilingual Output

- generate ms key skeleton
- generate en key skeleton
- wire generated page to keys

### Epic 6: Verification

- generate sample blank page
- generate sample datatable page
- syntax check output
- smoke test output

## Recommended Definition of Done

Phase 1 dianggap siap apabila:

1. developer boleh generate `Blank Page`
2. developer boleh generate `DataTable Page`
3. page, controller, js, css, dan lang skeleton dijana ikut naming convention
4. collision fail disekat dengan selamat
5. generated output boleh dibuka dan dijadikan asas kerja lanjut

## Explicit Non-Goals

Perkara ini tidak perlu dibuat dalam Phase 1:

- modal CRUD standard
- ajax endpoint automation
- menu/module DB auto registration
- controller method generator yang kompleks
- template update existing page
- diff/merge generated files

## Cadangan Praktikal untuk Start Esok

Kalau mahu mula terus, urutan kerja paling practical ialah:

1. freeze satu contoh `Blank Page` sebenar dari e-Base
2. freeze satu contoh `DataTable Page` sebenar dari e-Base
3. bina folder `templates/generator/page-generator/`
4. sediakan 2 stub awal
5. bina backend service untuk render fail dari stub
6. bina page UI paling minimal untuk trigger preview + generate

Ini cara paling cepat untuk mendapatkan nilai awal tanpa menjadikan generator terlalu kompleks pada fasa pertama.
