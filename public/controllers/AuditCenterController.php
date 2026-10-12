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

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../setting/helper/access_helper.php';

class AuditCenterController
{
    private PDO $pdoMysql;
    private User $userModel;
    private array $profile = [];
    /** @var array<string,bool> */
    private array $tableExistsCache = [];
    /** @var array<string,bool> */
    private array $columnExistsCache = [];
    /** @var array<string,array<int,array{name:string,type:string}>> */
    private array $tableColumnsCache = [];

    public function __construct(?PDO $pdoMysql = null)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->pdoMysql = $pdoMysql ?: Database::pdoMysql();
        $this->pdoMysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->userModel = new User($this->pdoMysql);
        $this->profile = $this->resolveCurrentProfile();
    }

    public function getProfile(): array
    {
        return $this->profile;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,string>
     */
    public function normalizeFilters(array $input): array
    {
        $keys = [
            'date_from',
            'date_to',
            'login_id',
            'actor',
            'ip',
            'event_type',
            'outcome',
            'severity',
            'target_type',
            'route',
            'method',
            'status_code',
            'session_id',
            'change_reason',
            'scope_type',
        ];

        $filters = [];
        foreach ($keys as $key) {
            $value = trim((string)($input[$key] ?? ''));
            if ($value === '') {
                $filters[$key] = '';
                continue;
            }

            if (in_array($key, ['date_from', 'date_to'], true)) {
                $date = date_create($value);
                $filters[$key] = $date ? $date->format('Y-m-d') : '';
                continue;
            }

            $filters[$key] = mb_substr($value, 0, 120);
        }

        return $filters;
    }

    public function isSuperAdmin(): bool
    {
        return function_exists('is_user_super_admin')
            ? is_user_super_admin($this->profile, $this->pdoMysql)
            : false;
    }

    /**
     * @return array<string,int>
     */
    public function getSummary(): array
    {
        return [
            'events_today' => $this->countTodayEvents(),
            'security_events_today' => $this->countTodaySecurityEvents(),
            'active_sessions' => $this->countActiveSessions(),
            'active_lockouts' => $this->countActiveLockouts(),
            'active_throttles' => $this->countActiveThrottles(),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getRecentEvents(int $limit = 50, string $search = ''): array
    {
        if (!$this->tableExists('audit_event')) {
            return [];
        }

        $limit = max(5, min(200, $limit));
        $where = [];
        $params = [];

        $this->appendGlobalSearchWhere('audit_event', $search, $where, $params);

        $sql = "SELECT
                    id,
                    occurred_at,
                    request_id,
                    session_id,
                    user_id,
                    " . $this->selectColumnOrNull('audit_event', 'login_id') . ",
                    actor_label,
                    " . $this->buildIpSelect('audit_event') . ",
                    event_type,
                    severity,
                    outcome,
                    target_type,
                    target_id,
                    target_label,
                    message
                FROM audit_event";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY occurred_at DESC, id DESC LIMIT ' . $limit;

        return $this->fetchAllAssoc($sql, $params);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getRecentRequests(int $limit = 50, string $search = ''): array
    {
        if (!$this->tableExists('audit_request')) {
            return [];
        }

        $limit = max(5, min(200, $limit));
        $selectParts = [
            $this->selectColumnOrNull('audit_request', 'id'),
            $this->selectColumnOrNull('audit_request', 'request_id'),
            $this->selectColumnOrNull('audit_request', 'session_id'),
            $this->selectColumnOrNull('audit_request', 'user_id'),
            $this->selectColumnOrNull('audit_request', 'login_id'),
            $this->selectColumnOrNull('audit_request', 'method'),
            $this->selectColumnOrNull('audit_request', 'route'),
            $this->selectColumnOrNull('audit_request', 'started_at'),
            $this->selectColumnOrNull('audit_request', 'ended_at'),
            $this->selectColumnOrNull('audit_request', 'status_code'),
            $this->buildRequestDurationSelect(),
            $this->buildIpSelect('audit_request'),
        ];

        $where = [];
        $params = [];
        $this->appendGlobalSearchWhere('audit_request', $search, $where, $params);

        $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM audit_request';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . $this->buildLatestFirstOrder('audit_request', ['started_at', 'id']) . ' LIMIT ' . $limit;

        return $this->fetchAllAssoc($sql, $params);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getRecentSessions(int $limit = 50, string $search = ''): array
    {
        if (!$this->tableExists('audit_session')) {
            return [];
        }

        $limit = max(5, min(200, $limit));
        $where = [];
        $params = [];

        $this->appendGlobalSearchWhere('audit_session', $search, $where, $params);

        $hasSessionLoginId = $this->tableHasColumn('audit_session', 'login_id');

        $sql = "SELECT
                    id,
                    session_id,
                    " . $this->selectColumnOrNull('audit_session', 'user_id') . ",
                    " . $this->selectColumnOrNull('audit_session', 'login_id') . ",
                    " . $this->selectColumnOrNull('audit_session', 'user_nopekerja') . ",
                    COALESCE(NULLIF(TRIM(u.f_nama), ''), NULLIF(TRIM(u.f_nickname), '')) AS display_name,
                    " . $this->selectColumnOrNull('audit_session', 'started_at') . ",
                    " . $this->selectColumnOrNull('audit_session', 'ended_at') . ",
                    " . $this->buildIpSelect('audit_session') . ",
                    " . $this->selectColumnOrNull('audit_session', 'user_agent') . ",
                    " . $this->buildSessionDurationSelect() . "
                FROM audit_session
                LEFT JOIN tbl_m_user u ON " . ($hasSessionLoginId
                    ? "TRIM(COALESCE(u.f_loginID, '')) = TRIM(COALESCE(audit_session.login_id, ''))"
                    : "1 = 0");
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . $this->buildLatestFirstOrder('audit_session', ['started_at', 'id']) . ' LIMIT ' . $limit;

        return $this->fetchAllAssoc($sql, $params);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getActiveLockouts(int $limit = 50, string $search = ''): array
    {
        if (!$this->tableExists('tbl_auth_login_lockout')) {
            return [];
        }

        $limit = max(5, min(200, $limit));
        $where = ['f_locked_until IS NOT NULL', 'f_locked_until > NOW()'];
        $params = [];

        if ($search !== '') {
            $where[] = '(f_loginID LIKE :search OR COALESCE(f_last_ip, \'\') LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT
                    id,
                    f_loginID,
                    f_failed_attempts,
                    f_locked_until,
                    " . $this->selectColumnOrNull('tbl_auth_login_lockout', 'f_last_failed_at') . ",
                    " . $this->selectColumnOrNull('tbl_auth_login_lockout', 'f_last_ip') . "
                FROM tbl_auth_login_lockout
                WHERE " . implode(' AND ', $where) . "
                ORDER BY " . $this->buildLatestFirstOrder('tbl_auth_login_lockout', ['f_last_failed_at', 'f_locked_until', 'id']) . "
                LIMIT " . $limit;

        return $this->fetchAllAssoc($sql, $params);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getActiveThrottles(int $limit = 50, string $search = ''): array
    {
        if (!$this->tableExists('tbl_auth_login_throttle')) {
            return [];
        }

        $limit = max(5, min(200, $limit));
        $where = ['f_locked_until IS NOT NULL', 'f_locked_until > NOW()'];
        $params = [];

        if ($search !== '') {
            $where[] = '(f_scope_type LIKE :search OR f_scope_key LIKE :search OR COALESCE(f_last_ip, \'\') LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT
                    id,
                    f_scope_type,
                    f_scope_key,
                    f_failed_attempts,
                    f_locked_until,
                    " . $this->selectColumnOrNull('tbl_auth_login_throttle', 'f_last_failed_at') . ",
                    " . $this->selectColumnOrNull('tbl_auth_login_throttle', 'f_last_ip') . "
                FROM tbl_auth_login_throttle
                WHERE " . implode(' AND ', $where) . "
                ORDER BY " . $this->buildLatestFirstOrder('tbl_auth_login_throttle', ['f_last_failed_at', 'f_locked_until', 'id']) . "
                LIMIT " . $limit;

        return $this->fetchAllAssoc($sql, $params);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getRecentSecurityEvents(int $limit = 20, string $search = ''): array
    {
        if (!$this->tableExists('audit_event')) {
            return [];
        }

        $limit = max(5, min(100, $limit));
        $where = ['(severity = :severity OR event_type = :eventType)'];
        $params = [
            ':severity' => 'SECURITY',
            ':eventType' => 'LOGIN',
        ];

        $this->appendGlobalSearchWhere('audit_event', $search, $where, $params);

        $sql = "SELECT
                    id,
                    occurred_at,
                    " . $this->selectColumnOrNull('audit_event', 'login_id') . ",
                    actor_label,
                    " . $this->buildIpSelect('audit_event') . ",
                    event_type,
                    severity,
                    outcome,
                    message
                FROM audit_event
                WHERE " . implode(' AND ', $where) . "
                ORDER BY occurred_at DESC, id DESC
                LIMIT " . $limit;

        return $this->fetchAllAssoc($sql, $params);
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getEventsPage(int $page = 1, int $limit = 25, string $search = '', array $filters = [], int $maxLimit = 100): array
    {
        if (!$this->tableExists('audit_event')) {
            return $this->emptyPagePayload($page, $limit);
        }

        $page = $this->normalizePage($page);
        $limit = $this->normalizeLimit($limit, $maxLimit);
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        $this->appendGlobalSearchWhere('audit_event', $search, $where, $params);
        $this->appendAuditEventFilters('', $filters, $where, $params, 'events');

        $sql = "SELECT
                    id,
                    occurred_at,
                    request_id,
                    session_id,
                    user_id,
                    " . $this->selectColumnOrNull('audit_event', 'login_id') . ",
                    actor_label,
                    " . $this->buildIpSelect('audit_event') . ",
                    event_type,
                    severity,
                    outcome,
                    target_type,
                    target_id,
                    target_label,
                    message
                FROM audit_event";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY occurred_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $this->fetchAllAssoc($sql, $params);
        $total = $this->countRows('audit_event', $where, $params);
        return $this->pagePayload($rows, $total, $page, $limit);
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getRequestsPage(int $page = 1, int $limit = 25, string $search = '', array $filters = [], int $maxLimit = 100): array
    {
        if (!$this->tableExists('audit_request')) {
            return $this->emptyPagePayload($page, $limit);
        }

        $page = $this->normalizePage($page);
        $limit = $this->normalizeLimit($limit, $maxLimit);
        $offset = ($page - 1) * $limit;
        $selectParts = [
            $this->selectColumnOrNull('audit_request', 'id'),
            $this->selectColumnOrNull('audit_request', 'request_id'),
            $this->selectColumnOrNull('audit_request', 'session_id'),
            $this->selectColumnOrNull('audit_request', 'user_id'),
            $this->selectColumnOrNull('audit_request', 'login_id'),
            $this->selectColumnOrNull('audit_request', 'method'),
            $this->selectColumnOrNull('audit_request', 'route'),
            $this->selectColumnOrNull('audit_request', 'started_at'),
            $this->selectColumnOrNull('audit_request', 'ended_at'),
            $this->selectColumnOrNull('audit_request', 'status_code'),
            $this->buildRequestDurationSelect(),
            $this->buildIpSelect('audit_request'),
        ];

        $where = [];
        $params = [];
        $this->appendGlobalSearchWhere('audit_request', $search, $where, $params);
        $this->appendDateRangeWhere('started_at', $filters, $where, $params, 'requests');
        $this->appendColumnLikeFilter('login_id', $filters['login_id'] ?? '', $where, $params, 'requests_login_id');
        $this->appendColumnLikeFilter('route', $filters['route'] ?? '', $where, $params, 'requests_route');
        $this->appendColumnLikeFilter('method', $filters['method'] ?? '', $where, $params, 'requests_method');
        $this->appendColumnLikeFilter('status_code', $filters['status_code'] ?? '', $where, $params, 'requests_status');
        $this->appendColumnLikeFilter('session_id', $filters['session_id'] ?? '', $where, $params, 'requests_session');
        $this->appendIpExpressionFilter($this->getIpExpression('audit_request'), $filters['ip'] ?? '', $where, $params, 'requests_ip');

        $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM audit_request';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . $this->buildLatestFirstOrder('audit_request', ['started_at', 'id']) . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $this->fetchAllAssoc($sql, $params);
        $total = $this->countRows('audit_request', $where, $params);
        return $this->pagePayload($rows, $total, $page, $limit);
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getSessionsPage(int $page = 1, int $limit = 25, string $search = '', array $filters = [], int $maxLimit = 100): array
    {
        if (!$this->tableExists('audit_session')) {
            return $this->emptyPagePayload($page, $limit);
        }

        $page = $this->normalizePage($page);
        $limit = $this->normalizeLimit($limit, $maxLimit);
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        $this->appendGlobalSearchWhere('audit_session', $search, $where, $params);
        $this->appendDateRangeWhere('audit_session.started_at', $filters, $where, $params, 'sessions');
        $this->appendColumnLikeFilter('audit_session.login_id', $filters['login_id'] ?? '', $where, $params, 'sessions_login_id');
        $this->appendColumnLikeFilter('audit_session.session_id', $filters['session_id'] ?? '', $where, $params, 'sessions_session');
        $this->appendIpExpressionFilter($this->getIpExpression('audit_session', 'audit_session'), $filters['ip'] ?? '', $where, $params, 'sessions_ip');

        $hasSessionLoginId = $this->tableHasColumn('audit_session', 'login_id');

        $sql = "SELECT
                    id,
                    session_id,
                    " . $this->selectColumnOrNull('audit_session', 'user_id') . ",
                    " . $this->selectColumnOrNull('audit_session', 'login_id') . ",
                    " . $this->selectColumnOrNull('audit_session', 'user_nopekerja') . ",
                    COALESCE(NULLIF(TRIM(u.f_nama), ''), NULLIF(TRIM(u.f_nickname), '')) AS display_name,
                    " . $this->selectColumnOrNull('audit_session', 'started_at') . ",
                    " . $this->selectColumnOrNull('audit_session', 'ended_at') . ",
                    " . $this->buildIpSelect('audit_session') . ",
                    " . $this->selectColumnOrNull('audit_session', 'user_agent') . ",
                    " . $this->buildSessionDurationSelect() . "
                FROM audit_session
                LEFT JOIN tbl_m_user u ON " . ($hasSessionLoginId
                    ? "TRIM(COALESCE(u.f_loginID, '')) = TRIM(COALESCE(audit_session.login_id, ''))"
                    : "1 = 0");
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . $this->buildLatestFirstOrder('audit_session', ['started_at', 'id']) . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $this->fetchAllAssoc($sql, $params);
        $total = $this->countRows('audit_session', $where, $params);
        return $this->pagePayload($rows, $total, $page, $limit);
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getChangesPage(int $page = 1, int $limit = 25, string $search = '', array $filters = [], int $maxLimit = 100): array
    {
        if (
            !$this->tableExists('audit_change_set')
            || !$this->tableExists('audit_change_field')
            || !$this->tableExists('audit_event')
        ) {
            return $this->emptyPagePayload($page, $limit);
        }

        $page = $this->normalizePage($page);
        $limit = $this->normalizeLimit($limit, $maxLimit);
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        $this->appendChangesSearchWhere($search, $where, $params);
        $this->appendDateRangeWhere('e.occurred_at', $filters, $where, $params, 'changes');
        $this->appendColumnLikeFilter('e.login_id', $filters['login_id'] ?? '', $where, $params, 'changes_login_id');
        $this->appendColumnLikeFilter('e.actor_label', $filters['actor'] ?? '', $where, $params, 'changes_actor');
        $this->appendColumnLikeFilter('e.event_type', $filters['event_type'] ?? '', $where, $params, 'changes_event_type');
        $this->appendColumnLikeFilter('cs.target_type', $filters['target_type'] ?? '', $where, $params, 'changes_target_type');
        $this->appendColumnLikeFilter('cs.change_reason', $filters['change_reason'] ?? '', $where, $params, 'changes_reason');

        $sql = "SELECT
                    cs.id,
                    cs.event_id,
                    cs.target_type,
                    cs.target_id,
                    cs.change_reason,
                    cs.meta,
                    e.occurred_at,
                    e.event_type,
                    e.actor_label,
                    e.message,
                    " . ($this->tableHasColumn('audit_event', 'login_id') ? "e.login_id" : "NULL AS login_id") . ",
                    (
                        SELECT COUNT(*)
                        FROM audit_change_field cf_count
                        WHERE cf_count.change_set_id = cs.id
                    ) AS field_count
                FROM audit_change_set cs
                LEFT JOIN audit_event e ON e.id = cs.event_id";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . ($this->tableHasColumn('audit_event', 'occurred_at')
            ? 'e.occurred_at DESC, cs.id DESC'
            : 'cs.id DESC') . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $this->fetchAllAssoc($sql, $params);
        $this->attachChangeFieldRows($rows);
        $total = $this->countChangesRows($where, $params);
        return $this->pagePayload($rows, $total, $page, $limit);
    }

    /**
     * @return array{
     *   events: array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int},
     *   lockouts: array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int},
     *   throttles: array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int}
     * }
     */
    public function getSecurityPage(int $limit = 15, string $search = '', int $eventsPage = 1, int $lockoutsPage = 1, int $throttlesPage = 1, array $filters = [], int $maxLimit = 100): array
    {
        return [
            'events' => $this->getSecurityEventsPage($eventsPage, $limit, $search, $filters, $maxLimit),
            'lockouts' => $this->getLockoutsPage($lockoutsPage, $limit, $search, $filters, $maxLimit),
            'throttles' => $this->getThrottlesPage($throttlesPage, $limit, $search, $filters, $maxLimit),
        ];
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getSecurityEventsPage(int $page = 1, int $limit = 15, string $search = '', array $filters = [], int $maxLimit = 100): array
    {
        if (!$this->tableExists('audit_event')) {
            return $this->emptyPagePayload($page, $limit);
        }

        $page = $this->normalizePage($page);
        $limit = $this->normalizeLimit($limit, $maxLimit);
        $offset = ($page - 1) * $limit;
        $where = ['(severity = :severity OR event_type = :eventType)'];
        $params = [
            ':severity' => 'SECURITY',
            ':eventType' => 'LOGIN',
        ];

        $this->appendGlobalSearchWhere('audit_event', $search, $where, $params);
        $this->appendAuditEventFilters('', $filters, $where, $params, 'security_events');

        $sql = "SELECT
                    id,
                    occurred_at,
                    " . $this->selectColumnOrNull('audit_event', 'login_id') . ",
                    actor_label,
                    " . $this->buildIpSelect('audit_event') . ",
                    event_type,
                    severity,
                    outcome,
                    message
                FROM audit_event
                WHERE " . implode(' AND ', $where) . "
                ORDER BY occurred_at DESC, id DESC
                LIMIT " . $limit . ' OFFSET ' . $offset;

        $rows = $this->fetchAllAssoc($sql, $params);
        $total = $this->countRows('audit_event', $where, $params);
        return $this->pagePayload($rows, $total, $page, $limit);
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getLockoutsPage(int $page = 1, int $limit = 15, string $search = '', array $filters = [], int $maxLimit = 100): array
    {
        if (!$this->tableExists('tbl_auth_login_lockout')) {
            return $this->emptyPagePayload($page, $limit);
        }

        $page = $this->normalizePage($page);
        $limit = $this->normalizeLimit($limit, $maxLimit);
        $offset = ($page - 1) * $limit;
        $where = ['f_locked_until IS NOT NULL', 'f_locked_until > NOW()'];
        $params = [];

        if ($search !== '') {
            $where[] = '(f_loginID LIKE :search OR COALESCE(f_last_ip, \'\') LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $this->appendColumnLikeFilter('f_loginID', $filters['login_id'] ?? '', $where, $params, 'lockouts_login_id');
        $this->appendIpExpressionFilter('COALESCE(f_last_ip, \'\')', $filters['ip'] ?? '', $where, $params, 'lockouts_ip');

        $sql = "SELECT
                    id,
                    f_loginID,
                    f_failed_attempts,
                    f_locked_until,
                    " . $this->selectColumnOrNull('tbl_auth_login_lockout', 'f_last_failed_at') . ",
                    " . $this->selectColumnOrNull('tbl_auth_login_lockout', 'f_last_ip') . "
                FROM tbl_auth_login_lockout
                WHERE " . implode(' AND ', $where) . "
                ORDER BY " . $this->buildLatestFirstOrder('tbl_auth_login_lockout', ['f_last_failed_at', 'f_locked_until', 'id']) . "
                LIMIT " . $limit . ' OFFSET ' . $offset;

        $rows = $this->fetchAllAssoc($sql, $params);
        $total = $this->countRows('tbl_auth_login_lockout', $where, $params);
        return $this->pagePayload($rows, $total, $page, $limit);
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getThrottlesPage(int $page = 1, int $limit = 15, string $search = '', array $filters = [], int $maxLimit = 100): array
    {
        if (!$this->tableExists('tbl_auth_login_throttle')) {
            return $this->emptyPagePayload($page, $limit);
        }

        $page = $this->normalizePage($page);
        $limit = $this->normalizeLimit($limit, $maxLimit);
        $offset = ($page - 1) * $limit;
        $where = ['f_locked_until IS NOT NULL', 'f_locked_until > NOW()'];
        $params = [];

        if ($search !== '') {
            $where[] = '(f_scope_type LIKE :search OR f_scope_key LIKE :search OR COALESCE(f_last_ip, \'\') LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $this->appendColumnLikeFilter('f_scope_type', $filters['scope_type'] ?? '', $where, $params, 'throttles_scope_type');
        $this->appendColumnLikeFilter('f_scope_key', $filters['login_id'] ?? '', $where, $params, 'throttles_scope_key');
        $this->appendIpExpressionFilter('COALESCE(f_last_ip, \'\')', $filters['ip'] ?? '', $where, $params, 'throttles_ip');

        $sql = "SELECT
                    id,
                    f_scope_type,
                    f_scope_key,
                    f_failed_attempts,
                    f_locked_until,
                    " . $this->selectColumnOrNull('tbl_auth_login_throttle', 'f_last_failed_at') . ",
                    " . $this->selectColumnOrNull('tbl_auth_login_throttle', 'f_last_ip') . "
                FROM tbl_auth_login_throttle
                WHERE " . implode(' AND ', $where) . "
                ORDER BY " . $this->buildLatestFirstOrder('tbl_auth_login_throttle', ['f_last_failed_at', 'f_locked_until', 'id']) . "
                LIMIT " . $limit . ' OFFSET ' . $offset;

        $rows = $this->fetchAllAssoc($sql, $params);
        $total = $this->countRows('tbl_auth_login_throttle', $where, $params);
        return $this->pagePayload($rows, $total, $page, $limit);
    }

    private function resolveCurrentProfile(): array
    {
        $loginID = trim((string)($_SESSION['f_loginID'] ?? ''));
        if ($loginID !== '') {
            $profile = $this->userModel->getProfileByLoginID($loginID);
            if (is_array($profile) && $profile !== []) {
                return $profile;
            }
        }

        $stafID = trim((string)($_SESSION['f_stafID'] ?? ''));
        if ($stafID !== '') {
            $profile = $this->userModel->getProfile($stafID);
            if (is_array($profile) && $profile !== []) {
                return $profile;
            }
        }

        return [];
    }

    /**
     * @return array{tab:string, security_subtab:string, rows: array<int,array<string,mixed>>, total:int, limit:int}
     */
    public function getExportData(string $tab, string $securitySubtab = 'events', string $search = '', array $filters = [], int $limit = 2000): array
    {
        $tab = strtolower(trim($tab));
        if (!in_array($tab, ['events', 'requests', 'sessions', 'changes', 'security'], true)) {
            $tab = 'events';
        }

        $securitySubtab = strtolower(trim($securitySubtab));
        if (!in_array($securitySubtab, ['events', 'lockouts', 'throttles'], true)) {
            $securitySubtab = 'events';
        }

        $limit = $this->normalizeLimit($limit, 2000);

        if ($tab === 'requests') {
            $payload = $this->getRequestsPage(1, $limit, $search, $filters, 2000);
        } elseif ($tab === 'sessions') {
            $payload = $this->getSessionsPage(1, $limit, $search, $filters, 2000);
        } elseif ($tab === 'changes') {
            $payload = $this->getChangesPage(1, $limit, $search, $filters, 2000);
        } elseif ($tab === 'security') {
            if ($securitySubtab === 'lockouts') {
                $payload = $this->getLockoutsPage(1, $limit, $search, $filters, 2000);
            } elseif ($securitySubtab === 'throttles') {
                $payload = $this->getThrottlesPage(1, $limit, $search, $filters, 2000);
            } else {
                $payload = $this->getSecurityEventsPage(1, $limit, $search, $filters, 2000);
            }
        } else {
            $payload = $this->getEventsPage(1, $limit, $search, $filters, 2000);
        }

        return [
            'tab' => $tab,
            'security_subtab' => $securitySubtab,
            'rows' => $payload['rows'] ?? [],
            'total' => (int)($payload['total'] ?? 0),
            'limit' => (int)($payload['limit'] ?? $limit),
        ];
    }

    private function countTodayEvents(): int
    {
        if (!$this->tableExists('audit_event')) {
            return 0;
        }
        return (int)$this->pdoMysql->query("SELECT COUNT(*) FROM audit_event WHERE occurred_at >= CURRENT_DATE() AND occurred_at < CURRENT_DATE() + INTERVAL 1 DAY")->fetchColumn();
    }

    private function countTodaySecurityEvents(): int
    {
        if (!$this->tableExists('audit_event')) {
            return 0;
        }

        $stmt = $this->pdoMysql->prepare(
            "SELECT COUNT(*)
             FROM audit_event
             WHERE occurred_at >= CURRENT_DATE()
               AND occurred_at < CURRENT_DATE() + INTERVAL 1 DAY
               AND (severity = :severity OR event_type = :eventType)"
        );
        $stmt->execute([
            ':severity' => 'SECURITY',
            ':eventType' => 'LOGIN',
        ]);
        return (int)$stmt->fetchColumn();
    }

    private function countActiveSessions(): int
    {
        if (!$this->tableExists('audit_session') || !$this->tableHasColumn('audit_session', 'ended_at')) {
            return 0;
        }
        return (int)$this->pdoMysql->query("SELECT COUNT(*) FROM audit_session WHERE ended_at IS NULL")->fetchColumn();
    }

    private function countActiveLockouts(): int
    {
        if (!$this->tableExists('tbl_auth_login_lockout')) {
            return 0;
        }
        return (int)$this->pdoMysql->query("SELECT COUNT(*) FROM tbl_auth_login_lockout WHERE f_locked_until IS NOT NULL AND f_locked_until > NOW()")->fetchColumn();
    }

    private function countActiveThrottles(): int
    {
        if (!$this->tableExists('tbl_auth_login_throttle')) {
            return 0;
        }
        return (int)$this->pdoMysql->query("SELECT COUNT(*) FROM tbl_auth_login_throttle WHERE f_locked_until IS NOT NULL AND f_locked_until > NOW()")->fetchColumn();
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function attachChangeFieldRows(array &$rows): void
    {
        if ($rows === []) {
            return;
        }

        $changeSetIds = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $changeSetIds[] = $id;
            }
        }
        $changeSetIds = array_values(array_unique($changeSetIds));
        if ($changeSetIds === []) {
            return;
        }

        $placeholders = [];
        $params = [];
        foreach ($changeSetIds as $index => $changeSetId) {
            $paramName = ':cs_' . $index;
            $placeholders[] = $paramName;
            $params[$paramName] = $changeSetId;
        }

        $sql = "SELECT
                    id,
                    change_set_id,
                    field,
                    old_value,
                    new_value,
                    data_type,
                    is_sensitive,
                    diff_hint
                FROM audit_change_field
                WHERE change_set_id IN (" . implode(', ', $placeholders) . ")
                ORDER BY change_set_id ASC, id ASC";

        $fieldRows = $this->fetchAllAssoc($sql, $params);
        $grouped = [];
        foreach ($fieldRows as $fieldRow) {
            $changeSetId = (int)($fieldRow['change_set_id'] ?? 0);
            if ($changeSetId <= 0) {
                continue;
            }
            $grouped[$changeSetId][] = $fieldRow;
        }

        foreach ($rows as &$row) {
            $changeSetId = (int)($row['id'] ?? 0);
            $row['field_changes'] = $grouped[$changeSetId] ?? [];
        }
        unset($row);
    }

    private function buildRequestDurationSelect(): string
    {
        if ($this->tableHasColumn('audit_request', 'duration_ms')) {
            return 'duration_ms';
        }
        if ($this->tableHasColumn('audit_request', 'started_at') && $this->tableHasColumn('audit_request', 'ended_at')) {
            return 'TIMESTAMPDIFF(MICROSECOND, started_at, COALESCE(ended_at, NOW(6))) / 1000 AS duration_ms';
        }
        return 'NULL AS duration_ms';
    }

    private function buildSessionDurationSelect(): string
    {
        if ($this->tableHasColumn('audit_session', 'duration_seconds')) {
            return 'duration_seconds';
        }
        if ($this->tableHasColumn('audit_session', 'started_at') && $this->tableHasColumn('audit_session', 'ended_at')) {
            return 'TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, NOW(6))) AS duration_seconds';
        }
        return 'NULL AS duration_seconds';
    }

    private function buildIpSelect(string $table): string
    {
        if ($this->tableHasColumn($table, 'ip_text')) {
            return 'ip_text';
        }
        if ($this->tableHasColumn($table, 'ip_address')) {
            return 'INET6_NTOA(ip_address) AS ip_text';
        }
        if ($this->tableHasColumn($table, 'f_last_ip')) {
            return 'f_last_ip AS ip_text';
        }
        return 'NULL AS ip_text';
    }

    private function getIpExpression(string $table, string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        if ($this->tableHasColumn($table, 'ip_text')) {
            return $prefix . 'ip_text';
        }
        if ($this->tableHasColumn($table, 'ip_address')) {
            return 'INET6_NTOA(' . $prefix . 'ip_address)';
        }
        if ($this->tableHasColumn($table, 'f_last_ip')) {
            return 'COALESCE(' . $prefix . 'f_last_ip, \'\')';
        }
        return "''";
    }

    private function selectColumnOrNull(string $table, string $column): string
    {
        return $this->tableHasColumn($table, $column)
            ? $column
            : ('NULL AS ' . $column);
    }

    /**
     * @param string[] $preferredColumns
     */
    private function pickExistingOrderColumn(string $table, array $preferredColumns): string
    {
        foreach ($preferredColumns as $column) {
            if ($this->tableHasColumn($table, $column)) {
                return $column;
            }
        }
        return '1';
    }

    /**
     * @param string[] $preferredColumns
     */
    private function buildLatestFirstOrder(string $table, array $preferredColumns): string
    {
        $parts = [];
        foreach ($preferredColumns as $column) {
            if ($this->tableHasColumn($table, $column)) {
                $parts[] = $column . ' DESC';
            }
        }

        return $parts !== [] ? implode(', ', $parts) : '1 DESC';
    }

    /**
     * @param array<int,string> $where
     * @param array<string,mixed> $params
     */
    private function countChangesRows(array $where = [], array $params = []): int
    {
        $sql = "SELECT COUNT(DISTINCT cs.id)
                FROM audit_change_set cs
                LEFT JOIN audit_event e ON e.id = cs.event_id";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->pdoMysql->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    private function fetchAllAssoc(string $sql, array $params = []): array
    {
        $stmt = $this->pdoMysql->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param string[] $where
     * @param array<string,mixed> $params
     */
    private function countRows(string $table, array $where = [], array $params = []): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $table;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdoMysql->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int}
     */
    private function emptyPagePayload(int $page, int $limit): array
    {
        $page = $this->normalizePage($page);
        $limit = $this->normalizeLimit($limit);
        return [
            'rows' => [],
            'total' => 0,
            'page' => $page,
            'limit' => $limit,
            'pages' => 1,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array{rows: array<int,array<string,mixed>>, total: int, page: int, limit: int, pages: int}
     */
    private function pagePayload(array $rows, int $total, int $page, int $limit): array
    {
        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => max(1, (int)ceil($total / max(1, $limit))),
        ];
    }

    private function normalizePage(int $page): int
    {
        return max(1, $page);
    }

    private function normalizeLimit(int $limit, int $max = 100): int
    {
        return max(5, min($max, $limit));
    }

    /**
     * @param array<int,string> $where
     * @param array<string,mixed> $params
     */
    private function appendGlobalSearchWhere(string $table, string $search, array &$where, array &$params): void
    {
        $search = trim($search);
        if ($search === '' || !$this->tableExists($table)) {
            return;
        }

        $columns = $this->getTableColumns($table);
        if ($columns === []) {
            return;
        }

        $expressions = [];
        $paramIndex = 0;
        foreach ($columns as $columnInfo) {
            $column = $columnInfo['name'];
            $type = $columnInfo['type'];
            if ($column === 'ip_address') {
                $paramName = ':search_' . $paramIndex++;
                $expressions[] = 'INET6_NTOA(ip_address) LIKE ' . $paramName;
                $params[$paramName] = '%' . $search . '%';
                continue;
            }
            if (!$this->isSearchableDataType($type)) {
                continue;
            }
            $paramName = ':search_' . $paramIndex++;
            $expressions[] = 'CAST(`' . str_replace('`', '``', $column) . '` AS CHAR) LIKE ' . $paramName;
            $params[$paramName] = '%' . $search . '%';
        }

        if ($expressions !== []) {
            $where[] = '(' . implode(' OR ', $expressions) . ')';
        }
    }

    /**
     * @param array<int,string> $where
     * @param array<string,mixed> $params
     */
    private function appendChangesSearchWhere(string $search, array &$where, array &$params): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $expressions = [];
        $columns = [
            'cs.target_type',
            'cs.target_id',
            'cs.change_reason',
            'cs.meta',
            'e.event_type',
            'e.actor_label',
            'e.message',
        ];

        if ($this->tableHasColumn('audit_event', 'login_id')) {
            $columns[] = 'e.login_id';
        }

        foreach ($columns as $index => $column) {
            $paramName = ':changes_search_' . $index;
            $expressions[] = 'CAST(' . $column . ' AS CHAR) LIKE ' . $paramName;
            $params[$paramName] = '%' . $search . '%';
        }

        $fieldConditions = [];
        foreach (['field', 'old_value', 'new_value', 'data_type', 'diff_hint'] as $fieldIndex => $column) {
            $paramName = ':changes_field_search_' . $fieldIndex;
            $fieldConditions[] = 'CAST(cf.' . $column . ' AS CHAR) LIKE ' . $paramName;
            $params[$paramName] = '%' . $search . '%';
        }

        $expressions[] = 'EXISTS (
            SELECT 1
            FROM audit_change_field cf
            WHERE cf.change_set_id = cs.id
              AND (' . implode(' OR ', $fieldConditions) . ')
        )';

        $where[] = '(' . implode(' OR ', $expressions) . ')';
    }

    /**
     * @param array<string,string> $filters
     * @param array<int,string> $where
     * @param array<string,mixed> $params
     */
    private function appendAuditEventFilters(string $alias, array $filters, array &$where, array &$params, string $prefix): void
    {
        $columnPrefix = $alias !== '' ? $alias . '.' : '';
        $this->appendDateRangeWhere($columnPrefix . 'occurred_at', $filters, $where, $params, $prefix);
        $this->appendColumnLikeFilter($columnPrefix . 'login_id', $filters['login_id'] ?? '', $where, $params, $prefix . '_login_id');
        $this->appendColumnLikeFilter($columnPrefix . 'actor_label', $filters['actor'] ?? '', $where, $params, $prefix . '_actor');
        $this->appendIpExpressionFilter($this->getIpExpression('audit_event', $alias), $filters['ip'] ?? '', $where, $params, $prefix . '_ip');
        $this->appendColumnLikeFilter($columnPrefix . 'event_type', $filters['event_type'] ?? '', $where, $params, $prefix . '_event_type');
        $this->appendColumnLikeFilter($columnPrefix . 'outcome', $filters['outcome'] ?? '', $where, $params, $prefix . '_outcome');
        $this->appendColumnLikeFilter($columnPrefix . 'severity', $filters['severity'] ?? '', $where, $params, $prefix . '_severity');
        $this->appendColumnLikeFilter($columnPrefix . 'target_type', $filters['target_type'] ?? '', $where, $params, $prefix . '_target_type');
    }

    /**
     * @param array<string,string> $filters
     * @param array<int,string> $where
     * @param array<string,mixed> $params
     */
    private function appendDateRangeWhere(string $column, array $filters, array &$where, array &$params, string $prefix): void
    {
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));

        if ($dateFrom !== '') {
            $param = ':' . $prefix . '_date_from';
            $where[] = $column . ' >= ' . $param;
            $params[$param] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== '') {
            $param = ':' . $prefix . '_date_to';
            $where[] = $column . ' <= ' . $param;
            $params[$param] = $dateTo . ' 23:59:59';
        }
    }

    /**
     * @param array<int,string> $where
     * @param array<string,mixed> $params
     */
    private function appendColumnLikeFilter(string $column, string $value, array &$where, array &$params, string $paramKey): void
    {
        $value = trim($value);
        if ($value === '' || strpos($column, 'NULL AS') !== false) {
            return;
        }

        $param = ':' . $paramKey;
        $where[] = 'CAST(' . $column . ' AS CHAR) LIKE ' . $param;
        $params[$param] = '%' . $value . '%';
    }

    /**
     * @param array<int,string> $where
     * @param array<string,mixed> $params
     */
    private function appendIpExpressionFilter(string $expression, string $value, array &$where, array &$params, string $paramKey): void
    {
        $value = trim($value);
        if ($value === '' || trim($expression) === "''") {
            return;
        }

        $param = ':' . $paramKey;
        $where[] = 'CAST(' . $expression . ' AS CHAR) LIKE ' . $param;
        $params[$param] = '%' . $value . '%';
    }

    /**
     * @return array<int,array{name:string,type:string}>
     */
    private function getTableColumns(string $table): array
    {
        $cacheKey = strtolower($table);
        if (array_key_exists($cacheKey, $this->tableColumnsCache)) {
            return $this->tableColumnsCache[$cacheKey];
        }

        try {
            $databaseName = (string)$this->pdoMysql->query('SELECT DATABASE()')->fetchColumn();
            if ($databaseName === '') {
                return $this->tableColumnsCache[$cacheKey] = [];
            }

            $stmt = $this->pdoMysql->prepare(
                'SELECT COLUMN_NAME, DATA_TYPE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = :database
                   AND TABLE_NAME = :table
                 ORDER BY ORDINAL_POSITION'
            );
            $stmt->execute([
                ':database' => $databaseName,
                ':table' => $table,
            ]);

            $columns = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $column) {
                $name = trim((string)($column['COLUMN_NAME'] ?? ''));
                $type = strtolower(trim((string)($column['DATA_TYPE'] ?? '')));
                if ($name === '' || strpos($name, '`') !== false) {
                    continue;
                }
                $columns[] = [
                    'name' => $name,
                    'type' => $type,
                ];
            }
            return $this->tableColumnsCache[$cacheKey] = $columns;
        } catch (Throwable $e) {
            return $this->tableColumnsCache[$cacheKey] = [];
        }
    }

    private function isSearchableDataType(string $type): bool
    {
        static $allowed = [
            'char',
            'varchar',
            'tinytext',
            'text',
            'mediumtext',
            'longtext',
            'tinyint',
            'smallint',
            'mediumint',
            'int',
            'integer',
            'bigint',
            'decimal',
            'numeric',
            'float',
            'double',
            'real',
            'date',
            'datetime',
            'timestamp',
            'time',
            'year',
        ];

        return in_array($type, $allowed, true);
    }

    private function tableExists(string $table): bool
    {
        $cacheKey = strtolower($table);
        if (array_key_exists($cacheKey, $this->tableExistsCache)) {
            return $this->tableExistsCache[$cacheKey];
        }

        try {
            $databaseName = (string)$this->pdoMysql->query('SELECT DATABASE()')->fetchColumn();
            if ($databaseName === '') {
                return $this->tableExistsCache[$cacheKey] = false;
            }

            $stmt = $this->pdoMysql->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table'
            );
            $stmt->execute([
                ':database' => $databaseName,
                ':table' => $table,
            ]);
            return $this->tableExistsCache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            return $this->tableExistsCache[$cacheKey] = false;
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = strtolower($table . '.' . $column);
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        try {
            $databaseName = (string)$this->pdoMysql->query('SELECT DATABASE()')->fetchColumn();
            if ($databaseName === '') {
                return $this->columnExistsCache[$cacheKey] = false;
            }

            $stmt = $this->pdoMysql->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = :database
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column'
            );
            $stmt->execute([
                ':database' => $databaseName,
                ':table' => $table,
                ':column' => $column,
            ]);
            return $this->columnExistsCache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            return $this->columnExistsCache[$cacheKey] = false;
        }
    }
}
