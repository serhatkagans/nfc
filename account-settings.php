<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

requireLogin();

$user = getCurrentUser();
if (!$user) {
    flash('error', 'Oturum süresi dolmuş. Lütfen tekrar giriş yapın.');
    redirect('login.php');
}

$hasPassword = !empty($user['password_hash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');
        redirect('account-settings.php');
    }

    $action = $_POST['action'] ?? '';

    // ---------------------------------------------
    // Kullanıcı adı değiştir
    // ---------------------------------------------
    if ($action === 'change_username') {
        $newUsername = sanitize($_POST['username'] ?? '');

        $usernameError = validateUsername($newUsername);
        if ($usernameError !== null) {
            flash('error', $usernameError);
            redirect('account-settings.php');
        }

        if (mb_strtolower($newUsername) !== mb_strtolower($user['username'])) {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $check->execute([$newUsername, $user['id']]);
            if ($check->fetch()) {
                flash('error', 'Bu kullanıcı adı zaten kullanılıyor.');
                redirect('account-settings.php');
            }
        }

        $pdo->prepare('UPDATE users SET username = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$newUsername, $user['id']]);
        $_SESSION['username'] = $newUsername;

        flash('success', 'Kullanıcı adınız güncellendi. Yeni profil linkiniz: ' . SITE_URL . '/u/' . htmlspecialchars($newUsername));
        redirect('account-settings.php');
    }

    // ---------------------------------------------
    // E-posta değiştir
    // ---------------------------------------------
    if ($action === 'change_email') {
        $newEmail = sanitize($_POST['email'] ?? '');
        $password = $_POST['current_password'] ?? '';

        if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Geçerli bir e-posta adresi girin.');
            redirect('account-settings.php');
        }

        // Şifresi olan kullanıcılar için mevcut şifre doğrulaması
        if ($hasPassword && !password_verify($password, $user['password_hash'])) {
            flash('error', 'Mevcut şifreniz hatalı.');
            redirect('account-settings.php');
        }

        if (mb_strtolower($newEmail) !== mb_strtolower($user['email'])) {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $check->execute([$newEmail, $user['id']]);
            if ($check->fetch()) {
                flash('error', 'Bu e-posta adresi zaten kayıtlı.');
                redirect('account-settings.php');
            }
        }

        $pdo->prepare('UPDATE users SET email = ?, email_verified = 0, updated_at = NOW() WHERE id = ?')
            ->execute([$newEmail, $user['id']]);

        // Yeni adrese doğrulama gönder
        sendEmailVerification((int) $user['id'], $newEmail, $user['full_name'] ?? '');

        flash('success', 'E-posta adresiniz güncellendi. Yeni adresinize bir doğrulama bağlantısı gönderdik.');
        redirect('account-settings.php');
    }

    // ---------------------------------------------
    // Şifre değiştir / belirle
    // ---------------------------------------------
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirm'] ?? '';

        $errors = [];

        // Mevcut şifresi olanlar mevcut şifreyi doğrulamalı
        if ($hasPassword && !password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Mevcut şifreniz hatalı.';
        }
        if (strlen($newPassword) < 6) {
            $errors[] = 'Yeni şifre en az 6 karakter olmalıdır.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Yeni şifreler eşleşmiyor.';
        }

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            redirect('account-settings.php');
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$hash, $user['id']]);

        flash('success', $hasPassword ? 'Şifreniz başarıyla güncellendi.' : 'Şifreniz oluşturuldu. Artık e-posta ve şifrenizle de giriş yapabilirsiniz.');
        redirect('account-settings.php');
    }

    // ---------------------------------------------
    // Hesabı sil
    // ---------------------------------------------
    if ($action === 'delete_account') {
        $confirmText = trim($_POST['confirm_text'] ?? '');
        $password    = $_POST['current_password'] ?? '';

        // Şifresi olanlar şifre, olmayanlar (Google) "SİL" yazarak onaylar
        if ($hasPassword) {
            if (!password_verify($password, $user['password_hash'])) {
                flash('error', 'Hesabı silmek için mevcut şifrenizi doğru girin.');
                redirect('account-settings.php');
            }
        } else {
            // ASCII karşılaştırma (Türkçe büyük/küçük harf sorununu önlemek için)
            if (strtoupper(str_replace(['i', 'İ', 'ı'], ['I', 'I', 'I'], $confirmText)) !== 'SIL') {
                flash('error', 'Hesabı silmek için kutuya SIL yazmalısınız.');
                redirect('account-settings.php');
            }
        }

        // Profil fotoğrafını diskten sil (varsa)
        if (!empty($user['profile_image'])) {
            deleteProfileImage($user['profile_image']);
        }

        // Kullanıcıyı sil (ilişkili kayıtlar ON DELETE CASCADE ile silinir)
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);

        logoutUser();
        flash('success', 'Hesabınız kalıcı olarak silindi.');
        redirect('index.php');
    }

    flash('error', 'Bilinmeyen işlem.');
    redirect('account-settings.php');
}

$csrfToken  = generateCSRFToken();
$profileUrl = SITE_URL . '/u/' . $user['username'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesap Ayarları - <?= SITE_NAME ?></title>
    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-animated">

    <?php $activeNav = 'settings'; require __DIR__ . '/partials/user-header.php'; ?>

    <div class="dashboard">
        <div class="container container--md">

            <div class="dashboard__header">
                <h1 class="dashboard__title"><span class="text-gradient">Hesap Ayarları</span></h1>
                <p class="text-secondary mt-sm">Giriş bilgilerinizi ve hesabınızı buradan yönetin.</p>
            </div>

            <!-- Kullanıcı Adı -->
            <div class="glass-card glass-card--no-hover dashboard-section">
                <h3 class="dashboard-section__title"><i class="fas fa-at"></i> Kullanıcı Adı</h3>
                <form method="POST" action="account-settings.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="change_username">
                    <div class="form-group">
                        <label class="form-label" for="username">Kullanıcı Adı</label>
                        <input type="text" id="username" name="username" class="form-input"
                               value="<?= htmlspecialchars($user['username']) ?>" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_-]+">
                        <div class="form-hint">Profil linkiniz: <?= SITE_URL ?>/u/<strong><?= htmlspecialchars($user['username']) ?></strong></div>
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm"><i class="fas fa-save"></i> Kullanıcı Adını Güncelle</button>
                </form>
            </div>

            <!-- E-posta -->
            <div class="glass-card glass-card--no-hover dashboard-section" style="margin-top: 24px;">
                <h3 class="dashboard-section__title"><i class="fas fa-envelope"></i> E-posta Adresi</h3>
                <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 16px;">
                    Mevcut durum:
                    <?php if (!empty($user['email_verified'])): ?>
                        <span class="status-ok"><i class="fas fa-circle-check"></i> Doğrulanmış</span>
                    <?php else: ?>
                        <span style="color:#f7971e;"><i class="fas fa-triangle-exclamation"></i> Doğrulanmamış</span>
                    <?php endif; ?>
                </p>
                <form method="POST" action="account-settings.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="change_email">
                    <div class="form-group">
                        <label class="form-label" for="email">E-posta</label>
                        <input type="email" id="email" name="email" class="form-input"
                               value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <?php if ($hasPassword): ?>
                    <div class="form-group">
                        <label class="form-label" for="email_current_password">Mevcut Şifre</label>
                        <input type="password" id="email_current_password" name="current_password" class="form-input" placeholder="Doğrulamak için şifrenizi girin" required>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn--primary btn--sm"><i class="fas fa-save"></i> E-postayı Güncelle</button>
                </form>
            </div>

            <!-- Şifre -->
            <div class="glass-card glass-card--no-hover dashboard-section" style="margin-top: 24px;">
                <h3 class="dashboard-section__title"><i class="fas fa-lock"></i> <?= $hasPassword ? 'Şifre Değiştir' : 'Şifre Belirle' ?></h3>
                <?php if (!$hasPassword): ?>
                <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 16px;">
                    Hesabınız Google ile oluşturulmuş. Bir şifre belirlerseniz e-posta + şifre ile de giriş yapabilirsiniz.
                </p>
                <?php endif; ?>
                <form method="POST" action="account-settings.php" data-validate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="change_password">
                    <?php if ($hasPassword): ?>
                    <div class="form-group">
                        <label class="form-label" for="current_password">Mevcut Şifre</label>
                        <input type="password" id="current_password" name="current_password" class="form-input" required>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label class="form-label" for="new_password">Yeni Şifre</label>
                        <input type="password" id="new_password" name="password" class="form-input" placeholder="En az 6 karakter" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Yeni Şifre (Tekrar)</label>
                        <input type="password" id="confirm_password" name="password_confirm" class="form-input" required>
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm"><i class="fas fa-save"></i> <?= $hasPassword ? 'Şifreyi Güncelle' : 'Şifreyi Belirle' ?></button>
                </form>
            </div>

            <!-- Tehlikeli Bölge -->
            <div class="glass-card glass-card--no-hover dashboard-section danger-zone" style="margin-top: 24px;">
                <h3 class="dashboard-section__title danger-title"><i class="fas fa-triangle-exclamation"></i> Tehlikeli Bölge</h3>
                <p class="text-secondary" style="font-size: 0.9rem; margin-bottom: 16px;">
                    Hesabınızı sildiğinizde profiliniz, bağlantılarınız, kişi kayıtlarınız ve tüm verileriniz
                    <strong>kalıcı olarak</strong> silinir. Bu işlem geri alınamaz.
                </p>
                <button type="button" class="btn btn--danger btn--sm" id="openDeleteModal">
                    <i class="fas fa-trash"></i> Hesabımı Sil
                </button>
            </div>

        </div>
    </div>

    <!-- Hesap Silme Modal -->
    <div class="qr-modal" id="deleteModal" aria-hidden="true">
        <div class="qr-modal__backdrop" data-delete-close></div>
        <div class="qr-modal__content" role="dialog" aria-modal="true" aria-label="Hesabı Sil" style="max-width: 420px; text-align: left;">
            <button class="qr-modal__close" data-delete-close aria-label="Kapat"><i class="fas fa-times"></i></button>
            <h2 class="qr-modal__title" style="color:#ff6b81;"><i class="fas fa-triangle-exclamation"></i> Emin misiniz?</h2>
            <p class="qr-modal__subtitle">Bu işlem geri alınamaz. Devam etmek için aşağıyı doldurun.</p>
            <form method="POST" action="account-settings.php">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="delete_account">
                <?php if ($hasPassword): ?>
                <div class="form-group">
                    <label class="form-label" for="delete_password">Şifreniz</label>
                    <input type="password" id="delete_password" name="current_password" class="form-input" placeholder="Onaylamak için şifrenizi girin" required>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label class="form-label" for="confirm_text">Onay</label>
                    <input type="text" id="confirm_text" name="confirm_text" class="form-input" placeholder="Kutuya SIL yazın" required>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn btn--danger btn--block">
                    <i class="fas fa-trash"></i> Hesabımı Kalıcı Olarak Sil
                </button>
            </form>
        </div>
    </div>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <script src="assets/js/app.js"></script>
    <script>
    (function () {
        var modal = document.getElementById('deleteModal');
        var openBtn = document.getElementById('openDeleteModal');
        if (openBtn) {
            openBtn.addEventListener('click', function () {
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
            });
        }
        modal.querySelectorAll('[data-delete-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }
        });
    })();
    </script>
</body>
</html>
