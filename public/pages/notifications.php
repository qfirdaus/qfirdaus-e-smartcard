<?php
/** IQS FRAMEWORK CORE FILE */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

if (!function_exists('h')) {
    function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
function nt(string $key, string $fallback): string
{
    $value = __($key);
    return ($value === $key || $value === null || $value === '') ? $fallback : (string)$value;
}

$lang = (string)($_SESSION['lang'] ?? 'ms');
$version = (string)($_ENV['APP_ASSET_VER'] ?? '1');
$PAGE_TITLE = nt('notification_page_title', 'Notifications');
$defaultHome = (string)app_config('site.default_home', 'pages/dashboard.php');
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!doctype html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php
    $NEED_DATERANGE = false; $NEED_VECTORMAP = false; $NEED_DATATABLES = false; $NEED_SELECT2 = false;
    include __DIR__ . '/../includes/head.php';
  ?>
  <meta name="referrer" content="same-origin">
  <link rel="stylesheet" href="<?= h(base_url('assets/css/pages/notifications.css')) ?>?v=<?= h($version) ?>">
</head>
<body data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
      data-menu-color="<?= h($_SESSION['theme.menu'] ?? $_SESSION['theme.sidebar'] ?? 'dark') ?>"
      data-layout="vertical" data-sidebar-size="default" class="loading">
<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="content-page">
    <div class="content">
      <div class="container-fluid notification-center">
        <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
          <h4 class="page-title"><i class="ri-notification-3-line me-1"></i><?= h($PAGE_TITLE) ?></h4>
          <ol class="breadcrumb m-0">
            <li class="breadcrumb-item"><a href="<?= h(base_path($defaultHome)) ?>"><?= h(nt('common_dashboard', 'Dashboard')) ?></a></li>
            <li class="breadcrumb-item active"><?= h($PAGE_TITLE) ?></li>
          </ol>
        </div>

        <section class="notification-hero" aria-labelledby="notificationHeroTitle">
          <div>
            <span class="notification-eyebrow"><i class="ri-shield-check-line"></i><?= h(nt('notification_hero_eyebrow', 'PERSONAL NOTIFICATION CENTRE')) ?></span>
            <h1 id="notificationHeroTitle"><?= h(nt('notification_hero_title', 'Stay informed and act with confidence')) ?></h1>
            <p><?= h(nt('notification_hero_text', 'Review system updates, announcements and tasks assigned to your account in one organised workspace.')) ?></p>
          </div>
          <div class="notification-hero-note"><i class="ri-lock-2-line"></i><div><strong><?= h(nt('notification_private_title', 'Private to your account')) ?></strong><span><?= h(nt('notification_private_text', 'Only notifications visible to your active identity and access group are shown.')) ?></span></div></div>
        </section>

        <section class="notification-workspace">
          <header class="notification-workspace-head">
            <div><span class="notification-eyebrow"><i class="ri-inbox-archive-line"></i><?= h(nt('notification_workspace_eyebrow', 'YOUR INBOX')) ?></span><h2><?= h(nt('notification_workspace_title', 'Notification workspace')) ?></h2><p><?= h(nt('notification_workspace_text', 'Filter, search and review messages without leaving this page.')) ?></p></div>
            <button type="button" class="btn notification-read-all" id="notificationPageReadAll"><i class="ri-check-double-line"></i><span><?= h(nt('topbar_notification_mark_all_read', 'Mark All Read')) ?></span></button>
          </header>

          <div class="notification-controls">
            <div class="notification-tabs" role="tablist" aria-label="<?= h(nt('notification_filter_label', 'Notification filters')) ?>">
              <?php foreach ([['all','notification_filter_all','All','ri-stack-line'],['unread','notification_filter_unread','Unread','ri-mail-unread-line'],['read','notification_filter_read','Read','ri-mail-check-line'],['action_required','notification_filter_action_required','Action Required','ri-task-line'],['overdue','notification_filter_overdue','Overdue','ri-alarm-warning-line']] as $filter): ?>
                <button type="button" class="notification-filter<?= $filter[0] === 'all' ? ' is-active' : '' ?>" data-filter="<?= h($filter[0]) ?>" role="tab" aria-selected="<?= $filter[0] === 'all' ? 'true' : 'false' ?>">
                  <i class="<?= h($filter[3]) ?>" aria-hidden="true"></i>
                  <span><?= h(nt($filter[1], $filter[2])) ?></span>
                  <span class="notification-filter-count" data-notification-filter-count="<?= h($filter[0]) ?>" aria-hidden="true">—</span>
                </button>
              <?php endforeach; ?>
            </div>
            <label class="notification-search"><span class="visually-hidden"><?= h(nt('notification_search_label', 'Search notifications')) ?></span><i class="ri-search-line"></i><input type="search" id="notificationSearch" maxlength="100" autocomplete="off" placeholder="<?= h(nt('notification_search_placeholder', 'Search title or message...')) ?>"></label>
          </div>

          <div class="notification-list-wrap" id="notificationPageListWrap" aria-live="polite" aria-busy="true">
            <div class="notification-local-loader" id="notificationLocalLoader"><span class="notification-spinner" aria-hidden="true"></span><span><?= h(nt('notification_loading_local', 'Loading notifications...')) ?></span></div>
            <div class="notification-list" id="notificationPageList"></div>
          </div>
          <footer class="notification-pagination" id="notificationPagination" hidden>
            <p id="notificationPageSummary"></p>
            <nav aria-label="<?= h(nt('notification_pagination_label', 'Notification pages')) ?>"><button type="button" id="notificationPrevious"><i class="ri-arrow-left-s-line"></i><?= h(nt('notification_previous', 'Previous')) ?></button><span id="notificationPageNumber"></span><button type="button" id="notificationNext"><?= h(nt('notification_next', 'Next')) ?><i class="ri-arrow-right-s-line"></i></button></nav>
          </footer>
        </section>
      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/script.php'; ?>
<script>
window.notificationPageConfig = <?= json_encode([
  'endpoints' => ['list'=>base_url('ajax/notification-list.php'),'read'=>base_url('ajax/notification-read.php'),'readAll'=>base_url('ajax/notification-read-all.php')],
  'labels' => [
    'loading'=>nt('notification_loading_local','Loading notifications...'),'emptyTitle'=>nt('notification_empty_title','Your inbox is clear'),'emptyText'=>nt('notification_empty_text','No notifications match the current filter or search.'),'failedTitle'=>nt('notification_error_title','Notifications could not be loaded'),'failed'=>nt('topbar_notification_load_failed','Unable to load notifications.'),'retry'=>nt('notification_retry','Try again'),'action'=>nt('notification_action_required','Action required'),'overdue'=>nt('notification_action_overdue','Overdue'),'open'=>nt('notification_open','Open'),'markRead'=>nt('notification_mark_read','Mark as read'),'readSuccess'=>nt('topbar_notification_read_success','Notification marked as read.'),'readFailed'=>nt('topbar_notification_read_failed','Unable to mark notification as read.'),'readAllSuccess'=>nt('topbar_notification_read_all_success','All notifications marked as read.'),'readAllFailed'=>nt('topbar_notification_read_all_failed','Unable to mark all notifications as read.'),'readAllBusy'=>nt('notification_read_all_busy','Updating...'),'page'=>nt('notification_page_label','Page {page} of {pages}'),'results'=>nt('notification_results_label','Showing {start}–{end} of {total} notifications')
  ]
], $jsonFlags) ?>;
</script>
<script src="<?= h(base_url('assets/js/pages/notifications.js')) ?>?v=<?= h($version) ?>"></script>
</body>
</html>
