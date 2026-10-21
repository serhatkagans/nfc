<?php
/**
 * NFC Kartvizit - Yapılandırma Dosyası
 * 
 * Bu dosya dışarıdan erişime .htaccess ile kapatılmıştır.
 * Tüm hassas bilgileri burada saklayın.
 */

// Hata raporlama (canlıda kapatın)
// error_reporting(E_ALL);
// ini_set('display_errors', '1');
error_reporting(0);
ini_set('display_errors', '0');

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');

// =====================================================
// Veritabanı Ayarları
// =====================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'nfc_kartvizit');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// =====================================================
// Site Ayarları
// =====================================================
define('SITE_URL', 'http://localhost/nfc');         // Sonunda / olmasın
define('SITE_NAME', 'NFC Kartvizit');

// Şifre sıfırlama e-postalarının gönderileceği adres
define('MAIL_FROM', 'no-reply@siteadiniz.com');
define('MAIL_FROM_NAME', SITE_NAME);

// =====================================================
// Google OAuth 2.0 Ayarları
// =====================================================
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', SITE_URL . '/google-callback.php');

// =====================================================
// Dosya Yükleme Ayarları
// =====================================================
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2 MB

// =====================================================
// Güvenlik Ayarları
// =====================================================
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 7200); // 2 saat (saniye)
define('BCRYPT_COST', 12);

// =====================================================
// Güvenli Oturum Başlatma
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    // Oturum çerez parametreleri
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly'  => true,
        'samesite'=> 'Lax',
    ]);

    session_name('NFC_SESSION');
    session_start();

    // Oturum sabitleme saldırısına karşı koruma
    if (!isset($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
    }
}
