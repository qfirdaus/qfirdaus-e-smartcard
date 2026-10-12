# User Identity Mapping

Dokumen ini merekodkan mapping identifier semasa yang digunakan oleh flow `Add Staff` dalam e-Base.

## Tujuan

- elak kekeliruan antara identifier staf dari sumber Sybase dan field legacy dalam `tbl_m_user`
- jadi rujukan rasmi sebelum flow `Add Pelajar` dibina
- kurangkan risiko salah mapping pada controller, AJAX endpoint, dan modul login

## Scope Semasa

Flow yang diliputi:

- `Senarai Pengguna > Akses Staf > Add Staff`
- endpoint [user-add.php](/var/www/app\e-base\public\ajax\user-add.php)
- source dropdown [user-list-staf-options.php](/var/www/app\e-base\public\ajax\user-list-staf-options.php)

## Mapping Rasmi Semasa

Sumber Sybase `v630staf_service_skim_all`:

- `nopekerja`
  - identifier utama staf yang dipilih dalam dropdown
  - digunakan sebagai lookup utama semasa save
  - disimpan ke `tbl_m_user.f_stafID`

- `idpekerja`
  - identifier HR / employee number
  - dipulangkan bersama option dropdown
  - disimpan ke `tbl_m_user.f_nopekerja`

## Mapping Insert Semasa

Dalam [user-add.php](/var/www/app\e-base\public\ajax\user-add.php):

- `Sybase.nopekerja` -> `tbl_m_user.f_stafID`
- `Sybase.idpekerja` -> `tbl_m_user.f_nopekerja`
- `Sybase.gelar_nama` -> `tbl_m_user.f_nama`
- `Sybase.nama` -> `tbl_m_user.f_nickname`
- `Sybase.nokp` -> `tbl_m_user.f_nokp`
- `Sybase.nokp` -> `tbl_m_user.f_password` (hashed, current local flow)
- `Sybase.email` -> `tbl_m_user.f_email`
- `Sybase.handphone` -> `tbl_m_user.f_handphone`
- `Sybase.kdjwtsemasa` -> `tbl_m_user.f_jawatanKod`
- `Sybase.jawatansemasa` -> `tbl_m_user.f_jawatan`
- `Sybase.kdjenis` -> `tbl_m_user.f_jenisID`
- `Sybase.jenis` -> `tbl_m_user.f_jenis`
- `Sybase.kdjbtnsemasa` -> `tbl_m_user.f_jabatanKod`
- `Sybase.jabatansemasa` -> `tbl_m_user.f_namajabatan`
- `Sybase.kumpjwt` -> `tbl_m_user.f_kumpjawatan`
- `Sybase.kodstatus` -> `tbl_m_user.f_statusID`
- `Sybase.status` -> `tbl_m_user.f_status`

## Governance Note

Walaupun schema legacy menggunakan nama field berikut:

- `f_stafID`
- `f_nopekerja`

interpretasi business semasa untuk flow staf ialah:

- `f_stafID` = `staff login identifier` dari Sybase `nopekerja`
- `f_nopekerja` = `employee / HR identifier` dari Sybase `idpekerja`

## Recommendation for Next Phase

Semasa design `Add Pelajar`, gunakan istilah neutral di service/controller layer:

- `login_identifier`
- `external_person_id`
- `user_category`

dan elakkan andaian bahawa:

- `f_stafID` sentiasa bermaksud “no staf” dalam semua kategori
- `f_nopekerja` sentiasa bermaksud field UI yang sama untuk semua kategori

Dokumen ini patut dirujuk sebelum:

- membina `Add Pelajar`
- membina `Add Umum`
- menukar flow login lokal / SSO
