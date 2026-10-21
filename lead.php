<?php
/**
 * Lead (kişi) toplama uç noktası.
 * Profil sayfasındaki "Bilgini Bırak" formundan AJAX ile çağrılır.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Geçersiz istek.']);
    exit;
}

// CSRF doğrulaması
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.']);
    exit;
}

// Honeypot: dolu ise bot kabul et (sessizce başarı dön)
if (!empty($_POST['website_hp'])) {
    echo json_encode(['ok' => true]);
    exit;
}

$username = sanitize($_POST['username'] ?? '');
$name     = sanitize($_POST['name'] ?? '');
$phone    = sanitize($_POST['phone'] ?? '');
$email    = sanitize($_POST['email'] ?? '');
$company  = sanitize($_POST['company'] ?? '');
$note     = sanitize($_POST['note'] ?? '');

if ($username === '' || $name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ad Soyad alanı zorunludur.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Geçerli bir e-posta adresi girin.']);
    exit;
}

// En az bir iletişim bilgisi (telefon veya e-posta) iste
if ($phone === '' && $email === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Lütfen telefon veya e-posta bilgisi girin.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$username]);
    $userId = (int) $stmt->fetchColumn();

    if ($userId <= 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Profil bulunamadı.']);
        exit;
    }

    $pdo->prepare(
        'INSERT INTO leads (user_id, name, phone, email, company, note) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $userId,
        mb_substr($name, 0, 120),
        mb_substr($phone, 0, 30),
        mb_substr($email, 0, 255),
        mb_substr($company, 0, 120),
        mb_substr($note, 0, 500),
    ]);

    logProfileEvent($userId, 'lead');

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Bir hata oluştu. Lütfen tekrar deneyin.']);
}
