<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

class PermohonanEmelController
{
    public string $lang = 'ms';
    public array $user = [];

    private PDO $pdoMysql;
    private User $userModel;

    public function __construct(?PDO $pdoMysql = null)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->lang = $_SESSION['lang'] ?? 'ms';
        $this->pdoMysql = $pdoMysql ?: Database::pdoMysql();
        $this->pdoMysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->userModel = new User($this->pdoMysql);
        $this->user = $this->getCurrentUser();
    }

    public function getCurrentUser(): array
    {
        $stafID = trim((string) ($_SESSION['f_stafID'] ?? ''));
        if ($stafID === '') {
            return [];
        }

        $stmt = $this->pdoMysql->prepare(
            "
            SELECT
                u.f_userID,
                u.f_stafID,
                u.f_nopekerja,
                u.f_nama,
                u.f_nickname,
                u.f_email,
                u.f_handphone,
                u.f_jawatan,
                u.f_kumpjawatan,
                u.f_namajabatan
            FROM tbl_m_user u
            WHERE u.f_stafID = :id
            LIMIT 1
            "
        );
        $stmt->execute([':id' => $stafID]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [];
        }

        return [
            'f_userID' => (int) $row['f_userID'],
            'f_stafID' => (string) $row['f_stafID'],
            'f_nopekerja' => (string) $row['f_nopekerja'],
            'f_nama' => (string) $row['f_nama'],
            'f_nickname' => (string) $row['f_nickname'],
            'f_email' => (string) $row['f_email'],
            'f_handphone' => (string) $row['f_handphone'],
            'f_jawatan' => (string) $row['f_jawatan'],
            'f_kumpjawatan' => (string) $row['f_kumpjawatan'],
            'f_namajabatan' => (string) $row['f_namajabatan'],
        ];
    }
}