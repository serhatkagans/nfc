<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Verify state parameter
$state = $_GET['state'] ?? '';
$sessionState = $_SESSION['oauth_state'] ?? '';

if (empty($state) || empty($sessionState) || $state !== $sessionState) {
    flash('error', 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');
    redirect('login.php');
}

// Clear used state
unset($_SESSION['oauth_state']);

// Check for error from Google
if (isset($_GET['error'])) {
    flash('error', 'Google ile giriş iptal edildi.');
    redirect('login.php');
}

// Get authorization code
$code = $_GET['code'] ?? '';
if (empty($code)) {
    flash('error', 'Yetkilendirme kodu alınamadı.');
    redirect('login.php');
}

// Exchange authorization code for access token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode !== 200) {
    flash('error', 'Google ile iletişim kurulamadı. Lütfen tekrar deneyin.');
    redirect('login.php');
}

$tokenResult = json_decode($tokenResponse, true);

if (!isset($tokenResult['access_token'])) {
    flash('error', 'Erişim jetonu alınamadı. Lütfen tekrar deneyin.');
    redirect('login.php');
}

$accessToken = $tokenResult['access_token'];

// Get user info from Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';

$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$userResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode !== 200) {
    flash('error', 'Kullanıcı bilgileri alınamadı. Lütfen tekrar deneyin.');
    redirect('login.php');
}

$googleUser = json_decode($userResponse, true);

if (!isset($googleUser['sub']) || !isset($googleUser['email'])) {
    flash('error', 'Google hesap bilgileri eksik. Lütfen tekrar deneyin.');
    redirect('login.php');
}

// Attempt login/registration with Google
// loginWithGoogle 'sub' anahtarını bekler (Google'ın benzersiz kullanıcı kimliği)
$user = loginWithGoogle([
    'sub' => $googleUser['sub'],
    'email' => $googleUser['email'],
    'name' => $googleUser['name'] ?? '',
    'picture' => $googleUser['picture'] ?? ''
]);

if ($user) {
    flash('success', 'Google ile başarıyla giriş yaptınız. Hoş geldiniz!');
    redirect('dashboard.php');
} else {
    flash('error', 'Bir hata oluştu. Lütfen tekrar deneyin.');
    redirect('login.php');
}
