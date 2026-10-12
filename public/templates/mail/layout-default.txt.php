<?= (string)($title ?? 'Email Notification') . PHP_EOL ?>
<?= str_repeat('=', max(20, strlen((string)($title ?? 'Email Notification')))) . PHP_EOL . PHP_EOL ?>
<?= trim((string)($contentText ?? '')) . PHP_EOL . PHP_EOL ?>
--
<?= (string)($systemName ?? 'e-Base') . PHP_EOL ?>
<?php if (!empty($supportEmail)): ?>
Support: <?= (string)$supportEmail . PHP_EOL ?>
<?php endif; ?>
<?php if (!empty($footerNote)): ?>
<?= (string)$footerNote . PHP_EOL ?>
<?php endif; ?>
