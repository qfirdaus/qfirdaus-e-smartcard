<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */function now_datetime() {
    date_default_timezone_set('Asia/Kuala_Lumpur');
    return date('Y-m-d H:i:s');
}

function current_year() {
    date_default_timezone_set('Asia/Kuala_Lumpur');
    return date('Y');
}
