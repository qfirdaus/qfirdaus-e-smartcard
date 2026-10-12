# OneID SSO Known Vendor Risks

Tarikh: 2026-03-30  
Skop: `public/sso_sp_client.php` vendor block selepas:

```php
//********* [END OF USER EDITABLE] *************************
//Do not Edit Below this line -------
```

Dokumen ini merekod baki risiko yang masih wujud pada integrasi OneID SSO tetapi tidak diubah kerana vendor SSO telah menetapkan bahawa vendor block tidak boleh disentuh.

## Status semasa

Yang sudah dihardening pada app side:

- `login.php` tidak lagi percaya cookie `sso_cre` sebagai sumber auth tempatan
- `login.php` hanya menerima `$_SESSION['sso_auth_handoff']` yang masih fresh
- `sso_sp_client.php` dalam blok editable hanya menyimpan handoff minimum ke session
- `index.php` dan `sso_sp_client.php` kini berkongsi config `site_id` dan `IDP domain` yang sama

Maksudnya, boundary auth tempatan sudah diperketatkan. Baki risiko di bawah adalah risiko vendor/client integration yang masih tinggal.

## Risk 1: TLS verification dimatikan

Lokasi:
- [sso_sp_client.php](//var/www/app/e-base/public/sso_sp_client.php)

Pemerhatian:
- `API_REQUEST()` dalam vendor block mematikan:
  - `CURLOPT_SSL_VERIFYHOST`
  - `CURLOPT_SSL_VERIFYPEER`

Kesan:
- server aplikasi menerima sambungan HTTPS ke OneID tanpa pengesahan sijil yang ketat
- ini membuka risiko MITM, endpoint spoofing, atau penerimaan sijil yang tidak sah

Tahap:
- Critical

Tindakan disyorkan:
- vendor OneID perlu keluarkan versi client yang mengaktifkan TLS verification dengan betul

## Risk 2: Full OneID payload disimpan dalam cookie browser

Lokasi:
- [sso_sp_client.php](//var/www/app/e-base/public/sso_sp_client.php)

Pemerhatian:
- `COOKIE_SETTER()` dalam vendor block menyimpan seluruh `respond_user_packet` ke cookie `sso_cre`
- cookie itu mengandungi data sensitif seperti:
  - nama
  - staf ID
  - no matrik
  - e-mel
  - jabatan
  - jawatan
- tempoh cookie semasa ialah 30 hari

Kesan:
- pendedahan data peribadi pada browser lebih besar daripada yang perlu
- cookie menjadi surface replay atau inspection walaupun app side tidak lagi menggunakannya sebagai sumber login tempatan

Tahap:
- High

Tindakan disyorkan:
- vendor OneID perlu minimakan payload cookie
- vendor OneID perlu semak semula tempoh cookie dan atribut keselamatan cookie

## Risk 3: Branch response vendor yang brittle

Lokasi:
- [sso_sp_client.php](//var/www/app/e-base/public/sso_sp_client.php)

Pemerhatian:
- branch tertentu pada vendor flow seperti `respond_flag == "2"` hanya `echo "X";`

Kesan:
- UX boleh kelihatan kosong atau tidak jelas
- troubleshooting callback jadi sukar
- behavior ini rapuh jika OneID pulangkan response yang tidak berada pada happy path

Tahap:
- Medium

Tindakan disyorkan:
- vendor OneID perlu standardize response handling dan redirect/error contract

## Risk 4: Vendor flow masih bergantung pada cookie `sso_cre`

Lokasi:
- [sso_sp_client.php](//var/www/app/e-base/public/sso_sp_client.php)

Pemerhatian:
- walaupun app side tidak lagi percaya cookie itu untuk local auth completion, vendor state machine masih bergantung pada kewujudan `sso_cre`

Kesan:
- isu browser policy, stale cookie, atau callback mismatch masih boleh mempengaruhi kestabilan flow vendor
- callback SSO boleh jadi fragile jika state cookie tidak seperti yang dijangka oleh vendor client

Tahap:
- Medium

Tindakan disyorkan:
- vendor OneID perlu dokumentasikan secara rasmi lifecycle `sso_cre`
- vendor OneID perlu jelaskan dependency cookie vs token callback

## Risk 5: Callback contract sangat sensitif kepada URL yang tepat

Lokasi:
- [sso_sp_client.php](//var/www/app/e-base/public/sso_sp_client.php)

Pemerhatian:
- vendor flow hanya berjalan dengan betul apabila `SSO_SP_LOGINPAGE` sepadan dengan callback sebenar yang didaftarkan di OneID

Kesan:
- perubahan kecil pada callback URL boleh menyebabkan:
  - blank page
  - redirect loop
  - token callback tidak complete

Tahap:
- Medium

Tindakan disyorkan:
- semua perubahan `site_id`, domain, atau callback registration perlu diuji semula end-to-end
- pasukan aplikasi perlu treat callback URL sebagai configuration yang sensitif

## Batas tanggungjawab aplikasi tempatan

Yang boleh dikawal dari app side:

- policy login tempatan
- pemisahan auth source `MANUAL` vs `SSO`
- handoff TTL
- local session hardening
- audit trail untuk login/logout/deny

Yang tidak boleh dikawal tanpa vendor update:

- TLS verification dalam `API_REQUEST()`
- struktur cookie `sso_cre`
- response branching vendor
- lifecycle dalaman vendor flow

## Recommendation

1. Kekalkan hardening app side yang sudah dibuat.
2. Jangan ubah vendor block tanpa persetujuan vendor OneID.
3. Naikkan dokumen ini kepada vendor/pasukan OneID sebagai senarai baki risiko.
4. Jika vendor keluarkan client baharu, buat regression test penuh untuk:
   - callback flow
   - token validation
   - manual vs SSO policy enforcement
   - logout behavior
