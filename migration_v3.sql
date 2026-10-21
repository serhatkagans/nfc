-- =====================================================
-- NFC Kartvizit - Sürüm 3 Güncellemesi
-- Kalıcı dinamik NFC kısa linki (/t/KOD).
-- Kullanıcı adı değişse bile etikete yazılı link bozulmaz.
--
-- Zaten kurulu bir veritabanın varsa bunu phpMyAdmin'den çalıştır.
-- =====================================================

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN `nfc_code` VARCHAR(16) NULL DEFAULT NULL COMMENT 'Kalıcı NFC/QR kısa kodu' AFTER `card_theme`,
    ADD UNIQUE KEY `uk_nfc_code` (`nfc_code`);
