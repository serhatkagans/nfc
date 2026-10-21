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
        redirect('login.php');
    }

    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Server-side validation
    if (empty($email) || empty($password)) {
        flash('error', 'Lütfen tüm alanları doldurun.');
        redirect('login.php');
    }

    // Attempt login (hatalı girişte loginUser ilgili mesajı kendisi flash'lar)
    $user = loginUser($email, $password);

    if ($user) {
        flash('success', 'Başarıyla giriş yaptınız. Hoş geldiniz!');
        redirect('dashboard.php');
    } else {
        redirect('login.php');
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
    <title>Giriş Yap - <?= SITE_NAME ?></title>
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
                <h1 class="auth-card__title">Tekrar Hoş Geldiniz</h1>
                <p class="auth-card__subtitle">Hesabınıza giriş yapın</p>
            </div>

            <!-- Google Sign In -->
            <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="btn btn--google btn--block">
                <svg class="google-icon" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Google ile Giriş Yap
            </a>

            <div class="form-divider">veya</div>

            <!-- Login Form -->
            <form method="POST" action="login.php" data-validate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

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
                            placeholder="Şifrenizi girin"
                            required
                        >
                    </div>
                </div>

                <!-- Remember Me + Şifremi Unuttum -->
                <div class="form-group flex-between" style="flex-wrap: wrap; gap: 8px;">
                    <label class="form-checkbox">
                        <input type="checkbox" name="remember" value="1">
                        <span>Beni Hatırla</span>
                    </label>
                    <a href="forgot-password.php" style="font-size: 0.875rem; color: var(--text-secondary);">Şifremi unuttum?</a>
                </div>

                <button type="submit" class="btn btn--primary btn--block btn--lg">
                    <i class="fas fa-right-to-bracket"></i>
                    Giriş Yap
                </button>
            </form>

            <!-- Footer -->
            <div class="auth-card__footer">
                Hesabınız yok mu? <a href="register.php">Kayıt Ol</a>
            </div>
        </div>
    </div>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <script src="assets/js/app.js"></script>
</body>
</html>
