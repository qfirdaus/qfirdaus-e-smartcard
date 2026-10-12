<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
return [
    // Maklumat asas laman:
    // - title: nama sistem untuk title browser / fallback paparan umum
    // - favicon: ikon tab browser
    // - default_home: laluan utama sistem selepas login / untuk canonical dan logo link
    'site' => [
        'title'        => 'Base System',
        'favicon'      => 'assets/images/favicon.ico',
        'default_home' => 'pages/dashboard.php',
    ],

    // Branding aset visual utama sistem.
    // Semua path merujuk kepada fail dalam folder `assets/images/`.
    // Tukar path di sini jika projek clone ini menggunakan logo lain.
    'branding' => [
        'login_header_logo' => 'assets/images/logo-upnm.png',
        'login_panel_logo'  => 'assets/images/upnm30-logo.png',
        'topbar_logo_light' => 'assets/images/logo.png',
        'topbar_logo_dark'  => 'assets/images/logo-dark.png',
        'topbar_logo_sm'    => 'assets/images/logo-sm.png',
        'sidebar_logo'      => 'assets/images/new-logo.png',
    ],

    // Teks footer global sistem.
    // Sesuai untuk hak cipta atau organisasi pemilik projek.
    'footer' => [
        'text' => 'Hak Cipta © ' . date('Y') . ' Sistem Induk',
    ],

    // Metadata dan identiti teknikal sistem:
    // - name: nama sistem yang dipaparkan pada login / tempat umum
    // - version: versi semasa sistem
    // - author: pemilik / pembangun sistem
    // - meta_author: nilai meta author dalam <head>
    // - support: emel sokongan global
    'system' => [
        'name'        => 'Base System',
        'version'     => '1.11.0',
        'author'      => 'Sistem Induk',
        'meta_author' => 'Base System',
        'support'     => 'support@example.com',
    ],

    // Tetapan global untuk kandungan email sistem.
    // - system_name: nama sistem yang dipaparkan dalam template email
    // - default_action_url: pautan tindakan utama dalam email (jika berkaitan)
    // - footer_note: nota standard di bahagian bawah email
    'mail' => [
        'system_name'        => 'Base System',
        'default_action_url' => '#',
        'footer_note'        => 'Emel ini dijana secara automatik. Sila jangan balas emel ini.',
    ],

    // Tetapan tingkah laku aplikasi:
    // - idle_timeout_minutes: had masa tiada aktiviti sebelum prompt sesi tamat dipaparkan
    'session' => [
        'idle_timeout_minutes' => 30,
    ],

    // Tetapan Admin View As:
    // - timeout_minutes: had masa sesi View As sebelum dipulihkan semula
    'impersonation' => [
        'timeout_minutes' => 60,
    ],

    // Tetapan had muat naik fail:
    // - manual_max_mb: had saiz maksimum PDF untuk modul manual pengguna
    'upload' => [
        'manual_max_mb' => 10,
    ],

    // Maklumat organisasi induk / pemilik sistem.
    // Belum digunakan sepenuhnya di semua page, tetapi disediakan untuk kegunaan akan datang.
    'organization' => [
        'name'    => 'Sistem Induk',
        'short'   => 'BASE',
        'website' => '#',
    ],
];
