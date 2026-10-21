<?php
/**
 * Öğrenci Dijital Portfolyosu - düzenleme sayfası (panel).
 * Ortaokul ve lise öğrencileri okul, sınıf, hakkında, yetenekler ve
 * başarı / proje / sertifika kayıtlarını buradan yönetir.
 */
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

const PORTFOLIO_MAX_ITEMS = 40;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');
        redirect('portfolio-edit.php');
    }

    $action = $_POST['action'] ?? '';

    // --- Genel bilgileri kaydet ---
    if ($action === 'save_portfolio') {
        $grade = $_POST['grade'] ?? '';
        $validGrades = [];
        foreach (getGradeOptions() as $opts) {
            $validGrades += $opts;
        }
        if (!isset($validGrades[$grade])) {
            $grade = '';
        }

        $data = [
            'is_enabled'  => !empty($_POST['is_enabled']) ? 1 : 0,
            'school_name' => cleanText($_POST['school_name'] ?? '', 150),
            'grade'       => $grade,
            'headline'    => cleanText($_POST['headline'] ?? '', 150),
            'about'       => cleanText($_POST['about'] ?? '', 1000),
            'goals'       => cleanText($_POST['goals'] ?? '', 500),
            'skills'      => cleanText($_POST['skills'] ?? '', 500),
            'interests'   => cleanText($_POST['interests'] ?? '', 500),
            'languages'   => cleanText($_POST['languages'] ?? '', 255),
        ];

        $sql = 'INSERT INTO portfolios (user_id, is_enabled, school_name, grade, headline, about, goals, skills, interests, languages)
                VALUES (:uid, :is_enabled, :school_name, :grade, :headline, :about, :goals, :skills, :interests, :languages)
                ON DUPLICATE KEY UPDATE
                    is_enabled = VALUES(is_enabled), school_name = VALUES(school_name), grade = VALUES(grade),
                    headline = VALUES(headline), about = VALUES(about), goals = VALUES(goals),
                    skills = VALUES(skills), interests = VALUES(interests), languages = VALUES(languages)';
        try {
            $pdo->prepare($sql)->execute([':uid' => $userId] + array_combine(
                array_map(fn($k) => ':' . $k, array_keys($data)),
                array_values($data)
            ));
            flash('success', $data['is_enabled']
                ? 'Portfolyonuz kaydedildi ve yayında.'
                : 'Portfolyonuz kaydedildi (henüz yayında değil).');
        } catch (PDOException $e) {
            flash('error', 'Kaydedilemedi. Veritabanı güncellemesi (migration_v4.sql) yapıldı mı?');
        }
        redirect('portfolio-edit.php');
    }

    // --- Kayıt ekle ---
    if ($action === 'add_item') {
        $title = cleanText($_POST['item_title'] ?? '', 150);
        $type  = sanitizePortfolioItemType($_POST['item_type'] ?? '');
        $desc  = cleanText($_POST['item_description'] ?? '', 1000);
        $date  = cleanText($_POST['item_date'] ?? '', 30);
        $url   = trim($_POST['item_url'] ?? '');

        if ($title === '') {
            flash('error', 'Başlık zorunludur.');
            redirect('portfolio-edit.php#items');
        }
        if ($url !== '') {
            if (!preg_match('~^https?://~i', $url)) {
                $url = 'https://' . $url;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL) || mb_strlen($url) > 500) {
                flash('error', 'Geçerli bir bağlantı adresi girin.');
                redirect('portfolio-edit.php#items');
            }
        }

        $cnt = $pdo->prepare('SELECT COUNT(*) FROM portfolio_items WHERE user_id = ?');
        $cnt->execute([$userId]);
        if ((int) $cnt->fetchColumn() >= PORTFOLIO_MAX_ITEMS) {
            flash('error', 'En fazla ' . PORTFOLIO_MAX_ITEMS . ' kayıt ekleyebilirsiniz.');
            redirect('portfolio-edit.php#items');
        }

        $image = null;
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $image = uploadProfileImage($_FILES['item_image']);
            if (!$image) {
                redirect('portfolio-edit.php#items'); // hata mesajı flash ile ayarlandı
            }
        }

        $ord = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM portfolio_items WHERE user_id = ?');
        $ord->execute([$userId]);

        $pdo->prepare(
            'INSERT INTO portfolio_items (user_id, item_type, title, description, item_date, url, image, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $type, $title, $desc ?: null, $date ?: null, $url ?: null, $image, (int) $ord->fetchColumn()]);

        flash('success', 'Kayıt eklendi.');
        redirect('portfolio-edit.php#items');
    }

    // --- Kayıt sil ---
    if ($action === 'delete_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $sel = $pdo->prepare('SELECT image FROM portfolio_items WHERE id = ? AND user_id = ?');
        $sel->execute([$itemId, $userId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pdo->prepare('DELETE FROM portfolio_items WHERE id = ? AND user_id = ?')->execute([$itemId, $userId]);
            deleteProfileImage($row['image']);
            flash('success', 'Kayıt silindi.');
        }
        redirect('portfolio-edit.php#items');
    }

    // --- Sırayı değiştir (yukarı / aşağı) ---
    if ($action === 'move_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $dir    = ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down';
        $items  = getPortfolioItems($userId);
        $ids    = array_map(fn($i) => (int) $i['id'], $items);
        $pos    = array_search($itemId, $ids, true);
        if ($pos !== false) {
            $swap = $dir === 'up' ? $pos - 1 : $pos + 1;
            if (isset($ids[$swap])) {
                [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
                $upd = $pdo->prepare('UPDATE portfolio_items SET sort_order = ? WHERE id = ? AND user_id = ?');
                foreach ($ids as $i => $id) {
                    $upd->execute([$i + 1, $id, $userId]);
                }
            }
        }
        redirect('portfolio-edit.php#items');
    }

    redirect('portfolio-edit.php');
}

$csrfToken    = generateCSRFToken();
$portfolio    = getPortfolio($userId);
$items        = getPortfolioItems($userId);
$itemTypes    = getPortfolioItemTypes();
$gradeOptions = getGradeOptions();
$portfolioUrl = SITE_URL . '/p/' . $user['username'];
$nfcTarget    = sanitizeNfcTarget($user['nfc_target'] ?? 'card');

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolyom - <?= SITE_NAME ?></title>
    <script>(function(){try{if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-animated">

    <?php $activeNav = 'portfolio'; require __DIR__ . '/partials/user-header.php'; ?>

    <div class="dashboard">
        <div class="container">

            <div class="dashboard__header flex-between" style="flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 class="dashboard__title">
                        <span class="text-gradient">Dijital Portfolyom</span> 🎓
                    </h1>
                    <p class="text-secondary mt-sm">Ortaokul ve lise öğrencileri için başarı, proje ve etkinlik vitrini.</p>
                </div>
                <?php if ((int) $portfolio['is_enabled'] === 1): ?>
                <a href="<?= $e($portfolioUrl) ?>" target="_blank" class="btn btn--primary btn--sm">
                    <i class="fas fa-external-link-alt"></i> Portfolyomu Gör
                </a>
                <?php endif; ?>
            </div>

            <!-- Durum / NFC bilgisi -->
            <div class="glass-card glass-card--no-hover mb-xl" style="padding: 20px 24px;">
                <div class="flex-between" style="flex-wrap: wrap; gap: 12px;">
                    <div>
                        <div class="text-secondary" style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                            <i class="fas fa-graduation-cap"></i> Portfolyo Linkiniz
                        </div>
                        <div class="nfc-url-box__url" style="font-size: 1rem;"><?= $e($portfolioUrl) ?></div>
                        <div class="form-hint" style="margin-top: 6px;">
                            <i class="fas fa-wifi"></i>
                            NFC okutulunca açılan sayfa:
                            <strong><?= $e(getNfcTargets()[$nfcTarget]) ?></strong>
                            — <a href="dashboard.php">Panel'den değiştirin</a>.
                            <?php if ($nfcTarget === 'portfolio' && (int) $portfolio['is_enabled'] !== 1): ?>
                                <br><span style="color:#f7971e;"><i class="fas fa-triangle-exclamation"></i> Portfolyo yayında olmadığı için NFC şimdilik kartvizite gider.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button class="btn btn--glass btn--sm" data-copy="<?= $e($portfolioUrl) ?>">
                        <i class="fas fa-copy"></i> Kopyala
                    </button>
                </div>
            </div>

            <!-- Genel bilgiler -->
            <form method="POST" action="portfolio-edit.php">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="save_portfolio">

                <div class="dashboard__grid">
                    <div class="glass-card glass-card--no-hover dashboard-section">
                        <h3 class="dashboard-section__title"><i class="fas fa-school"></i> Okul Bilgileri</h3>

                        <label class="pf-switch">
                            <input type="checkbox" name="is_enabled" value="1" <?= (int) $portfolio['is_enabled'] === 1 ? 'checked' : '' ?>>
                            <span class="pf-switch__track"></span>
                            <span class="pf-switch__label">Portfolyomu yayınla</span>
                        </label>
                        <p class="form-hint" style="margin: -6px 0 20px;">Kapalıyken portfolyo sayfanız kimseye görünmez.</p>

                        <div class="form-group">
                            <label class="form-label" for="school_name">Okul Adı</label>
                            <input type="text" id="school_name" name="school_name" class="form-input" maxlength="150"
                                   placeholder="Örn. Atatürk Anadolu Lisesi" value="<?= $e($portfolio['school_name']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="grade">Sınıf</label>
                            <select id="grade" name="grade" class="form-input">
                                <option value="">Seçiniz</option>
                                <?php foreach ($gradeOptions as $group => $opts): ?>
                                <optgroup label="<?= $e($group) ?>">
                                    <?php foreach ($opts as $val => $label): ?>
                                    <option value="<?= $e($val) ?>" <?= (string) $portfolio['grade'] === (string) $val ? 'selected' : '' ?>><?= $e($label) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="headline">Kısa Tanıtım</label>
                            <input type="text" id="headline" name="headline" class="form-input" maxlength="150"
                                   placeholder="Örn. Robotik ve yazılım meraklısı" value="<?= $e($portfolio['headline']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="goals">Hedefim / Hayalimdeki Meslek</label>
                            <input type="text" id="goals" name="goals" class="form-input" maxlength="500"
                                   placeholder="Örn. Bilgisayar mühendisi olmak" value="<?= $e($portfolio['goals']) ?>">
                        </div>
                    </div>

                    <div class="glass-card glass-card--no-hover dashboard-section">
                        <h3 class="dashboard-section__title"><i class="fas fa-user-graduate"></i> Hakkımda</h3>

                        <div class="form-group">
                            <label class="form-label" for="about">Kendini Tanıt</label>
                            <textarea id="about" name="about" class="form-input" rows="6" maxlength="1000"
                                      placeholder="Neleri seversin, nelerle uğraşırsın?"><?= $e($portfolio['about']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="skills">Yetenekler</label>
                            <input type="text" id="skills" name="skills" class="form-input" maxlength="500"
                                   placeholder="Matematik, Python, Gitar, Takım çalışması" value="<?= $e($portfolio['skills']) ?>">
                            <div class="form-hint">Virgülle ayırın</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="interests">İlgi Alanları / Hobiler</label>
                            <input type="text" id="interests" name="interests" class="form-input" maxlength="500"
                                   placeholder="Satranç, Basketbol, Kitap okumak" value="<?= $e($portfolio['interests']) ?>">
                            <div class="form-hint">Virgülle ayırın</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="languages">Yabancı Diller</label>
                            <input type="text" id="languages" name="languages" class="form-input" maxlength="255"
                                   placeholder="İngilizce (B1), Almanca (A2)" value="<?= $e($portfolio['languages']) ?>">
                        </div>
                    </div>
                </div>

                <div class="text-center mt-xl" style="padding-bottom: 32px;">
                    <button type="submit" class="btn btn--primary btn--lg">
                        <i class="fas fa-check"></i> Portfolyoyu Kaydet
                    </button>
                </div>
            </form>

            <!-- Kayıtlar: başarı, proje, sertifika... -->
            <div class="glass-card glass-card--no-hover dashboard-section" id="items">
                <h3 class="dashboard-section__title"><i class="fas fa-trophy"></i> Başarılar, Projeler ve Etkinlikler</h3>
                <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 20px;">
                    Ödüller, yarışmalar, projeler, sertifikalar, kulüp ve gönüllülük çalışmaları. En fazla <?= PORTFOLIO_MAX_ITEMS ?> kayıt.
                </p>

                <?php if ($items): ?>
                <div class="links-list">
                    <?php foreach ($items as $idx => $item):
                        $t = $itemTypes[$item['item_type']] ?? $itemTypes['other']; ?>
                    <div class="link-item">
                        <div class="link-item__icon"><i class="<?= $e($t['icon']) ?>"></i></div>
                        <div class="link-item__info">
                            <div class="link-item__title"><?= $e($item['title']) ?></div>
                            <div class="link-item__url" style="white-space: normal;">
                                <?= $e($t['label']) ?><?= $item['item_date'] ? ' · ' . $e($item['item_date']) : '' ?>
                                <?= $item['image'] ? ' · <i class="fas fa-image"></i>' : '' ?>
                                <?= $item['url'] ? ' · <i class="fas fa-link"></i>' : '' ?>
                            </div>
                        </div>
                        <div class="pf-item-actions">
                            <?php foreach (['up' => 'fa-arrow-up', 'down' => 'fa-arrow-down'] as $dir => $icon):
                                $disabled = ($dir === 'up' && $idx === 0) || ($dir === 'down' && $idx === count($items) - 1); ?>
                            <form method="POST" action="portfolio-edit.php">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="move_item">
                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="dir" value="<?= $dir ?>">
                                <button type="submit" class="btn btn--glass btn--sm" title="<?= $dir === 'up' ? 'Yukarı' : 'Aşağı' ?>" <?= $disabled ? 'disabled' : '' ?>>
                                    <i class="fas <?= $icon ?>"></i>
                                </button>
                            </form>
                            <?php endforeach; ?>
                            <form method="POST" action="portfolio-edit.php" onsubmit="return confirm('Bu kayıt silinsin mi?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="btn btn--glass btn--sm link-item__delete" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 20px;">Henüz kayıt eklemediniz. İlk başarınızı ekleyin!</p>
                <?php endif; ?>

                <form method="POST" action="portfolio-edit.php" enctype="multipart/form-data" class="add-link-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="add_item">

                    <div class="pf-add-grid">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="item_type">Tür</label>
                            <select id="item_type" name="item_type" class="form-input">
                                <?php foreach ($itemTypes as $key => $t): ?>
                                <option value="<?= $e($key) ?>"><?= $e($t['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="item_title">Başlık *</label>
                            <input type="text" id="item_title" name="item_title" class="form-input" maxlength="150" required
                                   placeholder="Örn. TÜBİTAK Bilim Olimpiyatı - Bölge 2.si">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="item_date">Tarih</label>
                            <input type="text" id="item_date" name="item_date" class="form-input" maxlength="30" placeholder="Örn. Mayıs 2025">
                        </div>
                        <div class="form-group pf-add-grid__wide" style="margin-bottom:0;">
                            <label class="form-label" for="item_description">Açıklama</label>
                            <textarea id="item_description" name="item_description" class="form-input" rows="2" maxlength="1000"
                                      placeholder="Ne yaptın, ne öğrendin?"></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="item_url">Bağlantı (isteğe bağlı)</label>
                            <input type="text" id="item_url" name="item_url" class="form-input" placeholder="https://...">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="item_image">Fotoğraf / Belge görseli</label>
                            <input type="file" id="item_image" name="item_image" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>
                    </div>
                    <div class="mt-md">
                        <button type="submit" class="btn btn--primary btn--sm">
                            <i class="fas fa-plus"></i> Kayıt Ekle
                        </button>
                    </div>
                </form>
            </div>

            <p class="form-hint text-center" style="margin: 24px 0 40px;">
                <i class="fas fa-shield-halved"></i>
                Güvenliğin için portfolyonda telefon numarası, ev adresi veya okul numarası paylaşma.
                Portfolyo sayfası arama motorlarında listelenmez.
            </p>
        </div>
    </div>

    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Temayı değiştir">
        <i class="fas fa-moon"></i>
    </button>

    <script src="assets/js/app.js"></script>
</body>
</html>
