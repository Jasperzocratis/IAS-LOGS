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
ADD COLUMN IF NOT EXISTS `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`,
ADD COLUMN IF NOT EXISTS `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`;

-- Add columns to archive_documents table
ALTER TABLE `archive_documents` 
ADD COLUMN IF NOT EXISTS `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`,
ADD COLUMN IF NOT EXISTS `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`;

-- Add columns to deleted_documents table
ALTER TABLE `deleted_documents` 
ADD COLUMN IF NOT EXISTS `other_document_type` VARCHAR(150) DEFAULT NULL AFTER `document_type`,
ADD COLUMN IF NOT EXISTS `amount` DECIMAL(10,2) DEFAULT NULL AFTER `other_document_type`;

-- Note: IF NOT EXISTS is MySQL 5.7.4+ syntax
-- If you get an error, use the following syntax instead (remove IF NOT EXISTS):

/*
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
*/




