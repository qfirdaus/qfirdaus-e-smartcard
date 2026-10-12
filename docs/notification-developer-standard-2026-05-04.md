# Notification Developer Standard

Date: 2026-05-04
Workspace: `/var/www/app\iqs-framework`

Purpose:
- Standard rujukan untuk programmer yang mahu tambah notification dalam mana-mana module.
- Pastikan semua sistem yang guna framework ini publish notification dengan struktur yang sama.
- Elakkan programmer insert terus ke table notification.

## Rule Utama

Programmer mesti guna service notification framework.

Do:
- guna `NotificationPublisher::default()` untuk notification umum/event biasa
- guna `NotificationWorkflowService::default()` untuk task workflow/approval
- guna `completeSourceStep()` atau `cancelSource()` bila workflow bergerak ke step lain
- guna `resolved_login_ids` untuk task approval yang sudah diketahui penerimanya
- guna `dedupe_key` untuk elak spam notification berulang

Do not:
- jangan insert/update direct table `tbl_notification`
- jangan jadikan notification sebagai access control
- jangan biarkan task lama pending selepas permohonan sudah approve/reject/bergerak ke step lain
- jangan guna role/group dynamic untuk task historical jika penerima sebenar sudah diketahui

## Bila Guna Service Yang Mana

### `NotificationPublisher`

Guna untuk:
- announcement
- reminder umum
- event notification
- notification yang tidak semestinya ada action

Example:

```php
require_once __DIR__ . '/../classes/NotificationPublisher.php';

NotificationPublisher::default()->publish([
    'event_code' => 'profile.detail.updated',
    'module_code' => 'PROFILE',
    'type' => 'event',
    'category' => 'profile',
    'severity' => 'success',
    'priority' => 'normal',
    'title_ms' => 'Maklumat Profil Dikemaskini',
    'body_ms' => 'Maklumat profil anda telah dikemaskini.',
    'source_type' => 'profile',
    'source_id' => (string)$profileId,
    'audience' => [
        'login_ids' => [$targetLoginId],
    ],
    'dedupe_key' => 'profile:' . $profileId . ':detail_updated',
], [
    'dedupe' => 'update',
]);
```

### `NotificationWorkflowService`

Guna untuk:
- permohonan perlu semakan
- approval task
- multi-step approval
- action required notification

Example:

```php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

$workflowNotification = NotificationWorkflowService::default();

$workflowNotification->publishTask([
    'event_code' => 'permohonan.pending.officer_review',
    'module_code' => 'PERMOHONAN',
    'source_type' => 'permohonan',
    'source_id' => (string)$permohonanId,
    'title_ms' => 'Permohonan Baru Menunggu Semakan',
    'body_ms' => 'Permohonan ' . $noRujukan . ' memerlukan semakan pegawai.',
    'action_url' => 'pages/permohonan-review.php?id=' . urlencode((string)$permohonanId),
    'action_label_ms' => 'Semak Permohonan',
    'due_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
    'dedupe_key' => 'permohonan:' . $permohonanId . ':officer_review',
    'audience' => [
        'resolved_login_ids' => $officerLoginIds,
    ],
], [
    'dedupe' => 'update',
]);
```

## Standard Naming

### `event_code`

Format:

```text
module.entity.status_or_action
```

Examples:

```text
permohonan.submitted.pending_officer
permohonan.reviewed.pending_hod
permohonan.approved.final
profile.detail.updated
core.password.reminder
```

### `source_type`

Format:

```text
nama entity utama
```

Examples:

```text
permohonan
profile
aduan
booking
```

### `source_id`

Value:
- ID rekod sebenar daripada module.
- Simpan sebagai string.

Example:

```php
'source_id' => (string)$permohonanId
```

### `dedupe_key`

Format:

```text
source_type:source_id:workflow_step
```

Examples:

```php
'dedupe_key' => 'permohonan:' . $permohonanId . ':officer_review'
'dedupe_key' => 'permohonan:' . $permohonanId . ':hod_approval'
'dedupe_key' => 'profile:' . $profileId . ':detail_updated'
```

Rule:
- Untuk workflow task, `dedupe` wajib guna `update`.
- Untuk final status yang tidak mahu berubah, boleh guna `skip`.
- Untuk reminder berkala, guna key ikut cycle seperti bulan/tahun.

## Audience Standard

### Workflow Task

Prefer:

```php
'audience' => [
    'resolved_login_ids' => [$approverLoginId],
]
```

Reason:
- Penerima task kekal walaupun role/group user berubah kemudian.
- Sesuai untuk audit trail dan historical task.

### Announcement Umum

Use:

```php
'audience' => ['all' => true]
```

or:

```php
'audience' => [
    'category_users' => ['STAF'],
]
```

### Role/Department Based Task

Jika programmer hanya tahu role/department dan mahu framework resolve kepada login ID semasa publish:

```php
NotificationPublisher::default()->publish([
    'event_code' => 'permohonan.pending.finance',
    'module_code' => 'PERMOHONAN',
    'type' => 'workflow',
    'category' => 'approval',
    'severity' => 'warning',
    'priority' => 'high',
    'title_ms' => 'Permohonan Menunggu Semakan Kewangan',
    'body_ms' => 'Permohonan ini perlu disemak oleh pegawai kewangan.',
    'source_type' => 'permohonan',
    'source_id' => (string)$permohonanId,
    'requires_action' => 1,
    'action_url' => 'pages/permohonan-finance.php?id=' . urlencode((string)$permohonanId),
    'dedupe_key' => 'permohonan:' . $permohonanId . ':finance_review',
    'audience' => [
        'role_ids' => ['FINANCE_ADMIN'],
        'department_ids' => [$departmentCode],
    ],
], [
    'audience_context' => [
        'resolve_to_login_ids' => true,
    ],
    'dedupe' => 'update',
]);
```

## Standard Workflow Pattern

Setiap workflow module perlu buat 4 benda.

1. Simpan state permohonan dalam table module sendiri.
2. Resolve siapa penerima next step.
3. Complete/cancel notification step lama.
4. Publish notification step baru.

Notification framework tidak menentukan flow business. Module yang tentukan.

## Pattern A: Submit Permohonan

```php
function notifyPermohonanSubmitted(
    int $permohonanId,
    string $noRujukan,
    array $officerLoginIds
): void {
    require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

    NotificationWorkflowService::default()->publishTask([
        'event_code' => 'permohonan.submitted.pending_officer',
        'module_code' => 'PERMOHONAN',
        'source_type' => 'permohonan',
        'source_id' => (string)$permohonanId,
        'title_ms' => 'Permohonan Baru Menunggu Semakan',
        'body_ms' => 'Permohonan ' . $noRujukan . ' memerlukan semakan pegawai.',
        'action_url' => 'pages/permohonan-review.php?id=' . urlencode((string)$permohonanId),
        'action_label_ms' => 'Semak Permohonan',
        'due_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
        'dedupe_key' => 'permohonan:' . $permohonanId . ':officer_review',
        'audience' => [
            'resolved_login_ids' => $officerLoginIds,
        ],
    ], [
        'dedupe' => 'update',
    ]);
}
```

## Pattern B: Move To Next Approval Step

```php
function notifyPermohonanMoveToHod(
    int $permohonanId,
    string $noRujukan,
    array $hodLoginIds
): void {
    require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

    $notification = NotificationWorkflowService::default();

    $notification->completeSourceStep(
        'permohonan',
        (string)$permohonanId,
        'permohonan.submitted.pending_officer'
    );

    $notification->publishTask([
        'event_code' => 'permohonan.reviewed.pending_hod',
        'module_code' => 'PERMOHONAN',
        'source_type' => 'permohonan',
        'source_id' => (string)$permohonanId,
        'title_ms' => 'Permohonan Menunggu Pengesahan Ketua Jabatan',
        'body_ms' => 'Permohonan ' . $noRujukan . ' memerlukan pengesahan Ketua Jabatan.',
        'action_url' => 'pages/permohonan-hod.php?id=' . urlencode((string)$permohonanId),
        'action_label_ms' => 'Sahkan Permohonan',
        'due_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
        'dedupe_key' => 'permohonan:' . $permohonanId . ':hod_approval',
        'audience' => [
            'resolved_login_ids' => $hodLoginIds,
        ],
    ], [
        'dedupe' => 'update',
    ]);
}
```

## Pattern C: Final Approved

```php
function notifyPermohonanApproved(
    int $permohonanId,
    string $noRujukan,
    string $pemohonLoginId
): void {
    require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

    $notification = NotificationWorkflowService::default();

    $notification->completeSourceStep(
        'permohonan',
        (string)$permohonanId,
        'permohonan.reviewed.pending_hod'
    );

    $notification->publishInfo([
        'event_code' => 'permohonan.approved.final',
        'module_code' => 'PERMOHONAN',
        'source_type' => 'permohonan',
        'source_id' => (string)$permohonanId,
        'title_ms' => 'Permohonan Diluluskan',
        'body_ms' => 'Permohonan ' . $noRujukan . ' telah diluluskan.',
        'action_url' => 'pages/permohonan-view.php?id=' . urlencode((string)$permohonanId),
        'action_label_ms' => 'Lihat Permohonan',
        'dedupe_key' => 'permohonan:' . $permohonanId . ':approved',
        'audience' => [
            'resolved_login_ids' => [$pemohonLoginId],
        ],
    ], [
        'dedupe' => 'skip',
    ]);
}
```

## Pattern D: Rejected Or Cancelled

```php
function notifyPermohonanRejected(
    int $permohonanId,
    string $noRujukan,
    string $pemohonLoginId
): void {
    require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

    $notification = NotificationWorkflowService::default();

    $notification->cancelSource('permohonan', (string)$permohonanId);

    $notification->publishInfo([
        'event_code' => 'permohonan.rejected.final',
        'module_code' => 'PERMOHONAN',
        'source_type' => 'permohonan',
        'source_id' => (string)$permohonanId,
        'severity' => 'danger',
        'priority' => 'normal',
        'title_ms' => 'Permohonan Tidak Diluluskan',
        'body_ms' => 'Permohonan ' . $noRujukan . ' tidak diluluskan.',
        'action_url' => 'pages/permohonan-view.php?id=' . urlencode((string)$permohonanId),
        'action_label_ms' => 'Lihat Permohonan',
        'dedupe_key' => 'permohonan:' . $permohonanId . ':rejected',
        'audience' => [
            'resolved_login_ids' => [$pemohonLoginId],
        ],
    ], [
        'dedupe' => 'skip',
    ]);
}
```

## Pattern E: Parallel Approval

Jika satu permohonan perlu tindakan beberapa approver serentak:

```php
NotificationWorkflowService::default()->publishTask([
    'event_code' => 'permohonan.pending.parallel_review',
    'module_code' => 'PERMOHONAN',
    'source_type' => 'permohonan',
    'source_id' => (string)$permohonanId,
    'title_ms' => 'Permohonan Memerlukan Semakan Bersama',
    'body_ms' => 'Permohonan ' . $noRujukan . ' memerlukan semakan beberapa pegawai.',
    'action_url' => 'pages/permohonan-review.php?id=' . urlencode((string)$permohonanId),
    'action_label_ms' => 'Semak',
    'dedupe_key' => 'permohonan:' . $permohonanId . ':parallel_review',
    'audience' => [
        'resolved_login_ids' => $approverLoginIds,
    ],
], [
    'dedupe' => 'update',
]);
```

Important:
- Module business logic mesti decide sama ada cukup satu approver approve, majoriti, atau semua approver.
- Bila condition selesai, call:

```php
NotificationWorkflowService::default()->completeSourceStep(
    'permohonan',
    (string)$permohonanId,
    'permohonan.pending.parallel_review'
);
```

## Recommended Module Wrapper

Untuk elak setiap programmer tulis payload panjang dalam controller, setiap module patut sediakan wrapper sendiri.

Example file:

```text
public/classes/PermohonanNotification.php
```

Example structure:

```php
final class PermohonanNotification
{
    public static function submitted(int $id, string $refNo, array $officerLoginIds): void
    {
        notifyPermohonanSubmitted($id, $refNo, $officerLoginIds);
    }

    public static function moveToHod(int $id, string $refNo, array $hodLoginIds): void
    {
        notifyPermohonanMoveToHod($id, $refNo, $hodLoginIds);
    }

    public static function approved(int $id, string $refNo, string $pemohonLoginId): void
    {
        notifyPermohonanApproved($id, $refNo, $pemohonLoginId);
    }

    public static function rejected(int $id, string $refNo, string $pemohonLoginId): void
    {
        notifyPermohonanRejected($id, $refNo, $pemohonLoginId);
    }
}
```

Controller/module page hanya panggil:

```php
PermohonanNotification::submitted($permohonanId, $noRujukan, $officerLoginIds);
PermohonanNotification::moveToHod($permohonanId, $noRujukan, $hodLoginIds);
PermohonanNotification::approved($permohonanId, $noRujukan, $pemohonLoginId);
```

## Controller Integration Checklist

Setiap page/process yang ada workflow notification mesti jawab checklist ini:

- Apa `source_type` entity ini?
- Apa `source_id` rekod ini?
- Apa `event_code` current step?
- Apa `dedupe_key` current step?
- Siapa penerima sebenar notification?
- Adakah notification ini action required?
- Apa `action_url` yang user akan klik?
- Bila due date jika ada SLA?
- Notification step lama mana perlu complete/cancel?
- Apa final notification kepada pemohon/requester?

## Minimum Review Standard

Sebelum merge page baru, reviewer perlu semak:

- Tiada direct SQL insert/update ke table notification.
- Semua workflow task guna `NotificationWorkflowService`.
- Semua repeatable event ada `dedupe_key`.
- Semua action notification ada `source_type` dan `source_id`.
- Task lama ditutup bila flow berubah.
- `action_url` page tetap buat permission check sendiri.
- Audience workflow task guna `resolved_login_ids` jika penerima sudah diketahui.

## Relationship With `notification-admin.php`

`notification-admin.php` digunakan untuk:
- admin manual announcement
- reminder umum
- notification one-off
- template management/admin testing

Ia bukan standard integration untuk module permohonan.

Module permohonan dan custom system perlu integrate melalui service API seperti contoh di atas.
