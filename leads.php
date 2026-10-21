<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

requireLogin();

$user = getCurrentUser();
if (!$user) {
    flash('error', 'Oturum süresi dolmuş. Lütfen tekrar giriş yapın.');
    redirect('login.php');
}

// --- CSV Dışa Aktar ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = getUserLeads((int) $user['id']);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kisiler-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM (Excel uyumu)
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Ad Soyad', 'Telefon', 'E-posta', 'Şirket', 'Not', 'Tarih']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['name'],
            $r['phone'],
            $r['email'],
            $r['company'],
            $r['note'],
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// --- POST işlemleri ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');
        redirect('leads.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete_lead') {
        $leadId = (int) ($_POST['lead_id'] ?? 0);
        if ($leadId > 0) {
            $pdo->prepare('DELETE FROM leads WHERE id = ? AND user_id = ?')->execute([$leadId, $user['id']]);
            flash('success', 'Kişi kaydı silindi.');
        }
        redirect('leads.php');
    }

    if ($action === 'delete_all') {
        $pdo->prepare('DELETE FROM leads WHERE user_id = ?')->execute([$user['id']]);
        flash('success', 'Tüm kişi kayıtları silindi.');
        redirect('leads.php');
    }

    redirect('leads.php');
}

// Kişileri getir
$leads = getUserLeads((int) $user['id']);

// Görüntülendi: okunmamışları okundu yap (rozet temizlensin)
if (!empty($leads)) {
    $pdo->prepare('UPDATE leads SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$user['id']]);
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kişiler - <?= SITE_NAME ?></title>
    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-animated">

    <?php $activeNav = 'leads'; require __DIR__ . '/partials/user-header.php'; ?>

    <div class="dashboard">
        <div class="container">

            <div class="dashboard__header flex-between" style="flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 class="dashboard__title"><span class="text-gradient">Kişiler</span></h1>
                    <p class="text-secondary mt-sm">Profilinizden size bilgi bırakan ziyaretçiler.</p>
                </div>
                <?php if (!empty($leads)): ?>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a href="leads.php?export=csv" class="btn btn--glass btn--sm"><i class="fas fa-file-csv"></i> CSV İndir</a>
                    <form method="POST" action="leads.php" onsubmit="return confirm('Tüm kişi kayıtları silinsin mi? Bu işlem geri alınamaz.')" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="delete_all">
                        <button type="submit" class="btn btn--glass btn--sm"><i class="fas fa-trash"></i> Tümünü Sil</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <?php if (empty($leads)): ?>
            <div class="glass-card glass-card--no-hover text-center" style="padding: 56px 24px;">
                <i class="fas fa-address-book" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 16px;"></i>
                <h3 style="margin-bottom: 8px;">Henüz kişi kaydı yok</h3>
                <p class="text-secondary">Profilinizdeki <strong>"Bilgini Bırak"</strong> butonuyla ziyaretçiler size bilgilerini bıraktığında burada listelenir.</p>
            </div>
            <?php else: ?>
            <div class="leads-grid">
                <?php foreach ($leads as $lead): ?>
                <div class="glass-card glass-card--no-hover lead-card">
                    <div class="lead-card__head">
                        <div class="lead-card__avatar"><?= mb_strtoupper(mb_substr($lead['name'], 0, 1)) ?></div>
                        <div class="lead-card__id">
                            <div class="lead-card__name"><?= sanitize($lead['name']) ?></div>
                            <?php if (!empty($lead['company'])): ?>
                            <div class="lead-card__company"><i class="fas fa-building"></i> <?= sanitize($lead['company']) ?></div>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="leads.php" onsubmit="return confirm('Bu kişi kaydı silinsin mi?')" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="delete_lead">
                            <input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>">
                            <button type="submit" class="btn btn--glass btn--icon" title="Sil"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                    <div class="lead-card__body">
                        <?php if (!empty($lead['phone'])): ?>
                        <a href="tel:<?= htmlspecialchars($lead['phone']) ?>" class="lead-card__row"><i class="fas fa-phone"></i> <?= sanitize($lead['phone']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($lead['email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($lead['email']) ?>" class="lead-card__row"><i class="fas fa-envelope"></i> <?= sanitize($lead['email']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($lead['note'])): ?>
                        <div class="lead-card__note"><i class="fas fa-quote-left"></i> <?= nl2br(sanitize($lead['note'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="lead-card__foot"><i class="fas fa-clock"></i> <?= timeAgo($lead['created_at']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <script src="assets/js/app.js"></script>
</body>
</html>
