# Add Pelajar Flow

Date: 2026-03-26

## Scope

This document records the implementation contract for `Tambah Pelajar` in `Senarai Pengguna`.

The flow is intentionally separate from `Tambah Staf`:
- source data: `v210`
- category target: `PELAJAR`
- group restriction: `tbl_m_group.f_categoryUser = 'PELAJAR'`

## Source Rule

Only students with:
- `statuskategori = 'AKTIF'`

are allowed to appear in the add-student search and to be inserted into `tbl_m_user`.

## Identifier Rule

For student accounts:
- `matrik` is the primary identifier
- `matrik` is stored in `tbl_m_user.f_stafID`

This is a legacy compatibility decision so existing login and lookup flows can continue using a single identifier field.

## Field Mapping

Final mapping used for `Tambah Pelajar`:

- `v210.matrik` -> `tbl_m_user.f_stafID`
- `PELAJAR` -> `tbl_m_user.f_categoryUser`
- `NULL` -> `tbl_m_user.f_nopekerja`
- `v210.nama` -> `tbl_m_user.f_nama`
- `v210.nama` -> `tbl_m_user.f_nickname`
- `v210.nokp` -> `tbl_m_user.f_nokp`
- `password_hash(v210.nokp)` -> `tbl_m_user.f_password`
- `v210.email` -> `tbl_m_user.f_email`
- `notel_terkini -> hpno -> telno_terkini -> telno` -> `tbl_m_user.f_handphone`
- `v210.kdprogram` -> `tbl_m_user.f_jawatanKod`
- `v210.program` -> `tbl_m_user.f_jawatan`
- `v210.kdfakulti` -> `tbl_m_user.f_jabatanKod`
- `v210.fakulti` -> `tbl_m_user.f_namajabatan`
- `v210.kdtahap` -> `tbl_m_user.f_jenisID`
- `v210.tahap_pengajian` -> `tbl_m_user.f_jenis`
- `v210.kadet` -> `tbl_m_user.f_kumpjawatan`
- `NULL` -> `tbl_m_user.f_statusID`
- `v210.statuskategori` -> `tbl_m_user.f_status`
- selected group -> `tbl_m_user.f_groupID`
- selected group code -> `tbl_m_user.f_groupKod`
- selected access flag -> `tbl_m_user.f_flag`

## Validation Rules

Server-side validation must enforce:

1. user must be logged in
2. user must have group management permission
3. CSRF token must be valid
4. student mode must be enabled
5. `matrik` must be present
6. selected group must exist
7. selected group category must be `PELAJAR`
8. target student must exist in `v210`
9. target student must still be `AKTIF`
10. target identifier must not already exist in `tbl_m_user`

## UI Flow

In `Senarai Pengguna > Akses Pelajar`:

1. admin clicks `Add Pelajar`
2. shared add modal is switched into student mode
3. active students are searched remotely from `ajax/user-list-student-options.php`
4. student metadata is shown in the modal info card
5. admin selects a student group
6. save posts to `ajax/user-add-student.php`
7. page reloads and returns to the `Akses Pelajar` tab

## Notes

- local password using IC hash is a temporary development-only policy
- production login for student accounts is expected to move to SSO
- `Tambah Umum` is still deferred and should not reuse the student or staff add endpoint
