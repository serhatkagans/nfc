<?php
/**
 * Kalıcı NFC/QR kısa link yönlendirmesi.
 * /t/KOD  ->  kullanıcının seçtiği sayfaya (kartvizit veya portfolyo) yönlendirir.
 *
 * Etikete bu kalıcı kod yazılır; kullanıcı adı veya profil değişse bile
 * link bozulmaz çünkü kod sabittir.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$code = sanitize($_GET['code'] ?? '');

// Kaynak parametresi (varsayılan nfc). QR linkleri ?src=qr ile gelebilir.
$src = $_GET['src'] ?? 'nfc';
$allowedSrc = ['nfc', 'qr', 'link', 'share'];
if (!in_array($src, $allowedSrc, true)) {
    $src = 'nfc';
}

if ($code === '') {
    http_response_code(404);
    redirect(SITE_URL . '/index.php');
}

$stmt = $pdo->prepare(
    'SELECT u.username, u.nfc_target, COALESCE(p.is_enabled, 0) AS portfolio_enabled
       FROM users u
       LEFT JOIN portfolios p ON p.user_id = u.id
      WHERE u.nfc_code = ? AND u.is_active = 1
      LIMIT 1'
);
$stmt->execute([$code]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    redirect(SITE_URL . '/index.php');
}

// Kullanıcının seçtiği hedef: kartvizit veya portfolyo.
// Portfolyo yayında değilse güvenli şekilde kartvizite düş.
$target = sanitizeNfcTarget($row['nfc_target'] ?? 'card');
if ($target === 'portfolio' && (int) $row['portfolio_enabled'] === 1) {
    redirect(SITE_URL . '/p/' . rawurlencode($row['username']) . '?src=' . $src);
}

redirect(SITE_URL . '/u/' . rawurlencode($row['username']) . '?src=' . $src);
