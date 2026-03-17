-- ============================================
-- MERGED INTO audit_log_system.sql (see "Optional: Migrations for existing databases" section).
-- This file is kept for reference only; use audit_log_system.sql for new or existing DBs.
-- ============================================
-- Migration Script: Add other_document_type and amount columns
-- IAS-LOGS: Audit Document System
-- ============================================

USE `audit_log_system`;

-- Add columns to document_logs table
ALTER TABLE `document_logs` 
ADD COLUMN `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`,
ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`;

-- Add columns to archive_documents table
ALTER TABLE `archive_documents` 
ADD COLUMN `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`,
ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`;

-- Add columns to deleted_documents table
ALTER TABLE `deleted_documents` 
ADD COLUMN `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`,
ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`;

-- ============================================
-- Create other_documents table (3rd data table - "Other Document" only)
-- Run this if the table does not exist (e.g. after pulling latest SQL)
-- ============================================
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

-- ============================================
-- Create document_types master table (dropdown source)
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

