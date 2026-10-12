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

/**
 * Base class for framework-aware exceptions.
 *
 * Keep framework exceptions separate from native RuntimeException so central
 * handlers can distinguish expected service/domain failures from coding bugs.
 */
class FrameworkException extends RuntimeException
{
}

