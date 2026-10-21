-- =====================================================
-- NFC Kartvizit - Sürüm 4 Güncellemesi
-- Öğrenci Dijital Portfolyosu (ortaokul / lise)
--  * users.nfc_target : NFC/QR okutulunca açılacak sayfa (card | portfolio)
--  * portfolios       : portfolyo genel bilgileri (okul, sınıf, hakkında...)
--  * portfolio_items  : başarı, proje, sertifika, etkinlik kayıtları
--
-- Zaten kurulu bir veritabanın varsa bunu phpMyAdmin'den çalıştır.
-- =====================================================

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN `nfc_target` VARCHAR(20) NOT NULL DEFAULT 'card' COMMENT 'NFC okutulunca açılacak sayfa: card | portfolio' AFTER `nfc_code`;

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
