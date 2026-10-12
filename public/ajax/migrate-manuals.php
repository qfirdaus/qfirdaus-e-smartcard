<?php
/**
 * Legacy web migration endpoint intentionally disabled.
 * Database migrations must run through the controlled deployment workflow.
 */
declare(strict_types=1);

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
exit('Not Found');
