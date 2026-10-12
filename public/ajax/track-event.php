<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/track-event.php
// Simple analytics/tracking endpoint
declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// For now, just return success without logging
// Can be enhanced later to log events to database or external analytics service
echo json_encode([
    'success' => true,
    'message' => 'Event tracked'
]);
exit;
