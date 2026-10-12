<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

require_once __DIR__ . '/../controllers/__CONTROLLER_CLASS__.php';

$controllerClass = '__CONTROLLER_CLASS__';
$controller = new $controllerClass();

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function t(string $key, string $fallback): string
{
    $value = __($key);
    return ($value === $key || $value === null || $value === '') ? $fallback : (string)$value;
}

$PAGE_TITLE = t('__PAGE_KEY_PREFIX___page_title', '__PAGE_TITLE_MS__');
$formData = is_array($controller->formData ?? null) ? $controller->formData : [];
$selectedPriority = (string)($formData['priority'] ?? '');
$notificationsEnabled = !empty($formData['notifications_enabled']);
?>
<!DOCTYPE html>
<html lang="<?= h($_SESSION['lang'] ?? 'ms') ?>">
<head>
    <?php include __DIR__ . '/../includes/head.php'; ?>
    <link href="<?= base_url('assets/css/pages/__PAGE_SLUG__.css') ?>" rel="stylesheet">
</head>
<body
    data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
    data-menu-color="<?= h($_SESSION['theme.menu'] ?? 'light') ?>"
    data-layout="vertical"
    data-sidebar-size="default">

<div id="wrapper">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="content-page">
        <div class="content">
            <div class="container-fluid">
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
                            <h4 class="page-title"><i class="__PAGE_ICON__ me-1"></i> <?= h($PAGE_TITLE) ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item">
                                        <a href="dashboard.php"><i class="ri-home-4-line align-middle me-1"></i> <?= h(__('breadcrumb_home')) ?></a>
                                    </li>
                                    <li class="breadcrumb-item active"><?= h($PAGE_TITLE) ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card form-basic-card">
                            <div class="card-header border-bottom">
                                <h5 class="card-title mb-1"><?= h(t('__PAGE_KEY_PREFIX___form_title', 'Form Details')) ?></h5>
                                <p class="text-muted mb-0"><?= h(t('__PAGE_KEY_PREFIX___form_subtitle', 'Complete the information below and save the changes.')) ?></p>
                            </div>
                            <div class="card-body">
                                <form action="" method="post" class="row g-3 form-basic-sample-form" novalidate>
                                    <div class="col-12">
                                        <section class="form-basic-section-shell">
                                            <div class="form-basic-section-title"><?= h(t('__PAGE_KEY_PREFIX___section_basic', 'Basic Information')) ?></div>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_name', 'Name')) ?> <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="name" required minlength="3" value="<?= h((string)($formData['name'] ?? '')) ?>" placeholder="<?= h(t('__PAGE_KEY_PREFIX___field_name', 'Name')) ?>">
                                                    <div class="invalid-feedback"><?= h(t('__PAGE_KEY_PREFIX___validation_name', 'Please enter at least 3 characters for name.')) ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_email', 'Email')) ?> <span class="text-danger">*</span></label>
                                                    <input type="email" class="form-control" name="email" required value="<?= h((string)($formData['email'] ?? '')) ?>" placeholder="<?= h(t('__PAGE_KEY_PREFIX___field_email', 'Email')) ?>">
                                                    <div class="invalid-feedback"><?= h(t('__PAGE_KEY_PREFIX___validation_email', 'Please enter a valid email address.')) ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_code', 'Code')) ?></label>
                                                    <input type="text" class="form-control" name="code" value="<?= h((string)($formData['code'] ?? '')) ?>" placeholder="<?= h(t('__PAGE_KEY_PREFIX___field_code', 'Code')) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_status', 'Status')) ?> <span class="text-danger">*</span></label>
                                                    <select class="form-select" name="status" required>
                                                        <option value=""><?= h(t('__PAGE_KEY_PREFIX___field_select_status', 'Please select status')) ?></option>
                                                        <option value="active" <?= (($formData['status'] ?? '') === 'active') ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___status_active', 'Active')) ?></option>
                                                        <option value="inactive" <?= (($formData['status'] ?? '') === 'inactive') ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___status_inactive', 'Inactive')) ?></option>
                                                    </select>
                                                    <div class="invalid-feedback"><?= h(t('__PAGE_KEY_PREFIX___validation_status', 'Please select a status.')) ?></div>
                                                </div>
                                            </div>
                                        </section>
                                    </div>

                                    <div class="col-12">
                                        <section class="form-basic-section-shell">
                                            <div class="form-basic-section-title"><?= h(t('__PAGE_KEY_PREFIX___section_settings', 'Additional Settings')) ?></div>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_category', 'Category')) ?> <span class="text-danger">*</span></label>
                                                    <select class="form-select" name="category" required>
                                                        <option value=""><?= h(t('__PAGE_KEY_PREFIX___field_select_category', 'Please select category')) ?></option>
                                                        <option value="general" <?= (($formData['category'] ?? '') === 'general') ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___category_general', 'General')) ?></option>
                                                        <option value="secondary" <?= (($formData['category'] ?? '') === 'secondary') ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___category_secondary', 'Secondary')) ?></option>
                                                        <option value="restricted" <?= (($formData['category'] ?? '') === 'restricted') ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___category_restricted', 'Restricted')) ?></option>
                                                    </select>
                                                    <div class="invalid-feedback"><?= h(t('__PAGE_KEY_PREFIX___validation_category', 'Please select a category.')) ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_display_order', 'Display Order')) ?> <span class="text-danger">*</span></label>
                                                    <input type="number" class="form-control" name="display_order" required min="1" step="1" value="<?= h((string)($formData['display_order'] ?? '')) ?>" placeholder="1">
                                                    <div class="invalid-feedback"><?= h(t('__PAGE_KEY_PREFIX___validation_display_order', 'Display order must be a positive number.')) ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_effective_date', 'Effective Date')) ?> <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" name="effective_date" required value="<?= h((string)($formData['effective_date'] ?? '')) ?>">
                                                    <div class="invalid-feedback"><?= h(t('__PAGE_KEY_PREFIX___validation_effective_date', 'Please choose an effective date.')) ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label d-block"><?= h(t('__PAGE_KEY_PREFIX___field_priority', 'Priority')) ?> <span class="text-danger">*</span></label>
                                                    <div class="form-basic-radio-group" data-priority-group>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="priority" id="priorityLow" value="low" <?= $selectedPriority === 'low' ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="priorityLow"><?= h(t('__PAGE_KEY_PREFIX___priority_low', 'Low')) ?></label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="priority" id="priorityMedium" value="medium" <?= $selectedPriority === 'medium' ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="priorityMedium"><?= h(t('__PAGE_KEY_PREFIX___priority_medium', 'Medium')) ?></label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="priority" id="priorityHigh" value="high" <?= $selectedPriority === 'high' ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="priorityHigh"><?= h(t('__PAGE_KEY_PREFIX___priority_high', 'High')) ?></label>
                                                        </div>
                                                    </div>
                                                    <div class="invalid-feedback form-basic-radio-feedback"><?= h(t('__PAGE_KEY_PREFIX___validation_priority', 'Please choose a priority.')) ?></div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-basic-switch-card">
                                                        <div class="form-basic-switch-copy">
                                                            <div class="form-basic-switch-title"><?= h(t('__PAGE_KEY_PREFIX___field_notifications', 'Enable notifications')) ?></div>
                                                            <div class="form-basic-switch-text"><?= h(t('__PAGE_KEY_PREFIX___field_notifications_hint', 'Turn this on to send update alerts for this record.')) ?></div>
                                                        </div>
                                                        <div class="form-check form-switch form-basic-switch">
                                                            <input class="form-check-input" type="checkbox" role="switch" id="notificationsEnabled" name="notifications_enabled" <?= $notificationsEnabled ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="notificationsEnabled"><?= h($notificationsEnabled ? t('__PAGE_KEY_PREFIX___status_on', 'On') : t('__PAGE_KEY_PREFIX___status_off', 'Off')) ?></label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                    </div>

                                    <div class="col-md-8">
                                        <section class="form-basic-section-shell h-100">
                                            <div class="form-basic-section-title"><?= h(t('__PAGE_KEY_PREFIX___section_notes', 'Notes & Attachment')) ?></div>
                                            <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_notes', 'Notes')) ?></label>
                                            <textarea class="form-control" name="notes" rows="7" maxlength="500" placeholder="<?= h(t('__PAGE_KEY_PREFIX___field_notes', 'Notes')) ?>"><?= h((string)($formData['notes'] ?? '')) ?></textarea>
                                            <div class="form-text"><?= h(t('__PAGE_KEY_PREFIX___field_notes_hint', 'Maximum 500 characters.')) ?></div>
                                        </section>
                                    </div>
                                    <div class="col-md-4">
                                        <section class="form-basic-section-shell h-100">
                                            <div class="form-basic-section-title form-basic-section-title--muted"><?= h(t('__PAGE_KEY_PREFIX___field_attachment', 'Attachment')) ?></div>
                                            <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_attachment', 'Attachment')) ?></label>
                                            <input type="file" class="form-control" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            <div class="form-text"><?= h(t('__PAGE_KEY_PREFIX___field_attachment_hint', 'Accepted formats: PDF, JPG, PNG, DOC, DOCX. Maximum file size: 2MB.')) ?></div>
                                        </section>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex flex-wrap justify-content-end gap-2 pt-3">
                                            <button type="button" class="btn btn-light form-basic-btn form-basic-btn-cancel"><?= h(t('__PAGE_KEY_PREFIX___btn_cancel', 'Cancel')) ?></button>
                                            <button type="button" class="btn btn-primary form-basic-btn form-basic-btn-save"><?= h(t('__PAGE_KEY_PREFIX___btn_save', 'Save')) ?></button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/script.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var cancelBtn = document.querySelector('.form-basic-btn-cancel');
    var saveBtn = document.querySelector('.form-basic-btn-save');
    var form = document.querySelector('.form-basic-sample-form');
    var priorityGroup = document.querySelector('[data-priority-group]');
    var attachmentInput = form ? form.querySelector('input[name="attachment"]') : null;
    var allowedFileExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    var maxFileSize = 2 * 1024 * 1024;

    function clearPriorityValidation() {
        if (priorityGroup) {
            priorityGroup.classList.remove('is-invalid');
        }
    }

    function validatePriority() {
        if (!form) {
            return true;
        }

        var checked = form.querySelector('input[name="priority"]:checked');
        if (!checked) {
            if (priorityGroup) {
                priorityGroup.classList.add('is-invalid');
            }
            return false;
        }

        clearPriorityValidation();
        return true;
    }

    function validateAttachment() {
        if (!attachmentInput || !attachmentInput.files || attachmentInput.files.length === 0) {
            attachmentInput && attachmentInput.classList.remove('is-invalid');
            return true;
        }

        var file = attachmentInput.files[0];
        var extension = '';
        var dotPosition = file.name.lastIndexOf('.');
        if (dotPosition !== -1) {
            extension = file.name.substring(dotPosition + 1).toLowerCase();
        }

        var extensionValid = allowedFileExtensions.indexOf(extension) !== -1;
        var sizeValid = file.size <= maxFileSize;
        var isValid = extensionValid && sizeValid;

        attachmentInput.classList.toggle('is-invalid', !isValid);
        return isValid;
    }

    function validateForm() {
        if (!form) {
            return false;
        }

        var nativeValid = form.checkValidity();
        var priorityValid = validatePriority();
        var attachmentValid = validateAttachment();

        form.classList.add('was-validated');
        return nativeValid && priorityValid && attachmentValid;
    }

    if (cancelBtn && form) {
        cancelBtn.addEventListener('click', function () {
            form.reset();
            form.classList.remove('was-validated');
            clearPriorityValidation();
            if (attachmentInput) {
                attachmentInput.classList.remove('is-invalid');
            }
            var switchLabel = document.querySelector('label[for="notificationsEnabled"]');
            if (switchLabel) {
                switchLabel.textContent = <?= json_encode(t('__PAGE_KEY_PREFIX___status_on', 'On')) ?>;
            }
        });
    }

    if (form) {
        Array.prototype.slice.call(form.querySelectorAll('input[name="priority"]')).forEach(function (radio) {
            radio.addEventListener('change', clearPriorityValidation);
        });
    }

    if (attachmentInput) {
        attachmentInput.addEventListener('change', validateAttachment);
    }

    var notificationToggle = document.getElementById('notificationsEnabled');
    var notificationLabel = document.querySelector('label[for="notificationsEnabled"]');
    if (notificationToggle && notificationLabel) {
        var updateToggleLabel = function () {
            notificationLabel.textContent = notificationToggle.checked
                ? <?= json_encode(t('__PAGE_KEY_PREFIX___status_on', 'On')) ?>
                : <?= json_encode(t('__PAGE_KEY_PREFIX___status_off', 'Off')) ?>;
        };
        updateToggleLabel();
        notificationToggle.addEventListener('change', updateToggleLabel);
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            if (!validateForm()) {
                Swal.fire({
                    icon: 'error',
                    title: <?= json_encode(t('__PAGE_KEY_PREFIX___msg_validation_error_title', 'Validation Required')) ?>,
                    text: <?= json_encode(t('__PAGE_KEY_PREFIX___msg_validation_error', 'Please complete the required fields correctly before saving.')) ?>,
                    confirmButtonText: <?= json_encode(t('__PAGE_KEY_PREFIX___btn_ok', 'OK')) ?>
                });
                return;
            }

            Swal.fire({
                icon: 'success',
                title: <?= json_encode(t('__PAGE_KEY_PREFIX___msg_success_title', 'Sample Save Complete')) ?>,
                text: <?= json_encode(t('__PAGE_KEY_PREFIX___msg_success', 'This sample form completed successfully without sending any data to the backend.')) ?>,
                confirmButtonText: <?= json_encode(t('__PAGE_KEY_PREFIX___btn_ok', 'OK')) ?>
            });
        });
    }
});
</script>
</body>
</html>
