<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
$coreFile = __DIR__ . '/core/ms.php';
$customFile = __DIR__ . '/custom/ms.php';

$core = is_file($coreFile) ? require $coreFile : [];
$custom = is_file($customFile) ? require $customFile : [];

return array_replace(
    is_array($core) ? $core : [],
    is_array($custom) ? $custom : []
);
