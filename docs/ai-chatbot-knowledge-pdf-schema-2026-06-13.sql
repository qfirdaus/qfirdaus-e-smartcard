-- AI Chatbot Knowledge PDF schema for IQS-Framework
-- Date: 2026-06-13
-- Purpose:
--   Prepare schema for AI Chatbot Knowledge Manager PDF upload, extraction,
--   chunking, source/version metadata, and future semantic retrieval.
--
-- Manual run target:
--   Main IQS MySQL database.
--
-- Notes:
--   - PDF upload size must be enforced by application code using:
--       app_config('upload.manual_max_mb', 10)
--     The setting is maintained in System Settings > General > Limits.
--   - This migration does not upload files or extract PDF text.
--   - Runtime should continue to filter every source/chunk by language,
--     visibility, allowed groups, and status before sending context to an AI provider.
--   - This file avoids foreign keys to existing user/group tables because downstream
--     deployments may have slightly different framework table definitions.

-- ---------------------------------------------------------------------------
-- 1) Normalise legacy language values to the approved chatbot knowledge languages.
-- ---------------------------------------------------------------------------

UPDATE `tbl_ai_chat_knowledge`
SET `f_language` = 'ms'
WHERE `f_language` NOT IN ('ms', 'en');

-- ---------------------------------------------------------------------------
-- 2) Helper procedures for idempotent ALTER statements.
-- ---------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS `iqs_ai_chat_add_column_if_missing`;
DELIMITER $$
CREATE PROCEDURE `iqs_ai_chat_add_column_if_missing`(
  IN p_table_name VARCHAR(64),
  IN p_column_name VARCHAR(64),
  IN p_add_clause TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND COLUMN_NAME = p_column_name
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN ', p_add_clause);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$

DROP PROCEDURE IF EXISTS `iqs_ai_chat_add_index_if_missing`$$
CREATE PROCEDURE `iqs_ai_chat_add_index_if_missing`(
  IN p_table_name VARCHAR(64),
  IN p_index_name VARCHAR(64),
  IN p_add_clause TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND INDEX_NAME = p_index_name
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD ', p_add_clause);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------------
-- 3) Extend existing manual knowledge table with optional source/review metadata.
-- ---------------------------------------------------------------------------

CALL `iqs_ai_chat_add_column_if_missing`(
  'tbl_ai_chat_knowledge',
  'f_sourceType',
  "`f_sourceType` ENUM('manual_text','pdf','pdf_chunk') NOT NULL DEFAULT 'manual_text' AFTER `f_tags`"
);

CALL `iqs_ai_chat_add_column_if_missing`(
  'tbl_ai_chat_knowledge',
  'f_sourcePublicID',
  "`f_sourcePublicID` CHAR(36) NULL DEFAULT NULL AFTER `f_sourceType`"
);

CALL `iqs_ai_chat_add_column_if_missing`(
  'tbl_ai_chat_knowledge',
  'f_sourceTitle',
  "`f_sourceTitle` VARCHAR(255) NULL DEFAULT NULL AFTER `f_sourcePublicID`"
);

CALL `iqs_ai_chat_add_column_if_missing`(
  'tbl_ai_chat_knowledge',
  'f_version',
  "`f_version` VARCHAR(50) NULL DEFAULT NULL AFTER `f_sourceTitle`"
);

CALL `iqs_ai_chat_add_column_if_missing`(
  'tbl_ai_chat_knowledge',
  'f_reviewStatus',
  "`f_reviewStatus` ENUM('draft','reviewed','approved','needs_update') NOT NULL DEFAULT 'draft' AFTER `f_version`"
);

CALL `iqs_ai_chat_add_column_if_missing`(
  'tbl_ai_chat_knowledge',
  'f_effectiveDate',
  "`f_effectiveDate` DATE NULL DEFAULT NULL AFTER `f_reviewStatus`"
);

CALL `iqs_ai_chat_add_column_if_missing`(
  'tbl_ai_chat_knowledge',
  'f_reviewDueDate',
  "`f_reviewDueDate` DATE NULL DEFAULT NULL AFTER `f_effectiveDate`"
);

CALL `iqs_ai_chat_add_index_if_missing`(
  'tbl_ai_chat_knowledge',
  'idx_ai_chat_knowledge_source_status',
  "KEY `idx_ai_chat_knowledge_source_status` (`f_sourceType`, `f_status`, `f_language`, `f_priority`)"
);

CALL `iqs_ai_chat_add_index_if_missing`(
  'tbl_ai_chat_knowledge',
  'idx_ai_chat_knowledge_review_due',
  "KEY `idx_ai_chat_knowledge_review_due` (`f_reviewStatus`, `f_reviewDueDate`)"
);

-- ---------------------------------------------------------------------------
-- 4) Store uploaded PDF source metadata.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tbl_ai_chat_knowledge_source` (
  `f_aiChatKnowledgeSourceID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `f_publicID` CHAR(36) NOT NULL,
  `f_title` VARCHAR(255) NOT NULL,
  `f_description` TEXT NULL DEFAULT NULL,
  `f_sourceType` ENUM('pdf') NOT NULL DEFAULT 'pdf',
  `f_originalFilename` VARCHAR(255) NOT NULL,
  `f_storedFilename` VARCHAR(255) NOT NULL,
  `f_storedPath` VARCHAR(500) NOT NULL,
  `f_mimeType` VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
  `f_fileSizeBytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `f_fileHashSha256` CHAR(64) NOT NULL,
  `f_language` VARCHAR(10) NOT NULL DEFAULT 'ms',
  `f_visibility` ENUM('all_authenticated','selected_groups','super_admin_only') NOT NULL DEFAULT 'selected_groups',
  `f_allowedGroups` VARCHAR(500) NULL DEFAULT NULL,
  `f_tags` VARCHAR(500) NULL DEFAULT NULL,
  `f_version` VARCHAR(50) NULL DEFAULT NULL,
  `f_effectiveDate` DATE NULL DEFAULT NULL,
  `f_reviewDueDate` DATE NULL DEFAULT NULL,
  `f_reviewStatus` ENUM('draft','reviewed','approved','needs_update') NOT NULL DEFAULT 'draft',
  `f_extractionStatus` ENUM('pending','processing','processed','failed','skipped') NOT NULL DEFAULT 'pending',
  `f_extractionError` VARCHAR(1000) NULL DEFAULT NULL,
  `f_extractedCharCount` INT UNSIGNED NOT NULL DEFAULT 0,
  `f_chunkCount` INT UNSIGNED NOT NULL DEFAULT 0,
  `f_status` ENUM('draft','active','archived','deleted') NOT NULL DEFAULT 'draft',
  `f_priority` INT NOT NULL DEFAULT 100,
  `f_createdBy` VARCHAR(191) NULL DEFAULT NULL,
  `f_createdDt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `f_updatedBy` VARCHAR(191) NULL DEFAULT NULL,
  `f_updatedDt` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `f_processedBy` VARCHAR(191) NULL DEFAULT NULL,
  `f_processedDt` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`f_aiChatKnowledgeSourceID`),
  UNIQUE KEY `uk_ai_chat_knowledge_source_public_id` (`f_publicID`),
  KEY `idx_ai_chat_knowledge_source_status_lang` (`f_status`, `f_language`, `f_priority`),
  KEY `idx_ai_chat_knowledge_source_visibility_status` (`f_visibility`, `f_status`),
  KEY `idx_ai_chat_knowledge_source_extract_status` (`f_extractionStatus`, `f_updatedDt`),
  KEY `idx_ai_chat_knowledge_source_review_due` (`f_reviewStatus`, `f_reviewDueDate`),
  KEY `idx_ai_chat_knowledge_source_hash` (`f_fileHashSha256`),
  KEY `idx_ai_chat_knowledge_source_created` (`f_createdDt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5) Store extracted PDF chunks used by chatbot retrieval.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tbl_ai_chat_knowledge_chunk` (
  `f_aiChatKnowledgeChunkID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `f_publicID` CHAR(36) NOT NULL,
  `f_sourcePublicID` CHAR(36) NOT NULL,
  `f_chunkIndex` INT UNSIGNED NOT NULL DEFAULT 0,
  `f_chunkTitle` VARCHAR(255) NULL DEFAULT NULL,
  `f_chunkText` MEDIUMTEXT NOT NULL,
  `f_chunkHashSha256` CHAR(64) NOT NULL,
  `f_pageStart` INT UNSIGNED NULL DEFAULT NULL,
  `f_pageEnd` INT UNSIGNED NULL DEFAULT NULL,
  `f_language` VARCHAR(10) NOT NULL DEFAULT 'ms',
  `f_visibility` ENUM('all_authenticated','selected_groups','super_admin_only') NOT NULL DEFAULT 'selected_groups',
  `f_allowedGroups` VARCHAR(500) NULL DEFAULT NULL,
  `f_tags` VARCHAR(500) NULL DEFAULT NULL,
  `f_status` ENUM('draft','active','archived','deleted') NOT NULL DEFAULT 'draft',
  `f_priority` INT NOT NULL DEFAULT 100,
  `f_embeddingProvider` VARCHAR(64) NULL DEFAULT NULL,
  `f_embeddingModel` VARCHAR(191) NULL DEFAULT NULL,
  `f_embeddingStatus` ENUM('not_required','pending','ready','failed') NOT NULL DEFAULT 'not_required',
  `f_embeddingRef` VARCHAR(255) NULL DEFAULT NULL,
  `f_embeddingJson` JSON NULL DEFAULT NULL,
  `f_createdBy` VARCHAR(191) NULL DEFAULT NULL,
  `f_createdDt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `f_updatedBy` VARCHAR(191) NULL DEFAULT NULL,
  `f_updatedDt` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`f_aiChatKnowledgeChunkID`),
  UNIQUE KEY `uk_ai_chat_knowledge_chunk_public_id` (`f_publicID`),
  UNIQUE KEY `uk_ai_chat_knowledge_chunk_source_index` (`f_sourcePublicID`, `f_chunkIndex`),
  KEY `idx_ai_chat_knowledge_chunk_status_lang_priority` (`f_status`, `f_language`, `f_priority`),
  KEY `idx_ai_chat_knowledge_chunk_visibility_status` (`f_visibility`, `f_status`),
  KEY `idx_ai_chat_knowledge_chunk_source_status` (`f_sourcePublicID`, `f_status`, `f_chunkIndex`),
  KEY `idx_ai_chat_knowledge_chunk_embedding_status` (`f_embeddingStatus`, `f_updatedDt`),
  FULLTEXT KEY `ft_ai_chat_knowledge_chunk_text` (`f_chunkTitle`, `f_chunkText`, `f_tags`),
  CONSTRAINT `fk_ai_chat_knowledge_chunk_source`
    FOREIGN KEY (`f_sourcePublicID`)
    REFERENCES `tbl_ai_chat_knowledge_source` (`f_publicID`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 6) Cleanup helper procedures after migration.
-- ---------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS `iqs_ai_chat_add_column_if_missing`;
DROP PROCEDURE IF EXISTS `iqs_ai_chat_add_index_if_missing`;
