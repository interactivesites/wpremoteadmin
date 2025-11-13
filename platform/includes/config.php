<?php
/**
 * Configuration file for WordPress Remote Update Manager Platform
 */

// Database configuration
define('DB_TYPE', 'sqlite'); // 'sqlite' or 'mysql'
define('DB_PATH', __DIR__ . '/../data/database.sqlite');
define('DB_HOST', 'localhost');
define('DB_NAME', 'wordpress_update_manager');
define('DB_USER', 'root');
define('DB_PASS', '');

// Platform configuration
define('PLATFORM_URL', 'http://localhost'); // Update with your platform URL
define('SESSION_NAME', 'rum_platform_session');
define('SESSION_LIFETIME', 3600 * 24); // 24 hours

// Security
// IMPORTANT: Change these credentials before deploying!
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$12$33cMIjgs/dQAKMrV93PKzuvoH6h5U7hX9XncrDlwekiJE5GU7r//y'); // Hash for 'aa!'

// Timezone
date_default_timezone_set('UTC');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

