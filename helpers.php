<?php
/**
 * NFC Kartvizit - Yardımcı Fonksiyonlar
 */

require_once __DIR__ . '/config.php';

/**
 * Belirtilen URL'ye yönlendirir ve betiği sonlandırır.
 *
 * @param string $url Yönlendirilecek URL
 * @return void
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Girdi değerini güvenli hale getirir (XSS koruması).
 *
 * @param string|null $input Temizlenecek girdi
 * @return string Temizlenmiş girdi
 */
function sanitize(?string $input): string
{
    if ($input === null) {
        return '';
    }

    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $input;
}

/**
 * Profil fotoğrafını yükler.
 * Desteklenen formatlar: JPEG, PNG, GIF, WebP.
 * Rastgele dosya adı üretir, uploads/ dizinine kaydeder.
 *
 * @param array $file $_FILES['field_name'] dizisi
 * @return string|false Başarılıysa dosya adı, başarısızsa false
 */
function uploadProfileImage(array $file)
{
    // Yükleme hatası kontrolü
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'Dosya boyutu sunucu limitini aşıyor.',
            UPLOAD_ERR_FORM_SIZE  => 'Dosya boyutu form limitini aşıyor.',
            UPLOAD_ERR_PARTIAL    => 'Dosya yalnızca kısmen yüklendi.',
            UPLOAD_ERR_NO_FILE    => 'Dosya yüklenmedi.',
            UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı.',
            UPLOAD_ERR_CANT_WRITE => 'Dosya diske yazılamadı.',
            UPLOAD_ERR_EXTENSION  => 'Bir eklenti dosya yüklemeyi durdurdu.',
        ];
        $code = $file['error'] ?? -1;
        $msg  = $errorMessages[$code] ?? 'Bilinmeyen yükleme hatası.';
        flash('error', $msg);
        return false;
    }

    // Dosya boyutu kontrolü
    if ($file['size'] > MAX_FILE_SIZE) {
        $maxMB = MAX_FILE_SIZE / (1024 * 1024);
        flash('error', "Dosya boyutu en fazla {$maxMB} MB olabilir.");
        return false;
    }

    // MIME türü doğrulama (dosya içeriğinden)
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mimeType, $allowedMimeTypes)) {
        flash('error', 'Desteklenmeyen dosya formatı. Sadece JPEG, PNG, GIF ve WebP kabul edilir.');
        return false;
    }

    // Uzantıyı MIME türünden al (güvenli)
    $extension = $allowedMimeTypes[$mimeType];

    // Uploads dizini yoksa oluştur
    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            flash('error', 'Yükleme dizini oluşturulamadı.');
            return false;
        }
    }

    // Rastgele ve benzersiz dosya adı üret
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = UPLOAD_DIR . $filename;

    // Dosya adı çakışması (çok düşük ihtimal)
    while (file_exists($destination)) {
        $filename    = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = UPLOAD_DIR . $filename;
    }

    // Dosyayı taşı
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        flash('error', 'Dosya yüklenirken bir hata oluştu.');
        return false;
    }

    // Dosya izinlerini ayarla
    chmod($destination, 0644);

    return $filename;
}

/**
 * CSRF token üretir ve oturuma kaydeder.
 *
 * @return string Üretilen token
 */
function generateCSRFToken(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION[CSRF_TOKEN_NAME] = $token;
    $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();

    return $token;
}

/**
 * CSRF token'ı doğrular.
 * Token 1 saat içinde kullanılmalıdır.
 *
 * @param string|null $token Doğrulanacak token
 * @return bool
 */
function verifyCSRFToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }

    // Zamanlama saldırısına karşı güvenli karşılaştırma
    $isValid = hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);

    // Token süresi dolmuş mu? (1 saat = 3600 saniye)
    $tokenTime = $_SESSION[CSRF_TOKEN_NAME . '_time'] ?? 0;
    $isExpired = (time() - $tokenTime) > 3600;

    // Kullanıldıktan sonra token'ı geçersiz kıl (tek kullanımlık)
    unset($_SESSION[CSRF_TOKEN_NAME], $_SESSION[CSRF_TOKEN_NAME . '_time']);

    return $isValid && !$isExpired;
}

/**
 * Flash mesajı oturuma kaydeder.
 * Bir sonraki sayfa yüklemesinde gösterilip silinir.
 *
 * @param string $type    Mesaj türü: success, danger, warning, info
 * @param string $message Mesaj içeriği
 * @return void
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Flash mesajı döndürür ve oturumdan siler.
 *
 * @return array|null ['type' => '...', 'message' => '...'] veya null
 */
function getFlash(): ?array
{
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }

    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    return $flash;
}

/**
 * Geçen süreyi Türkçe olarak döndürür.
 * Örnek: "2 dakika önce", "1 saat önce", "3 gün önce"
 *
 * @param string $datetime MySQL datetime formatı (Y-m-d H:i:s)
 * @return string Türkçe göreceli zaman
 */
function timeAgo(string $datetime): string
{
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    // Yıl
    if ($diff->y > 0) {
        return $diff->y . ' yıl önce';
    }

    // Ay
    if ($diff->m > 0) {
        return $diff->m . ' ay önce';
    }

    // Gün
    if ($diff->d > 0) {
        if ($diff->d === 1) {
            return 'dün';
        }
        if ($diff->d < 7) {
            return $diff->d . ' gün önce';
        }
        $weeks = (int) floor($diff->d / 7);
        return $weeks . ' hafta önce';
    }

    // Saat
    if ($diff->h > 0) {
        return $diff->h . ' saat önce';
    }

    // Dakika
    if ($diff->i > 0) {
        return $diff->i . ' dakika önce';
    }

    // Saniye
    if ($diff->s > 10) {
        return $diff->s . ' saniye önce';
    }

    return 'az önce';
}

/**
 * Profil fotoğrafının URL'sini döndürür.
 * Harici URL (Google profil resmi) veya yerel dosya olabilir.
 *
 * @param string|null $profileImage Dosya adı veya tam URL
 * @param int         $size         Varsayılan avatar boyutu (kullanılmıyor)
 * @return string Profil fotoğrafı URL'si
 */
function getProfileImageUrl(?string $profileImage): string
{
    if (empty($profileImage)) {
        // Varsayılan avatar (gömülü SVG - harici dosya bağımlılığı yok)
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">'
             . '<rect width="200" height="200" fill="#1a1a2e"/>'
             . '<circle cx="100" cy="78" r="38" fill="#667eea"/>'
             . '<path d="M40 180c0-33 27-58 60-58s60 25 60 58z" fill="#667eea"/>'
             . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    // Harici URL mi? (Google vb.)
    if (filter_var($profileImage, FILTER_VALIDATE_URL)) {
        return $profileImage;
    }

    // Yerel dosya
    return SITE_URL . '/uploads/' . $profileImage;
}

/**
 * UTF-8 destekli düz metin e-posta gönderir (PHP mail()).
 * Shared hosting'lerin çoğunda çalışır. SMTP gerekiyorsa burası değiştirilebilir.
 *
 * @param string $to      Alıcı e-posta
 * @param string $subject Konu
 * @param string $body    Düz metin gövde
 * @return bool           Gönderim başarılıysa true
 */
function sendMail(string $to, string $subject, string $body): bool
{
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromName       = '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?=';

    $headers   = [];
    $headers[] = 'From: ' . $fromName . ' <' . MAIL_FROM . '>';
    $headers[] = 'Reply-To: ' . MAIL_FROM;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    return @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
}

/**
 * Eski profil fotoğrafını siler.
 *
 * @param string|null $filename Silinecek dosya adı
 * @return void
 */
function deleteProfileImage(?string $filename): void
{
    if (empty($filename)) {
        return;
    }

    // Harici URL'leri silmeye çalışma
    if (filter_var($filename, FILTER_VALIDATE_URL)) {
        return;
    }

    $filePath = UPLOAD_DIR . $filename;

    if (file_exists($filePath) && is_file($filePath)) {
        unlink($filePath);
    }
}

/**
 * Özel bağlantılar için izin verilen ikon listesi (Font Awesome sınıfları).
 * Anahtar: FA sınıfı, Değer: kullanıcıya gösterilen etiket.
 * Güvenlik: yalnızca bu listedeki ikonlar kabul edilir (XSS önlemi).
 *
 * @return array<string,string>
 */
function getLinkIconOptions(): array
{
    return [
        'fas fa-link'         => 'Genel Bağlantı',
        'fab fa-tiktok'       => 'TikTok',
        'fab fa-whatsapp'     => 'WhatsApp',
        'fab fa-telegram'     => 'Telegram',
        'fab fa-x-twitter'    => 'X (Twitter)',
        'fab fa-spotify'      => 'Spotify',
        'fab fa-github'       => 'GitHub',
        'fab fa-discord'      => 'Discord',
        'fab fa-pinterest'    => 'Pinterest',
        'fab fa-snapchat'     => 'Snapchat',
        'fab fa-twitch'       => 'Twitch',
        'fab fa-behance'      => 'Behance',
        'fab fa-dribbble'     => 'Dribbble',
        'fab fa-medium'       => 'Medium',
        'fab fa-soundcloud'   => 'SoundCloud',
        'fas fa-store'        => 'Mağaza',
        'fas fa-location-dot' => 'Konum / Harita',
        'fas fa-file-pdf'     => 'PDF / Döküman',
        'fas fa-calendar'     => 'Randevu / Takvim',
        'fas fa-envelope'     => 'E-posta',
        'fas fa-phone'        => 'Telefon',
    ];
}

/**
 * Verilen ikon sınıfı izin listesinde mi kontrol eder, değilse varsayılana döner.
 *
 * @param string|null $icon
 * @return string
 */
function sanitizeLinkIcon(?string $icon): string
{
    $allowed = getLinkIconOptions();
    return ($icon !== null && isset($allowed[$icon])) ? $icon : 'fas fa-link';
}

/**
 * Bir kullanıcının özel bağlantılarını sıralı şekilde getirir.
 *
 * @param int $userId
 * @return array
 */
function getUserLinks(int $userId): array
{
    $stmt = getDB()->prepare(
        'SELECT * FROM user_links WHERE user_id = :uid ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

/**
 * Profil kartı için kullanılabilir renk temaları.
 * Her tema: ad, vurgu gradyanının başlangıç/bitiş renkleri ve arka plan tonları.
 *
 * @return array<string,array<string,string>>
 */
function getCardThemes(): array
{
    return [
        'default' => ['name' => 'Mor Gece',     'start' => '#667eea', 'end' => '#764ba2', 'bg1' => '#0a0a1a', 'bg2' => '#16213e'],
        'sunset'  => ['name' => 'Gün Batımı',    'start' => '#f093fb', 'end' => '#f5576c', 'bg1' => '#190a14', 'bg2' => '#3a1622'],
        'ocean'   => ['name' => 'Okyanus',       'start' => '#00d2ff', 'end' => '#3a7bd5', 'bg1' => '#06121a', 'bg2' => '#0d2b3e'],
        'forest'  => ['name' => 'Orman',         'start' => '#11998e', 'end' => '#38ef7d', 'bg1' => '#06140f', 'bg2' => '#0d2e1f'],
        'gold'    => ['name' => 'Altın',         'start' => '#f7971e', 'end' => '#ffd200', 'bg1' => '#191405', 'bg2' => '#2e2410'],
        'rose'    => ['name' => 'Gül',           'start' => '#ff5f9a', 'end' => '#c44dff', 'bg1' => '#190a14', 'bg2' => '#2e1630'],
        'midnight'=> ['name' => 'Gece Yarısı',   'start' => '#8e9eab', 'end' => '#42484f', 'bg1' => '#070707', 'bg2' => '#1c1c20'],
    ];
}

/**
 * Verilen tema anahtarı geçerli mi kontrol eder, değilse 'default' döner.
 *
 * @param string|null $theme
 * @return string
 */
function sanitizeCardTheme(?string $theme): string
{
    $themes = getCardThemes();
    return ($theme !== null && isset($themes[$theme])) ? $theme : 'default';
}

/**
 * Kullanıcı adı olarak kullanılamayacak (rezerve) kelimeler.
 * Sistem sayfaları ve dizinleriyle çakışmayı önler.
 *
 * @return string[]
 */
function getReservedUsernames(): array
{
    return [
        'admin', 'administrator', 'root', 'system', 'support', 'help', 'api',
        'login', 'logout', 'register', 'dashboard', 'profile', 'settings',
        'account', 'user', 'users', 'u', 'assets', 'uploads', 'config',
        'analytics', 'leads', 'track', 'lead', 'verify', 'verify-email',
        'forgot-password', 'reset-password', 'google-callback', 'auth', 'db',
        'helpers', 'index', 'null', 'undefined', 'me', 'about', 'contact',
        't', 'verify-email', 'partials', 'p', 'portfolio', 'portfolio-edit',
    ];
}

/**
 * Kullanıcının kalıcı NFC kısa kodunu döndürür; yoksa üretip kaydeder.
 * Bu kod /t/KOD kısa linkinde kullanılır ve kullanıcı adı değişse bile sabit kalır.
 *
 * @param int $userId
 * @return string
 */
function ensureNfcCode(int $userId): string
{
    $db = getDB();

    // Mevcut kodu kontrol et
    $stmt = $db->prepare('SELECT nfc_code FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $existing = $stmt->fetchColumn();
    if (!empty($existing)) {
        return $existing;
    }

    // Benzersiz kod üret (8 karakter, karışması zor alfabe)
    $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
    do {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $check = $db->prepare('SELECT COUNT(*) FROM users WHERE nfc_code = ?');
        $check->execute([$code]);
    } while ((int) $check->fetchColumn() > 0);

    $db->prepare('UPDATE users SET nfc_code = ? WHERE id = ?')->execute([$code, $userId]);

    return $code;
}

/**
 * Kullanıcı adının kuralları sağlayıp sağlamadığını kontrol eder.
 *
 * @param string $username
 * @return string|null Hata mesajı; geçerliyse null
 */
function validateUsername(string $username): ?string
{
    if (strlen($username) < 3) {
        return 'Kullanıcı adı en az 3 karakter olmalıdır.';
    }
    if (strlen($username) > 50) {
        return 'Kullanıcı adı en fazla 50 karakter olabilir.';
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        return 'Kullanıcı adı sadece harf, rakam, tire ve alt çizgi içerebilir.';
    }
    if (in_array(mb_strtolower($username), getReservedUsernames(), true)) {
        return 'Bu kullanıcı adı kullanılamaz, lütfen başka bir tane seçin.';
    }
    return null;
}

/**
 * Profil etkileşim olayını (analitik) kaydeder.
 * Sayaç kritik olmadığından hatalar sessizce yutulur.
 *
 * @param int         $userId
 * @param string      $type   view, click, vcard, qr, lead
 * @param string|null $target Örn. instagram, phone, link:12
 * @return void
 */
function logProfileEvent(int $userId, string $type, ?string $target = null): void
{
    $allowed = ['view', 'click', 'vcard', 'qr', 'lead'];
    if (!in_array($type, $allowed, true) || $userId <= 0) {
        return;
    }

    try {
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        if ($referrer !== null) {
            $referrer = mb_substr($referrer, 0, 255);
        }
        if ($target !== null) {
            $target = mb_substr($target, 0, 100);
        }

        getDB()->prepare(
            'INSERT INTO profile_events (user_id, event_type, target, referrer) VALUES (?, ?, ?, ?)'
        )->execute([$userId, $type, $target, $referrer]);
    } catch (PDOException $e) {
        // Analitik kritik değil; sessizce geç
    }
}

/**
 * Bir kullanıcının lead (kişi) kayıtlarını getirir.
 *
 * @param int $userId
 * @return array
 */
function getUserLeads(int $userId): array
{
    $stmt = getDB()->prepare(
        'SELECT * FROM leads WHERE user_id = :uid ORDER BY created_at DESC'
    );
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

/**
 * Okunmamış lead sayısını döndürür (header rozeti için).
 *
 * @param int $userId
 * @return int
 */
function getUnreadLeadsCount(int $userId): int
{
    try {
        $stmt = getDB()->prepare('SELECT COUNT(*) FROM leads WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// =====================================================
// Öğrenci Dijital Portfolyosu
// =====================================================

/**
 * NFC/QR okutulunca açılabilecek sayfalar.
 *
 * @return array<string,string>
 */
function getNfcTargets(): array
{
    return [
        'card'      => 'Dijital Kartvizit',
        'portfolio' => 'Öğrenci Portfolyosu',
    ];
}

function sanitizeNfcTarget(?string $target): string
{
    return ($target !== null && isset(getNfcTargets()[$target])) ? $target : 'card';
}

/**
 * Sınıf seçenekleri (ortaokul + lise).
 *
 * @return array<string,array<string,string>> Grup adı => [değer => etiket]
 */
function getGradeOptions(): array
{
    return [
        'Ortaokul' => ['5' => '5. Sınıf', '6' => '6. Sınıf', '7' => '7. Sınıf', '8' => '8. Sınıf'],
        'Lise'     => ['9' => '9. Sınıf', '10' => '10. Sınıf', '11' => '11. Sınıf', '12' => '12. Sınıf', 'mezun' => 'Mezun'],
    ];
}

function getGradeLabel(?string $grade): string
{
    foreach (getGradeOptions() as $group => $opts) {
        if ($grade !== null && isset($opts[$grade])) {
            return $grade === 'mezun' ? 'Lise Mezunu' : $group . ' · ' . $opts[$grade];
        }
    }
    return '';
}

/**
 * Portfolyo kayıt türleri: anahtar => [etiket, ikon].
 *
 * @return array<string,array{label:string,icon:string}>
 */
function getPortfolioItemTypes(): array
{
    return [
        'achievement' => ['label' => 'Başarı / Ödül',         'icon' => 'fas fa-trophy'],
        'competition' => ['label' => 'Yarışma / Olimpiyat',   'icon' => 'fas fa-medal'],
        'project'     => ['label' => 'Proje',                 'icon' => 'fas fa-flask'],
        'certificate' => ['label' => 'Sertifika / Kurs',      'icon' => 'fas fa-certificate'],
        'activity'    => ['label' => 'Kulüp / Etkinlik',      'icon' => 'fas fa-people-group'],
        'volunteer'   => ['label' => 'Gönüllülük',            'icon' => 'fas fa-hand-holding-heart'],
        'art'         => ['label' => 'Sanat / Spor',          'icon' => 'fas fa-palette'],
        'other'       => ['label' => 'Diğer',                 'icon' => 'fas fa-star'],
    ];
}

function sanitizePortfolioItemType(?string $type): string
{
    return ($type !== null && isset(getPortfolioItemTypes()[$type])) ? $type : 'other';
}

/**
 * Kullanıcının portfolyo bilgilerini döndürür (yoksa boş varsayılanlar).
 */
function getPortfolio(int $userId): array
{
    $defaults = [
        'user_id' => $userId, 'is_enabled' => 0, 'school_name' => '', 'grade' => '',
        'headline' => '', 'about' => '', 'goals' => '', 'skills' => '', 'interests' => '', 'languages' => '',
    ];
    try {
        $stmt = getDB()->prepare('SELECT * FROM portfolios WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? array_merge($defaults, array_map(fn($v) => $v ?? '', $row)) : $defaults;
    } catch (PDOException $e) {
        return $defaults;
    }
}

function getPortfolioItems(int $userId): array
{
    try {
        $stmt = getDB()->prepare('SELECT * FROM portfolio_items WHERE user_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * "Matematik, Robotik , Satranç" -> ['Matematik','Robotik','Satranç']
 *
 * @return string[]
 */
function splitTags(?string $text, int $max = 20): array
{
    if ($text === null || trim($text) === '') {
        return [];
    }
    $parts = array_filter(array_map('trim', explode(',', $text)), fn($t) => $t !== '');
    return array_slice(array_values(array_unique($parts)), 0, $max);
}

/**
 * Serbest metin alanını temizler (HTML çıktıda ayrıca escape edilir).
 */
function cleanText(?string $text, int $maxLen): string
{
    $text = trim(strip_tags((string) $text));
    return mb_substr($text, 0, $maxLen);
}

/**
 * Hex rengi koyulaştırır (açık temada okunur vurgu yazısı için).
 * Örn. darkenHex('#f7971e', 0.45)
 */
function darkenHex(string $hex, float $amount): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $out = '#';
    foreach (str_split($hex, 2) as $c) {
        $out .= sprintf('%02x', (int) round(hexdec($c) * (1 - $amount)));
    }
    return $out;
}

