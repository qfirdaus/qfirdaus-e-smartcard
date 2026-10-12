# Notification Developer Examples

Working directory: `/var/www/app\iqs-framework`

These examples cover Phase 2 usage of the universal notification publisher.

## 1. General Admin/System Announcement

Use this for general reminders such as password update reminders.

```php
require_once __DIR__ . '/../classes/NotificationPublisher.php';

$notificationId = NotificationPublisher::default()->publish([
    'event_code' => 'core.password.reminder',
    'module_code' => 'CORE',
    'type' => 'announcement',
    'category' => 'system',
    'severity' => 'info',
    'priority' => 'normal',
    'title_ms' => 'Kemaskini Kata Laluan',
    'title_en' => 'Update Password',
    'body_ms' => 'Sila kemaskini kata laluan anda untuk keselamatan akaun.',
    'body_en' => 'Please update your password for account security.',
    'action_url' => 'change-password.php',
    'action_label_ms' => 'Kemaskini',
    'action_label_en' => 'Update',
    'icon' => 'ri-lock-password-line',
    'audience' => ['all' => true],
    'is_broadcast' => 1,
    'dedupe_key' => 'core.password.reminder.2026-05',
], [
    'dedupe' => 'update',
]);
```

## 2. Direct User Event Notification

Use this when a module needs to notify one or more specific users.

```php
$notificationId = NotificationPublisher::default()->publish([
    'event_code' => 'profile.personal_detail.changed',
    'module_code' => 'PROFILE',
    'type' => 'event',
    'category' => 'profile',
    'severity' => 'success',
    'title_ms' => 'Maklumat Profil Dikemaskini',
    'title_en' => 'Profile Updated',
    'body_ms' => 'Maklumat profil anda telah dikemaskini.',
    'body_en' => 'Your profile information has been updated.',
    'source_type' => 'profile',
    'source_id' => (string)$profileId,
    'audience' => [
        'login_ids' => [$targetLoginId],
    ],
    'dedupe_key' => 'profile.personal_detail.changed.' . $profileId,
]);
```

## 3. Workflow Task Notification

Use `resolved_login_ids` or `resolve_to_login_ids` for approval flows so the recipients are stable even if their group changes later.

```php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

$workflow = NotificationWorkflowService::default();

$notificationId = $workflow->publishTask([
    'event_code' => 'application.pending.department_head',
    'module_code' => 'APPLICATION',
    'category' => 'approval',
    'title_ms' => 'Permohonan Perlu Pengesahan',
    'title_en' => 'Application Requires Approval',
    'body_ms' => 'Permohonan baharu memerlukan pengesahan Ketua Jabatan.',
    'body_en' => 'A new application requires department head approval.',
    'action_url' => 'pages/permohonan-review.php?id=' . urlencode((string)$applicationId),
    'action_label_ms' => 'Semak',
    'action_label_en' => 'Review',
    'source_type' => 'application',
    'source_id' => (string)$applicationId,
    'due_at' => '+3 days',
    'audience' => [
        'resolved_login_ids' => $departmentHeadLoginIds,
    ],
    'dedupe_key' => 'application.pending.department_head.' . $applicationId,
], [
    'dedupe' => 'update',
]);
```

## 4. Resolve Group/Department to Stable Recipients

Use this when the audience comes from a group, role, or department but the task should remain assigned to the resolved users.

```php
$notificationId = NotificationPublisher::default()->publish([
    'event_code' => 'application.pending.finance',
    'module_code' => 'APPLICATION',
    'type' => 'workflow',
    'category' => 'approval',
    'severity' => 'warning',
    'priority' => 'high',
    'title_ms' => 'Permohonan Perlu Semakan Kewangan',
    'body_ms' => 'Permohonan ini perlu disemak oleh pegawai kewangan.',
    'source_type' => 'application',
    'source_id' => (string)$applicationId,
    'requires_action' => 1,
    'audience' => [
        'role_ids' => ['FINANCE_ADMIN'],
        'department_ids' => [$departmentCode],
    ],
    'dedupe_key' => 'application.pending.finance.' . $applicationId,
], [
    'audience_context' => [
        'resolve_to_login_ids' => true,
    ],
    'dedupe' => 'update',
]);
```

## 5. Complete or Cancel Stale Workflow Notifications

Call these from the module after the workflow moves to another step or is approved/rejected.

```php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';
require_once __DIR__ . '/../classes/NotificationService.php';

$workflow = NotificationWorkflowService::default();
$service = new NotificationService(Database::getInstance('mysql')->getConnection());

// When current actor completes their task:
$actor = $service->resolveCurrentActor();
$service->markActionCompleted($notificationId, $actor);

// When a source record has moved on and all related pending task notifications should close:
$workflow->completeSourceStep('application', (string)$applicationId, 'application.pending.department_head');

// If the application is rejected/cancelled:
$workflow->cancelSource('application', (string)$applicationId);
```

## 6. Expire Overdue Workflow Tasks

Use this from a controlled backend task or admin-maintained runner. Full scheduling belongs to Phase 4.

```php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

$expiredCount = NotificationWorkflowService::default()->expireOverdueTasks(500);
```

## Dedupe Behavior

Supported publisher options:

- `skip`: if `dedupe_key` already exists, return the existing notification ID.
- `update`: update the existing notification content and audience.
- `republish`: archive the existing notification and insert a new one.

Use a stable `dedupe_key` for repeatable workflow events, for example:

```php
'dedupe_key' => 'application.pending.department_head.' . $applicationId
```
