<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Zaten giriş yapmışsa panele yönlendir
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Form gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');
        redirect('login.php');
    }

    $token           = $_POST['token'] ?? '';
    $password        = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    $errors = [];

    if (strlen($password) < 6) {
        $errors[] = 'Şifre en az 6 karakter olmalıdır.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Şifreler eşleşmiyor.';
    }

    if (!empty($errors)) {
        flash('error', implode('<br>', $errors));
        redirect('reset-password.php?token=' . urlencode($token));
    }

    if (completePasswordReset($token, $password)) {
        flash('success', 'Şifreniz başarıyla güncellendi. Artık yeni şifrenizle giriş yapabilirsiniz.');
        redirect('login.php');
    } else {
        flash('error', 'Bağlantı geçersiz veya süresi dolmuş. Lütfen yeniden talep edin.');
        redirect('forgot-password.php');
    }
}

// GET: token doğrula
$token      = $_GET['token'] ?? '';
$validReset = findValidPasswordReset($token);

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırla - <?= SITE_NAME ?></title>
    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-animated">

    <!-- Flash Messages -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="flash-container">
        <div class="flash flash--<?= $flash['type'] ?>">
            <i class="fas <?php
                switch($flash['type']) {
                    case 'success': echo 'fa-circle-check'; break;
                    case 'error': echo 'fa-circle-exclamation'; break;
                    case 'warning': echo 'fa-triangle-exclamation'; break;
                    default: echo 'fa-circle-info';
                }
            ?>"></i>
            <div class="flash__content"><?= $flash['message'] ?></div>
            <span class="flash__close"><i class="fas fa-times"></i></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="auth-page">
        <div class="glass-card glass-card--heavy auth-card">
            <!-- Header -->
            <div class="auth-card__header">
                <a href="<?= SITE_URL ?>" class="auth-card__logo">
                    <i class="fas fa-id-card-clip"></i>
                    <span><?= SITE_NAME ?></span>
                </a>
                <h1 class="auth-card__title">Yeni Şifre Belirle</h1>
                <p class="auth-card__subtitle">Hesabınız için yeni bir şifre oluşturun</p>
            </div>

            <?php if (!$validReset): ?>
                <!-- Geçersiz / süresi dolmuş token -->
                <div class="text-center" style="padding: 16px 0;">
                    <i class="fas fa-link-slash" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 16px;"></i>
                    <p class="text-secondary mb-lg">
                        Bu şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.
                        Lütfen yeni bir bağlantı talep edin.
                    </p>
                    <a href="forgot-password.php" class="btn btn--primary btn--block btn--lg">
                        <i class="fas fa-rotate-right"></i>
                        Yeni Bağlantı İste
                    </a>
                </div>
            <?php else: ?>
                <!-- Yeni şifre formu -->
                <form method="POST" action="reset-password.php" data-validate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="form-group">
                        <label class="form-label" for="password">Yeni Şifre</label>
                        <div style="position: relative;">
                            <i class="fas fa-lock form-icon form-icon--labeled"></i>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-input form-input--icon"
                                placeholder="En az 6 karakter"
                                required
                                minlength="6"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password_confirm">Yeni Şifre (Tekrar)</label>
                        <div style="position: relative;">
                            <i class="fas fa-lock form-icon form-icon--labeled"></i>
                            <input
                                type="password"
                                id="password_confirm"
                                name="password_confirm"
                                class="form-input form-input--icon"
                                placeholder="Şifrenizi tekrar girin"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary btn--block btn--lg">
                        <i class="fas fa-check"></i>
                        Şifreyi Güncelle
                    </button>
                </form>
            <?php endif; ?>

            <!-- Footer -->
            <div class="auth-card__footer">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Giriş ekranına dön</a>
            </div>
        </div>
    </div>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <script src="assets/js/app.js"></script>
</body>
</html>
