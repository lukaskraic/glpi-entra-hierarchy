-- =====================================================
-- GLPI Entra Hierarchy Plugin - Database Schema v1.0.0
-- =====================================================
-- This is the original database schema for version 1.0.0
-- For migration from 1.0.0 to 1.1.0, use migration-1.1.0.sql
-- =====================================================

-- =====================================================
-- Configuration Table
-- =====================================================
-- Stores plugin configuration (credentials, sync settings)
-- =====================================================

CREATE TABLE IF NOT EXISTS `glpi_plugin_entrahierarchy_configs` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `client_id` varchar(255) DEFAULT NULL,
    `client_secret` varchar(255) DEFAULT NULL,
    `tenant_id` varchar(255) DEFAULT NULL,
    `sync_enabled` tinyint NOT NULL DEFAULT '0',
    `sync_interval` int NOT NULL DEFAULT '1800',
    `last_sync` datetime DEFAULT NULL,
    `date_creation` datetime DEFAULT NULL,
    `date_mod` datetime DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Sync Log Table
-- =====================================================
-- Tracks all synchronization operations and statistics
-- =====================================================

CREATE TABLE IF NOT EXISTS `glpi_plugin_entrahierarchy_synclogs` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `date` datetime NOT NULL,
    `status` varchar(50) NOT NULL,
    `message` text,
    `users_synced` int NOT NULL DEFAULT '0',
    `users_created` int NOT NULL DEFAULT '0',
    `users_updated` int NOT NULL DEFAULT '0',
    `users_failed` int NOT NULL DEFAULT '0',
    `duration` int DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- User Mapping Table
-- =====================================================
-- Maps GLPI users to Microsoft Entra ID users
-- Tracks manual supervisor override flag
-- =====================================================

CREATE TABLE IF NOT EXISTS `glpi_plugin_entrahierarchy_usermaps` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `users_id` int unsigned NOT NULL,
    `entra_id` varchar(255) NOT NULL,
    `entra_upn` varchar(255) DEFAULT NULL,
    `entra_email` varchar(255) DEFAULT NULL,
    `manual_supervisor` tinyint NOT NULL DEFAULT '0',
    `last_sync` datetime DEFAULT NULL,
    `date_creation` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `entra_id` (`entra_id`),
    KEY `users_id` (`users_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Default Configuration
-- =====================================================
-- Insert default config row if table is empty
-- =====================================================

INSERT INTO `glpi_plugin_entrahierarchy_configs` (`sync_enabled`, `sync_interval`, `date_creation`)
SELECT 0, 1800, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `glpi_plugin_entrahierarchy_configs`);

-- =====================================================
-- Notes
-- =====================================================
-- Version: 1.0.0
-- Created: 2024-10-16
-- Features:
--   - Automatic user provisioning from Entra ID
--   - Manager-subordinate synchronization
--   - Manual supervisor override support
--   - Scheduled sync via cron task
--   - Connection testing
--   - Detailed logging
-- =====================================================
