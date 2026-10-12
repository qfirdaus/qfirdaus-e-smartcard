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

require_once __DIR__ . '/FrameworkException.php';

/**
 * Application-level failure that should keep existing internal-error handling.
 */
class ApplicationException extends FrameworkException
{
}

