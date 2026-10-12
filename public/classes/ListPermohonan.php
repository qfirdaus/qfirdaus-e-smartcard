<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';

class ListPermohonan extends BaseModel
{

    public function getByStaf(string $stafID): array
    {

        $sql = "
           SELECT 
            p.f_permohonanID,
            p.f_no_permohonan,
            u.f_nama,
            p.f_status,
            p.f_created_at,
            'EMAIL' AS jenis,
            'Email Staf' AS perkhidmatan

            FROM tbl_permohonan_email p

            LEFT JOIN tbl_m_user u
            ON u.f_stafID = p.f_stafID

            WHERE p.f_stafID = :stafID

            ORDER BY p.f_permohonanID DESC
        ";

        return $this->fetchAll($sql,[
        ':stafID'=>$stafID
        ]);

        }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT *
            FROM v_permohonan_email
            WHERE f_permohonanID = :id
            LIMIT 1
        ";

        return $this->fetchOne($sql, [
            ':id' => $id
        ]);
    }

}