<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

return [
    'submit' => [
        'tab' => 'Submit Request',
        'title' => 'Submit Request',
        'description' => 'Guna selepas rekod permohonan berjaya disimpan dan perlu dihantar kepada pegawai pertama.',
        'code_id' => 'codeSampleSubmit',
        'code' => <<<'PHP'
<?php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

function notifyPermohonanSubmitted(
    int $permohonanId,
    string $noRujukan,
    array $officerLoginIds
): void {
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
PHP,
    ],
    'next-step' => [
        'tab' => 'Move To Next Approval',
        'title' => 'Move To Next Approval',
        'description' => 'Tutup task lama dahulu, kemudian publish task baru kepada role/pegawai seterusnya.',
        'code_id' => 'codeSampleNextStep',
        'code' => <<<'PHP'
<?php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

function notifyPermohonanMoveToHod(
    int $permohonanId,
    string $noRujukan,
    array $hodLoginIds
): void {
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
PHP,
    ],
    'approved' => [
        'tab' => 'Final Approved',
        'title' => 'Final Approved',
        'description' => 'Tutup task approval terakhir dan hantar info notification kepada pemohon.',
        'code_id' => 'codeSampleApproved',
        'code' => <<<'PHP'
<?php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

function notifyPermohonanApproved(
    int $permohonanId,
    string $noRujukan,
    string $pemohonLoginId
): void {
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
PHP,
    ],
    'rejected' => [
        'tab' => 'Rejected / Cancelled',
        'title' => 'Rejected / Cancelled',
        'description' => 'Cancel semua task pending untuk source yang sama dan maklumkan keputusan kepada pemohon.',
        'code_id' => 'codeSampleRejected',
        'code' => <<<'PHP'
<?php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

function notifyPermohonanRejected(
    int $permohonanId,
    string $noRujukan,
    string $pemohonLoginId
): void {
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
PHP,
    ],
    'parallel' => [
        'tab' => 'Parallel Approval',
        'title' => 'Parallel Approval',
        'description' => 'Guna bila beberapa approver perlu terima task serentak. Business rule tetap ditentukan module.',
        'code_id' => 'codeSampleParallel',
        'code' => <<<'PHP'
<?php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

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

// Bila business condition selesai, tutup task ini.
NotificationWorkflowService::default()->completeSourceStep(
    'permohonan',
    (string)$permohonanId,
    'permohonan.pending.parallel_review'
);
PHP,
    ],
    'wrapper' => [
        'tab' => 'Module Wrapper',
        'title' => 'Module Wrapper',
        'description' => 'Recommended: controller panggil wrapper module, bukan bina payload notification panjang di banyak tempat.',
        'code_id' => 'codeSampleWrapper',
        'code' => <<<'PHP'
<?php
require_once __DIR__ . '/NotificationWorkflowService.php';

final class PermohonanNotification
{
    public static function submitted(int $id, string $refNo, array $officerLoginIds): void
    {
        NotificationWorkflowService::default()->publishTask([
            'event_code' => 'permohonan.submitted.pending_officer',
            'module_code' => 'PERMOHONAN',
            'source_type' => 'permohonan',
            'source_id' => (string)$id,
            'title_ms' => 'Permohonan Baru Menunggu Semakan',
            'body_ms' => 'Permohonan ' . $refNo . ' memerlukan semakan pegawai.',
            'action_url' => 'pages/permohonan-review.php?id=' . urlencode((string)$id),
            'action_label_ms' => 'Semak Permohonan',
            'dedupe_key' => 'permohonan:' . $id . ':officer_review',
            'audience' => ['resolved_login_ids' => $officerLoginIds],
        ], ['dedupe' => 'update']);
    }

    public static function approved(int $id, string $refNo, string $pemohonLoginId): void
    {
        $notification = NotificationWorkflowService::default();
        $notification->cancelSource('permohonan', (string)$id);
        $notification->publishInfo([
            'event_code' => 'permohonan.approved.final',
            'module_code' => 'PERMOHONAN',
            'source_type' => 'permohonan',
            'source_id' => (string)$id,
            'title_ms' => 'Permohonan Diluluskan',
            'body_ms' => 'Permohonan ' . $refNo . ' telah diluluskan.',
            'action_url' => 'pages/permohonan-view.php?id=' . urlencode((string)$id),
            'dedupe_key' => 'permohonan:' . $id . ':approved',
            'audience' => ['resolved_login_ids' => [$pemohonLoginId]],
        ], ['dedupe' => 'skip']);
    }
}
PHP,
    ],
];
