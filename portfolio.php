<?php
/**
 * Öğrenci Dijital Portfolyosu - herkese açık sayfa.
 * /p/kullaniciadi  ->  portfolio.php?username=kullaniciadi
 *
 * Not: Rewrite ile açıldığı için tüm link ve asset yolları SITE_URL ile mutlak.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$username = sanitize($_GET['username'] ?? '');
$user = null;
if ($username !== '') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$portfolio = $user ? getPortfolio((int) $user['id']) : null;
$isOwner   = $user && isLoggedIn() && (int) ($_SESSION['user_id'] ?? 0) === (int) $user['id'];

// Yayında değilse: sahibi önizleyebilir, diğerleri 404 görür
if (!$user || ((int) $portfolio['is_enabled'] !== 1 && !$isOwner)) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolyo Bulunamadı - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-animated">
    <div class="not-found">
        <div>
            <div class="not-found__code">404</div>
            <p class="not-found__message">Bu portfolyo bulunamadı veya yayında değil.</p>
            <a href="<?= SITE_URL ?>" class="btn btn--primary"><i class="fas fa-home"></i> Ana Sayfa</a>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

// Trafik kaynağı ve analitik (sahibin kendi ziyareti sayılmaz)
$src = $_GET['src'] ?? 'link';
if (!in_array($src, ['nfc', 'qr', 'link', 'share'], true)) {
    $src = 'link';
}
if (!$isOwner) {
    logProfileEvent((int) $user['id'], 'view', 'portfolio:' . $src);
}

$items     = getPortfolioItems((int) $user['id']);
$itemTypes = getPortfolioItemTypes();

// Tür başına sayılar (üstteki özet ve filtre için)
$typeCounts = [];
foreach ($items as $it) {
    $k = isset($itemTypes[$it['item_type']]) ? $it['item_type'] : 'other';
    $typeCounts[$k] = ($typeCounts[$k] ?? 0) + 1;
}

$fullName     = $user['full_name'] ?? $user['username'];
$gradeLabel   = getGradeLabel($portfolio['grade']);
$schoolLine   = implode(' · ', array_filter([$portfolio['school_name'], $gradeLabel]));
$skills       = splitTags($portfolio['skills']);
$interests    = splitTags($portfolio['interests']);
$languages    = splitTags($portfolio['languages']);
$profileImage = !empty($user['profile_image']) ? SITE_URL . '/uploads/' . $user['profile_image'] : '';
$cardUrl      = SITE_URL . '/u/' . rawurlencode($user['username']);
$pageUrl      = SITE_URL . '/p/' . rawurlencode($user['username']);

$nameParts = preg_split('/\s+/', trim($fullName));
$initials  = count($nameParts) >= 2
    ? mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[1], 0, 1))
    : mb_strtoupper(mb_substr($nameParts[0] ?? '', 0, 2));

$theme = getCardThemes()[sanitizeCardTheme($user['card_theme'] ?? 'default')];
$desc  = $portfolio['headline'] ?: ($schoolLine ?: 'Öğrenci dijital portfolyosu');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($fullName) ?> - Portfolyo</title>
    <meta name="description" content="<?= $e($fullName . ' - ' . $desc) ?>">
    <meta property="og:type" content="profile">
    <meta property="og:title" content="<?= $e($fullName) ?> - Öğrenci Portfolyosu">
    <meta property="og:description" content="<?= $e($desc) ?>">
    <meta property="og:url" content="<?= $e($pageUrl) ?>">
    <?php if ($profileImage): ?><meta property="og:image" content="<?= $e($profileImage) ?>"><?php endif; ?>
    <!-- Öğrenci gizliliği: arama motorlarında listelenmesin -->
    <meta name="robots" content="noindex, nofollow">

    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-start: <?= $theme['start'] ?>;
            --primary-end: <?= $theme['end'] ?>;
            --primary-gradient: linear-gradient(135deg, <?= $theme['start'] ?> 0%, <?= $theme['end'] ?> 100%);
            --shadow-glow-primary: 0 0 30px <?= $theme['start'] ?>55;
            --pf-accent: <?= $theme['start'] ?>;
        }
        [data-theme="light"] { --primary-start: <?= darkenHex($theme['start'], 0.45) ?>; --pf-accent: <?= darkenHex($theme['start'], 0.45) ?>; }
        body.profile-bg {
            background: linear-gradient(135deg, <?= $theme['bg1'] ?> 0%, <?= $theme['bg2'] ?> 55%, <?= $theme['bg1'] ?> 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
        }
        .profile-avatar { background: var(--primary-gradient); }
    </style>
</head>
<body class="profile-bg">

    <?php if ($isOwner && (int) $portfolio['is_enabled'] !== 1): ?>
    <div class="pf-preview-bar">
        <i class="fas fa-eye-slash"></i> Önizleme: portfolyonuz henüz yayında değil.
        <a href="<?= SITE_URL ?>/portfolio-edit.php">Yayınla</a>
    </div>
    <?php endif; ?>

    <main class="pf-page">

        <!-- Kimlik kartı -->
        <section class="glass-card profile-card pf-hero">
            <div class="profile-avatar">
                <?php if ($profileImage): ?>
                    <img src="<?= $e($profileImage) ?>" alt="<?= $e($fullName) ?>">
                <?php else: ?>
                    <div class="avatar__initials"><?= $e($initials) ?></div>
                <?php endif; ?>
            </div>

            <span class="pf-badge"><i class="fas fa-graduation-cap"></i> Öğrenci Portfolyosu</span>
            <h1 class="profile-card__name" style="margin-top: 10px;"><?= $e($fullName) ?></h1>

            <?php if ($schoolLine !== ''): ?>
                <p class="pf-school"><i class="fas fa-school"></i> <?= $e($schoolLine) ?></p>
            <?php endif; ?>
            <?php if ($portfolio['headline'] !== ''): ?>
                <p class="pf-headline"><?= $e($portfolio['headline']) ?></p>
            <?php endif; ?>

            <?php if ($typeCounts): ?>
            <div class="pf-stats">
                <?php foreach ($typeCounts as $k => $c): ?>
                <div class="pf-stat">
                    <i class="<?= $e($itemTypes[$k]['icon']) ?>"></i>
                    <strong><?= $c ?></strong>
                    <span><?= $e($itemTypes[$k]['label']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="pf-hero__actions">
                <a href="<?= $e($cardUrl) ?>" class="social-btn social-btn--qr">
                    <i class="fas fa-id-card"></i><span>Kartvizitim</span>
                </a>
                <button type="button" class="social-btn social-btn--qr" id="pfShareBtn" data-url="<?= $e($pageUrl) ?>">
                    <i class="fas fa-share-nodes"></i><span>Paylaş</span>
                </button>
            </div>
        </section>

        <!-- Hakkımda + Hedef -->
        <?php if ($portfolio['about'] !== '' || $portfolio['goals'] !== ''): ?>
        <section class="glass-card pf-section">
            <?php if ($portfolio['about'] !== ''): ?>
            <h2 class="pf-section__title"><i class="fas fa-user-graduate"></i> Hakkımda</h2>
            <p class="pf-text"><?= nl2br($e($portfolio['about'])) ?></p>
            <?php endif; ?>
            <?php if ($portfolio['goals'] !== ''): ?>
            <div class="pf-goal">
                <i class="fas fa-bullseye"></i>
                <div>
                    <div class="pf-goal__label">Hedefim</div>
                    <div><?= $e($portfolio['goals']) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- Yetenekler / İlgi alanları / Diller -->
        <?php if ($skills || $interests || $languages): ?>
        <section class="glass-card pf-section">
            <?php foreach ([
                ['Yetenekler', 'fas fa-bolt', $skills],
                ['İlgi Alanları', 'fas fa-heart', $interests],
                ['Yabancı Diller', 'fas fa-language', $languages],
            ] as [$label, $icon, $tags]): if (!$tags) continue; ?>
            <h2 class="pf-section__title"><i class="<?= $icon ?>"></i> <?= $label ?></h2>
            <div class="pf-tags">
                <?php foreach ($tags as $tag): ?><span class="pf-tag"><?= $e($tag) ?></span><?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- Başarılar & projeler -->
        <?php if ($items): ?>
        <section class="glass-card pf-section">
            <h2 class="pf-section__title"><i class="fas fa-trophy"></i> Başarılar ve Çalışmalar</h2>

            <?php if (count($typeCounts) > 1): ?>
            <div class="pf-filter" role="tablist">
                <button type="button" class="pf-filter__btn active" data-filter="all">Tümü (<?= count($items) ?>)</button>
                <?php foreach ($typeCounts as $k => $c): ?>
                <button type="button" class="pf-filter__btn" data-filter="<?= $e($k) ?>"><?= $e($itemTypes[$k]['label']) ?> (<?= $c ?>)</button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="pf-timeline">
                <?php foreach ($items as $it):
                    $k = isset($itemTypes[$it['item_type']]) ? $it['item_type'] : 'other';
                    $t = $itemTypes[$k]; ?>
                <article class="pf-item" data-type="<?= $e($k) ?>">
                    <div class="pf-item__icon"><i class="<?= $e($t['icon']) ?>"></i></div>
                    <div class="pf-item__body">
                        <div class="pf-item__meta">
                            <span><?= $e($t['label']) ?></span>
                            <?php if (!empty($it['item_date'])): ?><span>· <?= $e($it['item_date']) ?></span><?php endif; ?>
                        </div>
                        <h3 class="pf-item__title"><?= $e($it['title']) ?></h3>
                        <?php if (!empty($it['description'])): ?>
                            <p class="pf-item__desc"><?= nl2br($e($it['description'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($it['image'])): ?>
                            <button type="button" class="pf-item__img" data-lightbox="<?= $e(SITE_URL . '/uploads/' . $it['image']) ?>">
                                <img src="<?= $e(SITE_URL . '/uploads/' . $it['image']) ?>" alt="<?= $e($it['title']) ?>" loading="lazy">
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($it['url'])): ?>
                            <a class="pf-item__link" href="<?= $e($it['url']) ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fas fa-arrow-up-right-from-square"></i> Görüntüle
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="profile-card__footer" style="text-align:center; border-top: 0;">
            <a href="<?= SITE_URL ?>"><i class="fas fa-id-card-clip"></i> <?= SITE_NAME ?> ile oluşturuldu</a>
        </div>
    </main>

    <!-- Görsel büyütme -->
    <div class="qr-modal" id="pfLightbox" aria-hidden="true">
        <div class="qr-modal__backdrop" data-lb-close></div>
        <div class="qr-modal__content pf-lightbox__content" role="dialog" aria-modal="true" aria-label="Görsel">
            <button class="qr-modal__close" data-lb-close aria-label="Kapat"><i class="fas fa-times"></i></button>
            <img id="pfLightboxImg" src="" alt="">
        </div>
    </div>

    <script>
    (function () {
        // Tür filtresi
        var btns = document.querySelectorAll('.pf-filter__btn');
        btns.forEach(function (b) {
            b.addEventListener('click', function () {
                var f = b.getAttribute('data-filter');
                btns.forEach(function (x) { x.classList.toggle('active', x === b); });
                document.querySelectorAll('.pf-item').forEach(function (it) {
                    it.hidden = f !== 'all' && it.getAttribute('data-type') !== f;
                });
            });
        });

        // Görsel büyütme
        var lb = document.getElementById('pfLightbox');
        var lbImg = document.getElementById('pfLightboxImg');
        function closeLb() { lb.classList.remove('open'); lb.setAttribute('aria-hidden', 'true'); }
        document.querySelectorAll('[data-lightbox]').forEach(function (el) {
            el.addEventListener('click', function () {
                lbImg.src = el.getAttribute('data-lightbox');
                lb.classList.add('open');
                lb.setAttribute('aria-hidden', 'false');
            });
        });
        lb.querySelectorAll('[data-lb-close]').forEach(function (el) { el.addEventListener('click', closeLb); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeLb(); });

        // Paylaş (destekleniyorsa sistem paylaşımı, yoksa kopyala)
        var share = document.getElementById('pfShareBtn');
        if (share) share.addEventListener('click', function () {
            var url = share.getAttribute('data-url') + '?src=share';
            if (navigator.share) {
                navigator.share({ title: document.title, url: url }).catch(function () {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    if (window.showToast) showToast('Link kopyalandı', 'success');
                });
            }
        });
    })();
    </script>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
