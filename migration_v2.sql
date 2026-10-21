-- =====================================================
-- NFC Kartvizit - Sürüm 2 Güncellemesi
-- Yeni özellikler: profesyonel alanlar, profil teması,
-- e-posta doğrulama, analitik olayları, lead (kişi) toplama.
--
-- Zaten kurulu bir veritabanın varsa bunu phpMyAdmin'den çalıştır.
-- Yeni kurulumlarda gerekmez (database.sql güncel hali içerir).
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- users tablosuna yeni sütunlar
-- -----------------------------------------------------
ALTER TABLE `users`
    ADD COLUMN `title`          VARCHAR(120) NULL DEFAULT NULL COMMENT 'Ünvan / pozisyon' AFTER `full_name`,
    ADD COLUMN `company`        VARCHAR(120) NULL DEFAULT NULL COMMENT 'Şirket / kurum'    AFTER `title`,
    ADD COLUMN `address`        VARCHAR(255) NULL DEFAULT NULL COMMENT 'Açık adres'        AFTER `website`,
    ADD COLUMN `map_url`        VARCHAR(500) NULL DEFAULT NULL COMMENT 'Harita bağlantısı' AFTER `address`,
    ADD COLUMN `working_hours`  VARCHAR(500) NULL DEFAULT NULL COMMENT 'Çalışma saatleri'  AFTER `map_url`,
    ADD COLUMN `card_theme`     VARCHAR(30)  NOT NULL DEFAULT 'default' COMMENT 'Profil kart teması' AFTER `working_hours`,
    ADD COLUMN `email_verified` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'E-posta doğrulandı mı' AFTER `card_theme`;

-- Google ile gelen ya da mevcut kullanıcıları doğrulanmış say (opsiyonel)
UPDATE `users` SET `email_verified` = 1 WHERE `google_id` IS NOT NULL;

-- -----------------------------------------------------
-- Profil analitik olayları (görüntülenme, tıklama, vCard, QR, lead)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `profile_events` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    NOT NULL,
    `event_type` VARCHAR(20)     NOT NULL COMMENT 'view, click, vcard, qr, lead',
    `target`     VARCHAR(100)    NULL DEFAULT NULL COMMENT 'Örn. instagram, phone, link:12',
    `referrer`   VARCHAR(255)    NULL DEFAULT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_created` (`user_id`, `created_at`),
    KEY `idx_user_type` (`user_id`, `event_type`),
    CONSTRAINT `fk_pe_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Lead (kişi) toplama: ziyaretçinin bıraktığı iletişim bilgileri
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `name`       VARCHAR(120) NOT NULL,
    `phone`      VARCHAR(30)  NULL DEFAULT NULL,
    `email`      VARCHAR(255) NULL DEFAULT NULL,
    `company`    VARCHAR(120) NULL DEFAULT NULL,
    `note`       VARCHAR(500) NULL DEFAULT NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_is_read` (`is_read`),
    CONSTRAINT `fk_lead_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- E-posta doğrulama tokenları
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64)  NOT NULL COMMENT 'SHA-256 token hash',
    `expires_at` DATETIME     NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ev_token_hash` (`token_hash`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_expires_at` (`expires_at`),
    CONSTRAINT `fk_ev_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
