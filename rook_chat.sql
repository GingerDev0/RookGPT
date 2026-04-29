CREATE DATABASE IF NOT EXISTS `rook_chat` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `rook_chat`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `plan` ENUM('free','plus','pro','business') NOT NULL DEFAULT 'free',
  `plan_expires_at` DATETIME NULL,
  `plan_billing_period` ENUM('monthly','annual','team','manual') NULL,
  `thinking_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `custom_prompt` TEXT NULL,
  `current_session_token` VARCHAR(128) NULL,
  `session_rotated_at` DATETIME NULL,
  `two_factor_secret` VARCHAR(64) NULL,
  `two_factor_enabled_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_username` (`username`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('owner','admin','support') NOT NULL DEFAULT 'admin',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by_user_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admins_user_id` (`user_id`),
  KEY `idx_admins_active` (`is_active`),
  KEY `idx_admins_created_by` (`created_by_user_id`),
  CONSTRAINT `fk_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_admins_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(50) NOT NULL,
  `target_id` BIGINT UNSIGNED NULL,
  `details` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_activity_admin_user_id` (`admin_user_id`),
  KEY `idx_admin_activity_created_at` (`created_at`),
  CONSTRAINT `fk_admin_activity_admin_user` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_daily_message_usage` (
  `user_id` INT UNSIGNED NOT NULL,
  `usage_date` DATE NOT NULL,
  `messages_used` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `usage_date`),
  CONSTRAINT `fk_user_daily_message_usage_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `promo_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) NULL,
  `discount_type` ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `applies_to_plan` ENUM('any','plus','pro','business') NOT NULL DEFAULT 'any',
  `applies_to_period` ENUM('any','monthly','annual') NOT NULL DEFAULT 'any',
  `max_redemptions` INT UNSIGNED NULL,
  `redeemed_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `starts_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_promo_codes_code` (`code`),
  KEY `idx_promo_codes_active` (`is_active`),
  KEY `idx_promo_codes_dates` (`starts_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `promo_code_redemptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `promo_code_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `stripe_session_id` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_promo_redemptions_session` (`stripe_session_id`),
  KEY `idx_promo_redemptions_promo_code_id` (`promo_code_id`),
  KEY `idx_promo_redemptions_user_id` (`user_id`),
  CONSTRAINT `fk_promo_redemptions_code` FOREIGN KEY (`promo_code_id`) REFERENCES `promo_codes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_promo_redemptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT 'New chat',
  `token` CHAR(32) NOT NULL,
  `share_token` CHAR(40) NULL,
  `team_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_conversations_token` (`token`),
  UNIQUE KEY `uniq_conversations_share_token` (`share_token`),
  KEY `idx_conversations_user_id` (`user_id`),
  KEY `idx_conversations_team_id` (`team_id`),
  CONSTRAINT `fk_conversations_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` INT UNSIGNED NOT NULL,
  `role` ENUM('user','assistant','thinking') NOT NULL,
  `author_user_id` INT UNSIGNED NULL,
  `content` MEDIUMTEXT NOT NULL,
  `images_json` MEDIUMTEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_conversation_id` (`conversation_id`),
  KEY `idx_messages_author_user_id` (`author_user_id`),
  CONSTRAINT `fk_messages_conversation`
    FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_messages_author_user`
    FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `team_id` INT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `key_hash` CHAR(64) NOT NULL,
  `key_prefix` VARCHAR(32) NULL,
  `key_suffix` VARCHAR(16) NULL,
  `secret_cipher` MEDIUMTEXT NULL,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `revoked_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_api_keys_hash` (`key_hash`),
  KEY `idx_api_keys_user_id` (`user_id`),
  KEY `idx_api_keys_team_id` (`team_id`),
  CONSTRAINT `fk_api_keys_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `two_factor_recovery_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_2fa_recovery_user_used` (`user_id`, `used_at`),
  CONSTRAINT `fk_2fa_recovery_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `team_id` INT UNSIGNED NULL,
  `api_key_id` BIGINT UNSIGNED NOT NULL,
  `endpoint` VARCHAR(120) NOT NULL,
  `status_code` INT NOT NULL,
  `prompt_eval_count` INT NOT NULL DEFAULT 0,
  `eval_count` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_api_logs_user_id` (`user_id`),
  KEY `idx_api_logs_key_id` (`api_key_id`),
  KEY `idx_api_logs_created_at` (`created_at`),
  CONSTRAINT `fk_api_logs_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_api_logs_key`
    FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teams` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `owner_user_id` INT UNSIGNED NOT NULL,
  `token` CHAR(32) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_teams_owner_user_id` (`owner_user_id`),
  UNIQUE KEY `uniq_teams_token` (`token`),
  KEY `idx_teams_owner_user_id` (`owner_user_id`),
  CONSTRAINT `fk_teams_owner_user`
    FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('owner','admin','member') NOT NULL DEFAULT 'member',
  `can_read` TINYINT(1) NOT NULL DEFAULT 1,
  `can_send_messages` TINYINT(1) NOT NULL DEFAULT 1,
  `can_create_conversations` TINYINT(1) NOT NULL DEFAULT 0,
  `can_view_api_keys` TINYINT(1) NOT NULL DEFAULT 0,
  `can_manage_api_keys` TINYINT(1) NOT NULL DEFAULT 0,
  `pre_team_plan` ENUM('free','plus','pro','business') NULL,
  `pre_team_thinking_enabled` TINYINT(1) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_team_members_team_user` (`team_id`, `user_id`),
  UNIQUE KEY `uniq_team_members_user_id` (`user_id`),
  KEY `idx_team_members_user_id` (`user_id`),
  CONSTRAINT `fk_team_members_team`
    FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_team_members_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `content` MEDIUMTEXT NOT NULL,
  `encrypted` TINYINT(1) NOT NULL DEFAULT 1,
  `is_ai` TINYINT(1) NOT NULL DEFAULT 0,
  `display_name` VARCHAR(80) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  `deleted_by_user_id` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_team_chat_team_id_id` (`team_id`, `id`),
  KEY `idx_team_chat_user_id` (`user_id`),
  KEY `idx_team_chat_created_at` (`created_at`),
  KEY `idx_team_chat_updated_at` (`updated_at`),
  KEY `idx_team_chat_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_team_chat_team`
    FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_team_chat_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_chat_delete_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` INT UNSIGNED NOT NULL,
  `actor_user_id` INT UNSIGNED NULL,
  `event_type` VARCHAR(16) NOT NULL DEFAULT 'delete',
  `message_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_team_chat_delete_events_team_id_id` (`team_id`, `id`),
  KEY `idx_team_chat_delete_events_created_at` (`created_at`),
  CONSTRAINT `fk_team_chat_delete_events_team`
    FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_team_chat_delete_events_actor`
    FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_changes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` INT UNSIGNED NOT NULL,
  `actor_user_id` INT UNSIGNED NULL,
  `action` VARCHAR(80) NOT NULL,
  `target_type` VARCHAR(40) NOT NULL DEFAULT 'team',
  `target_id` BIGINT UNSIGNED NULL,
  `target_label` VARCHAR(255) NULL,
  `details` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_team_changes_team_id` (`team_id`),
  KEY `idx_team_changes_actor_user_id` (`actor_user_id`),
  KEY `idx_team_changes_created_at` (`created_at`),
  CONSTRAINT `fk_team_changes_team`
    FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_team_changes_actor`
    FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conversations_team`
    FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
    ON DELETE SET NULL;


CREATE TABLE IF NOT EXISTS `team_invites` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` INT UNSIGNED NOT NULL,
  `invited_user_id` INT UNSIGNED NOT NULL,
  `invited_by_user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('admin','member') NOT NULL DEFAULT 'member',
  `can_read` TINYINT(1) NOT NULL DEFAULT 1,
  `can_send_messages` TINYINT(1) NOT NULL DEFAULT 1,
  `can_create_conversations` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('pending','accepted','declined','cancelled') NOT NULL DEFAULT 'pending',
  `responded_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_team_invites_user_status` (`invited_user_id`, `status`),
  KEY `idx_team_invites_team_user_status` (`team_id`, `invited_user_id`, `status`),
  CONSTRAINT `fk_team_invites_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_team_invites_user` FOREIGN KEY (`invited_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_team_invites_inviter` FOREIGN KEY (`invited_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `created_by_user_id` INT UNSIGNED NULL,
  `type` VARCHAR(40) NOT NULL DEFAULT 'system',
  `title` VARCHAR(180) NOT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `action_url` VARCHAR(255) NULL,
  `related_team_invite_id` BIGINT UNSIGNED NULL,
  `read_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`, `read_at`),
  KEY `idx_notifications_created_at` (`created_at`),
  KEY `idx_notifications_invite` (`related_team_invite_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notifications_invite` FOREIGN KEY (`related_team_invite_id`) REFERENCES `team_invites` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
