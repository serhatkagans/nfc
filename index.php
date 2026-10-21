<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Redirect logged-in users to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = SITE_NAME . ' - Dijital Kartvizitiniz';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="NFC Kartvizit ile dijital kartvizitinizi oluşturun. NFC etiketinizle tek dokunuşla paylaşın.">
    <meta property="og:title" content="<?= htmlspecialchars(SITE_NAME) ?>">
    <meta property="og:description" content="Dijital kartvizitinizi oluşturun ve NFC ile paylaşın.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= SITE_URL ?>">
    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-animated">

    <!-- Header -->
    <header class="header">
        <div class="container">
            <a href="<?= SITE_URL ?>" class="header__logo">
                <i class="fas fa-id-card-clip"></i>
                <span><?= SITE_NAME ?></span>
            </a>

            <div class="mobile-menu-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <nav class="header__nav">
                <a href="#ozellikler">Özellikler</a>
                <a href="#nasil-calisir">Nasıl Çalışır</a>
                <a href="login.php" class="btn btn--outline btn--sm">Giriş Yap</a>
                <a href="register.php" class="btn btn--primary btn--sm">Kayıt Ol</a>
            </nav>
        </div>
    </header>

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
            <div class="flash__content"><?= htmlspecialchars($flash['message']) ?></div>
            <span class="flash__close"><i class="fas fa-times"></i></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero__content">
                <div class="hero__badge">
                    <i class="fas fa-bolt"></i>
                    <span>Geleceğin Kartviziti</span>
                </div>

                <h1 class="hero__title">
                    <span class="text-gradient">Dijital Kartvizitinizi</span><br>
                    Tek Dokunuşla Paylaşın
                </h1>

                <p class="hero__subtitle subtitle">
                    NFC teknolojisi ile kağıt kartvizitlere veda edin. Profesyonel dijital
                    kartvizitinizi oluşturun ve tek bir dokunuşla paylaşın.
                </p>

                <div class="hero__cta">
                    <a href="register.php" class="btn btn--primary btn--lg">
                        <i class="fas fa-rocket"></i>
                        Hemen Başla
                    </a>
                    <a href="#ozellikler" class="btn btn--outline btn--lg">
                        <i class="fas fa-arrow-down"></i>
                        Daha Fazla
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="ozellikler">
        <div class="container">
            <div class="text-center">
                <h2><span class="text-gradient">Neden NFC Kartvizit?</span></h2>
                <p class="subtitle mt-md">Modern dünyada, modern bir kartvizit deneyimi</p>
            </div>

            <div class="features__grid stagger-children">
                <!-- Feature 1 -->
                <div class="glass-card feature-card animate-on-scroll">
                    <div class="feature-card__icon">
                        <i class="fas fa-wifi"></i>
                    </div>
                    <h3 class="feature-card__title">NFC ile Paylaş</h3>
                    <p class="feature-card__desc">
                        Telefonunuzu NFC etiketine yaklaştırın, dijital kartvizitiniz anında karşı tarafın ekranında görünsün. Uygulama indirmeye gerek yok.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="glass-card feature-card animate-on-scroll">
                    <div class="feature-card__icon">
                        <i class="fas fa-sliders"></i>
                    </div>
                    <h3 class="feature-card__title">Kolay Yönet</h3>
                    <p class="feature-card__desc">
                        Bilgilerinizi istediğiniz zaman güncelleyin. Telefon numarası, sosyal medya, e-posta — hepsini tek panelden yönetin.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="glass-card feature-card animate-on-scroll">
                    <div class="feature-card__icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h3 class="feature-card__title">Her Yerde Erişim</h3>
                    <p class="feature-card__desc">
                        Kartvizitiniz her cihazda, her tarayıcıda mükemmel görünür. QR kod veya link ile de paylaşabilirsiniz.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works" id="nasil-calisir">
        <div class="container">
            <div class="text-center">
                <h2><span class="text-gradient">Nasıl Çalışır?</span></h2>
                <p class="subtitle mt-md">3 basit adımda dijital kartvizitinizi oluşturun</p>
            </div>

            <div class="steps stagger-children">
                <div class="glass-card step animate-on-scroll">
                    <div class="step__number">1</div>
                    <h3 class="step__title">Kayıt Ol</h3>
                    <p class="step__desc">Ücretsiz hesabınızı oluşturun. E-posta veya Google ile hızlıca kaydolun.</p>
                </div>

                <div class="glass-card step animate-on-scroll">
                    <div class="step__number">2</div>
                    <h3 class="step__title">Bilgilerini Gir</h3>
                    <p class="step__desc">İletişim bilgilerinizi, sosyal medya hesaplarınızı ve profil fotoğrafınızı ekleyin.</p>
                </div>

                <div class="glass-card step animate-on-scroll">
                    <div class="step__number">3</div>
                    <h3 class="step__title">NFC Etiketine Yaz</h3>
                    <p class="step__desc">Profil linkinizi NFC etiketine yazın ve tek dokunuşla paylaşmaya başlayın.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section style="padding: 96px 0;">
        <div class="container text-center">
            <div class="glass-card glass-card--heavy" style="max-width: 600px; margin: 0 auto; padding: 48px;">
                <h2 class="mb-lg"><span class="text-gradient">Hazır mısınız?</span></h2>
                <p class="subtitle mb-xl">Dijital kartvizitinizi şimdi oluşturun ve profesyonel ağınızı genişletin.</p>
                <a href="register.php" class="btn btn--primary btn--lg">
                    <i class="fas fa-arrow-right"></i>
                    Ücretsiz Başla
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Tüm hakları saklıdır.</p>
        </div>
    </footer>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <script src="assets/js/app.js"></script>
</body>
</html>
