<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// pages/manage-manuals.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../setting/constants/manual_constants.php';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// Check if user is allowed to manage manuals
require_once __DIR__ . '/../classes/Database.php';
$db = Database::getInstance()->getConnection();

// Only allow super admins and admins to manage the manuals
if (!manual_can_manage($db)) {
    if (function_exists('prestasi_render_page_forbidden')) {
        prestasi_render_page_forbidden((string)__('manual_unauthorized_access'));
    }
    http_response_code(403);
    exit(h((string)__('manual_unauthorized_access')));
}

require_once __DIR__ . '/../controllers/ManualController.php';
$controller = new ManualController();
$csrf = (string)($_SESSION['csrf_token'] ?? '');
$manualMaxMb = (int)app_config('upload.manual_max_mb', 10);
if ($manualMaxMb <= 0) {
    $manualMaxMb = 10;
}
$PAGE_TITLE = (string)__('manual_page_title');

$message = '';
$messageType = '';
$flashAlert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    if ($postedCsrf === '' || $csrf === '' || !hash_equals($csrf, $postedCsrf)) {
        $message = (string)__('manual_csrf_reload');
        $messageType = 'danger';
    } else {
    $action = $_POST['action'] ?? '';
    $groupId = (int)($_POST['group_id'] ?? 0);
    
    if ($action === 'upload' && isset($_FILES['manual_file']) && $groupId > 0) {
        $result = $controller->uploadManual($groupId, $_FILES['manual_file']);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';
    } elseif ($action === 'delete' && $groupId > 0) {
        $result = $controller->deleteManual($groupId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';
    }
    }
}

if ($message !== '') {
    $flashAlert = [
        'success' => ($messageType === 'success'),
        'message' => $message,
    ];
}

$manualsData = $controller->getAllManuals();
foreach ($manualsData as &$manualRow) {
    $manualRow['_has_manual'] = !empty($manualRow['f_file_path'])
        && $controller->manualFileExists((string)$manualRow['f_file_path']);
}
unset($manualRow);

$lang = (string)($_SESSION['lang'] ?? 'ms');
$version = (string)($_ENV['APP_ASSET_VER'] ?? '1');
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
    <?php include __DIR__ . '/../includes/head.php'; ?>
    <meta name="csrf-token" content="<?= h($csrf) ?>">
    <link href="<?= base_url('assets/css/datatables-standard.css') ?>?v=<?= h($version) ?>" rel="stylesheet">
    <script src="<?= base_url('assets/js/helpers/datatables-standard.js') ?>?v=<?= h($version) ?>"></script>
    <style>
        #userDT { table-layout: fixed; width:100%; }
        #userDT th, #userDT td { vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        #userDT th.col-bil, #userDT td.col-bil { width:5%; text-align:center; }
        #userDT th.col-role, #userDT td.col-role { width:20%; }
        #userDT th.col-status, #userDT td.col-status { width:45%; }
        #userDT th.col-updated, #userDT td.col-updated { width:15%; }
        #userDT th.col-actions, #userDT td.col-actions { width:15%; text-align:center; }
        .truncate-1line { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dt-bottom-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:nowrap; gap:.5rem; }
        .dt-bottom-row .dataTables_info { margin:.25rem 0; white-space: nowrap; line-height: 1.5; }
        .dt-bottom-row .dataTables_paginate { margin-left:auto; }
        .dataTables_length,
        #userDT_wrapper .dataTables_length {
            white-space: nowrap !important;
            line-height: 1.4;
            display: inline-block;
        }
        .dataTables_length label,
        #userDT_wrapper .dataTables_length label {
            white-space: nowrap !important;
            display: inline-flex !important;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0;
            flex-wrap: nowrap !important;
            font-size: 0.875rem !important;
        }
        .dataTables_length select,
        #userDT_wrapper .dataTables_length select {
            display: inline-block !important;
            margin: 0 0.4rem !important;
            flex-shrink: 0 !important;
            height: 36px !important;
            min-height: 36px !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 0.875rem !important;
            line-height: 1.4 !important;
            border: 2px solid #e9ecef !important;
            border-radius: 8px !important;
            min-width: 70px !important;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
        }
        .dataTables_length select:hover,
        #userDT_wrapper .dataTables_length select:hover {
            border-color: #ced4da !important;
        }
        .dataTables_length select:focus,
        #userDT_wrapper .dataTables_length select:focus {
            border-color: #86b7fe !important;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
            outline: none !important;
        }
        .dataTables_length label > * {
            white-space: nowrap !important;
            display: inline !important;
        }
        .dt-top-left {
            white-space: nowrap !important;
            flex-wrap: nowrap !important;
            position: relative !important;
            top: 7px !important;
        }
        .dt-top-left .dataTables_length {
            white-space: nowrap !important;
        }
        #userDT {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: none;
            border: 1px solid rgba(148, 163, 184, 0.14);
            background: rgba(255, 255, 255, 0.96);
        }
        #userDT thead {
            background: transparent;
            color: inherit;
        }
        #userDT thead th {
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.9rem 0.85rem;
            border: 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.16);
            color: #334155;
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(241, 245, 249, 0.95) 100%);
        }
        #userDT tbody td {
            vertical-align: middle;
        }
        #userDT tbody tr {
            transition: background-color 0.18s ease, box-shadow 0.18s ease;
        }
        #userDT tbody tr:hover {
            background: rgba(241, 245, 249, 0.88) !important;
            transform: none;
            box-shadow: inset 0 0 0 999px rgba(241, 245, 249, 0.3);
        }
        #userDT tbody td {
            padding: 0.9rem 0.85rem;
            border-color: rgba(226, 232, 240, 0.9);
            vertical-align: middle;
            font-size: 0.875rem;
        }
        html[data-bs-theme="dark"] #userDT thead {
            background: transparent;
        }
        html[data-bs-theme="dark"] #userDT thead th {
            background: linear-gradient(180deg, rgba(30, 41, 59, 0.96) 0%, rgba(15, 23, 42, 0.94) 100%);
            color: #dbe4f0;
            border-bottom-color: rgba(148, 163, 184, 0.18);
        }
        html[data-bs-theme="dark"] #userDT tbody tr:hover {
            background: rgba(30, 41, 59, 0.76) !important;
            box-shadow: inset 0 0 0 999px rgba(30, 41, 59, 0.18);
        }
        #userDT tbody tr,
        #userDT tbody tr:nth-of-type(odd),
        #userDT tbody tr:nth-of-type(even) {
            background-color: transparent !important;
        }
        #userDT_wrapper .row.mb-2 { align-items: center; }
        #userDT_wrapper .dt-top-right,
        #userDT_wrapper .dt-top-right .dataTables_filter,
        #userDT_wrapper .dt-top-right .dataTables_filter label {
            display: flex !important;
            align-items: center !important;
        }
        #userDT_wrapper .dt-top-right {
            justify-content: flex-end;
            gap: 0.5rem !important;
        }
        #userDT_wrapper .dt-top-right .dataTables_filter {
            margin: 0 !important;
            text-align: right;
        }
        #userDT_wrapper .dt-bottom-row{
            display:flex !important;
            align-items:center !important;
            justify-content:space-between !important;
            flex-wrap:nowrap !important;
            width:100%;
            gap:.5rem !important;
            margin-top:0 !important;
            padding-top:.15rem !important;
        }
        #userDT_wrapper .dt-bottom-row > .dt-info-left{
            flex:0 1 auto !important;
            min-width:0 !important;
            overflow:hidden !important;
            display:flex !important;
            justify-content:flex-start !important;
            align-items:center !important;
            margin-right:auto !important;
        }
        #userDT_wrapper .dt-bottom-row > .dt-paging-right{
            flex:0 0 auto !important;
            margin-left:auto !important;
            display:flex !important;
            justify-content:flex-end !important;
            align-items:center !important;
            position:relative !important;
            top:-7px !important;
        }
        #userDT_wrapper .dataTables_paginate{
            margin-top:0 !important;
            white-space:nowrap !important;
            display:flex !important;
            align-items:center !important;
            justify-content:flex-end !important;
        }
        #userDT_wrapper .dataTables_filter { text-align: right; }
        #userDT_wrapper .dataTables_filter label { 
            margin: 0 !important;
            font-size: 0.875rem !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
        }
        #userDT_wrapper .dataTables_filter input {
            display: inline-block !important;
            width: 160px !important;
            max-width: 100% !important;
            height: 36px !important;
            min-height: 36px !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 0.875rem !important;
            line-height: 1.4 !important;
            border: 2px solid #e9ecef !important;
            border-radius: 8px !important;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
        }
        #userDT_wrapper .dataTables_filter input:focus {
            border-color: #86b7fe !important;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
            outline: none !important;
        }
        .icon-btn { padding:.25rem .5rem; line-height:1; }
        .manual-actions {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            flex-wrap: wrap;
        }
        .manual-actions .btn { white-space: nowrap; }
        .manual-updated-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.28rem 0.65rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #9a6700;
            background: #fff3cd;
            border: 1px solid #ffe69c;
        }
        .sync-groups-btn {
            height: 36px !important;
            min-height: 36px !important;
            min-width: 148px !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 0.875rem !important;
            line-height: 1.4 !important;
            border-radius: 8px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 0.4rem !important;
            white-space: nowrap !important;
            margin: 0 !important;
            align-self: center !important;
            box-shadow: 0 12px 26px rgba(37, 99, 235, 0.18) !important;
            vertical-align: middle !important;
        }
        .sync-groups-btn .sync-groups-spinner {
            display: none;
            width: 0.95rem;
            height: 0.95rem;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-top-color: #ffffff;
            animation: manual-sync-spin 0.75s linear infinite;
            flex: 0 0 auto;
        }
        .sync-groups-btn.is-busy .sync-groups-spinner {
            display: inline-block;
        }
        .sync-groups-btn.is-busy .sync-groups-icon {
            display: none;
        }
        .sync-groups-btn:disabled {
            opacity: 0.92 !important;
            cursor: wait !important;
        }
        .sync-groups-btn:hover,
        .sync-groups-btn:focus {
            box-shadow: 0 12px 26px rgba(37, 99, 235, 0.18) !important;
        }
        @keyframes manual-sync-spin {
            to { transform: rotate(360deg); }
        }
        .manual-link {
            font-size: .875rem;
            text-decoration: none;
        }
        .manual-link:hover { text-decoration: underline; }
        .manual-note {
            font-size: .875rem;
            color: #6c757d;
        }
        .manual-upload-shell {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.96) 0%, rgba(241, 245, 249, 0.92) 100%);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 8px;
            padding: 1.1rem 1rem;
        }
        .manual-upload-dropzone {
            border: 1.5px dashed rgba(99, 102, 241, 0.3);
            background: #ffffff;
            border-radius: 8px;
            padding: 1.1rem 1rem;
        }
        .manual-upload-meta {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1rem;
        }
        .manual-upload-meta-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            background: rgba(37, 99, 235, 0.12);
            color: #2563eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex: 0 0 auto;
        }
        .manual-upload-meta-title {
            font-weight: 700;
            color: #0f172a;
        }
        .manual-upload-meta-text {
            font-size: .92rem;
            color: #64748b;
            margin-top: .1rem;
        }
        .manual-upload-help {
            margin-top: .85rem;
            font-size: .86rem;
            color: #64748b;
        }
        .manual-upload-replace {
            display: none;
            margin-top: .9rem;
            padding: .85rem 1rem;
            border-radius: 8px;
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            font-size: .9rem;
        }
        .manual-upload-replace.is-visible {
            display: block;
        }
        #uploadModal,
        #uploadModal .modal-dialog,
        #uploadModal .modal-content,
        #uploadModal .modal-content::before,
        #uploadModal .modal-content::after,
        #uploadModal.modal-themed .modal-content,
        #uploadModal.modal-themed .modal-content::before,
        #uploadModal.modal-themed .modal-content::after {
            box-shadow: none !important;
            outline: 0 !important;
            filter: none !important;
        }
        #uploadModal .modal-dialog {
            border: 0 !important;
            background: transparent !important;
        }
        #uploadModal.modal.fade {
            transition: none !important;
        }
        #uploadModal.modal.fade .modal-dialog {
            transition: none !important;
            transform: none !important;
        }
        #uploadModal.modal.show .modal-dialog {
            transform: none !important;
        }
        #uploadModal .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: none !important;
            outline: 0 !important;
            filter: none !important;
            overflow: hidden;
        }
        #uploadModal .modal-dialog {
            max-width: 760px;
        }
        #uploadModal .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-bottom: none;
            padding: 1.1rem 1.35rem;
        }
        #uploadModal .modal-title {
            color: #fff;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: .65rem;
            font-size: 1.2rem;
        }
        #uploadModal .btn-close { filter: brightness(0) invert(1); opacity: .9; }
        #uploadModal .modal-body { padding: 1.35rem; }
        #uploadModal .modal-footer {
            padding: 1rem 1.35rem;
            background: #f8f9fa;
            border-top: 1px solid rgba(0,0,0,.08);
        }
        #uploadModal .form-label {
            font-weight: 600;
            margin-bottom: 0.65rem;
        }
        #uploadModal .form-control {
            min-height: 46px;
            border-radius: 8px;
        }
        .swal2-manual-popup {
            border-radius: 8px !important;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18) !important;
        }
        .swal2-manual-toast {
            width: min(34rem, calc(100vw - 1.5rem)) !important;
            max-width: min(34rem, calc(100vw - 1.5rem)) !important;
            padding: 1rem 1.15rem !important;
            border-radius: 10px !important;
        }
        .swal2-manual-toast.swal2-toast {
            align-items: flex-start !important;
        }
        .swal2-manual-toast .swal2-title {
            font-size: 1rem !important;
            line-height: 1.35 !important;
            width: 100% !important;
            margin: 0 !important;
            text-align: left !important;
        }
        .swal2-manual-toast .swal2-html-container,
        .swal2-manual-toast .swal2-content {
            font-size: 0.92rem !important;
            line-height: 1.45 !important;
            margin: 0.3rem 0 0 !important;
            width: 100% !important;
            text-align: left !important;
        }
        .swal2-manual-toast .swal2-icon {
            margin-top: 0.1rem !important;
            margin-bottom: 0.1rem !important;
        }
        .swal2-manual-toast .swal2-icon + .swal2-title,
        .swal2-manual-toast .swal2-icon + .swal2-html-container {
            margin-left: 0 !important;
        }
        .swal2-manual-toast-message {
            display: block;
            white-space: normal;
            word-break: break-word;
            width: 100%;
            text-align: left;
        }
        .swal2-manual-title {
            font-size: 1.4rem !important;
            font-weight: 700 !important;
            color: #0f172a !important;
        }
        .swal2-manual-confirm {
            border-radius: 8px !important;
            padding: 0.65rem 1.2rem !important;
            font-weight: 600 !important;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22) !important;
        }
        .swal2-manual-cancel {
            border-radius: 8px !important;
            padding: 0.65rem 1.2rem !important;
            font-weight: 600 !important;
        }

        /* Match senarai-pengguna table shell */
        .content-page .card {
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, 0.14);
            box-shadow: 0 16px 38px rgba(15, 23, 42, 0.07);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        .content-page .card > .card-body {
            padding: 1.15rem 1.15rem 1rem;
        }
        #userDT {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: none;
            border: 1px solid rgba(148, 163, 184, 0.14);
            background: rgba(255, 255, 255, 0.96);
        }
        #userDT thead {
            background: transparent;
            color: inherit;
        }
        #userDT thead th {
            padding: 0.9rem 0.85rem;
            border: 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.16);
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(241, 245, 249, 0.95) 100%);
            color: #334155;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        #userDT tbody td {
            padding: 0.9rem 0.85rem;
            border-color: rgba(226, 232, 240, 0.9);
            font-size: 0.92rem;
            vertical-align: middle;
        }
        #userDT tbody tr {
            transition: background-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }
        #userDT tbody tr:hover {
            background: rgba(241, 245, 249, 0.88) !important;
            transform: none;
            box-shadow: inset 0 0 0 999px rgba(241, 245, 249, 0.3);
        }
        #userDT_wrapper .dataTables_filter input,
        #userDT_wrapper .dataTables_length select {
            border-radius: 8px !important;
            border: 1px solid rgba(148, 163, 184, 0.24) !important;
            background: rgba(255, 255, 255, 0.98) !important;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }
        #userDT_wrapper .dataTables_filter input:focus,
        #userDT_wrapper .dataTables_length select:focus {
            border-color: rgba(59, 130, 246, 0.45) !important;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.14), 0 12px 24px rgba(15, 23, 42, 0.06) !important;
        }
        #userDT_wrapper .paginate_button .page-link {
            border-radius: 8px !important;
        }
        html[data-bs-theme="dark"] #userDT {
            background: rgba(15, 23, 42, 0.92);
            border-color: rgba(148, 163, 184, 0.22);
        }
        html[data-bs-theme="dark"] #userDT thead th {
            background: linear-gradient(180deg, rgba(30, 41, 59, 0.96) 0%, rgba(15, 23, 42, 0.94) 100%);
            color: #dbe4f0;
            border-bottom-color: rgba(148, 163, 184, 0.18);
        }
        html[data-bs-theme="dark"] #userDT tbody td {
            border-color: rgba(51, 65, 85, 0.95);
        }
        html[data-bs-theme="dark"] #userDT tbody tr:hover {
            background: rgba(30, 41, 59, 0.76) !important;
            box-shadow: inset 0 0 0 999px rgba(30, 41, 59, 0.18);
        }
        html[data-bs-theme="dark"] #userDT_wrapper .dataTables_filter input,
        html[data-bs-theme="dark"] #userDT_wrapper .dataTables_length select {
            background: rgba(15, 23, 42, 0.96) !important;
            border-color: rgba(148, 163, 184, 0.24) !important;
            color: #e2e8f0 !important;
        }
    </style>
    <link href="<?= h(base_url('assets/css/pages/manage-manuals.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
</head>
<body data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
      data-menu-color="<?= h($_SESSION['theme.menu'] ?? 'light') ?>"
      data-layout="vertical" data-sidebar-size="default" class="loading">
    <div class="wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="content-page">
            <div class="content">
                <div class="container-fluid manage-manuals-page">

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
                                <h4 class="page-title"><i class="ri-book-open-line me-1"></i> <?= h(__('manual_page_title')) ?></h4>
                                <div class="page-title-right">
                                    <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item">
                                            <a href="dashboard.php">
                                                <i class="ri-home-4-line align-middle me-1"></i> <?= h(__('manual_breadcrumb_home')) ?>
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item active"><?= h(__('manual_page_title')) ?></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <section class="manual-hero">
                        <div>
                            <span class="manual-eyebrow"><i class="ri-shield-check-line"></i> <?= h(__('manual_hero_eyebrow')) ?></span>
                            <h1><?= h(__('manual_hero_title')) ?></h1>
                            <p><?= h(__('manual_hero_text')) ?></p>
                        </div>
                        <div class="manual-hero__notice"><i class="ri-file-shield-2-line"></i><div><strong><?= h(__('manual_secure_title')) ?></strong><span><?= h(sprintf((string)__('manual_secure_text'), $manualMaxMb)) ?></span></div></div>
                    </section>

                    <div class="row">
                        <div class="col-12">
                            <div class="card manual-list-card">
                                <div class="card-body">
                                    <div class="manual-list-heading"><div><span class="manual-eyebrow"><?= h(__('manual_list_eyebrow')) ?></span><h2><?= h(__('manual_list_title')) ?></h2><p><?= h(__('manual_list_text')) ?></p></div><div class="manual-list-legend"><span><i class="ri-checkbox-circle-fill"></i><?= h(__('manual_status_saved')) ?></span><span><i class="ri-time-line"></i><?= h(__('manual_status_not_uploaded')) ?></span></div></div>
                                    <div class="table-responsive dt-standard">
                                    <table class="table table-bordered align-middle" id="userDT">
                                    <thead>
                                        <tr>
                                            <th class="col-bil"><?= h(__('manual_col_no')) ?></th>
                                            <th class="col-role"><?= h(__('manual_col_group')) ?></th>
                                            <th class="col-status"><?= h(__('manual_col_status')) ?></th>
                                            <th class="col-updated"><?= h(__('manual_col_updated_at')) ?></th>
                                            <th class="col-actions"><?= h(__('manual_col_actions')) ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($manualsData)): ?>
                                            <tr><td colspan="5" class="text-center text-muted"><?= h(__('manual_no_groups_found')) ?></td></tr>
                                        <?php else: ?>
                                            <?php foreach ($manualsData as $i => $row): ?>
                                                <?php $hasManual = !empty($row['_has_manual']); ?>
                                                <tr data-group-id="<?= (int)$row['f_groupID'] ?>">
                                                    <td class="col-bil"></td>
                                                    <td class="col-role">
                                                        <span class="truncate-1line fw-semibold"><?= h($row['f_groupName']) ?></span>
                                                    </td>
                                                    <td class="col-status manual-status-cell">
                                                        <?php if ($hasManual): ?>
                                                            <span class="badge bg-success"><?= h(__('manual_status_saved')) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary"><?= h(__('manual_status_not_uploaded')) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="col-updated manual-updated-cell">
                                                        <?php if (!empty($row['f_updated_at'])): ?>
                                                            <span class="small text-muted">
                                                                <?= date('d/m/Y h:i A', strtotime($row['f_updated_at'])) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="manual-updated-badge"><?= h(__('manual_none')) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="col-actions manual-actions-cell">
                                                        <div class="manual-actions">
                                                        <button class="btn btn-outline-primary btn-sm icon-btn btn-upload" 
                                                                data-id="<?= $row['f_groupID'] ?>" 
                                                                data-name="<?= h($row['f_groupName']) ?>"
                                                                data-has-manual="<?= $hasManual ? '1' : '0' ?>"
                                                                title="<?= h(__('manual_action_upload')) ?>">
                                                            <i class="ri-upload-2-line"></i>
                                                        </button>
                                                        <?php if ($hasManual): ?>
                                                            <a href="<?= h(base_url('ajax/manual-view.php?group_id=' . (int)$row['f_groupID'])) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm icon-btn" title="<?= h(__('manual_action_view')) ?>">
                                                                <i class="ri-eye-line"></i>
                                                            </a>
                                                            <button class="btn btn-outline-danger btn-sm icon-btn btn-delete" 
                                                                    data-id="<?= $row['f_groupID'] ?>"
                                                                    data-name="<?= h($row['f_groupName']) ?>"
                                                                    title="<?= h(__('manual_action_delete')) ?>">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include __DIR__ . '/../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Upload Modal -->
    <div class="modal fade modal-themed" id="uploadModal" tabindex="-1" aria-hidden="true" aria-labelledby="uploadModalTitle">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="" method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="group_id" id="uploadGroupId" value="">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="uploadModalTitle"><i class="ri-upload-cloud-2-line"></i> <?= h(__('manual_upload_modal_title')) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="manual-upload-shell">
                            <div class="manual-upload-meta">
                                <div class="manual-upload-meta-icon">
                                    <i class="ri-file-pdf-2-line"></i>
                                </div>
                                <div>
                                    <div class="manual-upload-meta-title"><?= h(__('manual_upload_modal_intro')) ?> <strong id="uploadGroupName"></strong></div>
                                    <div class="manual-upload-meta-text"><?= h(__('manual_upload_modal_subtext')) ?></div>
                                </div>
                            </div>
                            <div class="manual-upload-dropzone">
                                <label class="form-label"><?= h(sprintf((string)__('manual_upload_field_label'), $manualMaxMb)) ?></label>
                                <input type="file" name="manual_file" id="uploadManualFile" class="form-control" accept="application/pdf" required>
                                <div class="manual-file-preview d-none" id="manualFilePreview" aria-live="polite"><i class="ri-file-pdf-2-line"></i><div><strong id="manualFileName"></strong><span id="manualFileSize"></span></div><i class="ri-checkbox-circle-fill"></i></div>
                                <div class="manual-upload-help"><?= h(__('manual_upload_help_text')) ?></div>
                                <div class="manual-upload-replace" id="uploadReplaceNotice"><?= h(__('manual_upload_replace_notice')) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= h(__('manual_btn_cancel')) ?></button>
                        <button type="submit" class="btn btn-primary" id="uploadSubmitBtn">
                            <span class="submit-label"><?= h(__('manual_btn_upload_save')) ?></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Form -->
    <form id="deleteForm" action="" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="group_id" id="deleteGroupId" value="">
    </form>

    <?php include __DIR__ . '/../includes/script.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const uploadModalEl = document.getElementById('uploadModal');
            const uploadModal = new bootstrap.Modal(uploadModalEl);
            const uploadForm = document.getElementById('uploadForm');
            const uploadGroupId = document.getElementById('uploadGroupId');
            const uploadGroupName = document.getElementById('uploadGroupName');
            const uploadFileInput = document.getElementById('uploadManualFile');
            const uploadSubmitBtn = document.getElementById('uploadSubmitBtn');
            const uploadSubmitLabel = uploadSubmitBtn?.querySelector('.submit-label');
            const uploadReplaceNotice = document.getElementById('uploadReplaceNotice');
            const filePreview = document.getElementById('manualFilePreview');
            const filePreviewName = document.getElementById('manualFileName');
            const filePreviewSize = document.getElementById('manualFileSize');
            let uploadWasReplacement = false;

            const T = {
                uploadInvalidType: <?= json_encode(__('manual_upload_pdf_only'), JSON_UNESCAPED_UNICODE) ?>,
                uploadMaxSize: <?= json_encode(sprintf((string)__('manual_upload_max_size'), $manualMaxMb), JSON_UNESCAPED_UNICODE) ?>,
                uploadMissing: <?= json_encode(__('manual_upload_select_file'), JSON_UNESCAPED_UNICODE) ?>,
                uploadLoadingTitle: <?= json_encode(__('manual_upload_loading_title'), JSON_UNESCAPED_UNICODE) ?>,
                uploadLoadingText: <?= json_encode(__('manual_upload_loading_text'), JSON_UNESCAPED_UNICODE) ?>,
                uploadProcessing: <?= json_encode(__('manual_upload_processing_btn'), JSON_UNESCAPED_UNICODE) ?>,
                uploadSuccessTitle: <?= json_encode(__('manual_upload_success_title'), JSON_UNESCAPED_UNICODE) ?>,
                uploadErrorTitle: <?= json_encode(__('manual_upload_error_title'), JSON_UNESCAPED_UNICODE) ?>,
                uploadNetworkError: <?= json_encode(__('manual_server_upload_error'), JSON_UNESCAPED_UNICODE) ?>,
                statusSaved: <?= json_encode(__('manual_status_saved'), JSON_UNESCAPED_UNICODE) ?>,
                actionUpload: <?= json_encode(__('manual_action_upload'), JSON_UNESCAPED_UNICODE) ?>,
                actionView: <?= json_encode(__('manual_action_view'), JSON_UNESCAPED_UNICODE) ?>,
                actionDelete: <?= json_encode(__('manual_action_delete'), JSON_UNESCAPED_UNICODE) ?>,
                close: <?= json_encode(__('manual_btn_close'), JSON_UNESCAPED_UNICODE) ?>,
                deleteFallback: <?= json_encode(__('manual_group_fallback'), JSON_UNESCAPED_UNICODE) ?>,
                none: <?= json_encode(__('manual_none'), JSON_UNESCAPED_UNICODE) ?>,
                defaultUploadLabel: <?= json_encode(__('manual_btn_upload_save'), JSON_UNESCAPED_UNICODE) ?>
            };

            function setUploadBusy(isBusy) {
                if (!uploadSubmitBtn || !uploadSubmitLabel || !uploadFileInput) {
                    return;
                }
                uploadSubmitBtn.disabled = isBusy;
                uploadSubmitLabel.textContent = isBusy ? T.uploadProcessing : T.defaultUploadLabel;
            }

            function bindUploadButton(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const hasManual = this.getAttribute('data-has-manual') === '1';
                    uploadWasReplacement = hasManual;

                    uploadGroupId.value = id;
                    uploadGroupName.textContent = name;
                    uploadForm.reset();
                    filePreview.classList.add('d-none');
                    uploadReplaceNotice.classList.toggle('is-visible', hasManual);
                    setUploadBusy(false);
                    uploadModal.show();
                });
            }

            function bindDeleteButton(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name') || T.deleteFallback;
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: <?= json_encode(__('manual_delete_confirm_title'), JSON_UNESCAPED_UNICODE) ?>,
                            text: (<?= json_encode(__('manual_delete_confirm_text'), JSON_UNESCAPED_UNICODE) ?>).replace('{group}', name),
                            showCancelButton: true,
                            confirmButtonText: <?= json_encode(__('manual_btn_delete'), JSON_UNESCAPED_UNICODE) ?>,
                            cancelButtonText: <?= json_encode(__('manual_btn_cancel'), JSON_UNESCAPED_UNICODE) ?>,
                            confirmButtonColor: '#dc3545',
                            reverseButtons: true,
                            customClass: {
                                popup: 'swal2-manual-popup',
                                title: 'swal2-manual-title',
                                confirmButton: 'swal2-manual-confirm',
                                cancelButton: 'swal2-manual-cancel'
                            }
                        }).then(async (result) => {
                            if (result.isConfirmed) {
                                await deleteManual(id, this);
                            }
                        });
                    } else if (window.confirm((<?= json_encode(__('manual_delete_confirm_text'), JSON_UNESCAPED_UNICODE) ?>).replace('{group}', name))) {
                        deleteManual(id, this);
                    }
                });
            }

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
                })[char]);
            }

            function buildActionsHtml(data) {
                const safeName = escapeHtml(data.groupName || '');
                return `
                    <div class="manual-actions">
                        <button class="btn btn-outline-primary btn-sm icon-btn btn-upload"
                            data-id="${data.groupId}"
                            data-name="${safeName}"
                            data-has-manual="1"
                            title="${escapeHtml(T.actionUpload)}">
                            <i class="ri-upload-2-line"></i>
                        </button>
                        <a href="${escapeHtml(data.fileUrl)}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm icon-btn" title="${escapeHtml(T.actionView)}">
                            <i class="ri-eye-line"></i>
                        </a>
                        <button class="btn btn-outline-danger btn-sm icon-btn btn-delete"
                            data-id="${data.groupId}"
                            data-name="${safeName}"
                            title="${escapeHtml(T.actionDelete)}">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </div>
                `;
            }

            async function deleteManual(groupId, button) {
                if (button) button.disabled = true;
                try {
                    const response = await fetch('<?= h(base_url('ajax/manual-delete.php')) ?>', {
                        method: 'POST',
                        noLoader: true,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken,
                            'X-No-Loader': '1'
                        },
                        body: JSON.stringify({ group_id: Number(groupId) })
                    });
                    const result = await response.json();
                    if (!response.ok || !result || result.error) {
                        throw new Error((result && result.message) || T.uploadNetworkError);
                    }
                    const row = document.querySelector(`tr[data-group-id="${Number(groupId)}"]`);
                    if (row) {
                        const name = row.querySelector('.col-role .fw-semibold')?.textContent || '';
                        row.querySelector('.manual-status-cell').innerHTML = `<span class="badge bg-secondary">${escapeHtml(<?= json_encode(__('manual_status_not_uploaded'), JSON_UNESCAPED_UNICODE) ?>)}</span>`;
                        row.querySelector('.manual-updated-cell').innerHTML = `<span class="manual-updated-badge">${escapeHtml(T.none)}</span>`;
                        const actionsCell = row.querySelector('.manual-actions-cell');
                        actionsCell.innerHTML = `<div class="manual-actions"><button class="btn btn-outline-primary btn-sm icon-btn btn-upload" data-id="${Number(groupId)}" data-name="${escapeHtml(name)}" data-has-manual="0" title="${escapeHtml(T.actionUpload)}"><i class="ri-upload-2-line"></i></button></div>`;
                        const uploadButton = actionsCell.querySelector('.btn-upload');
                        if (uploadButton) bindUploadButton(uploadButton);
                        if (window.jQuery && jQuery.fn && jQuery.fn.DataTable && jQuery.fn.dataTable.isDataTable('#userDT')) {
                            jQuery('#userDT').DataTable().row(row).invalidate('dom').draw(false);
                        }
                    }
                    if (window.Swal) await Swal.fire({ icon: 'success', title: <?= json_encode(__('manual_alert_success_title'), JSON_UNESCAPED_UNICODE) ?>, text: result.message || '', confirmButtonText: T.close });
                } catch (error) {
                    if (window.Swal) await Swal.fire({ icon: 'error', title: <?= json_encode(__('manual_alert_error_title'), JSON_UNESCAPED_UNICODE) ?>, text: error.message || T.uploadNetworkError, confirmButtonText: T.close });
                    if (button) button.disabled = false;
                }
            }

            function refreshManualRow(data) {
                const row = document.querySelector(`tr[data-group-id="${data.groupId}"]`);
                if (!row) {
                    return;
                }

                const statusCell = row.querySelector('.manual-status-cell');
                const updatedCell = row.querySelector('.manual-updated-cell');
                const actionsCell = row.querySelector('.manual-actions-cell');

                if (statusCell) {
                    statusCell.innerHTML = `<span class="badge bg-success">${T.statusSaved}</span>`;
                }
                if (updatedCell) {
                    updatedCell.innerHTML = data.updatedAt
                        ? `<span class="small text-muted">${data.updatedAt}</span>`
                        : `<span class="manual-updated-badge">${T.none}</span>`;
                }
                if (actionsCell) {
                    actionsCell.innerHTML = buildActionsHtml(data);
                    actionsCell.querySelectorAll('.btn-upload').forEach(bindUploadButton);
                    actionsCell.querySelectorAll('.btn-delete').forEach(bindDeleteButton);
                }

                if (window.jQuery && jQuery.fn && jQuery.fn.DataTable && jQuery.fn.dataTable.isDataTable('#userDT')) {
                    jQuery('#userDT').DataTable().row(row).invalidate('dom').draw(false);
                }
            }

            function showSyncNotice(icon, title, text) {
                if (!window.Swal) {
                    return Promise.resolve();
                }

                const escapedText = String(text || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');

                return Swal.fire({
                    toast: true,
                    position: 'top-end',
                    width: '34rem',
                    icon,
                    title,
                    html: `<div class="swal2-manual-toast-message">${escapedText}</div>`,
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'swal2-manual-popup swal2-manual-toast',
                        title: 'swal2-manual-title',
                        htmlContainer: 'swal2-manual-toast-html'
                    }
                });
            }

            function setSyncButtonBusy(btn, isBusy) {
                if (!btn) {
                    return;
                }
                btn.disabled = isBusy;
                btn.classList.toggle('is-busy', isBusy);
                btn.setAttribute('aria-busy', isBusy ? 'true' : 'false');
            }

            if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
                jQuery('#userDT').DataTable({
                    pageLength: 10,
                    lengthChange: true,
                    lengthMenu: [10, 25, 50, 100, 200],
                    ordering: true,
                    order: [[1,'asc']],
                    autoWidth: false,
                    scrollX: false,
                    dom: '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
                        't' +
                        '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
                    language: {
                        lengthMenu: "<?= h(__('manual_dt_length_menu')) ?>",
                        search: "",
                        info: "<?= h(__('manual_dt_info')) ?>",
                        infoEmpty: "<?= h(__('manual_dt_info_empty')) ?>",
                        paginate: {
                            previous: "<?= h(__('manual_dt_paginate_prev')) ?>",
                            next: "<?= h(__('manual_dt_paginate_next')) ?>"
                        },
                        zeroRecords: "<?= h(__('manual_dt_zero_records')) ?>"
                    },
                    columnDefs: [
                        { targets: 0, orderable:false, searchable:false, width: 56 },
                        { targets: 4, orderable:false, searchable:false, width: 110 }
                    ],
                    rowCallback: function(row, data, displayIndex){
                        const api  = this.api();
                        const info = api.page.info();
                        jQuery('td:eq(0)', row).text(info.start + displayIndex + 1);
                    },
                    initComplete: function() {
                        if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
                            window.DataTableStandard.decorate('#userDT', {
                                searchPlaceholder: <?= json_encode(h(__('manual_dt_search_label'))) ?>
                            });
                        }
                        try {
                            const lbl = <?= json_encode(h(__('manual_dt_search_label'))) ?>;
                            const ph = String(lbl).replace(/[:\s]+$/, '').trim();
                            jQuery('#userDT_filter input').attr('placeholder', ph);
                        } catch (e) {}

                        jQuery('#userDT_length select').addClass('form-select w-auto');
                        jQuery('#userDT_length label').addClass('mb-0');
                        const $topLeft = jQuery('#userDT_wrapper .dt-top-left').addClass('d-flex align-items-center gap-2 flex-nowrap');
                        const $topRight = jQuery('#userDT_wrapper .dt-top-right');
                        $topRight.addClass('align-items-center gap-2 flex-nowrap');
                        const manualSyncEnabled = false;
                        if (manualSyncEnabled && $topRight.length && !jQuery('#btnSyncManualGroups').length) {
                            const $btn = jQuery(
                                '<button type="button" id="btnSyncManualGroups" class="btn btn-primary sync-groups-btn">' +
                                '<span class="sync-groups-spinner" aria-hidden="true"></span>' +
                                '<i class="ri-loop-right-line sync-groups-icon" aria-hidden="true"></i><span><?= h(__('manual_btn_sync_groups')) ?></span>' +
                                '</button>'
                            );
                            $topRight.append($btn);
                            $btn.on('click', async function () {
                                const btn = this;
                                if (btn.disabled) {
                                    return;
                                }
                                setSyncButtonBusy(btn, true);

                                try {
                                    const response = await fetch('<?= h(base_url('ajax/manual-sync-groups.php')) ?>', {
                                        method: 'POST',
                                        headers: {
                                            'Accept': 'application/json',
                                            'X-CSRF-Token': csrfToken
                                        }
                                    });

                                    const result = await response.json();
                                    if (!response.ok || !result || result.error) {
                                        throw new Error((result && result.message) || <?= json_encode(__('manual_sync_failed'), JSON_UNESCAPED_UNICODE) ?>);
                                    }

                                    await showSyncNotice(
                                        'success',
                                        <?= json_encode(__('manual_sync_success_title'), JSON_UNESCAPED_UNICODE) ?>,
                                        result.message || <?= json_encode(__('manual_sync_success_fallback'), JSON_UNESCAPED_UNICODE) ?>
                                    );
                                } catch (error) {
                                    await showSyncNotice(
                                        'error',
                                        <?= json_encode(__('manual_sync_error_title'), JSON_UNESCAPED_UNICODE) ?>,
                                        error.message || <?= json_encode(__('manual_unknown_error'), JSON_UNESCAPED_UNICODE) ?>
                                    );
                                } finally {
                                    setSyncButtonBusy(btn, false);
                                }
                            });
                        }
                    }
                });
            }

            document.querySelectorAll('.btn-upload').forEach(bindUploadButton);
            document.querySelectorAll('.btn-delete').forEach(bindDeleteButton);

            uploadFileInput.addEventListener('change', function () {
                const file = this.files && this.files[0] ? this.files[0] : null;
                filePreview.classList.toggle('d-none', !file);
                if (!file) return;
                filePreviewName.textContent = file.name || '';
                filePreviewSize.textContent = file.size < 1024 * 1024
                    ? `${(file.size / 1024).toFixed(1)} KB`
                    : `${(file.size / (1024 * 1024)).toFixed(2)} MB`;
            });

            uploadForm.addEventListener('submit', async function(event) {
                event.preventDefault();

                const file = uploadFileInput.files && uploadFileInput.files[0] ? uploadFileInput.files[0] : null;
                if (!file) {
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'warning',
                            title: T.uploadErrorTitle,
                            text: T.uploadMissing,
                            confirmButtonText: T.close,
                            confirmButtonColor: '#f59e0b',
                            customClass: {
                                popup: 'swal2-manual-popup',
                                title: 'swal2-manual-title',
                                confirmButton: 'swal2-manual-confirm'
                            }
                        });
                    }
                    return;
                }

                const fileName = (file.name || '').toLowerCase();
                const isPdf = file.type === 'application/pdf' || fileName.endsWith('.pdf');
                if (!isPdf) {
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'warning',
                            title: T.uploadErrorTitle,
                            text: T.uploadInvalidType,
                            confirmButtonText: T.close,
                            confirmButtonColor: '#f59e0b',
                            customClass: {
                                popup: 'swal2-manual-popup',
                                title: 'swal2-manual-title',
                                confirmButton: 'swal2-manual-confirm'
                            }
                        });
                    }
                    return;
                }

                if (file.size > (<?= (int)$manualMaxMb ?> * 1024 * 1024)) {
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'warning',
                            title: T.uploadErrorTitle,
                            text: T.uploadMaxSize,
                            confirmButtonText: T.close,
                            confirmButtonColor: '#f59e0b',
                            customClass: {
                                popup: 'swal2-manual-popup',
                                title: 'swal2-manual-title',
                                confirmButton: 'swal2-manual-confirm'
                            }
                        });
                    }
                    return;
                }

                const formData = new FormData(uploadForm);
                setUploadBusy(true);

                try {
                    const response = await fetch('<?= h(base_url('ajax/manual-upload.php')) ?>', {
                        method: 'POST',
                        noLoader: true,
                        headers: {
                            'Accept': 'application/json',
                            'X-No-Loader': '1'
                        },
                        body: formData
                    });
                    const result = await response.json();

                    if (!response.ok || !result || result.error) {
                        throw new Error((result && result.message) || T.uploadNetworkError);
                    }

                    refreshManualRow(result.data || {});
                    uploadModal.hide();
                    uploadForm.reset();
                    filePreview.classList.add('d-none');
                    uploadReplaceNotice.classList.remove('is-visible');

                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'success',
                            title: T.uploadSuccessTitle,
                            text: result.message || T.statusSaved,
                            confirmButtonText: T.close,
                            confirmButtonColor: '#2563eb',
                            customClass: {
                                popup: 'swal2-manual-popup',
                                title: 'swal2-manual-title',
                                confirmButton: 'swal2-manual-confirm'
                            }
                        });
                    }
                } catch (error) {
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'error',
                            title: T.uploadErrorTitle,
                            text: error.message || T.uploadNetworkError,
                            confirmButtonText: T.close,
                            confirmButtonColor: '#dc3545',
                            customClass: {
                                popup: 'swal2-manual-popup',
                                title: 'swal2-manual-title',
                                confirmButton: 'swal2-manual-confirm'
                            }
                        });
                    }
                } finally {
                    setUploadBusy(false);
                }
            });

            const flashAlert = <?= json_encode($flashAlert, JSON_UNESCAPED_UNICODE) ?>;
            if (flashAlert && flashAlert.message && window.Swal) {
                Swal.fire({
                    icon: flashAlert.success ? 'success' : 'error',
                    title: flashAlert.success ? <?= json_encode(__('manual_alert_success_title'), JSON_UNESCAPED_UNICODE) ?> : <?= json_encode(__('manual_alert_error_title'), JSON_UNESCAPED_UNICODE) ?>,
                    text: flashAlert.message,
                    confirmButtonText: <?= json_encode(__('manual_btn_close'), JSON_UNESCAPED_UNICODE) ?>,
                    confirmButtonColor: flashAlert.success ? '#2563eb' : '#dc3545',
                    customClass: {
                        popup: 'swal2-manual-popup',
                        title: 'swal2-manual-title',
                        confirmButton: 'swal2-manual-confirm'
                    }
                });
            }

        });
    </script>
</body>
</html>
