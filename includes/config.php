<?php
// Central configuration for mail (and other app-level constants).
// For production, prefer to read these from environment variables.

define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'reserveroom446@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'kaudwtzzuytnfjwi');
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'reserveroom446@gmail.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Room Reservation System');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls'); // 'tls' or 'ssl'

// Token expiration in seconds (default: 1 hour)
define('RESET_TOKEN_EXPIRY', 60 * 60);

// Site base URL for email links. Adjust if needed.
define('SITE_BASE_URL', (isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : 'http://localhost') . dirname($_SERVER['PHP_SELF']) );

// Debug flag
define('MAIL_DEBUG', false);

?>
