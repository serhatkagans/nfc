-- =====================================================
-- NFC Kartvizit - Veritabanı Şeması
-- MySQL 5.7+ uyumlu
-- =====================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';

-- -----------------------------------------------------
-- Veritabanı oluştur (gerekirse)
-- -----------------------------------------------------
-- CREATE DATABASE IF NOT EXISTS nfc_kartvizit
--     CHARACTER SET utf8mb4
--     COLLATE utf8mb4_unicode_ci;
-- USE nfc_kartvizit;

-- -----------------------------------------------------
-- Kullanıcılar tablosu
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`       VARCHAR(50)     NOT NULL,
    `email`          VARCHAR(255)    NOT NULL,
    `password_hash`  VARCHAR(255)    NULL DEFAULT NULL COMMENT 'SSO kullanıcıları için NULL olabilir',
    `google_id`      VARCHAR(255)    NULL DEFAULT NULL,
    `full_name`      VARCHAR(100)    NOT NULL DEFAULT '',
    `title`          VARCHAR(120)    NULL DEFAULT NULL COMMENT 'Ünvan / pozisyon',
    `company`        VARCHAR(120)    NULL DEFAULT NULL COMMENT 'Şirket / kurum',
    `phone`          VARCHAR(20)     NULL DEFAULT NULL,
    `instagram`      VARCHAR(100)    NULL DEFAULT NULL,
    `linkedin`       VARCHAR(255)    NULL DEFAULT NULL,
    `facebook`       VARCHAR(255)    NULL DEFAULT NULL,
    `youtube`        VARCHAR(255)    NULL DEFAULT NULL,
    `whatsapp`       VARCHAR(30)     NULL DEFAULT NULL,
    `tiktok`         VARCHAR(100)    NULL DEFAULT NULL,
    `telegram`       VARCHAR(100)    NULL DEFAULT NULL,
    `twitter`        VARCHAR(100)    NULL DEFAULT NULL,
    `website`        VARCHAR(255)    NULL DEFAULT NULL,
    `address`        VARCHAR(255)    NULL DEFAULT NULL COMMENT 'Açık adres',
    `map_url`        VARCHAR(500)    NULL DEFAULT NULL COMMENT 'Harita bağlantısı',
    `working_hours`  VARCHAR(500)    NULL DEFAULT NULL COMMENT 'Çalışma saatleri',
    `bio`            TEXT            NULL,
    `profile_image`  VARCHAR(255)    NULL DEFAULT NULL,
    `card_theme`     VARCHAR(30)     NOT NULL DEFAULT 'default' COMMENT 'Profil kart teması',
    `nfc_code`       VARCHAR(16)     NULL DEFAULT NULL COMMENT 'Kalıcı NFC/QR kısa kodu',
    `nfc_target`     VARCHAR(20)     NOT NULL DEFAULT 'card' COMMENT 'NFC okutulunca açılacak sayfa: card | portfolio',
    `view_count`     INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Profil görüntülenme sayısı',
    `email_verified` TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'E-posta doğrulandı mı',
    `is_admin`       TINYINT(1)      NOT NULL DEFAULT 0,
    `is_active`      TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_email` (`email`),
    UNIQUE KEY `uk_google_id` (`google_id`),
    UNIQUE KEY `uk_nfc_code` (`nfc_code`),
    KEY `idx_is_active` (`is_active`),
    KEY `idx_is_admin` (`is_admin`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Özel bağlantılar tablosu (kullanıcının eklediği serbest linkler)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_links` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `title`      VARCHAR(80)  NOT NULL,
    `url`        VARCHAR(500) NOT NULL,
    `icon`       VARCHAR(50)  NOT NULL DEFAULT 'fas fa-link',
    `sort_order` INT          NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_ul_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Öğrenci dijital portfolyosu
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `portfolios` (
    `user_id`     INT UNSIGNED  NOT NULL,
    `is_enabled`  TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Portfolyo yayında mı',
    `school_name` VARCHAR(150)  NULL DEFAULT NULL,
    `grade`       VARCHAR(20)   NULL DEFAULT NULL COMMENT '5..12 veya mezun',
    `headline`    VARCHAR(150)  NULL DEFAULT NULL COMMENT 'Kısa tanıtım / hedef satırı',
    `about`       TEXT          NULL,
    `goals`       VARCHAR(500)  NULL DEFAULT NULL COMMENT 'Hedefler / hayalindeki meslek',
    `skills`      VARCHAR(500)  NULL DEFAULT NULL COMMENT 'Virgülle ayrılmış yetenekler',
    `interests`   VARCHAR(500)  NULL DEFAULT NULL COMMENT 'Virgülle ayrılmış ilgi alanları / hobiler',
    `languages`   VARCHAR(255)  NULL DEFAULT NULL COMMENT 'Virgülle ayrılmış yabancı diller',
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_pf_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portfolio_items` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED  NOT NULL,
    `item_type`   VARCHAR(20)   NOT NULL DEFAULT 'achievement' COMMENT 'achievement, competition, project, certificate, activity, volunteer, other',
    `title`       VARCHAR(150)  NOT NULL,
    `description` VARCHAR(1000) NULL DEFAULT NULL,
    `item_date`   VARCHAR(30)   NULL DEFAULT NULL COMMENT 'Serbest metin: 2025, Mayıs 2025 vb.',
    `url`         VARCHAR(500)  NULL DEFAULT NULL,
    `image`       VARCHAR(255)  NULL DEFAULT NULL,
    `sort_order`  INT           NOT NULL DEFAULT 0,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_pfi_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
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

-- -----------------------------------------------------
-- Şifre sıfırlama tablosu
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64)  NOT NULL COMMENT 'SHA-256 token hash',
    `expires_at` DATETIME     NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_token_hash` (`token_hash`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_expires_at` (`expires_at`),
    CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Varsayılan admin kullanıcısı
-- Şifre: admin123
-- Bcrypt hash: password_hash('admin123', PASSWORD_BCRYPT)
-- -----------------------------------------------------
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `email_verified`, `is_admin`, `is_active`)
VALUES (
    'admin',
    'admin@admin.com',
    '$2y$12$LJ3m4ys3Gzf0UhsBCfHQnOSwVwf.VZShQTHRFeGQFJlGVKjYGKCDi',
    'Site Yöneticisi',
    1,
    1,
    1
) ON DUPLICATE KEY UPDATE `id` = `id`;
