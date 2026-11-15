<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metode tidak diizinkan.');
}

$csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
if (!taskflow_verify_csrf($csrfToken)) {
    http_response_code(400);
    exit('Permintaan tidak valid. Muat ulang halaman, lalu coba lagi.');
}

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $cookieParameters = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookieParameters['path'],
        $cookieParameters['domain'],
        $cookieParameters['secure'],
        $cookieParameters['httponly']
    );
}

session_destroy();
header('Location: login.php');
exit;
