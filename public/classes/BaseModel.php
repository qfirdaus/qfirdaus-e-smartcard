<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// classes/BaseModel.php
declare(strict_types=1);

/**
 * ✅ Kelas asas untuk semua model dalam sistem e-Prestasi
 */
class BaseModel
{
    /** 🔌 Sambungan PDO ke pangkalan data */
    protected PDO $db;

    /** 🧱 Constructor wajib terima PDO connection */
    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        // Tip: default fetch mode boleh set masa create PDO dalam Database.php
        // $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** ❔ Dapatkan sambungan PDO (jika perlu akses luar) */
    public function getConnection(): PDO
    {
        return $this->db;
    }

    /** ⚙️ Jalankan kueri dengan bind data secara umum */
    protected function runQuery(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // -------------------------
    // 👍 Helper yang selalu guna
    // -------------------------

    /** Ambil satu rekod (assoc) atau null */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->runQuery($sql, $params);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Ambil semua rekod (assoc[]) */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->runQuery($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ambil satu kolum (contoh kiraan) */
    protected function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $stmt = $this->runQuery($sql, $params);
        return $stmt->fetchColumn($column);
    }

    /** Execute (INSERT/UPDATE/DELETE) dan pulangkan rowCount */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->runQuery($sql, $params);
        return $stmt->rowCount();
    }

    /** Last insert id (kalau perlu) */
    protected function lastInsertId(?string $name = null): string
    {
        return $this->db->lastInsertId($name);
    }

    // -------------------------
    // 🔒 Transaksi (senang guna)
    // -------------------------

    /** Run dalam transaksi; auto commit/rollback */
    protected function transaction(callable $fn): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $fn($this->db);
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // -------------------------
    // 🧩 Util untuk IN (...) dan LIKE
    // -------------------------

    /**
     * Build placeholder untuk IN (...) dan merge params.
     * Contoh:
     *   [$ph, $bind] = $this->inClause('ids', [10,20,30]);
     *   $sql = "SELECT * FROM t WHERE id IN ($ph)";
     *   $rows = $this->fetchAll($sql, $bind);
     */
    protected function inClause(string $base, array $values): array
    {
        if (empty($values)) {
            // elak SQL invalid: IN ()
            return ['NULL', []];
        }
        $placeholders = [];
        $bind = [];
        foreach (array_values($values) as $i => $val) {
            $key = ":{$base}_{$i}";
            $placeholders[] = $key;
            $bind[$key] = $val;
        }
        return [implode(',', $placeholders), $bind];
    }

    /** Sanitise untuk LIKE (%...%) */
    protected function like(string $term): string
    {
        // Escape % dan _
        $term = str_replace(['%', '_'], ['\%', '\_'], $term);
        return "%{$term}%";
    }
}
