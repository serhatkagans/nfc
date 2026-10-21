<?php
/**
 * Giriş yapmış kullanıcı alanı için ortak başlık (header) + flash mesajları.
 *
 * Dahil etmeden önce ayarlanabilir değişkenler:
 *   $user      array  Geçerli kullanıcı (zorunlu)
 *   $activeNav string Aktif menü anahtarı: dashboard | portfolio | analytics | leads | settings
 *
 * Bu dosya doğrudan çağrılırsa erişimi engelle.
 */
if (!defined('SITE_URL') || !isset($user) || !is_array($user)) {
    http_response_code(403);
    exit('Doğrudan erişim engellendi.');
}

$activeNav    = $activeNav ?? '';
$unreadLeads  = function_exists('getUnreadLeadsCount') ? getUnreadLeadsCount((int) $user['id']) : 0;
$navUsername  = htmlspecialchars($user['username']);
?>
<!-- Header -->
<header class="header">
    <div class="container">
        <a href="<?= SITE_URL ?>/dashboard.php" class="header__logo">
            <i class="fas fa-id-card-clip"></i>
            <span><?= SITE_NAME ?></span>
        </a>

        <div class="mobile-menu-toggle">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <nav class="header__nav">
            <a href="<?= SITE_URL ?>/dashboard.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-gauge-high"></i> Panel
            </a>
            <a href="<?= SITE_URL ?>/portfolio-edit.php" class="<?= $activeNav === 'portfolio' ? 'active' : '' ?>">
                <i class="fas fa-graduation-cap"></i> Portfolyo
            </a>
            <a href="<?= SITE_URL ?>/analytics.php" class="<?= $activeNav === 'analytics' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i> Analitik
            </a>
            <a href="<?= SITE_URL ?>/leads.php" class="<?= $activeNav === 'leads' ? 'active' : '' ?>">
                <i class="fas fa-address-book"></i> Kişiler<?php if ($unreadLeads > 0): ?> <span class="nav-badge"><?= $unreadLeads > 99 ? '99+' : $unreadLeads ?></span><?php endif; ?>
            </a>
            <a href="<?= SITE_URL ?>/account-settings.php" class="<?= $activeNav === 'settings' ? 'active' : '' ?>">
                <i class="fas fa-gear"></i> Ayarlar
            </a>
            <a href="<?= SITE_URL ?>/profile.php?username=<?= $navUsername ?>" target="_blank">
                <i class="fas fa-eye"></i> Profilim
            </a>
            <?php if (!empty($user['is_admin'])): ?>
            <a href="<?= SITE_URL ?>/admin/index.php">
                <i class="fas fa-shield-halved"></i> Yönetim
            </a>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/logout.php" class="btn btn--outline btn--sm">
                <i class="fas fa-right-from-bracket"></i> Çıkış
            </a>
        </nav>
    </div>
</header>

<!-- Flash Messages -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="flash-container">
    <div class="flash flash--<?= $flash['type'] ?>">
        <i class="fas <?php
            switch ($flash['type']) {
                case 'success': echo 'fa-circle-check'; break;
                case 'error':   echo 'fa-circle-exclamation'; break;
                case 'warning': echo 'fa-triangle-exclamation'; break;
                default:        echo 'fa-circle-info';
            }
        ?>"></i>
        <div class="flash__content"><?= $flash['message'] ?></div>
        <span class="flash__close"><i class="fas fa-times"></i></span>
    </div>
</div>
<?php endif; ?>
