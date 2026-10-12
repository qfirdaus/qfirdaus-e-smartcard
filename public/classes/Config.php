<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// classes/Config.php
declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/SystemConfigConstants.php';

/**
 * ✅ Model: Config
 * - Guna BaseModel helpers
 * - Sokong getValue/setValue untuk key individu
 * - Kekalkan API sedia ada (getGroup/saveGroup/saveBahasa/getBahasaAktif/saveTema/getTema)
 *
 * 💡 Pastikan jadual `tbl_m_config` ada UNIQUE KEY (f_group, f_key)
 *    supaya ON DUPLICATE KEY UPDATE berfungsi.
 */
class Config extends BaseModel
{
    /** =========================
     *  Group-level operations
     * ========================= */

    /** Dapatkan semua config dalam satu group (cth: 'email') */
    public function getGroup(string $group): array
    {
        $sql = "SELECT f_key, f_value FROM tbl_m_config WHERE f_group = :g";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([':g' => $group]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return $rows ?: [];
    }

    /** Simpan/overwrite semua key dalam group */
    public function saveGroup(string $group, array $data): bool
    {
        // Nota: Sesetengah environment tiada UNIQUE KEY (f_group,f_key).
        // Untuk elak row duplikat, guna UPDATE dahulu, jika tiada row baru INSERT.
        $pdo = $this->getConnection();
        $pdo->beginTransaction();
        try {
            // Use INSERT ... ON DUPLICATE KEY UPDATE to avoid race/affected-rows ambiguity
            $ins = $pdo->prepare(
                "INSERT INTO tbl_m_config (f_group, f_key, f_value) VALUES (:g, :k, :v) 
                 ON DUPLICATE KEY UPDATE f_value = :v_upd"
            );
            foreach ($data as $key => $value) {
                $ins->execute([':g' => $group, ':k' => $key, ':v' => (string)$value, ':v_upd' => (string)$value]);
            }
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log("[Config] saveGroup failed for group={$group}: " . $e->getMessage());
            return false;
        }
    }

    /** =========================
     *  Key-level operations
     * ========================= */

    /**
     * Ambil satu key. Jika $group diberikan, limit pada group itu.
     * Jika tiada, cari ikut f_key sahaja (ambil satu paling awal).
     */
    public function getValue(string $key, mixed $default = null, ?string $group = null): mixed
    {
        if ($group !== null) {
            $sql = "SELECT f_value FROM tbl_m_config WHERE f_group = :g AND f_key = :k LIMIT 1";
            $row = $this->fetchColumn($sql, [':g' => $group, ':k' => $key]);
            return ($row === false || $row === null) ? $default : $row;
        }

        $sql = "SELECT f_value FROM tbl_m_config WHERE f_key = :k LIMIT 1";
        $row = $this->fetchColumn($sql, [':k' => $key]);
        return ($row === false || $row === null) ? $default : $row;
    }

    /**
     * Set/overwrite satu key. Default group = 'system' kalau tak diberi.
     */
    public function setValue(string $key, string $value, ?string $group = 'system'): bool
    {
        // Elak duplikat bila UNIQUE KEY tiada: cuba UPDATE dahulu, jika tiada row barulah INSERT.
        $pdo = $this->getConnection();
        try {
            if ($group !== null) {
                $upd = $pdo->prepare("UPDATE tbl_m_config SET f_value = :v WHERE f_group = :g AND f_key = :k");
                $upd->execute([':v' => $value, ':g' => (string)$group, ':k' => $key]);
                if ($upd->rowCount() > 0) {
                    return true;
                }

                $exists = $pdo->prepare("SELECT 1 FROM tbl_m_config WHERE f_group = :g AND f_key = :k LIMIT 1");
                $exists->execute([':g' => (string)$group, ':k' => $key]);
                if ($exists->fetchColumn() !== false) {
                    return true;
                }

                $ins = $pdo->prepare("INSERT INTO tbl_m_config (f_group, f_key, f_value) VALUES (:g, :k, :v)");
                return $ins->execute([':g' => (string)$group, ':k' => $key, ':v' => $value]) !== false;
            }

            // group null: try UPDATE first (no group in unique), then INSERT with NULL group
            $upd = $pdo->prepare("UPDATE tbl_m_config SET f_value = :v WHERE f_key = :k");
            $upd->execute([':v' => $value, ':k' => $key]);
            if ($upd->rowCount() > 0) {
                return true;
            }

            $exists = $pdo->prepare("SELECT 1 FROM tbl_m_config WHERE f_key = :k LIMIT 1");
            $exists->execute([':k' => $key]);
            if ($exists->fetchColumn() !== false) {
                return true;
            }

            $ins = $pdo->prepare("INSERT INTO tbl_m_config (f_group, f_key, f_value) VALUES (NULL, :k, :v)");
            return $ins->execute([':k' => $key, ':v' => $value]) !== false;
        } catch (Throwable $e) {
            error_log("[Config] setValue failed for key={$key} group=" . ($group ?? 'NULL') . ": " . $e->getMessage());
            return false;
        }
    }

    /**
     * Alias ringkas untuk backward-compat (digunakan dalam init.php).
     * Sama seperti getValue($key, null, null).
     */
    public function getOne(string $key): ?string
    {
        $val = $this->getValue($key, null, null);
        return $val === null ? null : (string)$val;
    }

    /** =========================
     *  Bahasa
     * ========================= */

    public function saveBahasa(array $senaraiKod): bool
    {
        $value = implode(',', $senaraiKod);
        return $this->setValue('active_languages', $value, 'system');
    }

    public function getBahasaAktif(): array
    {
        $value = $this->getValue('active_languages', null, 'system');
        if (!$value) return ['ms', 'en'];
        $arr = array_values(array_filter(array_map('trim', explode(',', $value))));
        return $arr ?: ['ms', 'en'];
    }

    public function saveDefaultBahasa(string $kod): bool
    {
        return $this->setValue('default_language', trim($kod), 'system');
    }

    public function getDefaultBahasa(?string $fallback = null): ?string
    {
        $value = trim((string)$this->getValue('default_language', '', 'system'));
        return $value === '' ? $fallback : $value;
    }

    public function saveLanguageSettings(array $senaraiKod, string $defaultKod): bool
    {
        $pdo = $this->getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO tbl_m_config (f_group, f_key, f_value) VALUES (:g, :k, :v)
                 ON DUPLICATE KEY UPDATE f_value = :v_upd"
            );
            $activeValue = implode(',', $senaraiKod);
            $stmt->execute([
                ':g' => 'system',
                ':k' => 'active_languages',
                ':v' => $activeValue,
                ':v_upd' => $activeValue,
            ]);
            $defaultKod = trim($defaultKod);
            $stmt->execute([
                ':g' => 'system',
                ':k' => 'default_language',
                ':v' => $defaultKod,
                ':v_upd' => $defaultKod,
            ]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('[Config] saveLanguageSettings failed: ' . $e->getMessage());
            return false;
        }
    }

    /** =========================
     *  Tema
     * ========================= */

    public function saveTema(array $data): bool
    {
        return $this->setValue('default_theme', json_encode($data, JSON_UNESCAPED_UNICODE), 'system');
    }

    public function getTema(): array
    {
        $json = $this->getValue('default_theme', null, 'system');
        if (!$json) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** =========================
     *  Main MySQL Environment
     * ========================= */

    public function setMainDbEnvironment(string $environment): bool
    {
        $allowed = SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS;
        if (!in_array($environment, $allowed, true)) {
            return false;
        }
        return $this->setValue('MAIN_DB_ENVIRONMENT', $environment, SystemConfigConstants::CONFIG_GROUP_SYSTEM);
    }

    public function getMainDbEnvironment(?string $default = null): ?string
    {
        $val = trim((string)$this->getValue('MAIN_DB_ENVIRONMENT', '', SystemConfigConstants::CONFIG_GROUP_SYSTEM));
        if ($val === '') {
            return $default;
        }
        return in_array($val, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)
            ? $val
            : $default;
    }

    /** =========================
     *  Sybase Environment + Operational Mode
     * ========================= */

    public function setSybaseEnvironment(string $environment): bool
    {
        $allowed = SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS;
        if (!in_array($environment, $allowed, true)) {
            return false;
        }
        return $this->setValue('SYBASE_ENVIRONMENT', $environment, SystemConfigConstants::CONFIG_GROUP_SYSTEM);
    }

    public function getSybaseEnvironment(?string $default = null): ?string
    {
        $val = trim((string)$this->getValue('SYBASE_ENVIRONMENT', '', SystemConfigConstants::CONFIG_GROUP_SYSTEM));
        if ($val === '') {
            return $default;
        }
        return in_array($val, SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS, true)
            ? $val
            : $default;
    }

    public function setSybaseOperationalMode(string $mode): bool
    {
        $allowed = SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES;
        if (!in_array($mode, $allowed, true)) {
            return false;
        }
        return $this->setValue('SYBASE_OPERATIONAL_MODE', $mode, SystemConfigConstants::CONFIG_GROUP_SYSTEM);
    }

    public function getSybaseOperationalMode(?string $default = null): ?string
    {
        $val = trim((string)$this->getValue('SYBASE_OPERATIONAL_MODE', '', SystemConfigConstants::CONFIG_GROUP_SYSTEM));
        if ($val === '') {
            return $default;
        }
        return in_array($val, SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES, true)
            ? $val
            : $default;
    }
}
