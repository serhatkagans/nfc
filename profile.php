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
// ---- Görünüm verileri (yeni profil tasarımı) ----
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$strip = fn($v) => ltrim(trim((string) $v), '@');

// Hızlı işlemler (yuvarlak butonlar): [hedef, href, ikon, etiket, yeni sekme]
$quick = [];
if ($phone !== '')          $quick[] = ['phone',    'tel:' . $phone,                    'fas fa-phone',          'Ara',        false];
if ($whatsappDigits !== '') $quick[] = ['whatsapp', 'https://wa.me/' . $whatsappDigits, 'fab fa-whatsapp',       'WhatsApp',   true];
if ($email !== '')          $quick[] = ['email',    'mailto:' . $email,                 'fas fa-envelope',       'E-posta',    false];
if ($mapUrlSafe !== '')     $quick[] = ['map',      $mapUrlSafe,                        'fas fa-location-arrow', 'Yol Tarifi', true];

// Sosyal medya kutucukları: [hedef, url, ikon, etiket, alt satır]
$socials = [];
if ($instagram !== '') $socials[] = ['instagram', 'https://instagram.com/' . $strip($instagram), 'fab fa-instagram', 'Instagram', '@' . $strip($instagram)];
if ($linkedin !== '')  $socials[] = ['linkedin', 'https://linkedin.com/in/' . $strip($linkedin), 'fab fa-linkedin-in', 'LinkedIn', $strip($linkedin)];
if ($facebook !== '')  $socials[] = ['facebook', 'https://facebook.com/' . $strip($facebook), 'fab fa-facebook-f', 'Facebook', $strip($facebook)];
if ($youtube !== '')   $socials[] = ['youtube', 'https://youtube.com/@' . $strip($youtube), 'fab fa-youtube', 'YouTube', '@' . $strip($youtube)];
if ($tiktok !== '')    $socials[] = ['tiktok', 'https://www.tiktok.com/@' . $strip($tiktok), 'fab fa-tiktok', 'TikTok', '@' . $strip($tiktok)];
if ($telegram !== '')  $socials[] = ['telegram', 'https://t.me/' . $strip($telegram), 'fab fa-telegram', 'Telegram', '@' . $strip($telegram)];
if ($twitter !== '')   $socials[] = ['twitter', 'https://x.com/' . $strip($twitter), 'fab fa-x-twitter', 'X', '@' . $strip($twitter)];
if ($website !== '') {
    $siteHref = preg_match('~^https?://~i', $website) ? $website : 'https://' . $website;
    $socials[] = ['website', $siteHref, 'fas fa-globe', 'Web Sitesi', preg_replace('~^www\.~', '', (string) parse_url($siteHref, PHP_URL_HOST))];
}
$vcardUrl = SITE_URL . '/profile.php?vcard=1&username=' . rawurlencode($user['username']);
$i = 0; // giriş animasyonu sırası
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
    <meta name="theme-color" content="<?= $theme['bg1'] ?>">

    <!-- Profil sayfaları gizlilik için indekslenmez -->
    <meta name="robots" content="noindex, nofollow">

    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Seçili profil teması -->
    <style>
        :root {
            --primary-start: <?= $theme['start'] ?>;
            --primary-end: <?= $theme['end'] ?>;
            --primary-gradient: linear-gradient(135deg, <?= $theme['start'] ?> 0%, <?= $theme['end'] ?> 100%);
            --shadow-glow-primary: 0 0 30px <?= $theme['start'] ?>55;
            --pv-a: <?= $theme['start'] ?>;
            --pv-b: <?= $theme['end'] ?>;
            --pv-bg1: <?= $theme['bg1'] ?>;
            --pv-bg2: <?= $theme['bg2'] ?>;
        }
        [data-theme="light"] { --primary-start: <?= darkenHex($theme['start'], 0.45) ?>; }
    </style>
</head>
<body class="pv">

    <!-- Hareketli renk bulutları -->
    <div class="pv-aurora" aria-hidden="true"><span></span><span></span><span></span></div>

    <main class="pv-wrap">
        <article class="pv-card">

            <!-- Kapak -->
            <div class="pv-cover" aria-hidden="true">
                <span class="pv-cover__shine"></span>
                <span class="pv-chip"><i class="fas fa-wifi"></i> NFC</span>
            </div>

            <!-- Avatar -->
            <div class="pv-avatar">
                <div class="pv-avatar__inner">
                    <?php if ($profileImage): ?>
                        <img src="<?= $profileImage ?>" alt="<?= $fullName ?>">
                    <?php else: ?>
                        <span class="pv-avatar__initials"><?= $e($initials) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <header class="pv-head pv-in" style="--i:<?= $i++ ?>">
                <h1 class="pv-name"><?= $fullName ?></h1>
                <?php if ($professionLine !== ''): ?>
                    <p class="pv-role"><?= $professionLine ?></p>
                <?php endif; ?>
                <?php if (!empty($bio)): ?>
                    <p class="pv-bio"><?= nl2br($bio) ?></p>
                <?php endif; ?>
            </header>

            <!-- Hızlı işlemler -->
            <?php if ($quick): ?>
            <nav class="pv-quick pv-in" style="--i:<?= $i++ ?>" aria-label="Hızlı iletişim">
                <?php foreach ($quick as [$target, $href, $icon, $label, $blank]): ?>
                <a href="<?= $e($href) ?>" class="pv-quick__btn pv-quick__btn--<?= $target ?>" data-target="<?= $target ?>"<?= $blank ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                    <span class="pv-quick__icon"><i class="<?= $icon ?>"></i></span>
                    <span class="pv-quick__label"><?= $label ?></span>
                </a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>

            <!-- Rehbere Kaydet -->
            <a href="<?= $e($vcardUrl) ?>" class="pv-cta pv-in" style="--i:<?= $i++ ?>" data-target="vcard">
                <i class="fas fa-user-plus"></i>
                <span>Rehbere Kaydet</span>
            </a>

            <!-- Adres & Çalışma Saatleri -->
            <?php if ($addressText !== '' || $workingHours !== ''): ?>
            <div class="pv-info pv-in" style="--i:<?= $i++ ?>">
                <?php if ($addressText !== ''): ?>
                <div class="pv-info__row">
                    <span class="pv-info__icon"><i class="fas fa-location-dot"></i></span>
                    <span>
                        <?php if ($mapUrlSafe !== ''): ?>
                            <a href="<?= $e($mapUrlSafe) ?>" target="_blank" rel="noopener noreferrer" data-target="map"><?= $addressText ?></a>
                        <?php else: ?>
                            <?= $addressText ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($workingHours !== ''): ?>
                <div class="pv-info__row">
                    <span class="pv-info__icon"><i class="fas fa-clock"></i></span>
                    <span><?= $workingHours ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Sosyal medya -->
            <?php if ($socials): ?>
            <section class="pv-socials" aria-label="Sosyal medya">
                <?php foreach ($socials as [$target, $href, $icon, $label, $sub]): ?>
                <a href="<?= $e($href) ?>" target="_blank" rel="noopener noreferrer" class="pv-social pv-social--<?= $target ?> pv-in" style="--i:<?= $i++ ?>" data-target="<?= $target ?>">
                    <span class="pv-social__icon"><i class="<?= $icon ?>"></i></span>
                    <span class="pv-social__text">
                        <span class="pv-social__label"><?= $label ?></span>
                        <?php if ($sub !== ''): ?><span class="pv-social__sub"><?= $e($sub) ?></span><?php endif; ?>
                    </span>
                    <i class="fas fa-arrow-up-right-from-square pv-social__go" aria-hidden="true"></i>
                </a>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

            <!-- Öğrenci Portfolyosu -->
            <?php if ($portfolioUrl !== ''): ?>
            <a href="<?= $e($portfolioUrl) ?>" class="pv-feature pv-in" style="--i:<?= $i++ ?>" data-target="portfolio">
                <span class="pv-feature__icon"><i class="fas fa-graduation-cap"></i></span>
                <span class="pv-feature__text">
                    <strong>Öğrenci Portfolyom</strong>
                    <span>Başarılarım, projelerim ve sertifikalarım</span>
                </span>
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </a>
            <?php endif; ?>

            <!-- Özel Bağlantılar -->
            <?php if (!empty($customLinks)): ?>
            <section class="pv-links">
                <?php foreach ($customLinks as $link): ?>
                <a href="<?= $e($link['url']) ?>" target="_blank" rel="noopener noreferrer" class="pv-link pv-in" style="--i:<?= $i++ ?>" data-target="link:<?= (int) $link['id'] ?>">
                    <span class="pv-link__icon"><i class="<?= $e($link['icon']) ?>"></i></span>
                    <span class="pv-link__label"><?= $e($link['title']) ?></span>
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </a>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

            <!-- QR / Bilgi Bırak / Paylaş -->
            <div class="pv-actions pv-in" style="--i:<?= $i++ ?>">
                <button type="button" class="pv-pill" id="qrToggleBtn"><i class="fas fa-qrcode"></i> QR Kod</button>
                <button type="button" class="pv-pill" id="leadToggleBtn"><i class="fas fa-handshake"></i> Bilgini Bırak</button>
                <button type="button" class="pv-pill" id="shareBtn"><i class="fas fa-share-nodes"></i> Paylaş</button>
            </div>

            <footer class="pv-footer">
                <a href="<?= SITE_URL ?>"><i class="fas fa-id-card-clip"></i> <?= SITE_NAME ?> ile oluşturuldu</a>
            </footer>
        </article>
    </main>

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
        var username = <?= json_encode($user['username']) ?>;
        var trackUrl = <?= json_encode(SITE_URL . '/track.php') ?>;
        var leadUrl  = <?= json_encode(SITE_URL . '/lead.php') ?>;
        var qrUrl    = <?= json_encode($qrUrl) ?>;
        var shareUrl = <?= json_encode($profileUrl . '?src=share') ?>;

        function track(type, target) {
            try {
                var data = new FormData();
                data.append('username', username);
                data.append('type', type);
                if (target) data.append('target', target);
                if (navigator.sendBeacon) navigator.sendBeacon(trackUrl, data);
                else fetch(trackUrl, { method: 'POST', body: data, keepalive: true });
            } catch (e) {}
        }

        // Tüm takip edilen bağlantılar (vCard sunucu tarafında ayrıca loglanır)
        document.querySelectorAll('a[data-target]').forEach(function (a) {
            a.addEventListener('click', function () {
                var t = a.getAttribute('data-target');
                if (t && t !== 'vcard') track('click', t);
            });
        });

        // Modal yardımcısı
        function modal(id, closeAttr) {
            var m = document.getElementById(id);
            var api = {
                open: function () { m.classList.add('open'); m.setAttribute('aria-hidden', 'false'); },
                close: function () { m.classList.remove('open'); m.setAttribute('aria-hidden', 'true'); }
            };
            m.querySelectorAll('[' + closeAttr + ']').forEach(function (el) { el.addEventListener('click', api.close); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') api.close(); });
            return api;
        }

        // ----- QR -----
        var qr = modal('qrModal', 'data-qr-close');
        var qrBox = document.getElementById('qrCode');
        var qrMade = false;
        document.getElementById('qrToggleBtn').addEventListener('click', function () {
            if (!qrMade && window.QRCode) {
                new QRCode(qrBox, { text: qrUrl, width: 240, height: 240, colorDark: '#0a0a1a', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
                qrMade = true;
            }
            qr.open();
            track('qr', null);
        });
        document.getElementById('qrDownloadBtn').addEventListener('click', function () {
            var img = qrBox.querySelector('img'), canvas = qrBox.querySelector('canvas');
            var url = img && img.src && img.src.indexOf('data:') === 0 ? img.src : (canvas ? canvas.toDataURL('image/png') : null);
            if (!url) return;
            var a = document.createElement('a');
            a.href = url; a.download = <?= json_encode($user['username'] . '-qr.png') ?>;
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
        });

        // ----- Paylaş -----
        document.getElementById('shareBtn').addEventListener('click', function () {
            if (navigator.share) {
                navigator.share({ title: document.title, url: shareUrl }).catch(function () {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(shareUrl).then(function () {
                    if (window.showToast) showToast('Link kopyalandı', 'success');
                });
            }
            track('click', 'share');
        });

        // ----- Bilgini Bırak -----
        var lead = modal('leadModal', 'data-lead-close');
        var leadForm = document.getElementById('leadForm');
        var leadBtn = document.getElementById('leadSubmitBtn');
        document.getElementById('leadToggleBtn').addEventListener('click', lead.open);

        leadForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!leadForm.querySelector('[name="name"]').value.trim()) return;
            leadBtn.disabled = true;
            leadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
            function fail(msg) {
                leadBtn.disabled = false;
                leadBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Gönder';
                if (window.showToast) showToast(msg, 'error');
            }
            fetch(leadUrl, { method: 'POST', body: new FormData(leadForm) })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.ok) {
                        leadForm.innerHTML = '<div style="text-align:center;padding:20px 0;">' +
                            '<i class="fas fa-circle-check" style="font-size:3rem;color:var(--primary-start);"></i>' +
                            '<p style="margin-top:12px;color:var(--text-secondary);">Bilgileriniz iletildi. Teşekkürler!</p></div>';
                    } else {
                        fail((res && res.error) || 'Bir hata oluştu.');
                    }
                })
                .catch(function () { fail('Bağlantı hatası. Lütfen tekrar deneyin.'); });
        });
    })();
    </script>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
