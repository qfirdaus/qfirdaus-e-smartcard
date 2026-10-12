# Page Template Generator First Files

Tarikh: 2026-03-27

## Tujuan

Dokumen ini menyenaraikan fail pertama yang patut diwujudkan untuk mula membina `Page Template Generator` dalam `e-Base`.

Senarai ini sengaja fokus kepada starter set yang minimum tetapi cukup untuk memulakan Phase 1.

## Prinsip

Fail yang diwujudkan dahulu mesti menyokong 3 perkara:

1. satu UI generator yang boleh digunakan
2. satu backend service yang boleh resolve template dan generate fail
3. satu struktur stub yang cukup untuk `Blank Page` dan `DataTable Page`

## Struktur Folder Sasaran

Semua stub generator patut bermula di lokasi ini:

- `/var/www/app\e-base\templates\generator\page-generator\`

Output generated file kekal di lokasi runtime biasa:

- `public/pages/`
- `public/controllers/`
- `public/assets/js/pages/`
- `public/assets/css/pages/`
- `public/lang/`

## Senarai Fail Pertama

## A. Registry & Template Metadata

1. `/var/www/app\e-base\templates\generator\page-generator\registry\templates.json`
- daftar template yang tersedia
- minimum: `blank`, `datatable`

2. `/var/www/app\e-base\templates\generator\page-generator\registry\features.json`
- untuk Phase 1 boleh sangat ringkas
- cukup untuk reserve struktur feature masa depan

## B. Blank Template Stubs

3. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\blank\page.stub.php`

4. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\blank\controller.stub.php`

5. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\blank\js.stub.js`

6. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\blank\css.stub.css`

7. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\blank\lang.ms.stub.php`

8. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\blank\lang.en.stub.php`

9. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\blank\meta.json`

## C. DataTable Template Stubs

10. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\datatable\page.stub.php`

11. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\datatable\controller.stub.php`

12. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\datatable\js.stub.js`

13. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\datatable\css.stub.css`

14. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\datatable\lang.ms.stub.php`

15. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\datatable\lang.en.stub.php`

16. `/var/www/app\e-base\templates\generator\page-generator\stubs\page\datatable\meta.json`

## D. Shared Partial Stubs

17. `/var/www/app\e-base\templates\generator\page-generator\stubs\partials\page\layout.stub.php`
- include standard page shell

18. `/var/www/app\e-base\templates\generator\page-generator\stubs\partials\page\datatable.stub.php`
- table block standard

19. `/var/www/app\e-base\templates\generator\page-generator\stubs\partials\controller\base-controller.stub.php`
- controller base pattern

20. `/var/www/app\e-base\templates\generator\page-generator\stubs\partials\lang\page-common.ms.stub.php`

21. `/var/www/app\e-base\templates\generator\page-generator\stubs\partials\lang\page-common.en.stub.php`

## E. Generator Backend

22. `/var/www/app\e-base\public\classes\TemplateRegistryService.php`
- baca registry template

23. `/var/www/app\e-base\public\classes\TemplateResolverService.php`
- resolve template path dan metadata

24. `/var/www/app\e-base\public\classes\FileGenerationService.php`
- render stub dan tulis fail

25. `/var/www/app\e-base\public\classes\GenerationAuditService.php`
- rekod event generation

## F. Generator Controller & Page

26. `/var/www/app\e-base\public\controllers\TemplateGeneratorController.php`

27. `/var/www/app\e-base\public\pages\template-generator.php`

28. `/var/www/app\e-base\public\assets\js\pages\template-generator.js`

29. `/var/www/app\e-base\public\assets\css\pages\template-generator.css`

## Fail Paling Kritikal untuk Start Pantas

Kalau mahu bermula dengan set paling minimum dahulu, wujudkan fail ini dahulu:

1. `templates.json`
2. `blank/page.stub.php`
3. `blank/controller.stub.php`
4. `datatable/page.stub.php`
5. `datatable/controller.stub.php`
6. `TemplateRegistryService.php`
7. `FileGenerationService.php`
8. `TemplateGeneratorController.php`
9. `template-generator.php`

Itu sudah cukup untuk membuktikan end-to-end flow awal.

## Suggested Order of Creation

Urutan paling practical:

1. `templates.json`
2. `blank` stubs
3. `datatable` stubs
4. `TemplateRegistryService.php`
5. `TemplateResolverService.php`
6. `FileGenerationService.php`
7. `TemplateGeneratorController.php`
8. `template-generator.php`
9. `template-generator.js`
10. `template-generator.css`

## Non-Critical Files That Can Wait

Fail ini boleh ditangguh sedikit jika mahu bergerak lebih cepat:

- `features.json`
- shared `lang partials`
- `GenerationAuditService.php`
- `examples/`
- `datatable/css.stub.css`

## Final Recommendation

Untuk mula bina generator ini dalam `e-Base`, saya syorkan jangan cipta terlalu banyak fail sekali gus.

Mulakan dengan:

- registry minimum
- 2 template stub
- 3 service backend
- 1 controller
- 1 page UI

Selepas baseline ini hidup, barulah tambah partials dan polish layer lain.
