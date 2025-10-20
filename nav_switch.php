<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$is_admin = (int)($_SESSION['is_admin'] ?? 0);

// If session flag missing, try DB (optional)
if ($is_admin !== 1 && !empty($_SESSION['ID'])) {
    if (!isset($connections)) {
        $conn = __DIR__ . '/connections.php';
        if (is_file($conn)) { include_once $conn; }
    }
    if (isset($connections) && $connections instanceof mysqli) {
        $uid = (int)$_SESSION['ID'];
        if ($r = mysqli_query($connections, "SELECT is_admin FROM users WHERE ID=$uid LIMIT 1")) {
            if ($row = mysqli_fetch_assoc($r)) {
                $is_admin = (int)$row['is_admin'];
                $_SESSION['is_admin'] = $is_admin;
            }
        }
    }
}

if ($is_admin === 1) {
    // If already inside /admin, include the admin navbar file directly
    $inAdmin = strpos(str_replace('\\','/', $_SERVER['SCRIPT_NAME']), '/admin/') !== false;
    if ($inAdmin) {
        include __DIR__ . '/admin/admin_navbar.php';
    } else {
        // Render a minimal admin navbar with absolute links that work from any page
        $script = str_replace('\\','/', $_SERVER['SCRIPT_NAME']);
        $rootSeg = explode('/', trim($script, '/'))[0] ?? '';
        $base = '/' . $rootSeg; // e.g., /RENTayo-main
        $dashUrl = $base . '/admin/index.php';
        $logoutUrl = $base . '/logout.php';
        ?>
                <div class="navbar">
          <div class="left">
                        <a href="<?php echo $dashUrl; ?>" class="logo-link"><img src="rentayo_logo.png" alt="RENTayo Admin" class="logo-img"></a>
          </div>
          <div class="right">
            <a href="<?php echo $dashUrl; ?>" class="nav-link">Back to Dashboard</a>
            <a href="<?php echo $logoutUrl; ?>" class="nav-link logout">Logout</a>
          </div>
        </div>
        <style>
                .navbar{background:#35418f;color:#FAF7F3;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 5px rgba(0,0,0,.1);position:sticky;top:0;z-index:1000}
                .logo-link{color:#FAF7F3;text-decoration:none;display:inline-flex;align-items:center;gap:10px}
            .logo-img{height:28px;width:auto;display:block}
        .right{display:flex;gap:10px;align-items:center}
        .nav-link{color:#FAF7F3;text-decoration:none;padding:6px 10px;border-radius:8px}
        .nav-link:hover{background:rgba(250,247,243,.12)}
        .nav-link.logout{color:#ffdddd}
        </style>
        <?php
    }
} else {
    include __DIR__ . '/navbar.php';
}