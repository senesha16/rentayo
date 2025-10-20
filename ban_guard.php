<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once __DIR__ . '/connections.php';

if (!empty($_SESSION['ID'])) {
    $uid = (int)$_SESSION['ID'];
    $res = mysqli_query($connections, "SELECT is_banned FROM users WHERE ID = $uid LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res)) && (int)$row['is_banned'] === 1) {
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