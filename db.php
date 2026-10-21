<?php
/**
 * NFC Kartvizit - Veritabanı Bağlantısı (PDO)
 * 
 * Tekil (singleton) bağlantı deseni kullanır.
 * Her zaman aynı PDO nesnesini döndürür.
 */

require_once __DIR__ . '/config.php';

/**
 * PDO veritabanı bağlantısını döndürür.
 * İlk çağrıda bağlantı oluşturulur, sonraki çağrılarda aynı nesne kullanılır.
 *
 * @return PDO
 * @throws RuntimeException Bağlantı başarısız olursa
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Canlı ortamda hata detayını kullanıcıya göstermeyin
            error_log('Veritabanı bağlantı hatası: ' . $e->getMessage());
            throw new RuntimeException('Veritabanı bağlantısı kurulamadı. Lütfen daha sonra tekrar deneyin.');
        }
    }

    return $pdo;
}

// Global $pdo değişkeni - doğrudan $pdo kullanılan dosyalar için
$pdo = getDB();
