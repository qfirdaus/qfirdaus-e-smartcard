<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AiChatbotProjectContextProviderInterface.php';

final class AiChatbotEPmsContextProvider implements AiChatbotProjectContextProviderInterface
{
    private const MAX_ROWS = 8;
    private const INTENT_MY_PROJECTS = 'epms_my_projects';
    private const INTENT_MY_ACTIVITIES = 'epms_my_activities';
    private const INTENT_PROJECT_PROGRESS = 'epms_project_progress';
    private const INTENT_LATEST_ANNOUNCEMENTS = 'epms_latest_announcements';
    private const INTENT_FEEDBACK_SUMMARY = 'epms_feedback_summary';
    private const INTENT_UNSUPPORTED = 'unsupported';

    public function code(): string
    {
        return 'epms';
    }

    public function label(): string
    {
        return 'Sistem Pemantauan Projek (e-PMS)';
    }

    /**
     * @return array<int,string>
     */
    public function aliases(): array
    {
        return [
            'Sistem Pemantauan Projek (e-PMS)',
            'Sistem Pemantauan Projek',
            'e-PMS',
            'ePMS',
            'PMS',
            'Pemantauan Projek',
            'Project Monitoring System',
        ];
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function build(string $message, array $profile, array $actor): array
    {
        $intent = $this->detectIntent($message);
        if ($intent === self::INTENT_UNSUPPORTED) {
            return [
                'provider' => $this->code(),
                'provider_label' => $this->label(),
                'status' => 'unsupported_intent',
                'intent' => $intent,
                'items' => [],
                'safety' => $this->safetyPolicy(),
            ];
        }

        $isPrivilegedProjectAdmin = !empty($actor['is_privileged_project_admin']);
        $scope = $this->scopeForIntent($intent, $isPrivilegedProjectAdmin);
        $staffId = $this->staffId($profile, $actor);
        if ($intent !== self::INTENT_LATEST_ANNOUNCEMENTS && !$isPrivilegedProjectAdmin && $staffId === '') {
            return [
                'provider' => $this->code(),
                'provider_label' => $this->label(),
                'status' => 'denied_missing_staff_scope',
                'intent' => $intent,
                'scope' => $scope,
                'items' => [],
                'safety' => $this->safetyPolicy(),
            ];
        }

        try {
            if (!$this->hasRequiredTables($this->requiredTablesForIntent($intent))) {
                return [
                    'provider' => $this->code(),
                    'provider_label' => $this->label(),
                    'status' => 'data_unavailable',
                    'intent' => $intent,
                    'scope' => $scope,
                    'items' => [],
                    'safety' => $this->safetyPolicy(),
                    'note' => 'Required e-PMS monitoring tables are not available in the current database.',
                ];
            }

            $items = match ($intent) {
                self::INTENT_MY_PROJECTS => $this->myProjects($staffId, $isPrivilegedProjectAdmin),
                self::INTENT_MY_ACTIVITIES => $this->myActivities($staffId, $isPrivilegedProjectAdmin),
                self::INTENT_PROJECT_PROGRESS => $this->projectProgress($staffId, $isPrivilegedProjectAdmin),
                self::INTENT_LATEST_ANNOUNCEMENTS => $this->latestAnnouncements(),
                self::INTENT_FEEDBACK_SUMMARY => $this->feedbackSummary($staffId, $isPrivilegedProjectAdmin),
                default => [],
            };
        } catch (Throwable $e) {
            error_log('[ai-chatbot-epms-provider] ' . $e->getMessage());
            return [
                'provider' => $this->code(),
                'provider_label' => $this->label(),
                'status' => 'data_unavailable',
                'intent' => $intent,
                'scope' => $scope,
                'items' => [],
                'safety' => $this->safetyPolicy(),
                'note' => 'e-PMS data source is unavailable or the required monitoring tables are not present.',
            ];
        }

        return [
            'provider' => $this->code(),
            'provider_label' => $this->label(),
            'status' => 'ready',
            'intent' => $intent,
            'scope' => $scope,
            'requires_staff_scope' => $intent !== self::INTENT_LATEST_ANNOUNCEMENTS && !$isPrivilegedProjectAdmin,
            'staff_id_available' => $staffId !== '' || $isPrivilegedProjectAdmin,
            'row_count' => count($items),
            'items' => $items,
            'records' => $items,
            'safety' => $this->safetyPolicy(),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function myProjects(string $staffId, bool $isSuperAdmin = false): array
    {
        if ($isSuperAdmin) {
            $sql = "
                SELECT
                    p.f_projectID AS project_id,
                    p.f_projectName AS project_name,
                    t.f_kodTeras AS teras_code,
                    t.f_namaTeras AS teras_name,
                    p.f_startDate AS start_date,
                    p.f_endDate AS end_date,
                    p.f_status AS status,
                    CASE WHEN TRIM(COALESCE(p.f_ownerStafID, '')) = ? THEN 1 ELSE 0 END AS is_primary_owner,
                    CASE WHEN TRIM(COALESCE(p.f_picStafID, '')) = ? THEN 1 ELSE 0 END AS is_primary_pic,
                    CASE WHEN po.f_stafID IS NULL THEN 0 ELSE 1 END AS is_owner,
                    CASE WHEN pp.f_stafID IS NULL THEN 0 ELSE 1 END AS is_pic
                FROM tbl_monitoring_project p
                LEFT JOIN tbl_monitoring_teras t ON t.f_terasID = p.f_terasID
                LEFT JOIN tbl_monitoring_project_owner po
                    ON po.f_projectID = p.f_projectID AND TRIM(COALESCE(po.f_stafID, '')) = ?
                LEFT JOIN tbl_monitoring_project_pic pp
                    ON pp.f_projectID = p.f_projectID AND TRIM(COALESCE(pp.f_stafID, '')) = ?
                WHERE {$this->activeStatusSql('p')}
                ORDER BY COALESCE(p.f_endDate, p.f_startDate) ASC, p.f_projectName ASC
                LIMIT " . self::MAX_ROWS;

            return array_map(fn(array $row): array => [
                'type' => 'project',
                'project_id' => (int)($row['project_id'] ?? 0),
                'project_name' => $this->cleanText($row['project_name'] ?? '', 180),
                'teras_code' => $this->cleanText($row['teras_code'] ?? '', 40),
                'teras_name' => $this->cleanText($row['teras_name'] ?? '', 160),
                'role' => $this->projectRole($row),
                'start_date' => $this->dateOrNull($row['start_date'] ?? null),
                'end_date' => $this->dateOrNull($row['end_date'] ?? null),
                'status' => $this->cleanText($row['status'] ?? '', 60),
            ], $this->fetchAll($sql, [$staffId, $staffId, $staffId, $staffId]));
        }

        $sql = "
            SELECT
                p.f_projectID AS project_id,
                p.f_projectName AS project_name,
                t.f_kodTeras AS teras_code,
                t.f_namaTeras AS teras_name,
                p.f_startDate AS start_date,
                p.f_endDate AS end_date,
                p.f_status AS status,
                CASE WHEN TRIM(COALESCE(p.f_ownerStafID, '')) = ? THEN 1 ELSE 0 END AS is_primary_owner,
                CASE WHEN TRIM(COALESCE(p.f_picStafID, '')) = ? THEN 1 ELSE 0 END AS is_primary_pic,
                CASE WHEN po.f_stafID IS NULL THEN 0 ELSE 1 END AS is_owner,
                CASE WHEN pp.f_stafID IS NULL THEN 0 ELSE 1 END AS is_pic
            FROM tbl_monitoring_project p
            LEFT JOIN tbl_monitoring_teras t ON t.f_terasID = p.f_terasID
            LEFT JOIN tbl_monitoring_project_owner po
                ON po.f_projectID = p.f_projectID AND TRIM(COALESCE(po.f_stafID, '')) = ?
            LEFT JOIN tbl_monitoring_project_pic pp
                ON pp.f_projectID = p.f_projectID AND TRIM(COALESCE(pp.f_stafID, '')) = ?
            WHERE {$this->activeStatusSql('p')}
              AND (
                TRIM(COALESCE(p.f_ownerStafID, '')) = ?
                OR TRIM(COALESCE(p.f_picStafID, '')) = ?
                OR po.f_stafID IS NOT NULL
                OR pp.f_stafID IS NOT NULL
              )
            ORDER BY COALESCE(p.f_endDate, p.f_startDate) ASC, p.f_projectName ASC
            LIMIT " . self::MAX_ROWS;

        return array_map(fn(array $row): array => [
            'type' => 'project',
            'project_id' => (int)($row['project_id'] ?? 0),
            'project_name' => $this->cleanText($row['project_name'] ?? '', 180),
            'teras_code' => $this->cleanText($row['teras_code'] ?? '', 40),
            'teras_name' => $this->cleanText($row['teras_name'] ?? '', 160),
            'role' => $this->projectRole($row),
            'start_date' => $this->dateOrNull($row['start_date'] ?? null),
            'end_date' => $this->dateOrNull($row['end_date'] ?? null),
            'status' => $this->cleanText($row['status'] ?? '', 60),
        ], $this->fetchAll($sql, [$staffId, $staffId, $staffId, $staffId, $staffId, $staffId]));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function myActivities(string $staffId, bool $isSuperAdmin = false): array
    {
        if ($isSuperAdmin) {
            $sql = "
                SELECT
                    a.f_aktivitiID AS activity_id,
                    a.f_namaAktiviti AS activity_name,
                    a.f_targetDate AS target_date,
                    a.f_weightage AS weightage,
                    a.f_status AS activity_status,
                    p.f_projectID AS project_id,
                    p.f_projectName AS project_name
                FROM tbl_monitoring_aktiviti a
                INNER JOIN tbl_monitoring_project p ON p.f_projectID = a.f_projectID
                LEFT JOIN tbl_monitoring_project_owner po
                    ON po.f_projectID = p.f_projectID AND TRIM(COALESCE(po.f_stafID, '')) = ?
                LEFT JOIN tbl_monitoring_project_pic pp
                    ON pp.f_projectID = p.f_projectID AND TRIM(COALESCE(pp.f_stafID, '')) = ?
                WHERE {$this->activeStatusSql('a')}
                  AND {$this->activeStatusSql('p')}
                ORDER BY COALESCE(a.f_targetDate, a.f_endDate, a.f_startDate) ASC, a.f_namaAktiviti ASC
                LIMIT " . self::MAX_ROWS;

            return array_map(fn(array $row): array => [
                'type' => 'activity',
                'activity_id' => (int)($row['activity_id'] ?? 0),
                'activity_name' => $this->cleanText($row['activity_name'] ?? '', 180),
                'project_id' => (int)($row['project_id'] ?? 0),
                'project_name' => $this->cleanText($row['project_name'] ?? '', 180),
                'target_date' => $this->dateOrNull($row['target_date'] ?? null),
                'weightage' => $this->numberOrNull($row['weightage'] ?? null),
                'status' => $this->cleanText($row['activity_status'] ?? '', 60),
            ], $this->fetchAll($sql, [$staffId, $staffId]));
        }

        $sql = "
            SELECT
                a.f_aktivitiID AS activity_id,
                a.f_namaAktiviti AS activity_name,
                a.f_targetDate AS target_date,
                a.f_weightage AS weightage,
                a.f_status AS activity_status,
                p.f_projectID AS project_id,
                p.f_projectName AS project_name
            FROM tbl_monitoring_aktiviti a
            INNER JOIN tbl_monitoring_project p ON p.f_projectID = a.f_projectID
            LEFT JOIN tbl_monitoring_project_owner po
                ON po.f_projectID = p.f_projectID AND TRIM(COALESCE(po.f_stafID, '')) = ?
            LEFT JOIN tbl_monitoring_project_pic pp
                ON pp.f_projectID = p.f_projectID AND TRIM(COALESCE(pp.f_stafID, '')) = ?
            WHERE {$this->activeStatusSql('a')}
              AND {$this->activeStatusSql('p')}
              AND (
                TRIM(COALESCE(a.f_ownerStafID, '')) = ?
                OR TRIM(COALESCE(p.f_ownerStafID, '')) = ?
                OR TRIM(COALESCE(p.f_picStafID, '')) = ?
                OR po.f_stafID IS NOT NULL
                OR pp.f_stafID IS NOT NULL
              )
            ORDER BY COALESCE(a.f_targetDate, a.f_endDate, a.f_startDate) ASC, a.f_namaAktiviti ASC
            LIMIT " . self::MAX_ROWS;

        return array_map(fn(array $row): array => [
            'type' => 'activity',
            'activity_id' => (int)($row['activity_id'] ?? 0),
            'activity_name' => $this->cleanText($row['activity_name'] ?? '', 180),
            'project_id' => (int)($row['project_id'] ?? 0),
            'project_name' => $this->cleanText($row['project_name'] ?? '', 180),
            'target_date' => $this->dateOrNull($row['target_date'] ?? null),
            'weightage' => $this->numberOrNull($row['weightage'] ?? null),
            'status' => $this->cleanText($row['activity_status'] ?? '', 60),
        ], $this->fetchAll($sql, [$staffId, $staffId, $staffId, $staffId, $staffId]));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function projectProgress(string $staffId, bool $isSuperAdmin = false): array
    {
        if ($isSuperAdmin) {
            $sql = "
                SELECT
                    p.f_projectID AS project_id,
                    p.f_projectName AS project_name,
                    a.f_aktivitiID AS activity_id,
                    a.f_namaAktiviti AS activity_name,
                    l.f_bulan AS report_month,
                    l.f_tahun AS report_year,
                    l.f_percentComplete AS percent_complete,
                    l.f_statusKemajuan AS progress_status,
                    l.f_submitteddt AS submitted_at
                FROM tbl_monitoring_laporan l
                INNER JOIN tbl_monitoring_aktiviti a ON a.f_aktivitiID = l.f_aktivitiID
                INNER JOIN tbl_monitoring_project p ON p.f_projectID = a.f_projectID
                LEFT JOIN tbl_monitoring_project_owner po
                    ON po.f_projectID = p.f_projectID AND TRIM(COALESCE(po.f_stafID, '')) = ?
                LEFT JOIN tbl_monitoring_project_pic pp
                    ON pp.f_projectID = p.f_projectID AND TRIM(COALESCE(pp.f_stafID, '')) = ?
                WHERE {$this->activeStatusSql('a')}
                  AND {$this->activeStatusSql('p')}
                ORDER BY CAST(l.f_tahun AS UNSIGNED) DESC, CAST(l.f_bulan AS UNSIGNED) DESC, l.f_submitteddt DESC
                LIMIT " . self::MAX_ROWS;

            return array_map(fn(array $row): array => [
                'type' => 'progress',
                'project_id' => (int)($row['project_id'] ?? 0),
                'project_name' => $this->cleanText($row['project_name'] ?? '', 180),
                'activity_id' => (int)($row['activity_id'] ?? 0),
                'activity_name' => $this->cleanText($row['activity_name'] ?? '', 180),
                'period' => $this->period((int)($row['report_month'] ?? 0), (int)($row['report_year'] ?? 0)),
                'percent_complete' => $this->numberOrNull($row['percent_complete'] ?? null),
                'progress_status' => $this->cleanText($row['progress_status'] ?? '', 40),
                'submitted_at' => $this->dateOrNull($row['submitted_at'] ?? null),
            ], $this->fetchAll($sql, [$staffId, $staffId]));
        }

        $sql = "
            SELECT
                p.f_projectID AS project_id,
                p.f_projectName AS project_name,
                a.f_aktivitiID AS activity_id,
                a.f_namaAktiviti AS activity_name,
                l.f_bulan AS report_month,
                l.f_tahun AS report_year,
                l.f_percentComplete AS percent_complete,
                l.f_statusKemajuan AS progress_status,
                l.f_submitteddt AS submitted_at
            FROM tbl_monitoring_laporan l
            INNER JOIN tbl_monitoring_aktiviti a ON a.f_aktivitiID = l.f_aktivitiID
            INNER JOIN tbl_monitoring_project p ON p.f_projectID = a.f_projectID
            LEFT JOIN tbl_monitoring_project_owner po
                ON po.f_projectID = p.f_projectID AND TRIM(COALESCE(po.f_stafID, '')) = ?
            LEFT JOIN tbl_monitoring_project_pic pp
                ON pp.f_projectID = p.f_projectID AND TRIM(COALESCE(pp.f_stafID, '')) = ?
            WHERE {$this->activeStatusSql('a')}
              AND {$this->activeStatusSql('p')}
              AND (
                TRIM(COALESCE(a.f_ownerStafID, '')) = ?
                OR TRIM(COALESCE(p.f_ownerStafID, '')) = ?
                OR TRIM(COALESCE(p.f_picStafID, '')) = ?
                OR po.f_stafID IS NOT NULL
                OR pp.f_stafID IS NOT NULL
              )
            ORDER BY CAST(l.f_tahun AS UNSIGNED) DESC, CAST(l.f_bulan AS UNSIGNED) DESC, l.f_submitteddt DESC
            LIMIT " . self::MAX_ROWS;

        return array_map(fn(array $row): array => [
            'type' => 'progress',
            'project_id' => (int)($row['project_id'] ?? 0),
            'project_name' => $this->cleanText($row['project_name'] ?? '', 180),
            'activity_id' => (int)($row['activity_id'] ?? 0),
            'activity_name' => $this->cleanText($row['activity_name'] ?? '', 180),
            'period' => $this->period((int)($row['report_month'] ?? 0), (int)($row['report_year'] ?? 0)),
            'percent_complete' => $this->numberOrNull($row['percent_complete'] ?? null),
            'progress_status' => $this->cleanText($row['progress_status'] ?? '', 40),
            'submitted_at' => $this->dateOrNull($row['submitted_at'] ?? null),
        ], $this->fetchAll($sql, [$staffId, $staffId, $staffId, $staffId, $staffId]));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function latestAnnouncements(): array
    {
        $sql = "
            SELECT
                f_announcementID AS announcement_id,
                f_title AS title,
                f_content AS content,
                f_priority AS priority,
                f_startDate AS start_date,
                f_endDate AS end_date
            FROM tbl_announcements
            WHERE {$this->activeStatusSql(null)}
              AND (f_startDate IS NULL OR f_startDate <= CURDATE())
              AND (f_endDate IS NULL OR f_endDate >= CURDATE())
            ORDER BY
                CASE f_priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END,
                COALESCE(f_startDate, f_createddt) DESC
            LIMIT " . self::MAX_ROWS;

        return array_map(fn(array $row): array => [
            'type' => 'announcement',
            'announcement_id' => (int)($row['announcement_id'] ?? 0),
            'title' => $this->cleanText($row['title'] ?? '', 180),
            'summary' => $this->cleanText($row['content'] ?? '', 220),
            'priority' => $this->cleanText($row['priority'] ?? '', 30),
            'start_date' => $this->dateOrNull($row['start_date'] ?? null),
            'end_date' => $this->dateOrNull($row['end_date'] ?? null),
        ], $this->fetchAll($sql));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function feedbackSummary(string $staffId, bool $isSuperAdmin = false): array
    {
        if ($isSuperAdmin) {
            $sql = "
                SELECT
                    p.f_projectID AS project_id,
                    p.f_projectName AS project_name,
                    COUNT(f.f_feedbackID) AS feedback_count,
                    SUM(CASE WHEN COALESCE(f.f_isAcknowledged, 0) IN (0, '0') THEN 1 ELSE 0 END) AS unacknowledged_count,
                    MAX(f.f_createdDt) AS latest_feedback_date
                FROM tbl_monitoring_project_feedback f
                INNER JOIN tbl_monitoring_project p ON p.f_projectID = f.f_projectID
                LEFT JOIN tbl_monitoring_project_owner po
                    ON po.f_projectID = p.f_projectID AND TRIM(COALESCE(po.f_stafID, '')) = ?
                LEFT JOIN tbl_monitoring_project_pic pp
                    ON pp.f_projectID = p.f_projectID AND TRIM(COALESCE(pp.f_stafID, '')) = ?
                WHERE {$this->activeStatusSql('p')}
                GROUP BY p.f_projectID, p.f_projectName
                ORDER BY unacknowledged_count DESC, latest_feedback_date DESC
                LIMIT " . self::MAX_ROWS;

            return array_map(fn(array $row): array => [
                'type' => 'feedback_summary',
                'project_id' => (int)($row['project_id'] ?? 0),
                'project_name' => $this->cleanText($row['project_name'] ?? '', 180),
                'feedback_count' => (int)($row['feedback_count'] ?? 0),
                'unacknowledged_count' => (int)($row['unacknowledged_count'] ?? 0),
                'latest_feedback_date' => $this->dateOrNull($row['latest_feedback_date'] ?? null),
                'raw_comments_included' => false,
            ], $this->fetchAll($sql, [$staffId, $staffId]));
        }

        $sql = "
            SELECT
                p.f_projectID AS project_id,
                p.f_projectName AS project_name,
                COUNT(f.f_feedbackID) AS feedback_count,
                SUM(CASE WHEN COALESCE(f.f_isAcknowledged, 0) IN (0, '0') THEN 1 ELSE 0 END) AS unacknowledged_count,
                MAX(f.f_createdDt) AS latest_feedback_date
            FROM tbl_monitoring_project_feedback f
            INNER JOIN tbl_monitoring_project p ON p.f_projectID = f.f_projectID
            LEFT JOIN tbl_monitoring_project_owner po
                ON po.f_projectID = p.f_projectID AND TRIM(COALESCE(po.f_stafID, '')) = ?
            LEFT JOIN tbl_monitoring_project_pic pp
                ON pp.f_projectID = p.f_projectID AND TRIM(COALESCE(pp.f_stafID, '')) = ?
            WHERE {$this->activeStatusSql('p')}
              AND (
                TRIM(COALESCE(p.f_ownerStafID, '')) = ?
                OR TRIM(COALESCE(p.f_picStafID, '')) = ?
                OR TRIM(COALESCE(f.f_ownerStafID, '')) = ?
                OR TRIM(COALESCE(f.f_picStafID, '')) = ?
                OR po.f_stafID IS NOT NULL
                OR pp.f_stafID IS NOT NULL
              )
            GROUP BY p.f_projectID, p.f_projectName
            ORDER BY unacknowledged_count DESC, latest_feedback_date DESC
            LIMIT " . self::MAX_ROWS;

        return array_map(fn(array $row): array => [
            'type' => 'feedback_summary',
            'project_id' => (int)($row['project_id'] ?? 0),
            'project_name' => $this->cleanText($row['project_name'] ?? '', 180),
            'feedback_count' => (int)($row['feedback_count'] ?? 0),
            'unacknowledged_count' => (int)($row['unacknowledged_count'] ?? 0),
            'latest_feedback_date' => $this->dateOrNull($row['latest_feedback_date'] ?? null),
            'raw_comments_included' => false,
        ], $this->fetchAll($sql, [$staffId, $staffId, $staffId, $staffId, $staffId, $staffId]));
    }

    private function detectIntent(string $message): string
    {
        $text = $this->normalize($message);

        if ($this->containsAny($text, [
            'pengumuman',
            'announcement',
            'announcements',
            'hebahan',
            'makluman',
        ])) {
            return self::INTENT_LATEST_ANNOUNCEMENTS;
        }

        if ($this->containsAny($text, [
            'feedback',
            'komen',
            'comment',
            'acknowledge',
            'acknowledged',
            'belum acknowledge',
            'maklum balas',
        ])) {
            return self::INTENT_FEEDBACK_SUMMARY;
        }

        if ($this->containsAny($text, [
            'aktiviti',
            'activity',
            'activities',
            'task',
            'tugasan',
            'milestone',
            'belum siap',
        ])) {
            return self::INTENT_MY_ACTIVITIES;
        }

        if ($this->containsAny($text, [
            'progress',
            'kemajuan',
            'status kemajuan',
            'lewat',
            'kritikal',
            'percent',
            'peratus',
        ])) {
            return self::INTENT_PROJECT_PROGRESS;
        }

        if ($this->containsAny($text, [
            'projek saya',
            'project saya',
            'senarai projek',
            'projek yang saya',
            'project yang saya',
            'projek di bawah saya',
            'my project',
            'my projects',
        ])) {
            return self::INTENT_MY_PROJECTS;
        }

        return self::INTENT_UNSUPPORTED;
    }

    private function scopeForIntent(string $intent, bool $isSuperAdmin): string
    {
        if ($isSuperAdmin) {
            return match ($intent) {
                self::INTENT_MY_PROJECTS => 'all_projects',
                self::INTENT_MY_ACTIVITIES => 'all_activities',
                self::INTENT_PROJECT_PROGRESS => 'all_project_progress',
                self::INTENT_FEEDBACK_SUMMARY => 'all_feedback_summary',
                self::INTENT_LATEST_ANNOUNCEMENTS => 'active_announcements',
                default => 'none',
            };
        }

        return match ($intent) {
            self::INTENT_MY_PROJECTS,
            self::INTENT_PROJECT_PROGRESS,
            self::INTENT_FEEDBACK_SUMMARY => 'owned_or_pic_project',
            self::INTENT_MY_ACTIVITIES => 'own_staff_or_owned_or_pic_project',
            self::INTENT_LATEST_ANNOUNCEMENTS => 'active_announcements',
            default => 'none',
        };
    }

    /**
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    private function fetchAll(string $sql, array $params = []): array
    {
        $stmt = Database::getInstance('mysql')->getConnection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int,string>
     */
    private function requiredTablesForIntent(string $intent): array
    {
        return match ($intent) {
            self::INTENT_MY_PROJECTS => [
                'tbl_monitoring_project',
                'tbl_monitoring_teras',
                'tbl_monitoring_project_owner',
                'tbl_monitoring_project_pic',
            ],
            self::INTENT_MY_ACTIVITIES => [
                'tbl_monitoring_aktiviti',
                'tbl_monitoring_project',
                'tbl_monitoring_project_owner',
                'tbl_monitoring_project_pic',
            ],
            self::INTENT_PROJECT_PROGRESS => [
                'tbl_monitoring_laporan',
                'tbl_monitoring_aktiviti',
                'tbl_monitoring_project',
                'tbl_monitoring_project_owner',
                'tbl_monitoring_project_pic',
            ],
            self::INTENT_LATEST_ANNOUNCEMENTS => [
                'tbl_announcements',
            ],
            self::INTENT_FEEDBACK_SUMMARY => [
                'tbl_monitoring_project_feedback',
                'tbl_monitoring_project',
                'tbl_monitoring_project_owner',
                'tbl_monitoring_project_pic',
            ],
            default => [],
        };
    }

    /**
     * @param array<int,string> $tables
     */
    private function hasRequiredTables(array $tables): bool
    {
        if ($tables === []) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $sql = "
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ({$placeholders})
        ";
        $stmt = Database::getInstance('mysql')->getConnection()->prepare($sql);
        $stmt->execute(array_values($tables));

        return (int)$stmt->fetchColumn() === count($tables);
    }

    private function activeStatusSql(?string $alias): string
    {
        $column = $alias === null ? 'f_status' : $alias . '.f_status';

        return "(
            {$column} IS NULL
            OR {$column} = 1
            OR {$column} = '1'
            OR {$column} = 'active'
            OR {$column} = 'aktif'
            OR {$column} = 'Aktif'
            OR {$column} = 'AKTIF'
            OR {$column} = 'published'
            OR {$column} = 'enabled'
        )";
    }

    /**
     * @param array<string,mixed> $row
     */
    private function projectRole(array $row): string
    {
        $roles = [];
        if ((int)($row['is_primary_owner'] ?? 0) === 1 || (int)($row['is_owner'] ?? 0) === 1) {
            $roles[] = 'owner';
        }
        if ((int)($row['is_primary_pic'] ?? 0) === 1 || (int)($row['is_pic'] ?? 0) === 1) {
            $roles[] = 'pic';
        }

        return $roles === [] ? 'accessible' : implode(',', array_values(array_unique($roles)));
    }

    private function cleanText(mixed $value, int $maxLength): string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$text);
        $text = preg_replace('/\s+/u', ' ', (string)$text);
        $text = trim((string)$text);

        return $this->substring($text, $maxLength);
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $this->substring($value, 30);
        }

        return date('Y-m-d', $timestamp);
    }

    private function numberOrNull(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float)$value;
        return floor($number) === $number ? (int)$number : $number;
    }

    private function period(int $month, int $year): string
    {
        if ($month <= 0 && $year <= 0) {
            return '';
        }
        if ($month <= 0) {
            return (string)$year;
        }
        if ($year <= 0) {
            return str_pad((string)$month, 2, '0', STR_PAD_LEFT);
        }

        return sprintf('%04d-%02d', $year, $month);
    }

    /**
     * @return array<string,mixed>
     */
    private function safetyPolicy(): array
    {
        return [
            'database_access' => 'provider_owned_read_only_queries_only',
            'sql_generation' => 'not_allowed',
            'raw_feedback_comments' => 'not_allowed_initially',
            'raw_document_paths' => 'not_allowed',
            'cross_user_private_data' => 'not_allowed',
        ];
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $actor
     */
    private function staffId(array $profile, array $actor): string
    {
        foreach (['f_stafID', 'staff_id', 'staf_id'] as $key) {
            $value = trim((string)($profile[$key] ?? $actor[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', (string)$value);
        $value = preg_replace('/\s+/u', ' ', (string)$value);

        return trim((string)$value);
    }

    /**
     * @param array<int,string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $needle = $this->normalize($needle);
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function substring(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }
}
