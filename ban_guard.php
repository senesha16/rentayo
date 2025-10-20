<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once __DIR__ . '/connections.php';

// If DB connection failed, skip ban checks to avoid fatal errors
if (!isset($connections) || !$connections) {
    return;
}

if (!empty($_SESSION['ID'])) {
    $uid = (int)$_SESSION['ID'];
    // Check if users.is_banned exists to prevent SQL errors on hosts lacking the column
    $hasIsBanned = mysqli_query($connections, "SHOW COLUMNS FROM users LIKE 'is_banned'");
    if ($hasIsBanned && mysqli_num_rows($hasIsBanned) > 0) {
        $res = mysqli_query($connections, "SELECT is_banned FROM users WHERE ID = $uid LIMIT 1");
        if ($res === false) {
            error_log('SQL error (ban_guard select is_banned): ' . mysqli_error($connections));
        }
    } else {
        $res = false; // column doesn't exist; skip ban enforcement
    }
    if ($res && ($row = mysqli_fetch_assoc($res)) && isset($row['is_banned']) && (int)$row['is_banned'] === 1) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: login.php?banned=1');
        exit;
    }
}