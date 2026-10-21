-- =====================================================
-- NFC Kartvizit - Mevcut Veritabanı Güncellemesi
-- Zaten kurulu bir veritabanın varsa bunu phpMyAdmin'den çalıştır.
-- Yeni kurulumlarda gerekmez (database.sql zaten içeriyor).
-- =====================================================

-- Görüntülenme sayacı
ALTER TABLE `users`
    ADD COLUMN `view_count` INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Profil görüntülenme sayısı' AFTER `profile_image`;

-- Şifre sıfırlama tablosu
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

-- Ek sabit sosyal medya platformları
ALTER TABLE `users`
    ADD COLUMN `whatsapp` VARCHAR(30)  NULL DEFAULT NULL AFTER `youtube`,
    ADD COLUMN `tiktok`   VARCHAR(100) NULL DEFAULT NULL AFTER `whatsapp`,
    ADD COLUMN `telegram` VARCHAR(100) NULL DEFAULT NULL AFTER `tiktok`,
    ADD COLUMN `twitter`  VARCHAR(100) NULL DEFAULT NULL AFTER `telegram`;

-- Özel bağlantılar (kullanıcının eklediği serbest linkler)
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
