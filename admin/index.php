<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$currentUser = getCurrentUser();

// --- İstatistikler ---
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$todayUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$googleUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE google_id IS NOT NULL")->fetchColumn();
$totalViews = (int)$pdo->query("SELECT COALESCE(SUM(view_count), 0) FROM users")->fetchColumn();

// --- Son 10 Kullanıcı ---
$recentUsers = $pdo->query("SELECT id, full_name, username, email, profile_image, is_active, created_at FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Yönetim Paneli';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-wrapper">

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><i class="fas fa-id-card-clip"></i></div>
            <div class="sidebar-brand">
                <span class="sidebar-brand-name">NFC Kartvizit</span>
                <span class="sidebar-brand-sub">Yönetim Paneli</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-nav-label">Ana Menü</div>
            <a href="<?= SITE_URL ?>/admin/index.php" class="sidebar-nav-item active">
                <i class="fas fa-chart-pie"></i> Ana Sayfa
            </a>
            <a href="<?= SITE_URL ?>/admin/users.php" class="sidebar-nav-item">
                <i class="fas fa-users"></i> Kullanıcılar
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= SITE_URL ?>" class="sidebar-nav-item" target="_blank">
                <i class="fas fa-external-link-alt"></i> Siteye Dön
            </a>
        </div>
    </aside>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Topbar -->
    <header class="admin-topbar">
        <div class="topbar-left">
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <span class="topbar-title"><?= $pageTitle ?></span>
        </div>
        <div class="topbar-right">
            <div class="topbar-admin-info">
                <div class="topbar-admin-avatar">
                    <?= mb_strtoupper(mb_substr($currentUser['full_name'] ?? $currentUser['username'], 0, 1)) ?>
                </div>
                <div class="topbar-admin-texts">
                    <div class="topbar-admin-name"><?= sanitize($currentUser['full_name'] ?? $currentUser['username']) ?></div>
                    <div class="topbar-admin-role">Yönetici</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="admin-content">

        <?php
        $flash = getFlash();
        if ($flash):
        ?>
        <div class="flash-message flash-<?= $flash['type'] ?>">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
            <?= $flash['message'] ?>
        </div>
        <?php endif; ?>

        <!-- Welcome -->
        <div class="welcome-section">
            <h1>Hoş geldiniz, <?= sanitize($currentUser['full_name'] ?? $currentUser['username']) ?>! 👋</h1>
            <p>Sitenizin genel durumunu buradan takip edebilirsiniz.</p>
        </div>

        <!-- Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= number_format($totalUsers) ?></h3>
                    <p>Toplam Kullanıcı</p>
                </div>
                <div class="stat-icon purple">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= number_format($activeUsers) ?></h3>
                    <p>Aktif Kullanıcı</p>
                </div>
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= number_format($todayUsers) ?></h3>
                    <p>Bugün Kayıt Olan</p>
                </div>
                <div class="stat-icon orange">
                    <i class="fas fa-user-plus"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= number_format($googleUsers) ?></h3>
                    <p>Google ile Giriş Yapan</p>
                </div>
                <div class="stat-icon blue">
                    <i class="fab fa-google"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= number_format($totalViews) ?></h3>
                    <p>Toplam Görüntülenme</p>
                </div>
                <div class="stat-icon orange">
                    <i class="fas fa-eye"></i>
                </div>
            </div>
        </div>

        <!-- Recent Users Table -->
        <div class="glass-card">
            <div class="card-header">
                <div>
                    <div class="card-title">Son Kayıt Olan Kullanıcılar</div>
                    <div class="card-subtitle">En son kaydolan 10 kullanıcı</div>
                </div>
                <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-arrow-right"></i> Tümünü Gör
                </a>
            </div>
            <div class="table-wrapper">
                <?php if (count($recentUsers) > 0): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th>Kayıt Tarihi</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td>
                                <div class="table-user-info">
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <img src="<?= htmlspecialchars(getProfileImageUrl($user['profile_image'])) ?>" alt="" class="table-avatar">
                                    <?php else: ?>
                                        <span class="table-avatar-placeholder">
                                            <?= mb_strtoupper(mb_substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="table-user-name"><?= sanitize($user['full_name'] ?? '-') ?></span>
                                </div>
                            </td>
                            <td><?= sanitize($user['username']) ?></td>
                            <td><?= sanitize($user['email']) ?></td>
                            <td><?= timeAgo($user['created_at']) ?></td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge badge-active"><i class="fas fa-circle" style="font-size:6px"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive"><i class="fas fa-circle" style="font-size:6px"></i> Pasif</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>Henüz kayıtlı kullanıcı bulunmuyor.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<script>
// Mobile sidebar toggle
const sidebar = document.getElementById('adminSidebar');
const overlay = document.getElementById('sidebarOverlay');
const toggle = document.getElementById('mobileMenuToggle');

if (toggle) {
    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    });
}

if (overlay) {
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    });
}
</script>
</body>
</html>
