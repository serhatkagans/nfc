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

$userId = (int) $user['id'];

// --- Olay türlerine göre toplamlar ---
$totals = ['view' => 0, 'click' => 0, 'qr' => 0, 'vcard' => 0, 'lead' => 0];
$stmt = $pdo->prepare('SELECT event_type, COUNT(*) c FROM profile_events WHERE user_id = ? GROUP BY event_type');
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $row) {
    if (isset($totals[$row['event_type']])) {
        $totals[$row['event_type']] = (int) $row['c'];
    }
}

// Tüm zamanlar görüntülenme (users.view_count geçmişi de içerir)
$totalViewsAllTime = (int) ($user['view_count'] ?? 0);

// --- Son 14 günlük günlük seri ---
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $days[$d] = ['view' => 0, 'click' => 0];
}
$stmt = $pdo->prepare(
    "SELECT DATE(created_at) d,
            SUM(event_type = 'view')  v,
            SUM(event_type = 'click') c
     FROM profile_events
     WHERE user_id = ? AND created_at >= (CURDATE() - INTERVAL 13 DAY)
     GROUP BY DATE(created_at)"
);
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $row) {
    $d = $row['d'];
    if (isset($days[$d])) {
        $days[$d]['view']  = (int) $row['v'];
        $days[$d]['click'] = (int) $row['c'];
    }
}

$chartLabels = [];
$chartViews  = [];
$chartClicks = [];
foreach ($days as $d => $vals) {
    $chartLabels[] = date('d.m', strtotime($d));
    $chartViews[]  = $vals['view'];
    $chartClicks[] = $vals['click'];
}

// --- En çok tıklanan hedefler ---
$stmt = $pdo->prepare(
    "SELECT target, COUNT(*) c FROM profile_events
     WHERE user_id = ? AND event_type = 'click' AND target IS NOT NULL AND target <> ''
     GROUP BY target ORDER BY c DESC LIMIT 10"
);
$stmt->execute([$userId]);
$topTargets = $stmt->fetchAll();

// --- Trafik kaynağı dağılımı (görüntülenme olaylarından) ---
$sourceCounts = ['nfc' => 0, 'qr' => 0, 'link' => 0, 'share' => 0];
$stmt = $pdo->prepare(
    "SELECT target, COUNT(*) c FROM profile_events
     WHERE user_id = ? AND event_type = 'view' AND target LIKE 'src:%'
     GROUP BY target"
);
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $row) {
    $key = substr($row['target'], 4); // 'src:nfc' -> 'nfc'
    if (isset($sourceCounts[$key])) {
        $sourceCounts[$key] = (int) $row['c'];
    }
}
$sourceTotal = array_sum($sourceCounts);
$sourceMeta = [
    'nfc'   => ['label' => 'NFC Dokunuş', 'icon' => 'fa-wifi'],
    'qr'    => ['label' => 'QR Kod',      'icon' => 'fa-qrcode'],
    'link'  => ['label' => 'Link',        'icon' => 'fa-link'],
    'share' => ['label' => 'Paylaşım',    'icon' => 'fa-share-nodes'],
];

// Özel bağlantı başlıklarını çöz
$linkTitles = [];
foreach (getUserLinks($userId) as $l) {
    $linkTitles[(int) $l['id']] = $l['title'];
}

$platformLabels = [
    'phone' => 'Telefon', 'email' => 'E-posta', 'instagram' => 'Instagram',
    'linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'youtube' => 'YouTube',
    'whatsapp' => 'WhatsApp', 'tiktok' => 'TikTok', 'telegram' => 'Telegram',
    'twitter' => 'X (Twitter)', 'website' => 'Web Sitesi', 'map' => 'Konum / Harita',
    'portfolio' => 'Portfolyo',
];

function targetLabel(string $target, array $linkTitles, array $platformLabels): string
{
    if (strpos($target, 'link:') === 0) {
        $id = (int) substr($target, 5);
        return $linkTitles[$id] ?? 'Özel bağlantı';
    }
    return $platformLabels[$target] ?? ucfirst($target);
}

$maxTargetClicks = 0;
foreach ($topTargets as $t) {
    $maxTargetClicks = max($maxTargetClicks, (int) $t['c']);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analitik - <?= SITE_NAME ?></title>
    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-animated">

    <?php $activeNav = 'analytics'; require __DIR__ . '/partials/user-header.php'; ?>

    <div class="dashboard">
        <div class="container">

            <div class="dashboard__header">
                <h1 class="dashboard__title"><span class="text-gradient">Analitik</span></h1>
                <p class="text-secondary mt-sm">Profilinizin performansını takip edin.</p>
            </div>

            <!-- Özet Kartları -->
            <div class="stats-cards">
                <div class="glass-card glass-card--no-hover stat-tile">
                    <div class="stat-tile__icon" style="background: var(--primary-gradient);"><i class="fas fa-eye"></i></div>
                    <div class="stat-tile__value"><?= number_format($totalViewsAllTime) ?></div>
                    <div class="stat-tile__label">Toplam Görüntülenme</div>
                </div>
                <div class="glass-card glass-card--no-hover stat-tile">
                    <div class="stat-tile__icon" style="background: var(--secondary-gradient);"><i class="fas fa-hand-pointer"></i></div>
                    <div class="stat-tile__value"><?= number_format($totals['click']) ?></div>
                    <div class="stat-tile__label">Bağlantı Tıklaması</div>
                </div>
                <div class="glass-card glass-card--no-hover stat-tile">
                    <div class="stat-tile__icon" style="background: var(--success-gradient);"><i class="fas fa-address-book"></i></div>
                    <div class="stat-tile__value"><?= number_format($totals['vcard']) ?></div>
                    <div class="stat-tile__label">Rehbere Kaydetme</div>
                </div>
                <div class="glass-card glass-card--no-hover stat-tile">
                    <div class="stat-tile__icon" style="background: linear-gradient(135deg,#f7971e,#ffd200);"><i class="fas fa-qrcode"></i></div>
                    <div class="stat-tile__value"><?= number_format($totals['qr']) ?></div>
                    <div class="stat-tile__label">QR Görüntüleme</div>
                </div>
                <div class="glass-card glass-card--no-hover stat-tile">
                    <div class="stat-tile__icon" style="background: linear-gradient(135deg,#11998e,#38ef7d);"><i class="fas fa-user-plus"></i></div>
                    <div class="stat-tile__value"><?= number_format($totals['lead']) ?></div>
                    <div class="stat-tile__label">Bırakılan İletişim</div>
                </div>
            </div>

            <!-- Grafik -->
            <div class="glass-card glass-card--no-hover dashboard-section" style="margin-top: 24px;">
                <h3 class="dashboard-section__title"><i class="fas fa-chart-line"></i> Son 14 Gün</h3>
                <?php if (array_sum($chartViews) === 0 && array_sum($chartClicks) === 0): ?>
                    <p class="text-secondary text-center" style="padding: 32px 0;">Son 14 günde henüz veri yok. Profiliniz görüntülendikçe burada görünecek.</p>
                <?php else: ?>
                    <div style="position: relative; height: 300px;">
                        <canvas id="trafficChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Trafik Kaynağı -->
            <div class="glass-card glass-card--no-hover dashboard-section" style="margin-top: 24px;">
                <h3 class="dashboard-section__title"><i class="fas fa-arrows-split-up-and-left"></i> Trafik Kaynağı</h3>
                <?php if ($sourceTotal === 0): ?>
                    <p class="text-secondary text-center" style="padding: 24px 0;">Henüz kaynak verisi yok. NFC etiketi veya QR ile gelen ziyaretler burada ayrışacak.</p>
                <?php else: ?>
                    <div class="source-grid">
                        <?php foreach ($sourceMeta as $key => $meta): ?>
                        <?php $pct = $sourceTotal > 0 ? round($sourceCounts[$key] / $sourceTotal * 100) : 0; ?>
                        <div class="source-tile">
                            <i class="fas <?= $meta['icon'] ?>"></i>
                            <div class="source-tile__value"><?= number_format($sourceCounts[$key]) ?></div>
                            <div class="source-tile__label"><?= $meta['label'] ?></div>
                            <div class="source-tile__pct"><?= $pct ?>%</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- En Çok Tıklananlar -->
            <div class="glass-card glass-card--no-hover dashboard-section" style="margin-top: 24px;">
                <h3 class="dashboard-section__title"><i class="fas fa-ranking-star"></i> En Çok Tıklanan Bağlantılar</h3>
                <?php if (empty($topTargets)): ?>
                    <p class="text-secondary text-center" style="padding: 24px 0;">Henüz bağlantı tıklaması kaydedilmedi.</p>
                <?php else: ?>
                    <div class="bar-list">
                        <?php foreach ($topTargets as $t): ?>
                        <?php $pct = $maxTargetClicks > 0 ? round((int)$t['c'] / $maxTargetClicks * 100) : 0; ?>
                        <div class="bar-row">
                            <div class="bar-row__label"><?= htmlspecialchars(targetLabel($t['target'], $linkTitles, $platformLabels)) ?></div>
                            <div class="bar-row__track"><div class="bar-row__fill" style="width: <?= $pct ?>%;"></div></div>
                            <div class="bar-row__value"><?= number_format((int)$t['c']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <?php if (array_sum($chartViews) > 0 || array_sum($chartClicks) > 0): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
    (function () {
        var ctx = document.getElementById('trafficChart');
        if (!ctx || !window.Chart) return;

        var isLight = document.documentElement.getAttribute('data-theme') === 'light';
        var gridColor = isLight ? 'rgba(20,20,50,0.08)' : 'rgba(255,255,255,0.08)';
        var tickColor = isLight ? 'rgba(26,26,46,0.6)' : 'rgba(255,255,255,0.6)';

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [
                    {
                        label: 'Görüntülenme',
                        data: <?= json_encode($chartViews) ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102,126,234,0.15)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 3
                    },
                    {
                        label: 'Tıklama',
                        data: <?= json_encode($chartClicks) ?>,
                        borderColor: '#f093fb',
                        backgroundColor: 'rgba(240,147,251,0.12)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { labels: { color: tickColor, usePointStyle: true } }
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } }
                }
            }
        });
    })();
    </script>
    <?php endif; ?>

    <script src="assets/js/app.js"></script>
</body>
</html>
