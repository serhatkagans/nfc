<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$currentUser = getCurrentUser();

// --- POST: Durum Değiştir ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Geçersiz istek. Lütfen tekrar deneyin.');
        redirect(SITE_URL . '/admin/users.php');
    }

    if ($_POST['action'] === 'toggle_active') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== $currentUser['id']) {
            $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $newStatus = $user['is_active'] ? 0 : 1;
                $update = $pdo->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
                $update->execute([$newStatus, $userId]);
                $statusText = $newStatus ? 'aktif' : 'pasif';
                flash('success', "Kullanıcı durumu başarıyla {$statusText} yapıldı.");
            }
        } else {
            flash('error', 'Kendi hesabınızın durumunu değiştiremezsiniz.');
        }
    }

    // Arama parametresini koru
    $redirectUrl = SITE_URL . '/admin/users.php';
    if (!empty($_POST['redirect_params'])) {
        $redirectUrl .= '?' . $_POST['redirect_params'];
    }
    redirect($redirectUrl);
}

// --- Arama ---
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];

if ($search !== '') {
    $where = "WHERE (full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $searchParam = "%{$search}%";
    $params = [$searchParam, $searchParam, $searchParam];
}

// Toplam sayı
$countSql = "SELECT COUNT(*) FROM users {$where}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalUsers = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalUsers / $perPage));

// Kullanıcılar
$sql = "SELECT id, full_name, username, email, profile_image, is_active, is_admin, created_at 
        FROM users {$where} 
        ORDER BY created_at DESC 
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = generateCSRFToken();
$pageTitle = 'Kullanıcılar';

// Redirect parametreleri (sayfa + arama)
$redirectParams = http_build_query(array_filter(['q' => $search, 'page' => $page > 1 ? $page : null]));
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
            <a href="<?= SITE_URL ?>/admin/index.php" class="sidebar-nav-item">
                <i class="fas fa-chart-pie"></i> Ana Sayfa
            </a>
            <a href="<?= SITE_URL ?>/admin/users.php" class="sidebar-nav-item active">
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

        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Kullanıcılar <span style="font-weight:400;font-size:14px;color:rgba(255,255,255,0.4)">(<?= number_format($totalUsers) ?>)</span></h1>
        </div>

        <!-- Search -->
        <div class="search-bar">
            <form method="GET" action="" class="search-input-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" name="q" class="form-control" placeholder="Ad, e-posta veya kullanıcı adı ile ara..." value="<?= sanitize($search) ?>">
            </form>
            <?php if ($search !== ''): ?>
                <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Temizle
                </a>
            <?php endif; ?>
        </div>

        <!-- Users Table -->
        <div class="glass-card">
            <div class="table-wrapper">
                <?php if (count($users) > 0): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Kullanıcı</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th>Durum</th>
                            <th>Kayıt Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td>
                                <div class="table-user-info">
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <img src="<?= htmlspecialchars(getProfileImageUrl($user['profile_image'])) ?>" alt="" class="table-avatar">
                                    <?php else: ?>
                                        <span class="table-avatar-placeholder">
                                            <?= mb_strtoupper(mb_substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
                                        </span>
                                    <?php endif; ?>
                                    <div>
                                        <div class="table-user-name"><?= sanitize($user['full_name'] ?? '-') ?></div>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="badge badge-admin" style="font-size:10px;padding:2px 8px;margin-top:2px">Yönetici</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= sanitize($user['username']) ?></td>
                            <td><?= sanitize($user['email']) ?></td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge badge-active"><i class="fas fa-circle" style="font-size:6px"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive"><i class="fas fa-circle" style="font-size:6px"></i> Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td><?= timeAgo($user['created_at']) ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= SITE_URL ?>/admin/user-edit.php?id=<?= $user['id'] ?>" class="btn btn-outline btn-icon" title="Düzenle">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <?php if ($user['id'] !== $currentUser['id']): ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Bu kullanıcının durumunu değiştirmek istediğinize emin misiniz?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="redirect_params" value="<?= sanitize($redirectParams) ?>">
                                        <?php if ($user['is_active']): ?>
                                            <button type="submit" class="btn btn-danger btn-icon" title="Pasif Yap">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-success btn-icon" title="Aktif Yap">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>
                                    <a href="<?= SITE_URL ?>/u/<?= sanitize($user['username']) ?>" class="btn btn-outline btn-icon" title="Profili Gör" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>
                        <?php if ($search !== ''): ?>
                            "<strong><?= sanitize($search) ?></strong>" için sonuç bulunamadı.
                        <?php else: ?>
                            Henüz kayıtlı kullanıcı bulunmuyor.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <?php
                $queryBase = $search !== '' ? "q=" . urlencode($search) . "&" : "";
                ?>

                <?php if ($page > 1): ?>
                    <a href="?<?= $queryBase ?>page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);

                if ($startPage > 1): ?>
                    <a href="?<?= $queryBase ?>page=1">1</a>
                    <?php if ($startPage > 2): ?>
                        <span class="disabled">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= $queryBase ?>page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span class="disabled">...</span>
                    <?php endif; ?>
                    <a href="?<?= $queryBase ?>page=<?= $totalPages ?>"><?= $totalPages ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= $queryBase ?>page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </nav>
            <div class="pagination-info">
                Toplam <?= number_format($totalUsers) ?> kullanıcıdan <?= (($page - 1) * $perPage) + 1 ?>-<?= min($page * $perPage, $totalUsers) ?> arası gösteriliyor
            </div>
            <?php endif; ?>
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
