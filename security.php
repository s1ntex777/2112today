<?php
require_once __DIR__ . '/config.php';

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(APP_SESSION);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax'
        ]);
        session_start();
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }
}

function apply_security_headers(): void {
    if (CSP_ENABLED) {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function base_url(): string {
    if (BASE_URL) return BASE_URL;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'];
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function csrf_token(): string {
    start_secure_session();
    $now = time();
    if (empty($_SESSION['csrf']) || ($now - ($_SESSION['csrf_time'] ?? 0)) > CSRF_TTL) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_time'] = $now;
    }
    return $_SESSION['csrf'];
}

function csrf_check(string $token): bool {
    start_secure_session();
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}

function is_admin(): bool {
    start_secure_session();
    return !empty($_SESSION['admin']);
}

function require_admin(): void {
    if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
}

function rate_limit_login(): bool {
    start_secure_session();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
    $now = time();
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], fn($t) => $now - $t < LOGIN_WINDOW_SEC);
    if (count($_SESSION['login_attempts']) >= LOGIN_MAX_ATTEMPTS) return false;
    $_SESSION['login_attempts'][] = $now;
    return true;
}
