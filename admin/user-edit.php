<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$currentUser = getCurrentUser();

// --- Kullanıcıyı getir ---
$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    flash('error', 'Geçersiz kullanıcı.');
    redirect(SITE_URL . '/admin/users.php');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    flash('error', 'Kullanıcı bulunamadı.');
    redirect(SITE_URL . '/admin/users.php');
}

// --- POST: Güncelle ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Geçersiz istek. Lütfen tekrar deneyin.');
        redirect(SITE_URL . '/admin/user-edit.php?id=' . $userId);
    }

    $fullName  = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $title     = trim($_POST['title'] ?? '');
    $company   = trim($_POST['company'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $linkedin  = trim($_POST['linkedin'] ?? '');
    $facebook  = trim($_POST['facebook'] ?? '');
    $youtube   = trim($_POST['youtube'] ?? '');
    $whatsapp  = trim($_POST['whatsapp'] ?? '');
    $tiktok    = trim($_POST['tiktok'] ?? '');
    $telegram  = trim($_POST['telegram'] ?? '');
    $twitter   = trim($_POST['twitter'] ?? '');
    $website   = trim($_POST['website'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $workingHours = trim($_POST['working_hours'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');
    $isAdmin   = isset($_POST['is_admin']) ? 1 : 0;
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    // Validasyon
    $errors = [];

    if ($fullName === '') {
        $errors[] = 'Ad Soyad alanı zorunludur.';
    }

    if ($username === '') {
        $errors[] = 'Kullanıcı adı zorunludur.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi giriniz.';
    }

    // Benzersizlik kontrolleri
    if ($username !== $user['username']) {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$username, $userId]);
        if ($check->fetch()) {
            $errors[] = 'Bu kullanıcı adı zaten kullanılıyor.';
        }
    }

    if ($email !== $user['email']) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            $errors[] = 'Bu e-posta adresi zaten kullanılıyor.';
        }
    }

    // Kendini admin'den çıkaramaz
    if ($userId === $currentUser['id'] && $isAdmin === 0) {
        $errors[] = 'Kendi yönetici yetkinizi kaldıramazsınız.';
        $isAdmin = 1;
    }

    if (empty($errors)) {
        $update = $pdo->prepare("
            UPDATE users SET
                full_name = ?, username = ?, email = ?,
                title = ?, company = ?,
                phone = ?, instagram = ?, linkedin = ?,
                facebook = ?, youtube = ?, whatsapp = ?,
                tiktok = ?, telegram = ?, twitter = ?,
                website = ?, address = ?, working_hours = ?,
                bio = ?, is_admin = ?, is_active = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $update->execute([
            $fullName, $username, $email,
            $title, $company,
            $phone, $instagram, $linkedin,
            $facebook, $youtube, $whatsapp,
            $tiktok, $telegram, $twitter,
            $website, $address, $workingHours,
            $bio, $isAdmin, $isActive,
            $userId
        ]);

        flash('success', 'Kullanıcı bilgileri başarıyla güncellendi.');
        redirect(SITE_URL . '/admin/user-edit.php?id=' . $userId);
    } else {
        // Hataları flash mesaj olarak göster
        flash('error', implode('<br>', $errors));

        // Form verilerini koru
        $user['full_name'] = $fullName;
        $user['username']  = $username;
        $user['email']     = $email;
        $user['title']     = $title;
        $user['company']   = $company;
        $user['phone']     = $phone;
        $user['instagram'] = $instagram;
        $user['linkedin']  = $linkedin;
        $user['facebook']  = $facebook;
        $user['youtube']   = $youtube;
        $user['whatsapp']  = $whatsapp;
        $user['tiktok']    = $tiktok;
        $user['telegram']  = $telegram;
        $user['twitter']   = $twitter;
        $user['website']   = $website;
        $user['address']   = $address;
        $user['working_hours'] = $workingHours;
        $user['bio']       = $bio;
        $user['is_admin']  = $isAdmin;
        $user['is_active'] = $isActive;
    }
}

$csrfToken = generateCSRFToken();
$pageTitle = 'Kullanıcı Düzenle';
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
            <h1><i class="fas fa-user-edit"></i> Kullanıcı Düzenle</h1>
            <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Kullanıcılara Dön
            </a>
        </div>

        <div class="glass-card">
            <!-- Profile Image -->
            <div style="margin-bottom: 24px;">
                <?php if (!empty($user['profile_image'])): ?>
                    <img src="<?= htmlspecialchars(getProfileImageUrl($user['profile_image'])) ?>" alt="Profil" class="profile-image-preview">
                <?php else: ?>
                    <div class="profile-image-placeholder">
                        <?= mb_strtoupper(mb_substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div style="font-size:13px;color:rgba(255,255,255,0.4)">
                    Kayıt: <?= date('d.m.Y H:i', strtotime($user['created_at'])) ?>
                    &bull; <i class="fas fa-eye"></i> <?= number_format((int)($user['view_count'] ?? 0)) ?> görüntülenme
                    <?php if ($user['google_id']): ?>
                        &bull; <span class="badge badge-admin" style="font-size:10px;padding:2px 8px"><i class="fab fa-google"></i> Google Hesabı</span>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <!-- Temel Bilgiler -->
                <div class="card-title mb-2">Temel Bilgiler</div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="full_name">Ad Soyad *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" value="<?= sanitize($user['full_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="username">Kullanıcı Adı *</label>
                        <input type="text" id="username" name="username" class="form-control" value="<?= sanitize($user['username']) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="email">E-posta *</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= sanitize($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">Telefon</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?= sanitize($user['phone'] ?? '') ?>" placeholder="+90 5xx xxx xx xx">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="title"><i class="fas fa-briefcase"></i> Ünvan / Pozisyon</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?= sanitize($user['title'] ?? '') ?>" placeholder="Örn. Pazarlama Müdürü">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="company"><i class="fas fa-building"></i> Şirket / Kurum</label>
                        <input type="text" id="company" name="company" class="form-control" value="<?= sanitize($user['company'] ?? '') ?>" placeholder="Örn. ABC Teknoloji A.Ş.">
                    </div>
                </div>

                <!-- Sosyal Medya -->
                <div class="card-title mb-2 mt-3">Sosyal Medya</div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="instagram"><i class="fab fa-instagram"></i> Instagram</label>
                        <input type="text" id="instagram" name="instagram" class="form-control" value="<?= sanitize($user['instagram'] ?? '') ?>" placeholder="kullanici_adi">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="linkedin"><i class="fab fa-linkedin"></i> LinkedIn</label>
                        <input type="text" id="linkedin" name="linkedin" class="form-control" value="<?= sanitize($user['linkedin'] ?? '') ?>" placeholder="profil-linki">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="facebook"><i class="fab fa-facebook"></i> Facebook</label>
                        <input type="text" id="facebook" name="facebook" class="form-control" value="<?= sanitize($user['facebook'] ?? '') ?>" placeholder="kullanici_adi">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="youtube"><i class="fab fa-youtube"></i> YouTube</label>
                        <input type="text" id="youtube" name="youtube" class="form-control" value="<?= sanitize($user['youtube'] ?? '') ?>" placeholder="kanal-linki">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp</label>
                        <input type="text" id="whatsapp" name="whatsapp" class="form-control" value="<?= sanitize($user['whatsapp'] ?? '') ?>" placeholder="905XXXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tiktok"><i class="fab fa-tiktok"></i> TikTok</label>
                        <input type="text" id="tiktok" name="tiktok" class="form-control" value="<?= sanitize($user['tiktok'] ?? '') ?>" placeholder="kullanici_adi">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="telegram"><i class="fab fa-telegram"></i> Telegram</label>
                        <input type="text" id="telegram" name="telegram" class="form-control" value="<?= sanitize($user['telegram'] ?? '') ?>" placeholder="kullanici_adi">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="twitter"><i class="fab fa-x-twitter"></i> X (Twitter)</label>
                        <input type="text" id="twitter" name="twitter" class="form-control" value="<?= sanitize($user['twitter'] ?? '') ?>" placeholder="kullanici_adi">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="website"><i class="fas fa-globe"></i> Website</label>
                    <input type="url" id="website" name="website" class="form-control" value="<?= sanitize($user['website'] ?? '') ?>" placeholder="https://ornek.com">
                </div>

                <!-- Konum & Çalışma -->
                <div class="card-title mb-2 mt-3">Konum & Çalışma</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="address"><i class="fas fa-location-dot"></i> Adres</label>
                        <input type="text" id="address" name="address" class="form-control" value="<?= sanitize($user['address'] ?? '') ?>" placeholder="Örn. Levent, İstanbul">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="working_hours"><i class="fas fa-clock"></i> Çalışma Saatleri</label>
                        <input type="text" id="working_hours" name="working_hours" class="form-control" value="<?= sanitize($user['working_hours'] ?? '') ?>" placeholder="Hafta içi 09:00 - 18:00">
                    </div>
                </div>

                <!-- Bio -->
                <div class="card-title mb-2 mt-3">Hakkında</div>
                <div class="form-group">
                    <label class="form-label" for="bio">Biyografi</label>
                    <textarea id="bio" name="bio" class="form-control" placeholder="Kullanıcı hakkında kısa bilgi..."><?= sanitize($user['bio'] ?? '') ?></textarea>
                </div>

                <!-- Yetkiler -->
                <div class="card-title mb-2 mt-3">Yetki & Durum</div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="is_admin" value="1" <?= $user['is_admin'] ? 'checked' : '' ?> <?= $userId === $currentUser['id'] ? 'disabled' : '' ?>>
                            <span class="form-check-label">
                                <i class="fas fa-shield-halved"></i> Yönetici Yetkisi
                            </span>
                        </label>
                        <?php if ($userId === $currentUser['id']): ?>
                            <input type="hidden" name="is_admin" value="1">
                            <small style="color:var(--text-muted);font-size:11px;margin-top:4px;display:block">Kendi yönetici yetkinizi kaldıramazsınız.</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="is_active" value="1" <?= $user['is_active'] ? 'checked' : '' ?>>
                            <span class="form-check-label">
                                <i class="fas fa-circle-check"></i> Hesap Aktif
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Değişiklikleri Kaydet
                    </button>
                    <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> İptal
                    </a>
                    <a href="<?= SITE_URL ?>/u/<?= sanitize($user['username']) ?>" class="btn btn-outline" target="_blank" style="margin-left:auto">
                        <i class="fas fa-eye"></i> Profili Gör
                    </a>
                </div>
            </form>
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
