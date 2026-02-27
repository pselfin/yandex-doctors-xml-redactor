<?php
require_once __DIR__ . '/config.php';

function sendSecurityHeaders(): void {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-XSS-Protection: 1; mode=block');
}

function requireAuth(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    sendSecurityHeaders();
    if (PASSWORD_HASH === '') {
        if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
            header('Location: /setup.php');
            exit;
        }
        return;
    }
    if (empty($_SESSION['auth'])) {
        header('Location: /login.php');
        exit;
    }
}

function login(string $password): bool {
    if (PASSWORD_HASH === '') return false;
    if (password_verify($password, PASSWORD_HASH)) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_regenerate_id(true); // предотвращение session fixation
        $_SESSION['auth'] = true;
        unset($_SESSION['csrf']); // сбросить CSRF-токен — будет выдан новый
        return true;
    }
    return false;
}

function logout(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = [];
    session_destroy();
}

// --- CSRF ---

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrfVerify(): void {
    $token = trim($_POST['_csrf'] ?? '');
    if (!$token || !hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        exit('Ошибка безопасности: недействительный токен. Обновите страницу и повторите.');
    }
}

function csrfVerifyGet(): void {
    $token = trim($_GET['_csrf'] ?? '');
    if (!$token || !hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        exit('Ошибка безопасности: недействительный токен. Вернитесь назад и повторите.');
    }
}

// --- Rate limiting для входа (файловый, без зависимостей) ---

function checkLoginRateLimit(): void {
    if (!defined('DATA_DIR') || !is_dir(DATA_DIR)) return;
    $file = DATA_DIR . '/.rl';
    $ip   = md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now  = time();
    $data = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: [];
    }
    // Очищаем попытки старше 15 минут
    foreach ($data as $k => $attempts) {
        $data[$k] = array_values(array_filter($attempts, fn($t) => $now - $t < 900));
        if (empty($data[$k])) unset($data[$k]);
    }
    if (count($data[$ip] ?? []) >= 10) {
        http_response_code(429);
        header('Retry-After: 900');
        exit('Слишком много неудачных попыток входа. Попробуйте через 15 минут.');
    }
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function recordFailedLogin(): void {
    if (!defined('DATA_DIR') || !is_dir(DATA_DIR)) return;
    $file = DATA_DIR . '/.rl';
    $ip   = md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now  = time();
    $data = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: [];
    }
    $data[$ip][] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);
}
