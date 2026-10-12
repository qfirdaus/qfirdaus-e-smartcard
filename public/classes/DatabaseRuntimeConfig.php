<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/SystemConfigConstants.php';
require_once __DIR__ . '/Config.php';

final class DatabaseRuntimeConfig
{
    public function __construct(
        private readonly ?Config $configModel = null,
        private readonly ?string $osFamilyOverride = null,
    )
    {
    }

    public function getOsFamily(): string
    {
        $override = strtolower(trim((string)$this->osFamilyOverride));
        if (in_array($override, ['windows', 'linux'], true)) {
            return $override;
        }

        return PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux';
    }

    public function getMainMysqlEnvironment(): string
    {
        $sessionValue = strtolower(trim((string)($_SESSION['MAIN_DB_ENVIRONMENT'] ?? '')));
        if (in_array($sessionValue, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)) {
            return $sessionValue;
        }

        $envValue = strtolower(trim((string)($_ENV['MAIN_DB_ENVIRONMENT'] ?? $_SERVER['MAIN_DB_ENVIRONMENT'] ?? getenv('MAIN_DB_ENVIRONMENT') ?? '')));
        if (in_array($envValue, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)) {
            return $envValue;
        }

        if (defined('MAIN_DB_ENVIRONMENT')) {
            $constantValue = strtolower(trim((string)MAIN_DB_ENVIRONMENT));
            if (in_array($constantValue, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)) {
                return $constantValue;
            }
        }

        $configValue = $this->configModel?->getMainDbEnvironment(SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT);
        $configValue = strtolower(trim((string)$configValue));

        return in_array($configValue, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)
            ? $configValue
            : SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT;
    }

    public function getSybaseEnvironment(): string
    {
        if (defined('SYBASE_ENVIRONMENT')) {
            $constantValue = strtolower(trim((string)SYBASE_ENVIRONMENT));
            if (in_array($constantValue, SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS, true)) {
                return $constantValue;
            }
        }

        $sessionValue = strtolower(trim((string)($_SESSION['SYBASE_ENVIRONMENT'] ?? '')));
        if (in_array($sessionValue, SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS, true)) {
            return $sessionValue;
        }

        $envValue = strtolower(trim((string)($_ENV['SYBASE_ENVIRONMENT'] ?? $_SERVER['SYBASE_ENVIRONMENT'] ?? getenv('SYBASE_ENVIRONMENT') ?? '')));
        if (in_array($envValue, SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS, true)) {
            return $envValue;
        }

        $configValue = $this->configModel?->getSybaseEnvironment(SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT);
        $configValue = strtolower(trim((string)$configValue));

        return in_array($configValue, SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS, true)
            ? $configValue
            : SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT;
    }

    public function getSybaseOperationalMode(): string
    {
        if (defined('SYBASE_OPERATIONAL_MODE')) {
            $constantValue = strtolower(trim((string)SYBASE_OPERATIONAL_MODE));
            if (in_array($constantValue, SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES, true)) {
                return $constantValue;
            }
        }

        $sessionValue = strtolower(trim((string)($_SESSION['SYBASE_OPERATIONAL_MODE'] ?? '')));
        if (in_array($sessionValue, SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES, true)) {
            return $sessionValue;
        }

        $envValue = strtolower(trim((string)($_ENV['SYBASE_OPERATIONAL_MODE'] ?? $_SERVER['SYBASE_OPERATIONAL_MODE'] ?? getenv('SYBASE_OPERATIONAL_MODE') ?? '')));
        if (in_array($envValue, SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES, true)) {
            return $envValue;
        }

        $configValue = $this->configModel?->getSybaseOperationalMode(SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE);
        $configValue = strtolower(trim((string)$configValue));

        return in_array($configValue, SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES, true)
            ? $configValue
            : SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE;
    }

    public function isStudentModeEnabled(): bool
    {
        return $this->getSybaseOperationalMode() === 'staff_student';
    }
}
