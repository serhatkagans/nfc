<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Require login
requireLogin();

// Get current user
$user = getCurrentUser();

if (!$user) {
    flash('error', 'Oturum süresi dolmuş. Lütfen tekrar giriş yapın.');
    redirect('login.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error','Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');
        redirect('dashboard.php');
    }

    $action = $_POST['action'] ?? 'update_profile';

    // --- Özel bağlantı ekle ---
    if ($action === 'add_link') {
        $linkTitle = sanitize($_POST['link_title'] ?? '');
        $linkUrl   = trim($_POST['link_url'] ?? '');
        $linkIcon  = sanitizeLinkIcon($_POST['link_icon'] ?? '');

        if ($linkTitle === '' || $linkUrl === '') {
            flash('error', 'Bağlantı başlığı ve adresi zorunludur.');
            redirect('dashboard.php');
        }
        // http/https yoksa başına ekle
        if (!preg_match('~^https?://~i', $linkUrl)) {
            $linkUrl = 'https://' . $linkUrl;
        }
        if (!filter_var($linkUrl, FILTER_VALIDATE_URL)) {
            flash('error', 'Geçerli bir bağlantı adresi girin.');
            redirect('dashboard.php');
        }
        // En fazla 20 bağlantı
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM user_links WHERE user_id = ?");
        $cnt->execute([$user['id']]);
        if ((int)$cnt->fetchColumn() >= 20) {
            flash('error', 'En fazla 20 özel bağlantı ekleyebilirsiniz.');
            redirect('dashboard.php');
        }
        $ord = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM user_links WHERE user_id = ?");
        $ord->execute([$user['id']]);
        $sortOrder = (int)$ord->fetchColumn();

        $ins = $pdo->prepare("INSERT INTO user_links (user_id, title, url, icon, sort_order) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$user['id'], $linkTitle, $linkUrl, $linkIcon, $sortOrder]);
        flash('success', 'Bağlantı eklendi.');
        redirect('dashboard.php');
    }

    // --- Özel bağlantı sil ---
    if ($action === 'delete_link') {
        $linkId = (int)($_POST['link_id'] ?? 0);
        if ($linkId > 0) {
            $del = $pdo->prepare("DELETE FROM user_links WHERE id = ? AND user_id = ?");
            $del->execute([$linkId, $user['id']]);
            flash('success', 'Bağlantı silindi.');
        }
        redirect('dashboard.php');
    }

    // --- NFC hedefi: kartvizit mi portfolyo mu? ---
    if ($action === 'set_nfc_target') {
        $target = sanitizeNfcTarget($_POST['nfc_target'] ?? 'card');
        try {
            $pdo->prepare('UPDATE users SET nfc_target = ? WHERE id = ?')->execute([$target, $user['id']]);
            $msg = 'NFC etiketi artık ' . getNfcTargets()[$target] . ' sayfasını açacak.';
            if ($target === 'portfolio' && (int) getPortfolio((int) $user['id'])['is_enabled'] !== 1) {
                $msg .= ' Not: Portfolyonuz yayında olmadığı için şimdilik kartvizit açılır.';
                flash('warning', $msg);
            } else {
                flash('success', $msg);
            }
        } catch (PDOException $e) {
            flash('error', 'Kaydedilemedi. Veritabanı güncellemesi (migration_v4.sql) yapıldı mı?');
        }
        redirect('dashboard.php');
    }

    // --- Profil güncelle ---
    // Collect form data
    $fullName = sanitize($_POST['full_name'] ?? '');
    $title = sanitize($_POST['title'] ?? '');
    $company = sanitize($_POST['company'] ?? '');
    $bio = sanitize($_POST['bio'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $contactEmail = sanitize($_POST['contact_email'] ?? '');
    $instagram = sanitize($_POST['instagram'] ?? '');
    $linkedin = sanitize($_POST['linkedin'] ?? '');
    $facebook = sanitize($_POST['facebook'] ?? '');
    $youtube = sanitize($_POST['youtube'] ?? '');
    $whatsapp = sanitize($_POST['whatsapp'] ?? '');
    $tiktok = sanitize($_POST['tiktok'] ?? '');
    $telegram = sanitize($_POST['telegram'] ?? '');
    $twitter = sanitize($_POST['twitter'] ?? '');
    $website = sanitize($_POST['website'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $mapUrl = trim($_POST['map_url'] ?? '');
    $workingHours = sanitize($_POST['working_hours'] ?? '');
    $cardTheme = sanitizeCardTheme($_POST['card_theme'] ?? 'default');

    // Validate
    $errors = [];
    if (empty($fullName)) {
        $errors[] = 'Ad Soyad alanı zorunludur.';
    }

    if (!empty($bio) && mb_strlen($bio) > 300) {
        $errors[] = 'Biyografi en fazla 300 karakter olabilir.';
    }

    if (!empty($contactEmail) && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir iletişim e-postası girin.';
    }

    // Harita bağlantısı: boş değilse http/https ekle ve doğrula
    if ($mapUrl !== '') {
        if (!preg_match('~^https?://~i', $mapUrl)) {
            $mapUrl = 'https://' . $mapUrl;
        }
        if (!filter_var($mapUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Geçerli bir harita bağlantısı girin.';
        }
    }

    if (!empty($errors)) {
        flash('error',implode('<br>', $errors));
        redirect('dashboard.php');
    }

    // Handle profile image upload
    $profileImage = $user['profile_image']; // Keep existing
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadProfileImage($_FILES['profile_image']);
        if ($uploadResult) {
            // Delete old image if exists
            if (!empty($user['profile_image'])) {
                deleteProfileImage($user['profile_image']);
            }
            $profileImage = $uploadResult;
        } else {
            redirect('dashboard.php');
        }
    }

    // Update database
    try {
        $stmt = $pdo->prepare("
            UPDATE users SET
                full_name = ?,
                title = ?,
                company = ?,
                bio = ?,
                phone = ?,
                email = ?,
                instagram = ?,
                linkedin = ?,
                facebook = ?,
                youtube = ?,
                whatsapp = ?,
                tiktok = ?,
                telegram = ?,
                twitter = ?,
                website = ?,
                address = ?,
                map_url = ?,
                working_hours = ?,
                card_theme = ?,
                profile_image = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $fullName,
            $title,
            $company,
            $bio,
            $phone,
            $contactEmail ?: $user['email'],
            $instagram,
            $linkedin,
            $facebook,
            $youtube,
            $whatsapp,
            $tiktok,
            $telegram,
            $twitter,
            $website,
            $address,
            $mapUrl,
            $workingHours,
            $cardTheme,
            $profileImage,
            $user['id']
        ]);

        flash('success', 'Profiliniz başarıyla güncellendi!');
    } catch (PDOException $e) {
        flash('error','Bir hata oluştu. Lütfen tekrar deneyin.');
    }

    redirect('dashboard.php');
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Özel bağlantılar + ikon seçenekleri
$userLinks   = getUserLinks((int)$user['id']);
$iconOptions = getLinkIconOptions();

// Profil kart temaları + seçili tema
$cardThemes  = getCardThemes();
$currentTheme = sanitizeCardTheme($user['card_theme'] ?? 'default');

// Profile URL
$profileUrl = SITE_URL . '/u/' . $user['username'];

// Kalıcı NFC kısa linki (kullanıcı adı değişse de bozulmaz)
$nfcCode = ensureNfcCode((int) $user['id']);
$nfcUrl  = SITE_URL . '/t/' . $nfcCode;

// NFC okutulunca açılacak sayfa
$nfcTarget        = sanitizeNfcTarget($user['nfc_target'] ?? 'card');
$portfolioEnabled = (int) getPortfolio((int) $user['id'])['is_enabled'] === 1;

// Generate initials
$initials = '';
$nameParts = explode(' ', $user['full_name'] ?? '');
if (count($nameParts) >= 2) {
    $initials = mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[1], 0, 1));
} elseif (count($nameParts) === 1) {
    $initials = mb_strtoupper(mb_substr($nameParts[0], 0, 2));
}

$profileImage = !empty($user['profile_image']) ? SITE_URL . '/uploads/' . $user['profile_image'] : '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontrol Paneli - <?= SITE_NAME ?></title>
    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-animated">

    <?php $activeNav = 'dashboard'; require __DIR__ . '/partials/user-header.php'; ?>

    <!-- Dashboard Content -->
    <div class="dashboard">
        <div class="container">

            <!-- E-posta Doğrulama Banner -->
            <?php if (empty($user['email_verified']) && empty($user['google_id'])): ?>
            <div class="verify-banner">
                <div class="verify-banner__text">
                    <i class="fas fa-envelope-circle-check"></i>
                    <span><strong>E-postanızı doğrulayın.</strong> Hesabınızın güvenliği için <?= htmlspecialchars($user['email']) ?> adresini doğrulamanız önerilir.</span>
                </div>
                <form method="POST" action="verify-email.php" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="resend">
                    <button type="submit" class="btn btn--glass btn--sm">
                        <i class="fas fa-paper-plane"></i> Doğrulama E-postası Gönder
                    </button>
                </form>
            </div>
            <?php endif; ?>


            <!-- Dashboard Header -->
            <div class="dashboard__header flex-between" style="flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 class="dashboard__title">
                        Merhaba, <span class="text-gradient"><?= htmlspecialchars($user['full_name'] ?? 'Kullanıcı') ?></span> 👋
                    </h1>
                    <p class="text-secondary mt-sm">Dijital kartvizitinizi buradan yönetin.</p>
                    <p class="text-secondary mt-sm">
                        <i class="fas fa-eye"></i>
                        Profiliniz <strong class="text-gradient"><?= number_format((int)($user['view_count'] ?? 0)) ?></strong> kez görüntülendi
                    </p>
                </div>
                <a href="profile.php?username=<?= htmlspecialchars($user['username']) ?>" target="_blank" class="btn btn--primary btn--sm">
                    <i class="fas fa-external-link-alt"></i>
                    Profilimi Gör
                </a>
            </div>

            <!-- NFC URL Box -->
            <div class="glass-card glass-card--no-hover mb-xl" style="padding: 20px 24px;">
                <div class="flex-between" style="flex-wrap: wrap; gap: 12px;">
                    <div>
                        <div class="text-secondary" style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                            <i class="fas fa-bolt"></i> Kalıcı NFC Linkiniz
                        </div>
                        <div class="nfc-url-box__url" style="font-size: 1rem;"><?= $nfcUrl ?></div>
                        <div class="form-hint" style="margin-top: 6px;">
                            <i class="fas fa-circle-info"></i>
                            Bu linki NFC etiketine yazın. Kullanıcı adınızı değiştirseniz bile <strong>etiket çalışmaya devam eder</strong>.
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="btn btn--primary btn--sm" id="nfcWriteBtn" data-nfc-url="<?= $nfcUrl ?>">
                            <i class="fas fa-wifi"></i>
                            NFC'ye Yaz
                        </button>
                        <button type="button" class="btn btn--glass btn--sm" id="qrToggleBtn">
                            <i class="fas fa-qrcode"></i>
                            QR Kod
                        </button>
                        <button class="btn btn--glass btn--sm" data-copy="<?= $nfcUrl ?>">
                            <i class="fas fa-copy"></i>
                            Kopyala
                        </button>
                    </div>
                </div>
                <div id="nfcWriteStatus" class="nfc-write-status" hidden></div>

                <!-- NFC okutulunca hangi sayfa açılsın? -->
                <form method="POST" action="dashboard.php" class="nfc-target-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="set_nfc_target">
                    <div class="text-secondary" style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px;">
                        <i class="fas fa-route"></i> NFC okutulunca açılacak sayfa
                    </div>
                    <div class="theme-grid">
                        <label class="theme-option<?= $nfcTarget === 'card' ? ' selected' : '' ?>">
                            <input type="radio" name="nfc_target" value="card" <?= $nfcTarget === 'card' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <i class="fas fa-id-card"></i>
                            <span class="theme-name">Dijital Kartvizit</span>
                        </label>
                        <label class="theme-option<?= $nfcTarget === 'portfolio' ? ' selected' : '' ?>">
                            <input type="radio" name="nfc_target" value="portfolio" <?= $nfcTarget === 'portfolio' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <i class="fas fa-graduation-cap"></i>
                            <span class="theme-name">Öğrenci Portfolyosu</span>
                        </label>
                    </div>
                    <div class="form-hint" style="margin-top: 8px;">
                        Etikete tekrar yazmanıza gerek yok; seçim anında geçerli olur.
                        <?php if (!$portfolioEnabled): ?>
                            Portfolyo için önce <a href="portfolio-edit.php">portfolyonuzu oluşturup yayınlayın</a>.
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Main Form -->
            <form method="POST" action="dashboard.php" enctype="multipart/form-data" data-validate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="dashboard__grid">

                    <!-- Profile Image & Personal Info -->
                    <div class="glass-card glass-card--no-hover dashboard-section">
                        <h3 class="dashboard-section__title">
                            <i class="fas fa-user-circle"></i>
                            Kişisel Bilgiler
                        </h3>

                        <!-- Avatar Upload -->
                        <div class="avatar-upload">
                            <div class="avatar avatar--lg avatar--bordered">
                                <?php if ($profileImage): ?>
                                    <img src="<?= $profileImage ?>" alt="Profil" id="profile-image-preview">
                                <?php else: ?>
                                    <div class="avatar__initials" id="avatar-initials"><?= $initials ?: '?' ?></div>
                                <?php endif; ?>
                            </div>
                            <label class="avatar-upload__overlay" for="profile-image-input">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" id="profile-image-input" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>
                        <p class="text-center text-muted" style="font-size: 0.8rem; margin-bottom: 24px;">Fotoğraf yüklemek için tıklayın (Maks. 2MB)</p>

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
                                    value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
                                    required
                                >
                            </div>
                        </div>

                        <!-- Title (Ünvan) -->
                        <div class="form-group">
                            <label class="form-label" for="title">Ünvan / Pozisyon</label>
                            <div style="position: relative;">
                                <i class="fas fa-briefcase form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    class="form-input form-input--icon"
                                    placeholder="Örn. Pazarlama Müdürü"
                                    value="<?= htmlspecialchars($user['title'] ?? '') ?>"
                                    maxlength="120"
                                >
                            </div>
                        </div>

                        <!-- Company (Şirket) -->
                        <div class="form-group">
                            <label class="form-label" for="company">Şirket / Kurum</label>
                            <div style="position: relative;">
                                <i class="fas fa-building form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="company"
                                    name="company"
                                    class="form-input form-input--icon"
                                    placeholder="Örn. ABC Teknoloji A.Ş."
                                    value="<?= htmlspecialchars($user['company'] ?? '') ?>"
                                    maxlength="120"
                                >
                            </div>
                        </div>

                        <!-- Bio -->
                        <div class="form-group">
                            <label class="form-label" for="bio">Biyografi</label>
                            <textarea
                                id="bio"
                                name="bio"
                                class="form-input"
                                placeholder="Kendinizi kısaca tanıtın..."
                                rows="3"
                                data-maxlength="300"
                            ><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Contact Info -->
                    <div class="glass-card glass-card--no-hover dashboard-section">
                        <h3 class="dashboard-section__title">
                            <i class="fas fa-address-card"></i>
                            İletişim Bilgileri
                        </h3>

                        <!-- Phone -->
                        <div class="form-group">
                            <label class="form-label" for="phone">Telefon</label>
                            <div style="position: relative;">
                                <i class="fas fa-phone form-icon form-icon--labeled"></i>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    class="form-input form-input--icon"
                                    placeholder="+90 5XX XXX XX XX"
                                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <!-- Contact Email -->
                        <div class="form-group">
                            <label class="form-label" for="contact_email">İletişim E-postası</label>
                            <div style="position: relative;">
                                <i class="fas fa-envelope form-icon form-icon--labeled"></i>
                                <input
                                    type="email"
                                    id="contact_email"
                                    name="contact_email"
                                    class="form-input form-input--icon"
                                    placeholder="iletisim@email.com"
                                    value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                >
                            </div>
                            <div class="form-hint">Kartvizitinizde gösterilecek e-posta adresi</div>
                        </div>

                        <!-- Address -->
                        <div class="form-group">
                            <label class="form-label" for="address">Adres</label>
                            <div style="position: relative;">
                                <i class="fas fa-location-dot form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="address"
                                    name="address"
                                    class="form-input form-input--icon"
                                    placeholder="Örn. Levent, İstanbul"
                                    value="<?= htmlspecialchars($user['address'] ?? '') ?>"
                                    maxlength="255"
                                >
                            </div>
                        </div>

                        <!-- Map URL -->
                        <div class="form-group">
                            <label class="form-label" for="map_url">Harita Bağlantısı</label>
                            <div style="position: relative;">
                                <i class="fas fa-map-location-dot form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="map_url"
                                    name="map_url"
                                    class="form-input form-input--icon"
                                    placeholder="Google Maps bağlantısı"
                                    value="<?= htmlspecialchars($user['map_url'] ?? '') ?>"
                                >
                            </div>
                            <div class="form-hint">Profilde "Yol Tarifi" butonu olarak görünür</div>
                        </div>

                        <!-- Working Hours -->
                        <div class="form-group">
                            <label class="form-label" for="working_hours">Çalışma Saatleri</label>
                            <div style="position: relative;">
                                <i class="fas fa-clock form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="working_hours"
                                    name="working_hours"
                                    class="form-input form-input--icon"
                                    placeholder="Örn. Hafta içi 09:00 - 18:00"
                                    value="<?= htmlspecialchars($user['working_hours'] ?? '') ?>"
                                    maxlength="500"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Social Media -->
                    <div class="glass-card glass-card--no-hover dashboard-section">
                        <h3 class="dashboard-section__title">
                            <i class="fas fa-share-nodes"></i>
                            Sosyal Medya
                        </h3>

                        <!-- Instagram -->
                        <div class="form-group">
                            <label class="form-label" for="instagram">Instagram</label>
                            <div style="position: relative;">
                                <i class="fab fa-instagram form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="instagram"
                                    name="instagram"
                                    class="form-input form-input--icon"
                                    placeholder="kullanici_adi"
                                    value="<?= htmlspecialchars($user['instagram'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <!-- LinkedIn -->
                        <div class="form-group">
                            <label class="form-label" for="linkedin">LinkedIn</label>
                            <div style="position: relative;">
                                <i class="fab fa-linkedin-in form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="linkedin"
                                    name="linkedin"
                                    class="form-input form-input--icon"
                                    placeholder="kullanici-adi"
                                    value="<?= htmlspecialchars($user['linkedin'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <!-- Facebook -->
                        <div class="form-group">
                            <label class="form-label" for="facebook">Facebook</label>
                            <div style="position: relative;">
                                <i class="fab fa-facebook-f form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="facebook"
                                    name="facebook"
                                    class="form-input form-input--icon"
                                    placeholder="kullanici.adi"
                                    value="<?= htmlspecialchars($user['facebook'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <!-- YouTube -->
                        <div class="form-group">
                            <label class="form-label" for="youtube">YouTube</label>
                            <div style="position: relative;">
                                <i class="fab fa-youtube form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="youtube"
                                    name="youtube"
                                    class="form-input form-input--icon"
                                    placeholder="kanal_adi"
                                    value="<?= htmlspecialchars($user['youtube'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <!-- WhatsApp -->
                        <div class="form-group">
                            <label class="form-label" for="whatsapp">WhatsApp</label>
                            <div style="position: relative;">
                                <i class="fab fa-whatsapp form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="whatsapp"
                                    name="whatsapp"
                                    class="form-input form-input--icon"
                                    placeholder="905XXXXXXXXX (ülke kodlu)"
                                    value="<?= htmlspecialchars($user['whatsapp'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <!-- TikTok -->
                        <div class="form-group">
                            <label class="form-label" for="tiktok">TikTok</label>
                            <div style="position: relative;">
                                <i class="fab fa-tiktok form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="tiktok"
                                    name="tiktok"
                                    class="form-input form-input--icon"
                                    placeholder="kullanici_adi"
                                    value="<?= htmlspecialchars($user['tiktok'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <!-- Telegram -->
                        <div class="form-group">
                            <label class="form-label" for="telegram">Telegram</label>
                            <div style="position: relative;">
                                <i class="fab fa-telegram form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="telegram"
                                    name="telegram"
                                    class="form-input form-input--icon"
                                    placeholder="kullanici_adi"
                                    value="<?= htmlspecialchars($user['telegram'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <!-- X (Twitter) -->
                        <div class="form-group">
                            <label class="form-label" for="twitter">X (Twitter)</label>
                            <div style="position: relative;">
                                <i class="fab fa-x-twitter form-icon form-icon--labeled"></i>
                                <input
                                    type="text"
                                    id="twitter"
                                    name="twitter"
                                    class="form-input form-input--icon"
                                    placeholder="kullanici_adi"
                                    value="<?= htmlspecialchars($user['twitter'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <!-- Website -->
                        <div class="form-group">
                            <label class="form-label" for="website">Web Sitesi</label>
                            <div style="position: relative;">
                                <i class="fas fa-globe form-icon form-icon--labeled"></i>
                                <input
                                    type="url"
                                    id="website"
                                    name="website"
                                    class="form-input form-input--icon"
                                    placeholder="https://example.com"
                                    value="<?= htmlspecialchars($user['website'] ?? '') ?>"
                                >
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Profil Teması -->
                <div class="glass-card glass-card--no-hover dashboard-section" style="margin-top: 24px;">
                    <h3 class="dashboard-section__title">
                        <i class="fas fa-palette"></i>
                        Profil Teması
                    </h3>
                    <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 20px;">
                        Kartvizit sayfanızın renk paletini seçin.
                    </p>
                    <div class="theme-grid">
                        <?php foreach ($cardThemes as $key => $theme): ?>
                        <label class="theme-option<?= $currentTheme === $key ? ' selected' : '' ?>">
                            <input type="radio" name="card_theme" value="<?= htmlspecialchars($key) ?>" <?= $currentTheme === $key ? 'checked' : '' ?>>
                            <span class="theme-swatch" style="background: linear-gradient(135deg, <?= $theme['start'] ?>, <?= $theme['end'] ?>);"></span>
                            <span class="theme-name"><?= htmlspecialchars($theme['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="text-center mt-xl" style="padding-bottom: 32px;">
                    <button type="submit" class="btn btn--primary btn--lg">
                        <i class="fas fa-check"></i>
                        Değişiklikleri Kaydet
                    </button>
                </div>
            </form>

            <!-- Özel Bağlantılar -->
            <div class="glass-card glass-card--no-hover dashboard-section" style="margin-top: 8px;">
                <h3 class="dashboard-section__title">
                    <i class="fas fa-link"></i>
                    Özel Bağlantılar
                </h3>
                <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 20px;">
                    Hazır platformlar dışında istediğiniz bağlantıları ekleyin (mağaza, blog, randevu linki vb.). En fazla 20 adet.
                </p>

                <!-- Mevcut bağlantılar -->
                <?php if (!empty($userLinks)): ?>
                <div class="links-list">
                    <?php foreach ($userLinks as $link): ?>
                    <div class="link-item">
                        <div class="link-item__icon"><i class="<?= htmlspecialchars($link['icon']) ?>"></i></div>
                        <div class="link-item__info">
                            <div class="link-item__title"><?= htmlspecialchars($link['title']) ?></div>
                            <a class="link-item__url" href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($link['url']) ?></a>
                        </div>
                        <form method="POST" action="dashboard.php" onsubmit="return confirm('Bu bağlantı silinsin mi?')">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="delete_link">
                            <input type="hidden" name="link_id" value="<?= (int)$link['id'] ?>">
                            <button type="submit" class="btn btn--glass btn--sm link-item__delete" title="Sil">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 20px;">Henüz özel bağlantı eklemediniz.</p>
                <?php endif; ?>

                <!-- Yeni bağlantı ekle -->
                <form method="POST" action="dashboard.php" class="add-link-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="add_link">

                    <div class="add-link-grid">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="link_title">Başlık</label>
                            <input type="text" id="link_title" name="link_title" class="form-input" placeholder="Örn. Online Mağazam" maxlength="80" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="link_url">Bağlantı (URL)</label>
                            <input type="text" id="link_url" name="link_url" class="form-input" placeholder="https://..." required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="link_icon">İkon</label>
                            <select id="link_icon" name="link_icon" class="form-input">
                                <?php foreach ($iconOptions as $cls => $label): ?>
                                <option value="<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-md">
                        <button type="submit" class="btn btn--primary btn--sm">
                            <i class="fas fa-plus"></i> Bağlantı Ekle
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <!-- QR Kod Modal -->
    <div class="qr-modal" id="qrModal" aria-hidden="true">
        <div class="qr-modal__backdrop" data-qr-close></div>
        <div class="qr-modal__content" role="dialog" aria-modal="true" aria-label="QR Kod">
            <button class="qr-modal__close" data-qr-close aria-label="Kapat"><i class="fas fa-times"></i></button>
            <h2 class="qr-modal__title">Profil QR Kodunuz</h2>
            <p class="qr-modal__subtitle">Bu kodu kartvizit, poster veya vitrininizde kullanabilirsiniz</p>
            <div class="qr-modal__code" id="qrCode"></div>
            <button class="btn btn--primary btn--sm" id="qrDownloadBtn">
                <i class="fas fa-download"></i> QR Kodu İndir
            </button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    (function () {
        var profileUrl = <?= json_encode($nfcUrl . '?src=qr') ?>;
        var modal = document.getElementById('qrModal');
        var openBtn = document.getElementById('qrToggleBtn');
        var qrContainer = document.getElementById('qrCode');
        var downloadBtn = document.getElementById('qrDownloadBtn');
        var qrInstance = null;

        function openModal() {
            if (!qrInstance && window.QRCode) {
                qrInstance = new QRCode(qrContainer, {
                    text: profileUrl,
                    width: 240,
                    height: 240,
                    colorDark: '#0a0a1a',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }

        if (openBtn) openBtn.addEventListener('click', openModal);

        modal.querySelectorAll('[data-qr-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });

        if (downloadBtn) {
            downloadBtn.addEventListener('click', function () {
                var img = qrContainer.querySelector('img');
                var canvas = qrContainer.querySelector('canvas');
                var dataUrl = img ? img.src : (canvas ? canvas.toDataURL('image/png') : null);
                if (!dataUrl) return;
                var a = document.createElement('a');
                a.href = dataUrl;
                a.download = <?= json_encode($user['username'] . '-qr.png') ?>;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            });
        }
    })();
    </script>

    <!-- Web NFC: tarayıcıdan etikete yazma (Android Chrome) -->
    <script>
    (function () {
        var btn = document.getElementById('nfcWriteBtn');
        var status = document.getElementById('nfcWriteStatus');
        if (!btn) return;

        function setStatus(type, html) {
            if (!status) return;
            status.hidden = false;
            status.className = 'nfc-write-status nfc-write-status--' + type;
            status.innerHTML = html;
        }

        // Web NFC desteklenmiyorsa kullanıcıyı bilgilendir
        if (!('NDEFReader' in window)) {
            btn.addEventListener('click', function () {
                setStatus('warn',
                    '<i class="fas fa-triangle-exclamation"></i> Bu cihaz/tarayıcı NFC yazmayı desteklemiyor. ' +
                    'Lütfen <strong>Android telefonda Chrome</strong> ile açın veya linki kopyalayıp bir NFC uygulamasıyla yazın.');
            });
            return;
        }

        btn.addEventListener('click', async function () {
            var url = btn.getAttribute('data-nfc-url');
            setStatus('info', '<i class="fas fa-spinner fa-spin"></i> Etiketi telefonunuzun arkasına yaklaştırın...');
            try {
                var ndef = new NDEFReader();
                await ndef.write({ records: [{ recordType: 'url', data: url }] });
                setStatus('success', '<i class="fas fa-circle-check"></i> Başarılı! Link NFC etiketine yazıldı. Etiketi okuttuğunuzda profiliniz açılacak.');
            } catch (err) {
                setStatus('error', '<i class="fas fa-circle-exclamation"></i> Yazılamadı: ' + (err && err.message ? err.message : err) + '. Etiketi sabit tutup tekrar deneyin.');
            }
        });
    })();
    </script>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <script src="assets/js/app.js"></script>
</body>
</html>
