<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// classes/SystemConfigConstants.php
declare(strict_types=1);

/**
 * Constants untuk System Configuration
 */
class SystemConfigConstants {
  // Supported Languages (trimmed to only Malay and English)
  const SUPPORTED_LANGUAGES = ['ms', 'en'];
  
  // Database Types
  const ALLOWED_DB_TYPES = ['ehrmdb', 'ehrmdb_dev'];
  const ALLOWED_MAIN_DB_ENVIRONMENTS = ['production', 'development'];
  const ALLOWED_SYBASE_ENVIRONMENTS = ['production', 'development'];
  const ALLOWED_SYBASE_OPERATIONAL_MODES = ['staff_only', 'staff_student'];
  const DEFAULT_MAIN_DB_ENVIRONMENT = 'production';
  const DEFAULT_SYBASE_ENVIRONMENT = 'production';
  const DEFAULT_SYBASE_OPERATIONAL_MODE = 'staff_only';
  const ALLOWED_DATABASE_FAMILIES = ['mysql', 'sybase', 'mssql'];
  const ALLOWED_DATABASE_CONNECTION_CATEGORIES = ['main', 'additional'];
  const ALLOWED_DATABASE_DRIVER_MODES = ['auto', 'dsn', 'dblib', 'odbc', 'sqlsrv'];
  const RESERVED_DATABASE_CODES = ['mysql', 'mysql_main', 'sybase_staff', 'sybase_student', 'ehrmdb', 'ehrmdb_dev'];
  
  // Theme Settings
  const ALLOWED_THEME_MODES = ['light', 'dark'];
  const ALLOWED_THEME_COLORS = ['light', 'dark', 'brand', 'emerald', 'navy', 'sunset', 'mist', 'strawberry', 'matcha', 'custom'];
  const DEFAULT_THEME_TOPBAR_CUSTOM_SEED = '#4254BA';
  const DEFAULT_THEME_SIDEBAR_CUSTOM_SEED = '#1E3A5F';
  
  // Email Settings
  const ALLOWED_MAIL_DRIVERS = ['smtp', 'mail', 'sendmail'];
  const ALLOWED_MAIL_ENCRYPTION = ['tls', 'ssl'];
  const ALLOWED_EMAIL_TEMPLATE_STATUSES = ['ACTIVE', 'DRAFT', 'ARCHIVED'];
  const ALLOWED_EMAIL_TEMPLATE_ROLES = ['staff', 'student', 'public', 'admin'];
  const ALLOWED_EMAIL_TEMPLATE_CATEGORIES = ['welcome', 'notification', 'reminder', 'approval', 'rejection', 'security', 'custom'];
  const MAX_STRING_LENGTH = 255;
  const MIN_PORT = 1;
  const MAX_PORT = 65535;
  
  // Cache TTL (in seconds)
  const CACHE_TTL_DB_CONFIG = 60;      // 1 minit (critical - changes affect all users)
  const CACHE_TTL_EMAIL = 300;          // 5 minit
  const CACHE_TTL_LANGUAGE = 600;       // 10 minit (rarely change)
  const CACHE_TTL_MYSQL_INFO = 1800;    // 30 minit (rarely change)
  const CACHE_TTL_DB_TEST = 30;         // 30 saat (database test results)
  
  // Database Test Settings
  const DB_TEST_CONNECTION_TIMEOUT = 5;        // seconds
  const DB_TEST_CACHE_CLEANUP_MAX_AGE = 3600; // 1 hour (seconds)
  const DB_TEST_CACHE_MAX_SIZE = 1048576;     // 1MB (bytes)
  const DB_TEST_RESPONSE_TIME_FAST = 1000;    // milliseconds
  const DB_TEST_RESPONSE_TIME_SLOW = 2000;   // milliseconds
  
  // Default Values
  const DEFAULT_LANGUAGE = 'ms';
  const DEFAULT_THEME_LAYOUT = 'light';
  const DEFAULT_THEME_TOPBAR = 'light';
  const DEFAULT_THEME_SIDEBAR = 'light';
  
  // Sidebar Cache TTL (in seconds)
  const CACHE_TTL_SIDEBAR = 600; // 10 minit (modules/menus rarely change)
  
  // Allowed Icon Classes (RemixIcon - common icons used in sidebar)
  const ALLOWED_SIDEBAR_ICONS = [
    'ri-folder-fill',
    'ri-folder-line',
    'ri-dashboard-fill',
    'ri-dashboard-line',
    'ri-apps-2-fill',
    'ri-apps-2-line',
    'ri-layout-grid-fill',
    'ri-layout-grid-line',
    'ri-function-fill',
    'ri-function-line',
    'ri-user-fill',
    'ri-user-line',
    'ri-user-settings-fill',
    'ri-user-settings-line',
    'ri-user-star-fill',
    'ri-user-star-line',
    'ri-settings-fill',
    'ri-settings-line',
    'ri-tools-fill',
    'ri-tools-line',
    'ri-sliders-fill',
    'ri-sliders-line',
    'ri-file-list-fill',
    'ri-file-list-line',
    'ri-file-settings-fill',
    'ri-file-settings-line',
    'ri-file-copy-fill',
    'ri-file-copy-line',
    'ri-file-paper-2-fill',
    'ri-file-paper-2-line',
    'ri-article-fill',
    'ri-article-line',
    'ri-database-fill',
    'ri-database-line',
    'ri-server-fill',
    'ri-server-line',
    'ri-hard-drive-fill',
    'ri-hard-drive-line',
    'ri-mail-fill',
    'ri-mail-line',
    'ri-inbox-fill',
    'ri-inbox-line',
    'ri-send-plane-fill',
    'ri-send-plane-line',
    'ri-notification-fill',
    'ri-notification-line',
    'ri-notification-badge-fill',
    'ri-notification-badge-line',
    'ri-shield-fill',
    'ri-shield-line',
    'ri-shield-user-fill',
    'ri-shield-user-line',
    'ri-lock-fill',
    'ri-lock-line',
    'ri-lock-password-fill',
    'ri-lock-password-line',
    'ri-key-fill',
    'ri-key-line',
    'ri-group-fill',
    'ri-group-line',
    'ri-team-fill',
    'ri-team-line',
    'ri-admin-fill',
    'ri-admin-line',
    'ri-calendar-fill',
    'ri-calendar-line',
    'ri-calendar-check-fill',
    'ri-calendar-check-line',
    'ri-time-fill',
    'ri-time-line',
    'ri-chart-fill',
    'ri-chart-line',
    'ri-bar-chart-line',
    'ri-bar-chart-fill',
    'ri-bar-chart-box-fill',
    'ri-bar-chart-box-line',
    'ri-line-chart-line',
    'ri-line-chart-fill',
    'ri-pie-chart-line',
    'ri-pie-chart-fill',
    'ri-book-fill',
    'ri-book-line',
    'ri-book-open-fill',
    'ri-book-open-line',
    'ri-book-2-fill',
    'ri-book-2-line',
    'ri-list-check',
    'ri-list-check-2',
    'ri-task-fill',
    'ri-task-line',
    'ri-todo-fill',
    'ri-todo-line',
    'ri-file-text-fill',
    'ri-file-text-line',
    'ri-questionnaire-fill',
    'ri-questionnaire-line',
    'ri-survey-fill',
    'ri-survey-line',
    'ri-logout-box-r-fill',
    'ri-logout-box-r-line',
    'ri-home-fill',
    'ri-home-line',
    'ri-home-office-fill',
    'ri-home-office-line',
    'ri-arrow-right-s-fill',
    'ri-arrow-right-s-line',
    'ri-links-fill',
    'ri-links-line',
    'ri-link-unlink-m',
    'ri-global-fill',
    'ri-global-line',
    'ri-earth-fill',
    'ri-earth-line',
    'ri-building-fill',
    'ri-building-line',
    'ri-building-2-fill',
    'ri-building-2-line',
    'ri-briefcase-fill',
    'ri-briefcase-line',
    'ri-award-fill',
    'ri-award-line',
    'ri-medal-fill',
    'ri-medal-line',
    'ri-wallet-3-fill',
    'ri-wallet-3-line',
    'ri-bank-card-fill',
    'ri-bank-card-line',
    'ri-price-tag-3-fill',
    'ri-price-tag-3-line',
    'ri-folder-chart-fill',
    'ri-folder-chart-line',
    'ri-folder-user-fill',
    'ri-folder-user-line',
  ];
  
  // Audit Event Types
  const AUDIT_EVENT_GENERAL_UPDATE = 'UPDATE';
  const AUDIT_EVENT_EMAIL_UPDATE = 'UPDATE';
  const AUDIT_EVENT_DB_UPDATE = 'UPDATE';
  const AUDIT_EVENT_THEME_UPDATE = 'UPDATE';
  const AUDIT_EVENT_LANGUAGE_UPDATE = 'UPDATE';
  const AUDIT_EVENT_AI_CHATBOT_UPDATE = 'UPDATE';
  
  // Target Types for Audit
  const AUDIT_TARGET_GENERAL = 'system_config_general';
  const AUDIT_TARGET_EMAIL = 'system_config_email';
  const AUDIT_TARGET_DB = 'system_config_database';
  const AUDIT_TARGET_THEME = 'system_config_theme';
  const AUDIT_TARGET_LANGUAGE = 'system_config_language';
  const AUDIT_TARGET_AI_CHATBOT = 'system_config_ai_chatbot';

  // Config Groups
  const CONFIG_GROUP_APP_SETTINGS = 'app_settings';
  const CONFIG_GROUP_SYSTEM = 'system';
  const CONFIG_GROUP_AI_CHATBOT = 'ai_chatbot';
}
