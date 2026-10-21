<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Handle vCard download
if (isset($_GET['vcard']) && isset($_GET['username'])) {
    $username = sanitize($_GET['username']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$username]);
    $vcardUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vcardUser) {
        $fullName = $vcardUser['full_name'] ?? '';
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        $phone = $vcardUser['phone'] ?? '';
        $email = $vcardUser['email'] ?? '';
        $website = $vcardUser['website'] ?? '';
        $bio = $vcardUser['bio'] ?? '';
        $titleVal = $vcardUser['title'] ?? '';
        $companyVal = $vcardUser['company'] ?? '';
        $addressVal = $vcardUser['address'] ?? '';
        $profileUrl = SITE_URL . '/u/' . $vcardUser['username'];

        // Build vCard
        $vcard = "BEGIN:VCARD\r\n";
        $vcard .= "VERSION:3.0\r\n";
        $vcard .= "FN:" . $fullName . "\r\n";
        $vcard .= "N:" . $lastName . ";" . $firstName . ";;;\r\n";

        if (!empty($titleVal)) {
            $vcard .= "TITLE:" . $titleVal . "\r\n";
        }
        if (!empty($companyVal)) {
            $vcard .= "ORG:" . $companyVal . "\r\n";
        }
        if (!empty($phone)) {
            $vcard .= "TEL;TYPE=CELL:" . $phone . "\r\n";
        }
        if (!empty($email)) {
            $vcard .= "EMAIL;TYPE=INTERNET:" . $email . "\r\n";
        }
        if (!empty($addressVal)) {
            $vcard .= "ADR;TYPE=WORK:;;" . str_replace(["\r\n", "\n", "\r", ";"], [" ", " ", " ", " "], $addressVal) . ";;;;\r\n";
        }
        if (!empty($website)) {
            $vcard .= "URL:" . $website . "\r\n";
        }
        if (!empty($bio)) {
            $vcard .= "NOTE:" . str_replace(["\r\n", "\n", "\r"], "\\n", $bio) . "\r\n";
        }

        $vcard .= "URL;TYPE=PROFILE:" . $profileUrl . "\r\n";
        $vcard .= "END:VCARD\r\n";

        // Analitik: vCard indirme olayı
        logProfileEvent((int) $vcardUser['id'], 'vcard');

        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $vcardUser['username'] . '.vcf"');
        header('Content-Length: ' . strlen($vcard));
        echo $vcard;
        exit;
    }
}

// Get username from URL
$username = sanitize($_GET['username'] ?? '');

if (empty($username)) {
    http_response_code(404);
    showNotFound();
    exit;
}

// Fetch user from database
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    showNotFound();
    exit;
}

// Trafik kaynağı (nfc / qr / link / share)
$src = $_GET['src'] ?? 'link';
$allowedSrc = ['nfc', 'qr', 'link', 'share'];
if (!in_array($src, $allowedSrc, true)) {
    $src = 'link';
}

// Görüntülenme sayacı (sahibi kendi profilini görüntülerse sayma)
$isOwner = isLoggedIn() && (int)($_SESSION['user_id'] ?? 0) === (int)$user['id'];
if (!$isOwner) {
    try {
        $pdo->prepare("UPDATE users SET view_count = view_count + 1 WHERE id = ?")->execute([$user['id']]);
    } catch (PDOException $e) {
        // Sayaç kritik değil; sessizce geç
    }
    // Detaylı analitik için görüntülenme olayını kaynağıyla kaydet
    logProfileEvent((int) $user['id'], 'view', 'src:' . $src);
}

// User data
$fullName = htmlspecialchars($user['full_name'] ?? '');
$titleText = htmlspecialchars($user['title'] ?? '');
$companyText = htmlspecialchars($user['company'] ?? '');
$addressText = htmlspecialchars($user['address'] ?? '');
$workingHours = htmlspecialchars($user['working_hours'] ?? '');
$mapUrl = $user['map_url'] ?? '';
$bio = htmlspecialchars($user['bio'] ?? '');
$phone = $user['phone'] ?? '';
$email = $user['email'] ?? '';
$instagram = $user['instagram'] ?? '';
$linkedin = $user['linkedin'] ?? '';
$facebook = $user['facebook'] ?? '';
$youtube = $user['youtube'] ?? '';
$whatsapp = $user['whatsapp'] ?? '';
$tiktok = $user['tiktok'] ?? '';
$telegram = $user['telegram'] ?? '';
$twitter = $user['twitter'] ?? '';
$website = $user['website'] ?? '';
$profileImage = !empty($user['profile_image']) ? SITE_URL . '/uploads/' . $user['profile_image'] : '';
$profileUrl = SITE_URL . '/u/' . $user['username'];

// QR için kalıcı kısa link (varsa); kaynak = qr olarak işaretle
$qrUrl = !empty($user['nfc_code'])
    ? SITE_URL . '/t/' . $user['nfc_code'] . '?src=qr'
    : $profileUrl . '?src=qr';

// Öğrenci portfolyosu yayındaysa kartvizitte bağlantısını göster
$portfolioUrl = (int) getPortfolio((int) $user['id'])['is_enabled'] === 1
    ? SITE_URL . '/p/' . rawurlencode($user['username'])
    : '';

// Özel bağlantılar
$customLinks = getUserLinks((int)$user['id']);

// WhatsApp numarasını link için sadeleştir (rakam dışını at)
$whatsappDigits = $whatsapp !== '' ? preg_replace('/\D+/', '', $whatsapp) : '';

// Generate initials for avatar fallback
$initials = '';
$nameParts = explode(' ', $user['full_name'] ?? '');
if (count($nameParts) >= 2) {
    $initials = mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[1], 0, 1));
} elseif (count($nameParts) === 1) {
    $initials = mb_strtoupper(mb_substr($nameParts[0], 0, 2));
}

// Check which social links exist
$hasSocials = !empty($phone) || !empty($email) || !empty($instagram) || !empty($linkedin) || !empty($facebook) || !empty($youtube) || !empty($whatsapp) || !empty($tiktok) || !empty($telegram) || !empty($twitter) || !empty($website);

// Ünvan / şirket satırı
$professionParts = array_filter([$titleText, $companyText]);
$professionLine = implode(' · ', $professionParts);

// Harita bağlantısını güvenli hale getir
$mapUrlSafe = '';
if ($mapUrl !== '') {
    $mapUrlSafe = preg_match('~^https?://~i', $mapUrl) ? $mapUrl : 'https://' . $mapUrl;
}

// Seçili profil teması
$cardThemes  = getCardThemes();
$theme       = $cardThemes[sanitizeCardTheme($user['card_theme'] ?? 'default')];

function showNotFound() {
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Bulunamadı - <?= SITE_NAME ?></title>
    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-animated">
    <div class="not-found">
        <div>
            <div class="not-found__code">404</div>
            <p class="not-found__message">Bu profil bulunamadı veya aktif değil.</p>
            <a href="<?= SITE_URL ?>" class="btn btn--primary">
                <i class="fas fa-home"></i>
                Ana Sayfa
            </a>
        </div>
    </div>
</body>
</html>
<?php
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $fullName ?> - <?= SITE_NAME ?></title>

    <!-- SEO & Social Sharing -->
    <meta name="description" content="<?= $fullName ?> - Dijital Kartvizit<?= !empty($bio) ? ' | ' . mb_substr($bio, 0, 120) : '' ?>">
    <meta property="og:type" content="profile">
    <meta property="og:title" content="<?= $fullName ?> - Dijital Kartvizit">
    <meta property="og:description" content="<?= !empty($bio) ? mb_substr($bio, 0, 200) : $fullName . ' dijital kartvizitine ulaşın.' ?>">
    <meta property="og:url" content="<?= $profileUrl ?>">
    <?php if ($profileImage): ?>
    <meta property="og:image" content="<?= $profileImage ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= $fullName ?>">
    <meta name="twitter:description" content="<?= !empty($bio) ? mb_substr($bio, 0, 200) : $fullName . ' dijital kartvizitine ulaşın.' ?>">
    <?php if ($profileImage): ?>
    <meta name="twitter:image" content="<?= $profileImage ?>">
    <?php endif; ?>

    <!-- Prevent indexing of profile pages for privacy -->
    <meta name="robots" content="noindex, nofollow">

    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* Profile page specific enhancements */
        .profile-bg {
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0a1a 0%, #1a1a2e 30%, #16213e 60%, #0d1b2a 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            position: relative;
            overflow: hidden;
        }

        .profile-bg::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(ellipse at 25% 25%, rgba(102, 126, 234, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 75% 75%, rgba(118, 75, 162, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(240, 147, 251, 0.05) 0%, transparent 40%);
            animation: float 10s ease-in-out infinite;
            pointer-events: none;
        }

        .profile-bg::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.015'%3E%3Ccircle cx='30' cy='30' r='1'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }

        .profile-page {
            position: relative;
            z-index: 1;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.5), rgba(240, 147, 251, 0.5), transparent);
        }

        .social-btn {
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        /* QR Kod */
        .profile-actions {
            margin-top: 14px;
            display: flex;
            justify-content: center;
        }
        .social-btn--qr {
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }
        .social-btn--qr:hover {
            background: rgba(255, 255, 255, 0.12);
        }
    </style>

    <!-- Seçili profil teması -->
    <style>
        :root {
            --primary-start: <?= $theme['start'] ?>;
            --primary-end: <?= $theme['end'] ?>;
            --primary-gradient: linear-gradient(135deg, <?= $theme['start'] ?> 0%, <?= $theme['end'] ?> 100%);
            --shadow-glow-primary: 0 0 30px <?= $theme['start'] ?>55;
        }
        [data-theme="light"] { --primary-start: <?= darkenHex($theme['start'], 0.45) ?>; }
        body.profile-bg {
            background: linear-gradient(135deg, <?= $theme['bg1'] ?> 0%, <?= $theme['bg2'] ?> 55%, <?= $theme['bg1'] ?> 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
        }
        .profile-avatar { background: var(--primary-gradient); }
        .profile-card__profession {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-top: 4px;
            font-weight: 600;
        }
        .profile-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 16px;
            text-align: left;
        }
        .profile-meta__item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        .profile-meta__item i {
            color: var(--primary-start);
            margin-top: 3px;
            width: 16px;
            text-align: center;
            flex-shrink: 0;
        }
        .profile-meta__item a { color: var(--text-secondary); }
        .profile-meta__item a:hover { color: var(--primary-start); }
    </style>
</head>
<body class="profile-bg">

    <div class="profile-page">
        <div class="glass-card profile-card">

            <!-- Profile Image -->
            <div class="profile-avatar">
                <?php if ($profileImage): ?>
                    <img src="<?= $profileImage ?>" alt="<?= $fullName ?>">
                <?php else: ?>
                    <div class="avatar__initials"><?= $initials ?></div>
                <?php endif; ?>
            </div>

            <!-- Name -->
            <h1 class="profile-card__name"><?= $fullName ?></h1>

            <!-- Ünvan / Şirket -->
            <?php if ($professionLine !== ''): ?>
                <p class="profile-card__profession"><?= $professionLine ?></p>
            <?php endif; ?>

            <!-- Bio -->
            <?php if (!empty($bio)): ?>
                <p class="profile-card__bio"><?= nl2br($bio) ?></p>
            <?php endif; ?>

            <!-- Adres & Çalışma Saatleri -->
            <?php if ($addressText !== '' || $workingHours !== ''): ?>
            <div class="profile-meta">
                <?php if ($addressText !== ''): ?>
                <div class="profile-meta__item">
                    <i class="fas fa-location-dot"></i>
                    <span>
                        <?php if ($mapUrlSafe !== ''): ?>
                            <a href="<?= htmlspecialchars($mapUrlSafe) ?>" target="_blank" rel="noopener noreferrer" data-track="click" data-target="map"><?= $addressText ?></a>
                        <?php else: ?>
                            <?= $addressText ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($workingHours !== ''): ?>
                <div class="profile-meta__item">
                    <i class="fas fa-clock"></i>
                    <span><?= $workingHours ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Social Media Buttons -->
            <?php if ($hasSocials): ?>
            <div class="social-grid stagger-children">

                <?php if (!empty($phone)): ?>
                <a href="tel:<?= htmlspecialchars($phone) ?>" class="social-btn social-btn--phone">
                    <i class="fas fa-phone"></i>
                    <span>Ara</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($email)): ?>
                <a href="mailto:<?= htmlspecialchars($email) ?>" class="social-btn social-btn--email">
                    <i class="fas fa-envelope"></i>
                    <span>E-posta</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($instagram)): ?>
                <a href="https://instagram.com/<?= htmlspecialchars($instagram) ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-btn--instagram">
                    <i class="fab fa-instagram"></i>
                    <span>Instagram</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($linkedin)): ?>
                <a href="https://linkedin.com/in/<?= htmlspecialchars($linkedin) ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-btn--linkedin">
                    <i class="fab fa-linkedin-in"></i>
                    <span>LinkedIn</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($facebook)): ?>
                <a href="https://facebook.com/<?= htmlspecialchars($facebook) ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-btn--facebook">
                    <i class="fab fa-facebook-f"></i>
                    <span>Facebook</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($youtube)): ?>
                <a href="https://youtube.com/@<?= htmlspecialchars($youtube) ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-btn--youtube">
                    <i class="fab fa-youtube"></i>
                    <span>YouTube</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($whatsappDigits)): ?>
                <a href="https://wa.me/<?= htmlspecialchars($whatsappDigits) ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-btn--whatsapp">
                    <i class="fab fa-whatsapp"></i>
                    <span>WhatsApp</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($tiktok)): ?>
                <a href="https://www.tiktok.com/@<?= htmlspecialchars(ltrim($tiktok, '@')) ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-btn--tiktok">
                    <i class="fab fa-tiktok"></i>
                    <span>TikTok</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($telegram)): ?>
                <a href="https://t.me/<?= htmlspecialchars(ltrim($telegram, '@')) ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-btn--telegram">
                    <i class="fab fa-telegram"></i>
                    <span>Telegram</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($twitter)): ?>
                <a href="https://x.com/<?= htmlspecialchars(ltrim($twitter, '@')) ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-btn--twitter">
                    <i class="fab fa-x-twitter"></i>
                    <span>X (Twitter)</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($website)): ?>
                <a href="<?= htmlspecialchars(str_starts_with($website, 'http') ? $website : 'https://' . $website) ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-btn--website">
                    <i class="fas fa-globe"></i>
                    <span>Web Sitesi</span>
                </a>
                <?php endif; ?>

                <!-- vCard Download -->
                <a href="<?= SITE_URL ?>/profile.php?vcard=1&username=<?= htmlspecialchars($user['username']) ?>" class="social-btn social-btn--vcard">
                    <i class="fas fa-address-book"></i>
                    <span>Rehbere Kaydet</span>
                </a>

            </div>
            <?php endif; ?>

            <!-- Özel Bağlantılar -->
            <?php if (!empty($customLinks)): ?>
            <div class="custom-links stagger-children">
                <?php foreach ($customLinks as $link): ?>
                <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-btn--custom" data-track="click" data-target="link:<?= (int)$link['id'] ?>">
                    <i class="<?= htmlspecialchars($link['icon']) ?>"></i>
                    <span><?= htmlspecialchars($link['title']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Öğrenci Portfolyosu -->
            <?php if ($portfolioUrl !== ''): ?>
            <div class="custom-links">
                <a href="<?= htmlspecialchars($portfolioUrl) ?>" class="social-btn social-btn--custom" data-target="portfolio">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Portfolyomu Gör</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- QR Kod & İletişim Bırak -->
            <div class="profile-actions">
                <button type="button" class="social-btn social-btn--qr" id="qrToggleBtn">
                    <i class="fas fa-qrcode"></i>
                    <span>QR Kod</span>
                </button>
                <button type="button" class="social-btn social-btn--qr" id="leadToggleBtn">
                    <i class="fas fa-user-plus"></i>
                    <span>Bilgini Bırak</span>
                </button>
            </div>

            <!-- Footer -->
            <div class="profile-card__footer">
                <a href="<?= SITE_URL ?>">
                    <i class="fas fa-id-card-clip"></i>
                    <?= SITE_NAME ?> ile oluşturuldu
                </a>
            </div>

        </div>
    </div>

    <!-- QR Kod Modal -->
    <div class="qr-modal" id="qrModal" aria-hidden="true">
        <div class="qr-modal__backdrop" data-qr-close></div>
        <div class="qr-modal__content" role="dialog" aria-modal="true" aria-label="QR Kod">
            <button class="qr-modal__close" data-qr-close aria-label="Kapat"><i class="fas fa-times"></i></button>
            <h2 class="qr-modal__title"><?= $fullName ?></h2>
            <p class="qr-modal__subtitle">Kartviziti açmak için QR kodu okutun</p>
            <div class="qr-modal__code" id="qrCode"></div>
            <button class="btn btn--primary btn--sm" id="qrDownloadBtn">
                <i class="fas fa-download"></i> QR Kodu İndir
            </button>
        </div>
    </div>

    <!-- Bilgi Bırak (Lead) Modal -->
    <div class="qr-modal" id="leadModal" aria-hidden="true">
        <div class="qr-modal__backdrop" data-lead-close></div>
        <div class="qr-modal__content" role="dialog" aria-modal="true" aria-label="Bilgini Bırak" style="max-width: 400px; text-align: left;">
            <button class="qr-modal__close" data-lead-close aria-label="Kapat"><i class="fas fa-times"></i></button>
            <h2 class="qr-modal__title"><i class="fas fa-user-plus"></i> Bilgini Bırak</h2>
            <p class="qr-modal__subtitle"><?= $fullName ?> sizinle iletişime geçsin</p>
            <form id="leadForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                <input type="hidden" name="username" value="<?= htmlspecialchars($user['username']) ?>">
                <!-- Honeypot (spam tuzağı) -->
                <input type="text" name="website_hp" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true">
                <div class="form-group">
                    <label class="form-label" for="lead_name">Ad Soyad *</label>
                    <input type="text" id="lead_name" name="name" class="form-input" placeholder="Adınız" required maxlength="120">
                </div>
                <div class="form-group">
                    <label class="form-label" for="lead_phone">Telefon</label>
                    <input type="tel" id="lead_phone" name="phone" class="form-input" placeholder="05XX XXX XX XX" maxlength="30">
                </div>
                <div class="form-group">
                    <label class="form-label" for="lead_email">E-posta</label>
                    <input type="email" id="lead_email" name="email" class="form-input" placeholder="ornek@email.com" maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label" for="lead_company">Şirket</label>
                    <input type="text" id="lead_company" name="company" class="form-input" placeholder="Şirket / kurum" maxlength="120">
                </div>
                <div class="form-group">
                    <label class="form-label" for="lead_note">Not</label>
                    <textarea id="lead_note" name="note" class="form-input" placeholder="Kısa bir mesaj (isteğe bağlı)" rows="2" maxlength="500"></textarea>
                </div>
                <button type="submit" class="btn btn--primary btn--block" id="leadSubmitBtn">
                    <i class="fas fa-paper-plane"></i> Gönder
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    (function () {
        var profileUrl = <?= json_encode($qrUrl) ?>;
        var modal = document.getElementById('qrModal');
        var openBtn = document.getElementById('qrToggleBtn');
        var qrContainer = document.getElementById('qrCode');
        var downloadBtn = document.getElementById('qrDownloadBtn');
        var qrInstance = null;

        function openModal() {
            // QR kodu ilk açılışta bir kez oluştur
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

    <!-- Analitik takip + Lead formu -->
    <script>
    (function () {
        var username = <?= json_encode($user['username']) ?>;
        var trackUrl = <?= json_encode(SITE_URL . '/track.php') ?>;
        var leadUrl  = <?= json_encode(SITE_URL . '/lead.php') ?>;

        function track(type, target) {
            try {
                var data = new FormData();
                data.append('username', username);
                data.append('type', type);
                if (target) data.append('target', target);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(trackUrl, data);
                } else {
                    fetch(trackUrl, { method: 'POST', body: data, keepalive: true });
                }
            } catch (e) {}
        }

        // Sosyal / iletişim / özel bağlantı tıklamaları
        document.querySelectorAll('a.social-btn').forEach(function (link) {
            link.addEventListener('click', function () {
                var target = link.getAttribute('data-target');
                if (!target) {
                    // Sınıftan platform adını çıkar (social-btn--instagram -> instagram)
                    link.classList.forEach(function (cls) {
                        if (cls.indexOf('social-btn--') === 0) {
                            var name = cls.replace('social-btn--', '');
                            if (name && name !== 'qr' && name !== 'custom') target = name;
                        }
                    });
                }
                // vCard sunucu tarafında ayrıca loglanır
                if (target && target !== 'vcard') track('click', target);
            });
        });

        // Harita bağlantısı (profil meta içindeki)
        document.querySelectorAll('a[data-track="click"][data-target]').forEach(function (link) {
            if (link.classList.contains('social-btn')) return; // yukarıda ele alındı
            link.addEventListener('click', function () {
                track('click', link.getAttribute('data-target'));
            });
        });

        // QR modal açıldığında olayı logla
        var qrBtn = document.getElementById('qrToggleBtn');
        if (qrBtn) qrBtn.addEventListener('click', function () { track('qr', null); });

        // ----- Lead (Bilgini Bırak) modal -----
        var leadModal = document.getElementById('leadModal');
        var leadOpen  = document.getElementById('leadToggleBtn');
        var leadForm  = document.getElementById('leadForm');
        var leadBtn   = document.getElementById('leadSubmitBtn');

        function openLead() {
            leadModal.classList.add('open');
            leadModal.setAttribute('aria-hidden', 'false');
        }
        function closeLead() {
            leadModal.classList.remove('open');
            leadModal.setAttribute('aria-hidden', 'true');
        }

        if (leadOpen) leadOpen.addEventListener('click', openLead);
        leadModal.querySelectorAll('[data-lead-close]').forEach(function (el) {
            el.addEventListener('click', closeLead);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeLead();
        });

        if (leadForm) {
            leadForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var nameVal = leadForm.querySelector('[name="name"]').value.trim();
                if (!nameVal) return;

                leadBtn.disabled = true;
                leadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';

                fetch(leadUrl, { method: 'POST', body: new FormData(leadForm) })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.ok) {
                            leadForm.innerHTML = '<div style="text-align:center;padding:20px 0;">' +
                                '<i class="fas fa-circle-check" style="font-size:3rem;color:#00d2ff;"></i>' +
                                '<p style="margin-top:12px;color:var(--text-secondary);">Bilgileriniz iletildi. Teşekkürler!</p>' +
                                '</div>';
                        } else {
                            leadBtn.disabled = false;
                            leadBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Gönder';
                            if (window.showToast) showToast((res && res.error) || 'Bir hata oluştu.', 'error');
                        }
                    })
                    .catch(function () {
                        leadBtn.disabled = false;
                        leadBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Gönder';
                        if (window.showToast) showToast('Bağlantı hatası. Lütfen tekrar deneyin.', 'error');
                    });
            });
        }
    })();
    </script>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
