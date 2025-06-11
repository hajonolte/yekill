-- YeKill Newsletter System Database Schema
-- Multi-Tenant Newsletter Management System

SET FOREIGN_KEY_CHECKS = 0;

-- Tenants table (main multi-tenant structure)
CREATE TABLE `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `subdomain` varchar(100) UNIQUE DEFAULT NULL,
  `custom_domain` varchar(255) UNIQUE DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `settings` json DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subdomain` (`subdomain`),
  KEY `idx_custom_domain` (`custom_domain`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles table
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `permissions` json NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table (tenant-specific users)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'pending',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_email` (`tenant_id`, `email`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact lists
CREATE TABLE `contact_lists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `double_optin` tinyint(1) DEFAULT 1,
  `settings` json DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contacts
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','unsubscribed','bounced','complained') DEFAULT 'active',
  `subscribed_at` timestamp NULL DEFAULT NULL,
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `bounce_count` int(11) DEFAULT 0,
  `last_bounce_at` timestamp NULL DEFAULT NULL,
  `custom_fields` json DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_email` (`tenant_id`, `email`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_subscribed_at` (`subscribed_at`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact list subscriptions (many-to-many)
CREATE TABLE `contact_list_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `list_id` int(11) NOT NULL,
  `status` enum('subscribed','unsubscribed','pending') DEFAULT 'pending',
  `subscribed_at` timestamp NULL DEFAULT NULL,
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `confirmation_token` varchar(100) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_contact_list` (`contact_id`, `list_id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_list_id` (`list_id`),
  KEY `idx_status` (`status`),
  KEY `idx_confirmation_token` (`confirmation_token`),
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`list_id`) REFERENCES `contact_lists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Segments (dynamic contact groups)
CREATE TABLE `segments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `conditions` json NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_calculated_at` timestamp NULL DEFAULT NULL,
  `contact_count` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email templates
CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `design_json` json DEFAULT NULL,
  `type` enum('newsletter','automation','transactional') DEFAULT 'newsletter',
  `status` enum('active','inactive','draft') DEFAULT 'draft',
  `version` int(11) DEFAULT 1,
  `parent_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_created_by` (`created_by`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaigns
CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `from_name` varchar(255) NOT NULL,
  `from_email` varchar(255) NOT NULL,
  `reply_to` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `type` enum('regular','ab_test','automation') DEFAULT 'regular',
  `status` enum('draft','scheduled','sending','sent','paused','cancelled') DEFAULT 'draft',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `recipient_count` int(11) DEFAULT 0,
  `delivered_count` int(11) DEFAULT 0,
  `opened_count` int(11) DEFAULT 0,
  `clicked_count` int(11) DEFAULT 0,
  `bounced_count` int(11) DEFAULT 0,
  `complained_count` int(11) DEFAULT 0,
  `unsubscribed_count` int(11) DEFAULT 0,
  `settings` json DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled_at` (`scheduled_at`),
  KEY `idx_created_by` (`created_by`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign recipients (who received the campaign)
CREATE TABLE `campaign_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `status` enum('pending','sent','delivered','bounced','complained','opened','clicked') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `bounced_at` timestamp NULL DEFAULT NULL,
  `complained_at` timestamp NULL DEFAULT NULL,
  `bounce_reason` text DEFAULT NULL,
  `open_count` int(11) DEFAULT 0,
  `click_count` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_campaign_contact` (`campaign_id`, `contact_id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign links (for click tracking)
CREATE TABLE `campaign_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `original_url` text NOT NULL,
  `hash` varchar(32) NOT NULL,
  `click_count` int(11) DEFAULT 0,
  `unique_click_count` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_hash` (`hash`),
  FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Click tracking
CREATE TABLE `campaign_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `link_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `clicked_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_link_id` (`link_id`),
  KEY `idx_clicked_at` (`clicked_at`),
  FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`link_id`) REFERENCES `campaign_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Automation workflows
CREATE TABLE `automations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `trigger_type` enum('contact_added','tag_added','date_based','webhook','manual') NOT NULL,
  `trigger_config` json DEFAULT NULL,
  `workflow_json` json NOT NULL,
  `status` enum('active','inactive','draft') DEFAULT 'draft',
  `total_entered` int(11) DEFAULT 0,
  `total_completed` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_trigger_type` (`trigger_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Automation executions (track contacts through workflows)
CREATE TABLE `automation_executions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `automation_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `current_step` int(11) DEFAULT 0,
  `status` enum('active','completed','failed','paused') DEFAULT 'active',
  `started_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `next_action_at` timestamp NULL DEFAULT NULL,
  `execution_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_automation_id` (`automation_id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_status` (`status`),
  KEY `idx_next_action_at` (`next_action_at`),
  FOREIGN KEY (`automation_id`) REFERENCES `automations` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
