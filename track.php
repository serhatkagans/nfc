<?php
/**
 * Profil etkileşim takibi (analitik) uç noktası.
 * Profil sayfasından navigator.sendBeacon / fetch ile çağrılır.
 * Herkese açık; sadece event kaydı yapar.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$username = sanitize($_POST['username'] ?? '');
$type     = $_POST['type'] ?? '';
$target   = isset($_POST['target']) ? sanitize($_POST['target']) : null;

$allowedTypes = ['click', 'qr', 'view'];
if ($username === '' || !in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$username]);
    $userId = (int) $stmt->fetchColumn();

    if ($userId > 0) {
        logProfileEvent($userId, $type, $target ?: null);
    }
} catch (PDOException $e) {
    // sessizce geç
}

echo json_encode(['ok' => true]);
