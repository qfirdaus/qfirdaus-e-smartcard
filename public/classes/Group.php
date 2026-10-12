<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// classes/Group.php
declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';

/**
 * ✅ Model untuk pengurusan kumpulan pengguna (tbl_m_group)
 */
class Group extends BaseModel
{
    /** ✅ Dapatkan semua rekod kumpulan */
    public function getAll(): array
    {
        $sql = "SELECT f_groupID, f_groupKod, f_groupName, f_modulAccess, f_menuAccess, f_categoryUser,
                       f_color, f_badge_class, f_row_class, f_priority, f_mod,
                       (SELECT COUNT(*) FROM tbl_m_user u WHERE u.f_groupID = tbl_m_group.f_groupID) AS userCount
                FROM tbl_m_group
                ORDER BY f_groupID ASC";
        return $this->fetchAll($sql);
    }

    /** ✅ Dapatkan kumpulan berdasarkan kod */
    public function findByKod(string $kod): ?array
    {
        $sql = "SELECT f_groupID, f_groupKod, f_groupName, f_modulAccess, f_menuAccess, f_categoryUser,
                       f_color, f_badge_class, f_row_class, f_priority, f_mod
                FROM tbl_m_group
                WHERE f_groupKod = :kod
                LIMIT 1";
        return $this->fetchOne($sql, [':kod' => $kod]);
    }

    /** ✅ Dapatkan kumpulan berdasarkan ID */
    public function findById(int $id): ?array
    {
        $sql = "SELECT f_groupID, f_groupKod, f_groupName, f_modulAccess, f_menuAccess, f_categoryUser,
                       f_color, f_badge_class, f_row_class, f_priority, f_mod
                FROM tbl_m_group
                WHERE f_groupID = :id
                LIMIT 1";
        return $this->fetchOne($sql, [':id' => $id]);
    }

    /** ✅ Dapatkan akses modul & menu berdasarkan ID */
    public function getAccessByGroup(int $groupId): array
    {
        return $this->getAccessByGroupId($groupId);
    }

    /** ✅ Dapatkan akses modul & menu berdasarkan ID */
    public function getAccessByGroupId(int $id): array
    {
        $sql = "SELECT f_modulAccess, f_menuAccess
                FROM tbl_m_group
                WHERE f_groupID = :id
                LIMIT 1";
        return $this->fetchOne($sql, [':id' => $id]) ?? ['f_modulAccess' => '', 'f_menuAccess' => ''];
    }
}
