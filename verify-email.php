<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// --- POST: Doğrulama e-postasını yeniden gönder ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');
        redirect('dashboard.php');
    }

    $user = getCurrentUser();
    if (!$user) {
        redirect('login.php');
    }

    if (!empty($user['email_verified'])) {
        flash('info', 'E-posta adresiniz zaten doğrulanmış.');
        redirect('dashboard.php');
    }

    sendEmailVerification((int) $user['id'], $user['email'], $user['full_name'] ?? '');
    flash('success', 'Doğrulama e-postası gönderildi. Lütfen gelen kutunuzu kontrol edin.');
    redirect('dashboard.php');
}

// --- GET: token doğrula ---
$token   = $_GET['token'] ?? '';
$success = verifyEmailToken($token);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-posta Doğrulama - <?= SITE_NAME ?></title>
    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-animated">

    <div class="auth-page">
        <div class="glass-card glass-card--heavy auth-card text-center">
            <div class="auth-card__header">
                <a href="<?= SITE_URL ?>" class="auth-card__logo">
                    <i class="fas fa-id-card-clip"></i>
                    <span><?= SITE_NAME ?></span>
                </a>
            </div>

            <?php if ($success): ?>
                <i class="fas fa-circle-check" style="font-size: 3.5rem; color: #00d2ff; margin-bottom: 16px;"></i>
                <h1 class="auth-card__title">E-posta Doğrulandı</h1>
                <p class="text-secondary mt-sm mb-lg">
                    Teşekkürler! E-posta adresiniz başarıyla doğrulandı.
                </p>
                <a href="<?= isLoggedIn() ? 'dashboard.php' : 'login.php' ?>" class="btn btn--primary btn--block btn--lg">
                    <i class="fas fa-arrow-right"></i>
                    <?= isLoggedIn() ? 'Panele Git' : 'Giriş Yap' ?>
                </a>
            <?php else: ?>
                <i class="fas fa-circle-exclamation" style="font-size: 3.5rem; color: #f5576c; margin-bottom: 16px;"></i>
                <h1 class="auth-card__title">Bağlantı Geçersiz</h1>
                <p class="text-secondary mt-sm mb-lg">
                    Bu doğrulama bağlantısı geçersiz veya süresi dolmuş. Panelinizden yeni bir
                    doğrulama e-postası talep edebilirsiniz.
                </p>
                <a href="<?= isLoggedIn() ? 'dashboard.php' : 'login.php' ?>" class="btn btn--primary btn--block btn--lg">
                    <i class="fas fa-arrow-right"></i>
                    <?= isLoggedIn() ? 'Panele Git' : 'Giriş Yap' ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <script src="assets/js/app.js"></script>
</body>
</html>
