<?php
/**
 * Authentication helper functions
 */

require_once __DIR__ . '/config.php';

/**
 * Start session if not already started
 */
function start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function is_logged_in() {
    start_session();
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Require login - redirect to login page if not logged in
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Login user
 * 
 * @param string $username Username
 * @param string $password Password
 * @return bool True on success, false on failure
 */
function login($username, $password) {
    start_session();
    
    if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        return true;
    }
    
    return false;
}

/**
 * Logout user
 */
function logout() {
    start_session();
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

