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
        redirect('forgot-password.php');
    }

    $email = sanitize($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Lütfen geçerli bir e-posta adresi girin.');
        redirect('forgot-password.php');
    }

    // Gizlilik: e-posta kayıtlı olsun olmasın aynı mesajı göster
    createPasswordReset($email);

    flash('success', 'Eğer bu e-posta kayıtlıysa, şifre sıfırlama bağlantısı gönderildi. Lütfen gelen kutunuzu kontrol edin.');
    redirect('forgot-password.php');
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifremi Unuttum - <?= SITE_NAME ?></title>
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
                <h1 class="auth-card__title">Şifremi Unuttum</h1>
                <p class="auth-card__subtitle">E-posta adresinizi girin, size sıfırlama bağlantısı gönderelim</p>
            </div>

            <!-- Form -->
            <form method="POST" action="forgot-password.php" data-validate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="form-group">
                    <label class="form-label" for="email">E-posta</label>
                    <div style="position: relative;">
                        <i class="fas fa-envelope form-icon form-icon--labeled"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input form-input--icon"
                            placeholder="ornek@email.com"
                            required
                        >
                    </div>
                </div>

                <button type="submit" class="btn btn--primary btn--block btn--lg">
                    <i class="fas fa-paper-plane"></i>
                    Sıfırlama Bağlantısı Gönder
                </button>
            </form>

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
