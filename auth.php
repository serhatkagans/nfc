<?php
/**
 * NFC Kartvizit - Kimlik Doğrulama Fonksiyonları
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Yeni kullanıcı kaydı oluşturur.
 *
 * @param string $username  Kullanıcı adı (benzersiz)
 * @param string $email     E-posta adresi (benzersiz)
 * @param string $password  Düz metin şifre
 * @param string $fullName  Ad ve soyad
 * @return int|false        Başarılıysa kullanıcı ID, başarısızsa false
 */
function registerUser(string $username, string $email, string $password, string $fullName)
{
    $db = getDB();

    // Kullanıcı adı kontrolü
    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    if ($stmt->fetchColumn() > 0) {
        flash('error', 'Bu kullanıcı adı zaten kullanılıyor.');
        return false;
    }

    // E-posta kontrolü
    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetchColumn() > 0) {
        flash('error', 'Bu e-posta adresi zaten kayıtlı.');
        return false;
    }

    // Şifre hash'le
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

    $stmt = $db->prepare(
        'INSERT INTO users (username, email, password_hash, full_name)
         VALUES (:username, :email, :password_hash, :full_name)'
    );

    try {
        $stmt->execute([
            ':username'      => $username,
            ':email'         => $email,
            ':password_hash' => $passwordHash,
            ':full_name'     => $fullName,
        ]);

        $newId = (int) $db->lastInsertId();

        // Yeni kullanıcıyı getir ve oturumu başlat (otomatik giriş)
        $userStmt = $db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $userStmt->execute([':id' => $newId]);
        $newUser = $userStmt->fetch();
        if ($newUser) {
            _setUserSession($newUser);
        }

        return $newId;
    } catch (PDOException $e) {
        error_log('Kayıt hatası: ' . $e->getMessage());
        flash('error', 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.');
        return false;
    }
}

/**
 * E-posta ve şifre ile giriş yapar.
 *
 * @param string $email    E-posta adresi
 * @param string $password Düz metin şifre
 * @return array|false     Başarılıysa kullanıcı dizisi, başarısızsa false
 */
function loginUser(string $email, string $password)
{
    $db = getDB();

    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        flash('error', 'E-posta adresi veya şifre hatalı.');
        return false;
    }

    // Hesap aktif mi?
    if (!$user['is_active']) {
        flash('error', 'Hesabınız devre dışı bırakılmış. Yönetici ile iletişime geçin.');
        return false;
    }

    // SSO kullanıcısı mı? (password_hash NULL)
    if ($user['password_hash'] === null) {
        flash('error', 'Bu hesap Google ile kayıt olmuş. Lütfen Google ile giriş yapın.');
        return false;
    }

    // Şifre doğrulama
    if (!password_verify($password, $user['password_hash'])) {
        flash('error', 'E-posta adresi veya şifre hatalı.');
        return false;
    }

    // Şifre hash'i güncellenebilir mi? (cost değişmişse)
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $updateStmt = $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $updateStmt->execute([':hash' => $newHash, ':id' => $user['id']]);
    }

    // Oturumu başlat
    _setUserSession($user);

    return $user;
}

/**
 * Google ile giriş/kayıt yapar.
 * Kullanıcı yoksa otomatik oluşturur, varsa bilgileri günceller.
 *
 * @param array $googleUser Google'dan gelen kullanıcı verisi
 *                          Beklenen anahtarlar: sub, email, name, picture
 * @return array Kullanıcı dizisi
 */
function loginWithGoogle(array $googleUser): array
{
    $db = getDB();

    $googleId = $googleUser['sub'];
    $email    = $googleUser['email'];
    $name     = $googleUser['name'] ?? '';
    $picture  = $googleUser['picture'] ?? null;

    // Önce google_id ile ara
    $stmt = $db->prepare('SELECT * FROM users WHERE google_id = :google_id LIMIT 1');
    $stmt->execute([':google_id' => $googleId]);
    $user = $stmt->fetch();

    if ($user) {
        // Mevcut kullanıcı - hesap aktif mi?
        if (!$user['is_active']) {
            flash('error', 'Hesabınız devre dışı bırakılmış. Yönetici ile iletişime geçin.');
            redirect(SITE_URL . '/login.php');
            exit;
        }

        _setUserSession($user);
        return $user;
    }

    // google_id bulunamadı, e-posta ile ara
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        // E-posta eşleşti, google_id ekle
        $updateStmt = $db->prepare('UPDATE users SET google_id = :google_id WHERE id = :id');
        $updateStmt->execute([':google_id' => $googleId, ':id' => $user['id']]);

        if (!$user['is_active']) {
            flash('error', 'Hesabınız devre dışı bırakılmış. Yönetici ile iletişime geçin.');
            redirect(SITE_URL . '/login.php');
            exit;
        }

        $user['google_id'] = $googleId;
        _setUserSession($user);
        return $user;
    }

    // Yeni kullanıcı oluştur
    $username = _generateUniqueUsername($email, $name);

    $stmt = $db->prepare(
        'INSERT INTO users (username, email, google_id, full_name, profile_image)
         VALUES (:username, :email, :google_id, :full_name, :profile_image)'
    );
    $stmt->execute([
        ':username'      => $username,
        ':email'         => $email,
        ':google_id'     => $googleId,
        ':full_name'     => $name,
        ':profile_image' => $picture,
    ]);

    $newId = (int) $db->lastInsertId();

    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $newId]);
    $user = $stmt->fetch();

    _setUserSession($user);
    return $user;
}

/**
 * Kullanıcının giriş yapıp yapmadığını kontrol eder.
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Giriş yapmış kullanıcının bilgilerini döndürür.
 *
 * @return array|null Kullanıcı verisi veya null
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Kullanıcı silinmiş veya devre dışı bırakılmış
        _destroyUserSession();
        return null;
    }

    return $user;
}

/**
 * Giriş yapmış kullanıcının admin olup olmadığını kontrol eder.
 *
 * @return bool
 */
function isAdmin(): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Giriş yapılmamışsa login sayfasına yönlendirir.
 *
 * @return void
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        flash('warning', 'Bu sayfaya erişmek için giriş yapmanız gerekiyor.');
        redirect(SITE_URL . '/login.php');
        exit;
    }
}

/**
 * Admin değilse ana sayfaya yönlendirir.
 *
 * @return void
 */
function requireAdmin(): void
{
    requireLogin();

    if (!isAdmin()) {
        flash('error', 'Bu sayfaya erişim yetkiniz bulunmuyor.');
        redirect(SITE_URL . '/index.php');
        exit;
    }
}

/**
 * Kullanıcı oturumunu sonlandırır.
 *
 * @return void
 */
function logoutUser(): void
{
    _destroyUserSession();
    flash('success', 'Başarıyla çıkış yaptınız.');
}

// =====================================================
// Dahili Yardımcı Fonksiyonlar
// =====================================================

/**
 * Oturum değişkenlerini ayarlar.
 *
 * @param array $user Kullanıcı satırı
 * @return void
 */
function _setUserSession(array $user): void
{
    // Oturum sabitleme saldırısına karşı yeni ID
    session_regenerate_id(true);

    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['is_admin']  = (int) $user['is_admin'];
    $_SESSION['login_time'] = time();
}

/**
 * Oturumu tamamen yok eder.
 *
 * @return void
 */
function _destroyUserSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    // Yeni oturum başlat (flash mesajları için)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * E-posta veya isimden benzersiz kullanıcı adı üretir.
 *
 * @param string $email
 * @param string $name
 * @return string
 */
function _generateUniqueUsername(string $email, string $name): string
{
    $db = getDB();

    // İsimden kullanıcı adı oluşturmayı dene
    if (!empty($name)) {
        $base = mb_strtolower(trim($name));
        $base = preg_replace('/[^a-z0-9]/', '', $base);
    }

    // İsimden oluşturulamadıysa e-postanın @ öncesini kullan
    if (empty($base)) {
        $base = strtolower(explode('@', $email)[0]);
        $base = preg_replace('/[^a-z0-9]/', '', $base);
    }

    // Çok kısa ise 'user' kullan
    if (strlen($base) < 3) {
        $base = 'user';
    }

    // 50 karakter limitine uygun kes
    $base = substr($base, 0, 42);

    // Benzersizlik kontrolü
    $username = $base;
    $counter  = 1;

    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');

    while (true) {
        $stmt->execute([':username' => $username]);
        if ($stmt->fetchColumn() == 0) {
            break;
        }
        $username = $base . $counter;
        $counter++;
    }

    return $username;
}

// =====================================================
// Şifre Sıfırlama
// =====================================================

/**
 * Şifre sıfırlama isteği oluşturur ve kullanıcıya e-posta gönderir.
 * Gizlilik için: e-posta kayıtlı olmasa bile çağıran taraf aynı mesajı göstermelidir.
 *
 * @param string $email
 * @return void
 */
function createPasswordReset(string $email): void
{
    $db = getDB();

    $stmt = $db->prepare('SELECT id, full_name FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return; // Kullanıcı yok; sessizce çık (e-posta sızdırmamak için)
    }

    // Önceki istekleri temizle
    $db->prepare('DELETE FROM password_resets WHERE user_id = :uid')->execute([':uid' => $user['id']]);

    // Token üret ve hash'ini sakla
    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

    $insert = $db->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)'
    );
    $insert->execute([':uid' => $user['id'], ':hash' => $tokenHash, ':exp' => $expiresAt]);

    // E-posta gönder
    $resetLink = SITE_URL . '/reset-password.php?token=' . $token;
    $greeting  = !empty($user['full_name']) ? $user['full_name'] : 'Merhaba';

    $subject = SITE_NAME . ' - Şifre Sıfırlama';
    $body    = $greeting . ",\n\n"
             . "Hesabınız için bir şifre sıfırlama isteği aldık.\n"
             . "Yeni şifrenizi belirlemek için aşağıdaki bağlantıya tıklayın:\n\n"
             . $resetLink . "\n\n"
             . "Bu bağlantı 1 saat boyunca geçerlidir.\n"
             . "Bu isteği siz yapmadıysanız bu e-postayı yok sayabilirsiniz.\n\n"
             . SITE_NAME;

    sendMail($email, $subject, $body);
}

/**
 * Geçerli (süresi dolmamış) bir sıfırlama token'ı varsa kaydını döndürür.
 *
 * @param string $token Düz token
 * @return array|null
 */
function findValidPasswordReset(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $db        = getDB();
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare(
        'SELECT * FROM password_resets WHERE token_hash = :hash AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([':hash' => $tokenHash]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Token geçerliyse kullanıcının şifresini günceller ve token'ları siler.
 *
 * @param string $token       Düz token
 * @param string $newPassword Yeni düz metin şifre
 * @return bool               Başarılıysa true
 */
function completePasswordReset(string $token, string $newPassword): bool
{
    $db    = getDB();
    $reset = findValidPasswordReset($token);

    if (!$reset) {
        return false;
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
       ->execute([':hash' => $hash, ':id' => $reset['user_id']]);

    // Bu kullanıcının tüm sıfırlama token'larını geçersiz kıl
    $db->prepare('DELETE FROM password_resets WHERE user_id = :uid')
       ->execute([':uid' => $reset['user_id']]);

    return true;
}

// =====================================================
// E-posta Doğrulama
// =====================================================

/**
 * Kullanıcı için e-posta doğrulama token'ı oluşturur ve doğrulama bağlantısını gönderir.
 *
 * @param int    $userId
 * @param string $email
 * @param string $fullName
 * @return void
 */
function sendEmailVerification(int $userId, string $email, string $fullName = ''): void
{
    $db = getDB();

    // Önceki tokenları temizle
    $db->prepare('DELETE FROM email_verifications WHERE user_id = :uid')->execute([':uid' => $userId]);

    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTime('+24 hour'))->format('Y-m-d H:i:s');

    $db->prepare(
        'INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)'
    )->execute([':uid' => $userId, ':hash' => $tokenHash, ':exp' => $expiresAt]);

    $verifyLink = SITE_URL . '/verify-email.php?token=' . $token;
    $greeting   = !empty($fullName) ? $fullName : 'Merhaba';

    $subject = SITE_NAME . ' - E-posta Adresinizi Doğrulayın';
    $body    = $greeting . ",\n\n"
             . SITE_NAME . " hesabınızı oluşturduğunuz için teşekkürler.\n"
             . "E-posta adresinizi doğrulamak için aşağıdaki bağlantıya tıklayın:\n\n"
             . $verifyLink . "\n\n"
             . "Bu bağlantı 24 saat boyunca geçerlidir.\n"
             . "Bu hesabı siz oluşturmadıysanız bu e-postayı yok sayabilirsiniz.\n\n"
             . SITE_NAME;

    sendMail($email, $subject, $body);
}

/**
 * Doğrulama token'ını işler. Geçerliyse kullanıcının e-postasını doğrulanmış yapar.
 *
 * @param string $token
 * @return bool Başarılıysa true
 */
function verifyEmailToken(string $token): bool
{
    if ($token === '') {
        return false;
    }

    $db        = getDB();
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare(
        'SELECT * FROM email_verifications WHERE token_hash = :hash AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([':hash' => $tokenHash]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    $db->prepare('UPDATE users SET email_verified = 1 WHERE id = :id')
       ->execute([':id' => $row['user_id']]);

    // Kullanılan ve eski tokenları temizle
    $db->prepare('DELETE FROM email_verifications WHERE user_id = :uid')
       ->execute([':uid' => $row['user_id']]);

    return true;
}
