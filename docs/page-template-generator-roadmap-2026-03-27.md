# Page Template Generator Roadmap

Tarikh: 2026-03-27

## Tujuan

Dokumen ini merekodkan cadangan seni bina, struktur template, dan pelan implementasi untuk membina ciri `Page Template Generator` di dalam `e-Base`.

Matlamat utama ciri ini:

- mempercepat pembinaan modul baharu oleh team developer
- memastikan page baharu terus ikut standard `e-Base`
- menghasilkan baseline yang konsisten
- membenarkan team bebas mengubah dan menambah baik page selepas generation

Prinsip teras:

- generator menghasilkan `starting point`, bukan final production module
- hasil generation mesti ringan, jelas, dan mudah diubah suai
- template mesti ikut pattern sistem semasa
- generator mesti selamat dari sudut naming, overwrite, dan consistency

## Sasaran Akhir

Apabila ciri ini matang, `e-Base` patut mempunyai satu generator dalaman yang membolehkan developer:

- memilih jenis template page
- memilih feature standard yang mahu dimasukkan
- menjana fail page, controller, JS, CSS, dan language key baseline
- menggunakan hasil generation itu sebagai asas untuk dibangunkan ikut keperluan sistem sebenar

Hasil generation patut terus menyokong:

- layout standard `e-Base`
- session dan permission guard
- language key usage
- asset include standard
- pattern controller yang seragam
- alert / feedback pattern
- struktur fail yang konsisten

## Prinsip Reka Bentuk

1. `generate baseline, then customize freely`
2. `template is a maintained platform artifact`
3. `feature selection must not create messy code`
4. `reuse existing helpers before generating new logic`
5. `safe generation first, advanced automation later`

## Architecture Overview

### Lapisan yang dicadangkan

Generator ini patut dibina dalam 3 lapisan utama:

1. `Generator UI`
- page admin untuk memilih nama page, template, dan options
- hanya kumpul input dan tunjuk preview/ringkasan

2. `Generation Service`
- validate input
- resolve template
- tentukan feature dependencies
- hasilkan pelan fail
- generate fail sebenar

3. `Template Registry`
- simpan senarai template dan metadata
- simpan feature partials
- jadi source of truth untuk semua generation

### Service yang dicadangkan

- `TemplateGeneratorController`
- `TemplateRegistryService`
- `TemplateResolverService`
- `FeatureAssemblerService`
- `FileGenerationService`
- `GenerationAuditService`

### Flow generation

1. developer buka page generator
2. isi `page name`, `title`, `template type`, dan `feature options`
3. sistem bina `generation preview`
4. backend validate:
   - nama fail
   - class name
   - collision fail
   - template compatibility
5. jika lulus, sistem jana fail
6. sistem pulangkan ringkasan:
   - fail yang dicipta
   - language keys yang ditambah
   - next steps untuk developer

## Template Design

### Pendekatan utama

Gunakan `file-based stub system`.

Kenapa:

- mudah version control
- senang diff/review
- mudah dikemas kini oleh platform team
- lebih sesuai untuk artifact code berbanding simpan dalam DB

### Struktur folder yang dicadangkan

```text
templates/
  page-generator/
    registry/
      templates.json
      features.json
    page/
      blank/
        page.stub.php
        controller.stub.php
        js.stub.js
        css.stub.css
        lang.stub.php
        meta.json
      datatable/
        ...
      crud-modal/
        ...
    partials/
      page/
        includes.stub.php
        filters.stub.php
        datatable.stub.php
        modal.stub.php
        swal.stub.php
      controller/
        index.stub.php
        create.stub.php
        update.stub.php
        delete.stub.php
      lang/
        common-list.stub.php
        common-crud.stub.php
```

### Template metadata

Setiap template patut ada metadata minimum:

- `template_key`
- `label`
- `description`
- `template_version`
- `generated_files`
- `supported_features`
- `required_features`
- `incompatible_features`

### Elakkan giant template

Jangan buat satu stub besar dengan terlalu banyak `if`.

Lebih baik:

- satu `base stub`
- beberapa `feature partials`
- guna placeholder yang jelas

Contoh placeholder:

- `{{PAGE_TITLE_MS}}`
- `{{PAGE_TITLE_EN}}`
- `{{PAGE_CLASS_NAME}}`
- `{{CONTROLLER_CLASS_NAME}}`
- `{{TABLE_MARKUP}}`
- `{{FILTER_MARKUP}}`
- `{{MODAL_MARKUP}}`
- `{{SCRIPT_BLOCK}}`

## Feature Injection Strategy

### Feature sebagai modul

Feature patut dianggap sebagai modul injection, bukan checkbox rawak.

Contoh feature awal:

- `multilingual_keys`
- `permission_guard`
- `datatable`
- `filter_bar`
- `crud_modal`
- `sweetalert_feedback`
- `js_file`
- `css_file`
- `ajax_bundle`
- `audit_logging_hooks`

### Setiap feature patut ada definisi

- target file
- placeholder target
- dependencies
- conflicts
- variables yang diperlukan

Contoh:

- `crud_modal`
  - depends on `datatable`
  - depends on `sweetalert_feedback`
  - inject ke page + js + controller

### Objective utama

Feature injection patut:

- kurangkan duplication
- elakkan code fragment berselerak
- hasilkan fail yang masih bersih dibaca developer

## File Generation Strategy

### Naming convention

Input:

- `senarai pengguna`

Normalized:

- page: `senarai-pengguna.php`
- controller: `SenaraiPenggunaController.php`
- js: `senarai-pengguna.js`
- css: `senarai-pengguna.css`

### Folder target

Fail yang dicadangkan:

- `public/pages/<page>.php`
- `public/controllers/<Controller>.php`
- `public/assets/js/pages/<page>.js`
- `public/assets/css/pages/<page>.css`

Optional pada fasa kemudian:

- `public/ajax/<page>-create.php`
- `public/ajax/<page>-update.php`
- `public/ajax/<page>-delete.php`
- `docs/generated/<page>.md`

### Polisi keselamatan

Generator mesti:

- reject nama fail tidak sah
- reject reserved keywords
- semak collision fail
- default kepada `abort if exists`

Saya syorkan 3 mode sahaja:

1. `Preview Only`
2. `Create New`
3. `Create With Suffix`

Jangan buat overwrite automatik sebagai default.

## UI/UX Generator Page

### Layout yang disyorkan

Gunakan layout 2 kolum:

Kiri:

- form configuration

Kanan:

- preview generation
- fail yang akan dicipta
- feature summary
- warning/conflict

### Input minimum

#### Section 1: Basic Info

- Page Name
- Page Title (MS)
- Page Title (EN)
- Description optional
- Target Module optional

#### Section 2: Template Type

- Blank Page
- DataTable Page
- CRUD Modal Page
- Form Page
- Dashboard Page

#### Section 3: Features

- multilingual support
- permission guard
- js file
- css file
- datatable
- filter bar
- modal CRUD
- sweetalert
- ajax bundle

#### Section 4: Output Preview

- generated filenames
- controller class name
- language key prefix
- generation notes

### Developer usability improvements

- sediakan `preset`
- sediakan `dry-run`
- sediakan `copy summary`
- sediakan post-generation checklist

## Integration with Existing e-Base

### Standard includes

Hasil generation mesti terus ikut shell `e-Base` semasa:

- topbar
- sidebar
- footer
- script include
- theme compatibility

### Multilingual integration

Generator patut:

- guna `__('key')` terus dalam stub
- tambah skeleton key ke `lang/ms.php`
- tambah skeleton key ke `lang/en.php`

Cadangan naming key:

- prefix ikut page slug

Contoh:

- `senarai_page_title`
- `senarai_col_name`
- `senarai_btn_save`

### Security integration

Semua page/controller generated mesti terus ada:

- login/session guard
- permission guard placeholder
- csrf usage pattern
- validation structure

### Reuse helper sedia ada

Generator patut reuse helper semasa jika tersedia, contohnya:

- page layout include
- DataTables decorator/helper
- modal helper
- fetch wrapper
- swal pattern
- response helper

Jangan hasilkan implementasi baru kalau helper platform sudah ada.

## Scalability and Maintenance

### Template versioning

Setiap template patut ada `template_version`.

Contoh:

- `blank@1`
- `datatable@1`
- `crud-modal@1`

Ini penting supaya:

- page yang dihasilkan hari ini boleh dijejak
- perubahan masa depan tidak mengganggu page lama

### Generated file header

Saya syorkan setiap fail generated ada header ringkas:

- generated by `Page Template Generator`
- template key
- template version
- generated date
- selected features

### Template governance

Platform team patut menjadi owner kepada:

- registry template
- feature manifest
- coding standard dalam stub

Developer team lain boleh guna hasil generation, tetapi perubahan kepada template patut dikawal.

## Additional Template Ideas

Selain 3 template awal, template yang berguna untuk jangka sederhana:

1. `Form Page`
- create/edit form

2. `Read-Only Report Page`
- filter + result table

3. `Dashboard Page`
- cards + chart container

4. `Detail/View Page`
- read-only entity view

5. `Master-Detail Page`
- list + side detail

6. `Settings Page`
- tabbed settings form

7. `API Controller`
- JSON response pattern

8. `Import/Export Page`
- upload + preview + result summary

## Risks and Considerations

### Risiko utama

1. generator menghasilkan code terlalu berat
2. template drift daripada standard semasa
3. terlalu banyak options menyebabkan UX serabut
4. overwrite fail sedia ada
5. language file menjadi terlalu kotor jika tiada convention

### Perkara yang perlu dielakkan

- one giant template dengan terlalu banyak branch
- overengineering pada fasa awal
- auto-overwrite fail
- hardcoded path tanpa registry
- generate semua feature walaupun tidak digunakan

## Implementation Roadmap

## Phase 1: Minimal Generator Baseline

### Objektif

Bina generator paling kecil tetapi usable.

### Skop

- satu page generator dalam admin/dev area
- template `Blank Page`
- template `DataTable Page`
- generate:
  - page
  - controller
  - js
  - css
  - lang skeleton
- dry-run preview
- collision check

### Hasil

Developer sudah boleh hasilkan page baseline dan ubah suai sendiri selepas itu.

## Phase 2: Structured Template Registry

### Objektif

Pisahkan UI daripada template logic.

### Skop

- `templates.json`
- `features.json`
- `TemplateRegistryService`
- `TemplateResolverService`
- template metadata

### Hasil

Template jadi maintainable dan mudah ditambah.

## Phase 3: CRUD Modal Template

### Objektif

Sediakan template yang paling bernilai untuk team admin system.

### Skop

- `CRUD Modal Page`
- modal standard
- js standard
- controller CRUD baseline
- optional ajax bundle

### Hasil

Team boleh generate modul CRUD standard dengan lebih laju.

## Phase 4: e-Base Platform Alignment

### Objektif

Kemas integration dengan standard platform sebenar.

### Skop

- audit helper sedia ada yang patut direuse
- inject permission guard standard
- inject current DataTables wrapper pattern
- inject current alert/swal pattern
- inject language key prefix rule

### Hasil

Generated page betul-betul rasa seperti modul native `e-Base`.

## Phase 5: Advanced Templates and Presets

### Skop

- `Form Page`
- `Dashboard Page`
- `Report Page`
- `API Controller`
- preset by use case

## Cadangan Start untuk e-Base Sekarang

Untuk keadaan `e-Base` semasa, saya syorkan bermula secara konservatif.

### Langkah 1

Kenal pasti standard platform yang benar-benar stabil dan patut dijadikan baseline:

- page layout include
- controller skeleton
- DataTable setup pattern
- modal style standard
- translation pattern
- current JS helper pattern

### Langkah 2

Pilih hanya 2 template awal:

- `Blank Page`
- `DataTable Page`

Jangan mula dengan CRUD dahulu.

Sebab:

- paling mudah validate
- cepat hasilkan nilai
- kurang risiko generator jadi rumit terlalu awal

### Langkah 3

Tetapkan output minimum:

- page file
- controller file
- js file
- css file
- lang skeleton `ms/en`

### Langkah 4

Tetapkan bahawa team developer bebas modify hasil generation selepas fail dicipta.

Prinsip ini mesti jelas:

- generator tidak `own` page selepas generate
- generated page ialah baseline kerja
- developer boleh extend, refactor, atau ubah ikut keperluan sistem sebenar

### Langkah 5

Tambahkan audit metadata sahaja, bukan regen/update automation.

Maksud:

- rekod template apa digunakan
- tetapi jangan cuba sync semula generated file dengan template source

Itu jauh lebih selamat.

## Final Recommendation

Untuk `e-Base`, pendekatan terbaik ialah:

- bina `Page Template Generator` sebagai platform accelerator
- fokus pada baseline generation
- kekalkan template registry yang kemas
- elakkan generator menjadi code builder yang terlalu kompleks

Sasaran fasa awal:

1. page generator UI
2. blank page template
3. datatable page template
4. safe file generation
5. lang skeleton generation

Selepas itu, team developer boleh gunakan hasil generation sebagai asas dan upgrade page tersebut secara manual ikut keperluan sistem yang sedang dibina.

## Dokumen Susulan

Backlog terperinci untuk fasa pertama direkodkan dalam:

- `docs/page-template-generator-phase1-backlog-2026-03-27.md`

Struktur folder sebenar yang dicadangkan untuk repo `e-Base` direkodkan dalam:

- `docs/page-template-generator-folder-structure-2026-03-27.md`

Senarai fail pertama yang patut diwujudkan untuk memulakan implementasi direkodkan dalam:

- `docs/page-template-generator-first-files-2026-03-27.md`

Baseline page reference yang dipilih untuk stub awal direkodkan dalam:

- `docs/page-template-generator-baseline-reference-2026-03-27.md`

Blueprint kandungan `templates.json` dan tanggungjawab service Batch 1 direkodkan dalam:

- `docs/page-template-generator-batch1-services-2026-03-27.md`

Urutan implementasi sebenar untuk mula membina generator direkodkan dalam:

- `docs/page-template-generator-execution-order-2026-03-27.md`
