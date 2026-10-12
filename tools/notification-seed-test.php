<?php
declare(strict_types=1);

/**
 * Seed notification test records for Phase 1-3 verification.
 *
 * Usage:
 *   php tools/notification-seed-test.php
 *   php tools/notification-seed-test.php --login=USER_LOGIN_ID
 *   php tools/notification-seed-test.php --login=USER_LOGIN_ID --resolved=APPROVER_LOGIN_ID
 *   php tools/notification-seed-test.php --login=USER_LOGIN_ID --resolved=APPROVER_LOGIN_ID --overdue
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This tool is CLI-only.\n");
    exit(1);
}

require_once __DIR__ . '/../public/classes/Database.php';
require_once __DIR__ . '/../public/classes/NotificationPublisher.php';

function optionValue(array $argv, string $name): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) {
            $value = trim(substr($arg, strlen($prefix)));
            return $value !== '' ? $value : null;
        }
    }
    return null;
}

function hasFlag(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true);
}

function findLoginId(PDO $pdo, ?string $preferred = null): string
{
    if ($preferred !== null && $preferred !== '') {
        $stmt = $pdo->prepare("
            SELECT f_loginID
            FROM tbl_m_user
            WHERE f_loginID = :login_id
            LIMIT 1
        ");
        $stmt->execute([':login_id' => $preferred]);
        $found = trim((string)$stmt->fetchColumn());
        if ($found !== '') {
            return $found;
        }

        throw new RuntimeException("Login ID not found in tbl_m_user: {$preferred}");
    }

    $stmt = $pdo->query("
        SELECT f_loginID
        FROM tbl_m_user
        WHERE TRIM(COALESCE(f_loginID, '')) <> ''
          AND COALESCE(f_flag, 1) = 1
        ORDER BY f_userID ASC
        LIMIT 1
    ");
    $found = trim((string)$stmt->fetchColumn());
    if ($found === '') {
        throw new RuntimeException('No active f_loginID found in tbl_m_user.');
    }

    return $found;
}

try {
    $pdo = Database::getInstance('mysql')->getConnection();
    $publisher = new NotificationPublisher($pdo, new NotificationAudienceResolver($pdo));

    $loginId = findLoginId($pdo, optionValue($argv, 'login'));
    $resolvedLoginId = findLoginId($pdo, optionValue($argv, 'resolved') ?? $loginId);
    $suffix = date('Ymd');
    $dueAt = hasFlag($argv, 'overdue') ? '-1 hour' : '+1 day';

    $ids = [];

    $ids['all'] = $publisher->publish([
        'event_code' => 'test.notification.all',
        'module_code' => 'NOTIFICATION_TEST',
        'type' => 'announcement',
        'category' => 'system',
        'severity' => 'info',
        'priority' => 'normal',
        'title_ms' => 'Test Notification ALL',
        'title_en' => 'Test Notification ALL',
        'body_ms' => 'Ini notification test untuk semua pengguna.',
        'body_en' => 'This is a test notification for all users.',
        'icon' => 'ri-megaphone-line',
        'audience' => ['all' => true],
        'is_broadcast' => 1,
        'dedupe_key' => 'test.notification.all.' . $suffix,
    ], ['dedupe' => 'update']);

    $ids['login_id'] = $publisher->publish([
        'event_code' => 'test.notification.login_id',
        'module_code' => 'NOTIFICATION_TEST',
        'type' => 'event',
        'category' => 'system',
        'severity' => 'success',
        'priority' => 'normal',
        'title_ms' => 'Test Notification LOGIN_ID',
        'title_en' => 'Test Notification LOGIN_ID',
        'body_ms' => 'Ini notification test khusus untuk login ID ' . $loginId . '.',
        'body_en' => 'This is a test notification for login ID ' . $loginId . '.',
        'icon' => 'ri-user-received-line',
        'audience' => ['login_ids' => [$loginId]],
        'dedupe_key' => 'test.notification.login_id.' . $loginId . '.' . $suffix,
    ], ['dedupe' => 'update']);

    $ids['workflow_resolved_login_id'] = $publisher->publish([
        'event_code' => 'test.workflow.pending.approval',
        'module_code' => 'NOTIFICATION_TEST',
        'type' => 'workflow',
        'category' => 'approval',
        'severity' => 'warning',
        'priority' => 'high',
        'title_ms' => 'Test Workflow Task',
        'title_en' => 'Test Workflow Task',
        'body_ms' => 'Ini workflow task test untuk resolved login ID ' . $resolvedLoginId . '.',
        'body_en' => 'This is a workflow task test for resolved login ID ' . $resolvedLoginId . '.',
        'action_url' => 'pages/notifications.php',
        'action_label_ms' => 'Semak',
        'action_label_en' => 'Review',
        'icon' => 'ri-git-pull-request-line',
        'source_type' => 'notification_seed_test',
        'source_id' => 'phase_3_' . $suffix,
        'requires_action' => 1,
        'due_at' => $dueAt,
        'audience' => ['resolved_login_ids' => [$resolvedLoginId]],
        'dedupe_key' => 'test.workflow.pending.approval.' . $resolvedLoginId . '.' . $suffix,
    ], ['dedupe' => 'update']);

    echo "Notification seed completed.\n";
    echo "Login target: {$loginId}\n";
    echo "Workflow target: {$resolvedLoginId}\n";
    echo "Due mode: " . (hasFlag($argv, 'overdue') ? 'overdue' : 'future') . "\n";
    foreach ($ids as $key => $id) {
        echo "- {$key}: {$id}\n";
    }
    echo "\nOpen the app as the target user and check topbar + pages/notifications.php.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Notification seed failed: " . $e->getMessage() . "\n");
    exit(1);
}
