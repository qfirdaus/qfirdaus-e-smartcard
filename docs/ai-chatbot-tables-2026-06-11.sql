-- AI Chatbot persistence tables for IQS-Framework
-- Date: 2026-06-11
-- Purpose:
--   Optional storage for chatbot session metadata, message rows, and provider usage.
--   The application should still keep AI_CHATBOT_STORE_CONVERSATIONS=false by default
--   unless conversation persistence is explicitly approved.
--
-- Manual run target:
--   Main IQS MySQL database.
--
-- Safety:
--   Uses CREATE TABLE IF NOT EXISTS.
--   Does not alter existing framework tables.
--   Does not create foreign keys to existing framework user/access tables,
--   to avoid deployment failures across downstream projects with slightly
--   different user table definitions.
--   Foreign keys are used only between the new chatbot tables.

CREATE TABLE IF NOT EXISTS `tbl_ai_chat_session` (
  `f_aiChatSessionID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `f_sessionPublicID` CHAR(36) NOT NULL,
  `f_userID` BIGINT NULL DEFAULT NULL,
  `f_loginID` VARCHAR(191) NULL DEFAULT NULL,
  `f_stafID` VARCHAR(64) NULL DEFAULT NULL,
  `f_groupID` INT NULL DEFAULT NULL,
  `f_groupKod` VARCHAR(64) NULL DEFAULT NULL,
  `f_provider` VARCHAR(64) NOT NULL,
  `f_model` VARCHAR(191) NOT NULL,
  `f_accessMode` VARCHAR(64) NOT NULL DEFAULT 'super_admin_only',
  `f_title` VARCHAR(255) NULL DEFAULT NULL,
  `f_status` ENUM('active','closed','expired','deleted') NOT NULL DEFAULT 'active',
  `f_startedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `f_lastMessageAt` DATETIME NULL DEFAULT NULL,
  `f_closedAt` DATETIME NULL DEFAULT NULL,
  `f_createdBy` VARCHAR(191) NULL DEFAULT NULL,
  `f_createdDt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `f_updatedBy` VARCHAR(191) NULL DEFAULT NULL,
  `f_updatedDt` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`f_aiChatSessionID`),
  UNIQUE KEY `uk_ai_chat_session_public_id` (`f_sessionPublicID`),
  KEY `idx_ai_chat_session_login_started` (`f_loginID`, `f_startedAt`),
  KEY `idx_ai_chat_session_user_started` (`f_userID`, `f_startedAt`),
  KEY `idx_ai_chat_session_provider_model` (`f_provider`, `f_model`),
  KEY `idx_ai_chat_session_status_last` (`f_status`, `f_lastMessageAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_ai_chat_message` (
  `f_aiChatMessageID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `f_aiChatSessionID` BIGINT UNSIGNED NOT NULL,
  `f_messagePublicID` CHAR(36) NOT NULL,
  `f_role` ENUM('system','user','assistant','tool','error') NOT NULL,
  `f_provider` VARCHAR(64) NULL DEFAULT NULL,
  `f_model` VARCHAR(191) NULL DEFAULT NULL,
  `f_contentStored` TINYINT(1) NOT NULL DEFAULT 0,
  `f_content` MEDIUMTEXT NULL DEFAULT NULL,
  `f_contentSha256` CHAR(64) NULL DEFAULT NULL,
  `f_contentLength` INT UNSIGNED NOT NULL DEFAULT 0,
  `f_sensitiveRedacted` TINYINT(1) NOT NULL DEFAULT 0,
  `f_latencyMs` INT UNSIGNED NULL DEFAULT NULL,
  `f_status` ENUM('queued','sent','completed','failed','blocked') NOT NULL DEFAULT 'completed',
  `f_errorCode` VARCHAR(100) NULL DEFAULT NULL,
  `f_errorMessage` VARCHAR(500) NULL DEFAULT NULL,
  `f_metaJson` JSON NULL DEFAULT NULL,
  `f_createdBy` VARCHAR(191) NULL DEFAULT NULL,
  `f_createdDt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`f_aiChatMessageID`),
  UNIQUE KEY `uk_ai_chat_message_public_id` (`f_messagePublicID`),
  KEY `idx_ai_chat_message_session_created` (`f_aiChatSessionID`, `f_createdDt`),
  KEY `idx_ai_chat_message_role_created` (`f_role`, `f_createdDt`),
  KEY `idx_ai_chat_message_status_created` (`f_status`, `f_createdDt`),
  KEY `idx_ai_chat_message_provider_model` (`f_provider`, `f_model`),
  CONSTRAINT `fk_ai_chat_message_session`
    FOREIGN KEY (`f_aiChatSessionID`)
    REFERENCES `tbl_ai_chat_session` (`f_aiChatSessionID`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_ai_chat_usage` (
  `f_aiChatUsageID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `f_aiChatSessionID` BIGINT UNSIGNED NULL DEFAULT NULL,
  `f_aiChatMessageID` BIGINT UNSIGNED NULL DEFAULT NULL,
  `f_userID` BIGINT NULL DEFAULT NULL,
  `f_loginID` VARCHAR(191) NULL DEFAULT NULL,
  `f_provider` VARCHAR(64) NOT NULL,
  `f_model` VARCHAR(191) NOT NULL,
  `f_requestType` VARCHAR(64) NOT NULL DEFAULT 'chat',
  `f_promptTokens` INT UNSIGNED NULL DEFAULT NULL,
  `f_completionTokens` INT UNSIGNED NULL DEFAULT NULL,
  `f_totalTokens` INT UNSIGNED NULL DEFAULT NULL,
  `f_estimatedCost` DECIMAL(12,6) NULL DEFAULT NULL,
  `f_currency` CHAR(3) NULL DEFAULT NULL,
  `f_latencyMs` INT UNSIGNED NULL DEFAULT NULL,
  `f_httpStatus` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `f_outcome` ENUM('success','failed','rate_limited','blocked','timeout') NOT NULL DEFAULT 'success',
  `f_errorCode` VARCHAR(100) NULL DEFAULT NULL,
  `f_errorMessage` VARCHAR(500) NULL DEFAULT NULL,
  `f_requestMetaJson` JSON NULL DEFAULT NULL,
  `f_createdDt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`f_aiChatUsageID`),
  KEY `idx_ai_chat_usage_created` (`f_createdDt`),
  KEY `idx_ai_chat_usage_login_created` (`f_loginID`, `f_createdDt`),
  KEY `idx_ai_chat_usage_provider_model_created` (`f_provider`, `f_model`, `f_createdDt`),
  KEY `idx_ai_chat_usage_outcome_created` (`f_outcome`, `f_createdDt`),
  KEY `idx_ai_chat_usage_session_created` (`f_aiChatSessionID`, `f_createdDt`),
  CONSTRAINT `fk_ai_chat_usage_session`
    FOREIGN KEY (`f_aiChatSessionID`)
    REFERENCES `tbl_ai_chat_session` (`f_aiChatSessionID`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_ai_chat_usage_message`
    FOREIGN KEY (`f_aiChatMessageID`)
    REFERENCES `tbl_ai_chat_message` (`f_aiChatMessageID`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
