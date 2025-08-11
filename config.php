<?php
// --- BEZPIECZEŃSTWO I KONFIG ---
// UZUPEŁNIJ PRZED URUCHOMIENIEM!

define('DB_HOST', 'localhost');
define('DB_NAME', 'host847557_2112today');
define('DB_USER', 'host847557_2112today');
define('DB_PASS', 'hHv9zUSwHcyXtd8pS8MF');

// Hasło admina: wklej HASH z generatora /tools/hash.php
define('ADMIN_PASS_HASH', '$2y$10$ug2ZWcTU8mPwSmc5kqDYQeC313xuG4ARDDp16HYB/2Q7HwBRbfKgK');

// Production security headers toggle
define('CSP_ENABLED', true);

// Session name
define('APP_SESSION', 'KAPSULA2112SESS');

// Base URL (bez trailing slash), np. 'https://twojadomena.pl'
// Zostaw pusty string aby próbować wykryć automatycznie
define('BASE_URL', '');

// CSRF token lifetime (seconds)
define('CSRF_TTL', 3600);

// Prosty limit prób logowania (na IP)
define('LOGIN_MAX_ATTEMPTS', 6);
define('LOGIN_WINDOW_SEC', 900);

// --- KONIEC ---
