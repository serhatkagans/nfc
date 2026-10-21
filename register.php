<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');
        redirect('register.php');
    }

    $fullName = sanitize($_POST['full_name'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Server-side validation
    $errors = [];

    if (empty($fullName)) {
        $errors[] = 'Ad Soyad alanı zorunludur.';
    }

    $usernameError = validateUsername($username);
    if ($usernameError !== null) {
        $errors[] = $usernameError;
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi girin.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Şifre en az 6 karakter olmalıdır.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Şifreler eşleşmiyor.';
    }

    if (!empty($errors)) {
        flash('error', implode('<br>', $errors));
        redirect('register.php');
    }

    // Kayıt dene (doğru sıra: kullanıcı adı, e-posta, şifre, ad soyad)
    $userId = registerUser($username, $email, $password, $fullName);

    if ($userId) {
        // registerUser başarılı kayıtta oturumu da başlatır (otomatik giriş)
        // Doğrulama e-postası gönder (hata olsa bile kayıt akışını bozma)
        sendEmailVerification((int) $userId, $email, $fullName);
        flash('success', 'Hesabınız başarıyla oluşturuldu! E-posta adresinizi doğrulamak için gelen kutunuzu kontrol edin.');
        redirect('dashboard.php');
    } else {
        // registerUser ilgili hata mesajını kendisi flash'lar (ör. kullanıcı adı dolu)
        redirect('register.php');
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Google OAuth URL
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'response_type' => 'code',
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account'
]);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - <?= SITE_NAME ?></title>
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
                <h1 class="auth-card__title">Hesap Oluştur</h1>
                <p class="auth-card__subtitle">Dijital kartvizitinizi hemen oluşturun</p>
            </div>

            <!-- Google Sign Up -->
            <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="btn btn--google btn--block">
                <svg class="google-icon" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Google ile Kayıt Ol
            </a>

            <div class="form-divider">veya</div>

            <!-- Registration Form -->
            <form method="POST" action="register.php" data-validate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <!-- Full Name -->
                <div class="form-group">
                    <label class="form-label" for="full_name">Ad Soyad</label>
                    <div style="position: relative;">
                        <i class="fas fa-user form-icon form-icon--labeled"></i>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            class="form-input form-input--icon"
                            placeholder="Adınız ve soyadınız"
                            value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label class="form-label" for="username">Kullanıcı Adı</label>
                    <div style="position: relative;">
                        <i class="fas fa-at form-icon form-icon--labeled"></i>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-input form-input--icon"
                            placeholder="kullanici-adi"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            required
                            minlength="3"
                            pattern="[a-zA-Z0-9_-]+"
                        >
                    </div>
                    <div class="form-hint">Profil linkiniz: <?= SITE_URL ?>/u/<strong>kullanici-adi</strong></div>
                </div>

                <!-- Email -->
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
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Şifre</label>
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

                <!-- Password Confirm -->
                <div class="form-group">
                    <label class="form-label" for="password_confirm">Şifre Tekrar</label>
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

                <button type="submit" class="btn btn--primary btn--block btn--lg mt-lg">
                    <i class="fas fa-user-plus"></i>
                    Kayıt Ol
                </button>
            </form>

            <!-- Footer -->
            <div class="auth-card__footer">
                Zaten hesabınız var mı? <a href="login.php">Giriş Yap</a>
            </div>
        </div>
    </div>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <script src="assets/js/app.js"></script>
</body>
</html>
