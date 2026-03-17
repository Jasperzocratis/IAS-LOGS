-- ============================================
-- IAS-LOGS: Audit Document System
-- Database Setup Script
-- ============================================
-- This script creates the database and tables
-- for the Audit Office Document Log System
-- ============================================

-- Create database
CREATE DATABASE IF NOT EXISTS `audit_log_system` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_general_ci;

-- Use the database
USE `audit_log_system`;

-- ============================================
-- Table: document_logs
-- Stores all incoming and outgoing documents
-- ============================================
CREATE TABLE IF NOT EXISTS `document_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `date_received` DATE NOT NULL,
  `office` VARCHAR(150) NOT NULL,
  `particulars` TEXT NOT NULL,
  `remarks` TEXT DEFAULT NULL,
  `time_in` TIME NOT NULL,
  `date_out` DATE DEFAULT NULL,
  `time_out` TIME DEFAULT NULL,
  `document_type` VARCHAR(150) DEFAULT NULL,
  `document_type_id` INT(11) DEFAULT NULL,
  `other_document_type` VARCHAR(150) DEFAULT NULL,
  `amount` DECIMAL(10,2) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_date_received` (`date_received`),
  INDEX `idx_document_type` (`document_type`),
  INDEX `idx_document_type_id` (`document_type_id`),
  INDEX `idx_office` (`office`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Table: other_documents
-- Stores "Other Document" entries only (when user selects "Other documents" and specifies a type).
-- This is the 3rd data table; the app shows it as "Other Document" on the Document Logbook.
-- ============================================
CREATE TABLE IF NOT EXISTS `other_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `date_received` DATE NOT NULL,
  `office` VARCHAR(150) NOT NULL,
  `particulars` TEXT NOT NULL,
  `remarks` TEXT DEFAULT NULL,
  `time_in` TIME NOT NULL,
  `date_out` DATE DEFAULT NULL,
  `time_out` TIME DEFAULT NULL,
  `document_type` VARCHAR(150) NOT NULL DEFAULT 'Other documents',
  `other_document_type` VARCHAR(150) NOT NULL,
  `amount` DECIMAL(10,2) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_date_received` (`date_received`),
  INDEX `idx_office` (`office`),
  INDEX `idx_other_document_type` (`other_document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Table: document_types
-- Master list of allowed document types for the dropdown (images 2–3).
-- Add/Edit pages should read from this table to ensure consistent saved values.
-- ============================================
CREATE TABLE IF NOT EXISTS `document_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_document_types_name` (`name`),
  INDEX `idx_document_types_active` (`is_active`),
  INDEX `idx_document_types_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed default document types (safe to run multiple times)
INSERT IGNORE INTO `document_types` (`name`, `sort_order`, `is_active`) VALUES
('Purchase Order', 10, 1),
('Purchase Request', 20, 1),
('Feedback Form Monitored', 30, 1),
('Notice of Award', 40, 1),
('Contract Of Service', 50, 1),
('Business Permit', 60, 1),
('Memorandum of Agreement', 70, 1),
('Memorandum Order', 80, 1),
('Administrative Order', 90, 1),
('Executive Order', 100, 1),
('Minutes and Resolution', 110, 1),
('Municipal Ordinance', 120, 1),
('Allotment Release Order', 130, 1),
('Plans and Program of Work', 140, 1),
('Supplemental Budget', 150, 1),
('Annual Investment Plan, MDRRMF Plan and Other Plans', 160, 1),
('Other documents', 999, 1);

-- ============================================
-- Table: users
-- Stores user accounts for login authentication
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `role` VARCHAR(50) DEFAULT 'Staff',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Table: archive_documents
-- Stores archived/deleted documents
-- ============================================
CREATE TABLE IF NOT EXISTS `archive_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `original_id` INT(11) NOT NULL,
  `date_received` DATE NOT NULL,
  `office` VARCHAR(150) NOT NULL,
  `particulars` TEXT NOT NULL,
  `remarks` TEXT DEFAULT NULL,
  `time_in` TIME NOT NULL,
  `date_out` DATE DEFAULT NULL,
  `time_out` TIME DEFAULT NULL,
  `document_type` VARCHAR(150) NOT NULL,
  `other_document_type` VARCHAR(150) DEFAULT NULL,
  `amount` DECIMAL(10,2) DEFAULT NULL,
  `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_date_received` (`date_received`),
  INDEX `idx_document_type` (`document_type`),
  INDEX `idx_office` (`office`),
  INDEX `idx_archived_at` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Table: deleted_documents
-- Stores permanently deleted documents
-- ============================================
CREATE TABLE IF NOT EXISTS `deleted_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `original_id` INT(11) NOT NULL,
  `archive_id` INT(11) DEFAULT NULL,
  `date_received` DATE NOT NULL,
  `office` VARCHAR(150) NOT NULL,
  `particulars` TEXT NOT NULL,
  `remarks` TEXT DEFAULT NULL,
  `time_in` TIME NOT NULL,
  `date_out` DATE DEFAULT NULL,
  `time_out` TIME DEFAULT NULL,
  `document_type` VARCHAR(150) NOT NULL,
  `other_document_type` VARCHAR(150) DEFAULT NULL,
  `amount` DECIMAL(10,2) DEFAULT NULL,
  `deleted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  `archived_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_date_received` (`date_received`),
  INDEX `idx_document_type` (`document_type`),
  INDEX `idx_office` (`office`),
  INDEX `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Sample Data (Optional - for testing)
-- ============================================
-- To create the default admin user, run setup_admin.php in your browser
-- Default credentials:
-- Username: admin
-- Password: admin123
-- 
-- Or manually insert a user with a properly hashed password:
-- INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`) 
-- VALUES ('admin', '[hashed_password]', 'Administrator', 'admin@audit.gov', 'Administrator');

-- ============================================
-- Optional: Migrations for existing databases
-- (Merged from migration_add_columns.sql and migration_add_columns_simple.sql)
-- Run this section only if your database was created before these columns/table existed.
-- Skip any statement if the column or table already exists.
-- ============================================

USE `audit_log_system`;

-- ============================================
-- AUTO-MIGRATION (safe to run repeatedly)
-- This block applies missing columns/tables automatically on older DBs.
-- ============================================

-- document_logs.other_document_type
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_logs' AND COLUMN_NAME = 'other_document_type');
SET @sql := IF(@c = 0,
  'ALTER TABLE `document_logs` ADD COLUMN `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- document_logs.amount
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_logs' AND COLUMN_NAME = 'amount');
SET @sql := IF(@c = 0,
  'ALTER TABLE `document_logs` ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- document_logs.document_type_id (links to document_types.id for display from master list)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_logs' AND COLUMN_NAME = 'document_type_id');
SET @sql := IF(@c = 0,
  'ALTER TABLE `document_logs` ADD COLUMN `document_type_id` INT(11) DEFAULT NULL AFTER `document_type`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- archive_documents.other_document_type
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'archive_documents' AND COLUMN_NAME = 'other_document_type');
SET @sql := IF(@c = 0,
  'ALTER TABLE `archive_documents` ADD COLUMN `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- archive_documents.amount
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'archive_documents' AND COLUMN_NAME = 'amount');
SET @sql := IF(@c = 0,
  'ALTER TABLE `archive_documents` ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deleted_documents.other_document_type
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deleted_documents' AND COLUMN_NAME = 'other_document_type');
SET @sql := IF(@c = 0,
  'ALTER TABLE `deleted_documents` ADD COLUMN `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deleted_documents.amount
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deleted_documents' AND COLUMN_NAME = 'amount');
SET @sql := IF(@c = 0,
  'ALTER TABLE `deleted_documents` ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- document_logs.date_out
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_logs' AND COLUMN_NAME = 'date_out');
SET @sql := IF(@c = 0,
  'ALTER TABLE `document_logs` ADD COLUMN `date_out` DATE DEFAULT NULL AFTER `time_in`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- other_documents.date_out
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'other_documents' AND COLUMN_NAME = 'date_out');
SET @sql := IF(@c = 0,
  'ALTER TABLE `other_documents` ADD COLUMN `date_out` DATE DEFAULT NULL AFTER `time_in`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- archive_documents.date_out
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'archive_documents' AND COLUMN_NAME = 'date_out');
SET @sql := IF(@c = 0,
  'ALTER TABLE `archive_documents` ADD COLUMN `date_out` DATE DEFAULT NULL AFTER `time_in`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deleted_documents.date_out
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deleted_documents' AND COLUMN_NAME = 'date_out');
SET @sql := IF(@c = 0,
  'ALTER TABLE `deleted_documents` ADD COLUMN `date_out` DATE DEFAULT NULL AFTER `time_in`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure other_documents table exists (3rd data table)
CREATE TABLE IF NOT EXISTS `other_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `date_received` DATE NOT NULL,
  `office` VARCHAR(150) NOT NULL,
  `particulars` TEXT NOT NULL,
  `remarks` TEXT DEFAULT NULL,
  `time_in` TIME NOT NULL,
  `time_out` TIME DEFAULT NULL,
  `document_type` VARCHAR(150) NOT NULL DEFAULT 'Other documents',
  `other_document_type` VARCHAR(150) NOT NULL,
  `amount` DECIMAL(10,2) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_date_received` (`date_received`),
  INDEX `idx_office` (`office`),
  INDEX `idx_other_document_type` (`other_document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ensure document_types master table exists and is seeded
CREATE TABLE IF NOT EXISTS `document_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_document_types_name` (`name`),
  INDEX `idx_document_types_active` (`is_active`),
  INDEX `idx_document_types_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `document_types` (`name`, `sort_order`, `is_active`) VALUES
('Purchase Order', 10, 1),
('Purchase Request', 20, 1),
('Feedback Form Monitored', 30, 1),
('Notice of Award', 40, 1),
('Contract Of Service', 50, 1),
('Business Permit', 60, 1),
('Memorandum of Agreement', 70, 1),
('Memorandum Order', 80, 1),
('Administrative Order', 90, 1),
('Executive Order', 100, 1),
('Minutes and Resolution', 110, 1),
('Municipal Ordinance', 120, 1),
('Allotment Release Order', 130, 1),
('Plans and Program of Work', 140, 1),
('Supplemental Budget', 150, 1),
('Annual Investment Plan, MDRRMF Plan and Other Plans', 160, 1),
('Other documents', 999, 1);

-- Add other_document_type and amount to document_logs (if missing)
-- ALTER TABLE `document_logs`
--   ADD COLUMN `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`,
--   ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`;

-- Add other_document_type and amount to archive_documents (if missing)
-- ALTER TABLE `archive_documents`
--   ADD COLUMN `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`,
--   ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`;

-- Add other_document_type and amount to deleted_documents (if missing)
-- ALTER TABLE `deleted_documents`
--   ADD COLUMN `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`,
--   ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`;

-- Create other_documents table (3rd data table - "Other Document" only) if missing
-- CREATE TABLE IF NOT EXISTS `other_documents` (
--   `id` INT(11) NOT NULL AUTO_INCREMENT,
--   `date_received` DATE NOT NULL,
--   `office` VARCHAR(150) NOT NULL,
--   `particulars` TEXT NOT NULL,
--   `remarks` TEXT DEFAULT NULL,
--   `time_in` TIME NOT NULL,
--   `time_out` TIME DEFAULT NULL,
--   `document_type` VARCHAR(150) NOT NULL DEFAULT 'Other documents',
--   `other_document_type` VARCHAR(150) NOT NULL,
--   `amount` DECIMAL(10,2) DEFAULT NULL,
--   `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--   `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--   PRIMARY KEY (`id`),
--   INDEX `idx_date_received` (`date_received`),
--   INDEX `idx_office` (`office`),
--   INDEX `idx_other_document_type` (`other_document_type`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Note: MySQL 8.0.12+ supports ADD COLUMN IF NOT EXISTS. If you use it, you can run
-- the ALTER statements without commenting (one column per ALTER for IF NOT EXISTS).

-- Uncomment the following block to insert sample document logs:
/*
-- Sample Document Logs
INSERT INTO `document_logs` (`date_received`, `office`, `particulars`, `remarks`, `time_in`, `document_type`) VALUES
('2024-01-15', 'Procurement Office', 'Contract of Service for January 2024', 'Urgent - Need approval', '09:30:00', 'Contract Of Service'),
('2024-01-16', 'Finance Department', 'Notice of Award for Equipment', 'Pending review', '10:15:00', 'Notice of Award'),
('2024-01-17', 'IT Department', 'Memorandum of Agreement for IT Services', 'Approved', '08:45:00', 'Memorandum of Agreement');
*/

