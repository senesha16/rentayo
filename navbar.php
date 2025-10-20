<!--
═══════════════════════════════════════════════════════════════════
MODERN NAVBAR FOR RENTAYO - Copy-Paste Ready
═══════════════════════════════════════════════════════════════════

INSTRUCTIONS:
1. BACKUP your current navbar.php
2. REPLACE your entire navbar.php file with this code
3. DELETE navbar.css (styles are now embedded)
4. Save and refresh - Done!

═══════════════════════════════════════════════════════════════════
-->
<?php
// filepath: c:\xampp\htdocs\RENTayo-main\navbar.php
// Guard against double-include
if (defined('RENTAYO_NAVBAR_INCLUDED')) { return; }
define('RENTAYO_NAVBAR_INCLUDED', true);

// Assumes the including page already did: include("connections.php"); session_start();
$user_name = "Guest";
$profile_picture = "";
$unread_count = 0;

if (!empty($_SESSION["ID"])) {
    $user_id = (int)$_SESSION["ID"];

    // User info
    if (isset($connections)) {
        if ($rs = @mysqli_query($connections, "SELECT username, profile_picture_url FROM users WHERE ID = {$user_id} LIMIT 1")) {
            if ($row = mysqli_fetch_assoc($rs)) {
                $user_name = $row['username'] ?: "User";
                $raw = trim((string)($row['profile_picture_url'] ?? ''));
                if ($raw !== '') {
                    if (preg_match('#^https?://#i', $raw)) {
                        $profile_picture = $raw;
                    } else {
                        $web = 'uploads/' . basename(str_replace('\\', '/', $raw));
                        $abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $web);
                        $profile_picture = is_file($abs) ? $web : "";
                    }
                }
            }
        }
        // Unread messages
        if ($rs = @mysqli_query($connections, "SELECT COUNT(*) AS c FROM messages WHERE receiver_id = {$user_id} AND is_read = 0")) {
            if ($row = mysqli_fetch_assoc($rs)) {
                $unread_count = (int)$row['c'];
            }
        }
    }
}
?>
<style>
/* ═══════════════════════════════════════════════════════════════
   MODERN NAVBAR STYLES - Blue Gradient Theme
   ═══════════════════════════════════════════════════════════ */

/* Reset & Base */
.navbar, .navbar * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

/* Main Navbar Container */
.navbar {
    position: sticky;
    top: 0;
    z-index: 5000;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #6366f1 100%);
    color: #fff;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    box-shadow: 
        0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -1px rgba(0, 0, 0, 0.06);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', sans-serif;
    flex-wrap: nowrap;
}

/* Navbar Sections */
.navbar .left,
.navbar .center,
.navbar .right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.navbar .left {
    flex: 0 0 auto;
}

.navbar .center {
    flex: 1 1 auto;
    justify-content: center;
    min-width: 0;
}

.navbar .right {
    flex: 0 0 auto;
    white-space: nowrap;
}

/* ═══════════════════════════════════════════════════════════════
  LOGO
  ═══════════════════════════════════════════════════════════ */

.logo-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: #fff;
    transition: transform 0.2s ease;
}

.logo-link:hover {
    transform: scale(1.05);
}

.logo-img { height: 56px; width: auto; display: block; }

/* ═══════════════════════════════════════════════════════════════
   SEARCH BAR
   ═══════════════════════════════════════════════════════════ */

.search-container {
    width: 100%;
    max-width: 600px;
}

.search-container form {
    margin: 0;
    width: 100%;
}

.search-container input[type="text"] {
    width: 100%;
    height: 42px;
    padding: 10px 18px;
    border-radius: 12px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    color: #fff;
    font-size: 15px;
    font-weight: 400;
    transition: all 0.3s ease;
    outline: none;
}

.search-container input[type="text"]::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.search-container input[type="text"]:focus {
    background: rgba(255, 255, 255, 0.95);
    color: #1e293b;
    border-color: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.search-container input[type="text"]:focus::placeholder {
    color: #94a3b8;
}

/* ═══════════════════════════════════════════════════════════════
   NAVIGATION ICON BUTTONS
   ═══════════════════════════════════════════════════════════ */

.nav-link,
.message-btn,
.notif-bell {
    position: relative;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 40px !important;
    height: 40px !important;
    padding: 0;
    border-radius: 10px;
    text-decoration: none;
    color: #fff !important;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    cursor: pointer;
    transition: all 0.2s ease;
    flex: 0 0 40px;
    visibility: visible !important;
    opacity: 1 !important;
}

.nav-link:hover,
.message-btn:hover,
.notif-bell:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.4);
    transform: translateY(-2px);
}

.nav-link.active {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.5);
}

/* Icon Sizes */
.nav-link .nav-icon,
.message-btn > svg {
    width: 20px !important;
    height: 20px !important;
    stroke: currentColor !important;
    fill: none !important;
    display: inline-block !important;
}

.bell-icon {
    font-size: 20px;
    line-height: 1;
}

/* ═══════════════════════════════════════════════════════════════
   NOTIFICATION BADGES
   ═══════════════════════════════════════════════════════════ */

.notification-badge,
.notif-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #ef4444;
    color: #fff;
    border: 2px solid #3b82f6;
    border-radius: 999px;
    min-width: 20px;
    height: 20px;
    line-height: 16px;
    font-size: 11px;
    font-weight: 700;
    padding: 0 5px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* ═══════════════════════════════════════════════════════════════
   PROFILE BUTTON & DROPDOWN
   ═══════════════════════════════════════════════════════════ */

.profile-dropdown {
    position: relative;
}

.profile-btn {
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff !important;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 10px;
    transition: all 0.2s ease;
    height: 40px;
}

.profile-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.4);
}

/* Profile Avatar */
.profile-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-pic {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.5);
}

.profile-pic-default {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.25);
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    border: 2px solid rgba(255, 255, 255, 0.5);
}

/* Dropdown Arrow */
.dropdown-arrow {
    width: 16px !important;
    height: 16px !important;
    stroke: currentColor !important;
    transition: transform 0.2s ease;
}

.profile-dropdown.open .dropdown-arrow {
    transform: rotate(180deg);
}

/* Dropdown Content */
.dropdown-content {
    position: absolute;
    right: 0;
    top: 48px;
    min-width: 240px;
    background: #fff !important;
    color: #1e293b !important;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
    display: none;
    z-index: 5300 !important;
    overflow: hidden;
    animation: dropdownSlide 0.2s ease;
}

@keyframes dropdownSlide {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.profile-dropdown.open .dropdown-content,
.dropdown-content.show {
    display: block !important;
}

/* Dropdown Items */
.dropdown-item {
    display: flex !important;
    align-items: center !important;
    gap: 12px;
    padding: 14px 16px;
    color: #1e293b !important;
    background: #fff !important;
    text-decoration: none !important;
    border-bottom: 1px solid #f1f5f9;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.15s ease;
    opacity: 1 !important;
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item:hover {
    background: #f8fafc !important;
    color: #3b82f6 !important;
}

.dropdown-item.logout-option {
    color: #dc2626 !important;
    border-top: 1px solid #f1f5f9;
}

.dropdown-item.logout-option:hover {
    background: #fef2f2 !important;
    color: #dc2626 !important;
}

/* Dropdown Icons */
.dropdown-icon {
    width: 20px !important;
    height: 20px !important;
    stroke: currentColor !important;
    fill: none !important;
    flex-shrink: 0;
    opacity: 1 !important;
}

.dropdown-divider {
    height: 1px;
    background: #f1f5f9;
    margin: 0;
}

/* ═══════════════════════════════════════════════════════════════
   NOTIFICATION DROPDOWN
   ═══════════════════════════════════════════════════════════ */

.notif-container {
    position: relative;
}

.notif-dropdown {
    position: absolute;
    right: 0;
    top: 48px;
    width: 360px;
    max-width: 90vw;
    background: #fff !important;
    color: #1e293b !important;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
    z-index: 5200 !important;
    overflow: hidden;
    animation: dropdownSlide 0.2s ease;
}

.notif-dropdown[hidden] {
    display: none !important;
}

/* Notification Header */
.notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    border-bottom: 0;
    font-weight: 700;
    font-size: 16px;
    background: linear-gradient(90deg, #6366f1 0%, #4f46e5 100%);
    color: #fff;
    padding: 12px 16px;
    align-items: center;
}

/* mark-all button in header */
.notif-markall {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.95);
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 8px;
    transition: background .15s ease;
}
.notif-markall:hover { background: rgba(255,255,255,0.06); }

/* scroll area adjustments (keeps existing scrollbar styles) */
.notif-list {
    max-height: 400px;
    overflow-y: auto;
    background: #fff;
    padding: 8px 8px;
}

/* notification item card (richer layout) */
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 8px;
    background: #fff;
    border: 1px solid #f1f5f9;
    transition: background .12s ease, transform .12s ease;
}
.notif-item:hover { background: #f8fafc; transform: translateY(-2px); }

.notif-item.unread {
    box-shadow: 0 6px 18px rgba(99,102,241,0.06);
    background: #eff6ff;
    border-color: #e0f2fe;
}

.notif-icon {
    flex: 0 0 44px;
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
    box-shadow: 0 4px 12px rgba(2,6,23,0.06);
}

/* content */
.notif-content { flex: 1 1 auto; min-width: 0; }
.notif-title { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.notif-title h4 { margin:0; font-size:14px; color:#0f172a; font-weight:600; }
.notif-desc { margin:6px 0 8px; font-size:13px; color:#475569; }
.notif-meta { display:flex; justify-content:space-between; align-items:center; gap:8px; font-size:12px; color:#94a3b8; }

.notif-open { color:#4f46e5; display:flex; align-items:center; gap:6px; text-decoration:none; }

/* simple separator rule used by JS */
.notif-sep { height:1px; background:#f1f5f9; margin:6px 0; border-radius:2px; }

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE DESIGN
   ═══════════════════════════════════════════════════════════ */

@media (max-width: 768px) {
    .navbar {
        padding: 10px 16px;
        gap: 12px;
    }

  .logo-img { height: 48px; }

    .search-container {
        max-width: 100%;
    }

    .search-container input[type="text"] {
        font-size: 14px;
        height: 38px;
        padding: 8px 14px;
    }

    .nav-link,
    .message-btn,
    .notif-bell {
        width: 36px !important;
        height: 36px !important;
    }

    .profile-btn {
        height: 36px;
        padding: 4px 10px;
    }

    .notif-dropdown {
        width: 320px;
    }
}

@media (max-width: 600px) {
    .navbar {
        gap: 8px;
    }

    .navbar .right {
        gap: 8px;
    }

    /* Hide labels on mobile, keep icons */
  .logo-img { height: 40px; }

    .search-container input[type="text"] {
        font-size: 13px;
        padding: 6px 12px;
    }

    .dropdown-content {
        min-width: 220px;
    }
}

/* ═══════════════════════════════════════════════════════════════
   ADDITIONAL ENHANCEMENTS
   ═══════════════════════════════════════════════════════════ */

/* Focus styles for accessibility */
.nav-link:focus-visible,
.message-btn:focus-visible,
.notif-bell:focus-visible,
.profile-btn:focus-visible {
    outline: 3px solid rgba(255, 255, 255, 0.5);
    outline-offset: 2px;
}

/* Smooth transitions */
* {
    -webkit-tap-highlight-color: transparent;
}

/* Hide mobile menu if present */
.hamburger,
.mobile-menu-btn,
#mobileMenu {
    display: none !important;
}
</style>

<div class="navbar">
  <div class="left">
    <div class="logo">
      <a href="index.php" class="logo-link">
        <img src="rentayo_logo.png" alt="RENTayo" class="logo-img" />
      </a>
    </div>
  </div>

  <div class="center">
    <div class="search-container">
      <form method="GET" action="index.php">
        <input 
          type="text" 
          name="search" 
          placeholder="Search items..." 
          value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
          autocomplete="off"
        >
      </form>
    </div>
  </div>

  <div class="right">
    <!-- Browse -->
    <a 
      href="index.php" 
      class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" 
      title="Browse Items"
    >
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v6a2 2 0 01-2 2H10a2 2 0 01-2-2V5z"></path>
      </svg>
    </a>

    <!-- Add Item -->
    <a 
      href="add_item.php" 
      class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add_item.php' ? 'active' : ''; ?>" 
      title="Add Item"
    >
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
      </svg>
    </a>

    <!-- Messages -->
    <a 
      href="messaging.php" 
      class="message-btn <?php echo (basename($_SERVER['PHP_SELF']) == 'messaging.php' || basename($_SERVER['PHP_SELF']) == 'chat.php') ? 'active' : ''; ?>" 
      title="Messages"
    >
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12c0 4.418-3.582 8-8 8a9.863 9.863 0 01-4.906-1.294l-3.57 1.294a.25.25 0 01-.331-.331l1.294-3.57A9.863 9.863 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
      </svg>
      <?php if ($unread_count > 0): ?>
        <span class="notification-badge" id="msgBadge"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
      <?php else: ?>
        <span class="notification-badge" id="msgBadge" hidden>0</span>
      <?php endif; ?>
    </a>

    <!-- Notifications -->
    <div class="notif-container">
      <button class="notif-bell" id="notifBell" type="button" aria-label="Notifications" title="Notifications">
        <span class="bell-icon">🔔</span>
        <span class="notif-badge" id="notifBadge" hidden>0</span>
      </button>

      <div class="notif-dropdown" id="notifDropdown" hidden>
        <div class="notif-header">
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;height:32px;background:rgba(255,255,255,0.12);border-radius:8px;display:flex;align-items:center;justify-content:center">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path></svg>
            </div>
            <strong>Notifications</strong>
          </div>
          <button id="notifMarkAll" type="button" class="notif-markall">Mark all read</button>
        </div>

        <div class="notif-list" id="notifList">
          <div class="notif-empty">No notifications</div>
        </div>

        <!-- footer removed: "View all notifications" link deleted -->
      </div>
    </div>

    <!-- Profile -->
    <div class="profile-dropdown">
      <button class="profile-btn" type="button" id="profileBtn" aria-label="Profile menu">
        <div class="profile-avatar">
          <?php if (!empty($profile_picture)): ?>
            <img 
              src="<?php echo htmlspecialchars($profile_picture); ?>" 
              alt="Profile" 
              class="profile-pic"
              onerror="this.remove(); this.insertAdjacentHTML('afterend','<div class=&quot;profile-pic-default&quot;><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>');"
            >
          <?php else: ?>
            <div class="profile-pic-default"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
          <?php endif; ?>
        </div>
        <svg class="dropdown-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
      </button>
      
      <div class="dropdown-content" id="profileDropdown">
        <a href="profile.php" class="dropdown-item">
          <svg class="dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
          </svg>
          My Profile
        </a>
        <a href="my_items.php" class="dropdown-item">
          <svg class="dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
          </svg>
          My Items
        </a>
        <div class="dropdown-divider"></div>
        <a href="logout.php" class="dropdown-item logout-option">
          <svg class="dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
          </svg>
          Sign Out
        </a>
      </div>
    </div>
  </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════
// NAVBAR JAVASCRIPT - Profile, Messages, Notifications
// ═══════════════════════════════════════════════════════════════

// Profile Dropdown Toggle
document.getElementById('profileBtn')?.addEventListener('click', (e) => {
  e.stopPropagation();
  const dropdown = document.querySelector('.profile-dropdown');
  dropdown?.classList.toggle('open');
  document.getElementById('profileDropdown')?.classList.toggle('show');
});

// Close profile dropdown when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.profile-dropdown')) {
    document.querySelector('.profile-dropdown')?.classList.remove('open');
    document.getElementById('profileDropdown')?.classList.remove('show');
  }
});

// ═══════════════════════════════════════════════════════════════
// MESSAGE BADGE - Refresh unread count
// ═══════════════════════════════════════════════════════════════

async function refreshMsgBadge() {
  try {
    const r = await fetch('/RENTayo-main/get_unread_count.php', {cache:'no-store'});
    const j = await r.json();
    const c = Number(j.unread_count || 0);
    const b = document.getElementById('msgBadge');
    if (!b) return;
    if (c > 0) { 
      b.hidden = false; 
      b.textContent = c > 99 ? '99+' : c; 
    } else { 
      b.hidden = true; 
    }
  } catch (_) {}
}

// Poll every 5 seconds (only once, even if navbar loads multiple times)
if (!window.__RENTAYO_MSG_TIMER__) {
  window.__RENTAYO_MSG_TIMER__ = setInterval(refreshMsgBadge, 5000);
}
refreshMsgBadge();

// ═══════════════════════════════════════════════════════════════
// NOTIFICATIONS BELL
// ═══════════════════════════════════════════════════════════════

(function(){
  if (window.__RENTAYO_NOTIF_INIT__) return;
  window.__RENTAYO_NOTIF_INIT__ = true;

  const bell   = document.getElementById('notifBell');
  const badge  = document.getElementById('notifBadge');
  const dd     = document.getElementById('notifDropdown');
  const listEl = document.getElementById('notifList');
  const markAllBtn = document.getElementById('notifMarkAll');

  const CURRENT_UID = <?php echo isset($_SESSION['ID']) ? (int)$_SESSION['ID'] : 0; ?>;
  const API_BASE = location.pathname.toLowerCase().includes('/rentayo-main/') ? '/rentayo-main/api/' : '/RENTayo-main/api/';

  function escapeHtml(s){ 
    return String(s).replace(/[&<>"']/g, m=>({'&':'&','<':'<','>':'>','"':'&quot;',"'":'&#39;'}[m])); 
  }
  
  async function fetchJSON(u,o={}){ 
    const r=await fetch(u,{cache:'no-store',...o}); 
    const t=await r.text(); 
    try{return JSON.parse(t);}
    catch{throw new Error('Invalid JSON: '+t.slice(0,200));} 
  }

  function updateBadge(count){
    const c = Number(count || 0);
    if (c > 0) { 
      badge.hidden = false; 
      badge.textContent = c > 99 ? '99+' : c; 
    } else { 
      badge.textContent = '0'; 
      badge.hidden = true; 
    }
  }

  async function refreshBellCount(){
    try{
      const d = await fetchJSON(API_BASE+'notifications_count.php?t=' + Date.now());
      updateBadge(d.count ?? d.unread ?? 0);
    }catch(_){}
  }

  function normalizeItems(d){
    let it = Array.isArray(d?.items)?d.items: Array.isArray(d?.notifications)?d.notifications: Array.isArray(d?.data)?d.data: (d&&typeof d==='object'?Object.values(d):[]);
    if (!Array.isArray(it)) it=[];
    return it.map(x=>({
      id:        Number(x.id ?? x.notification_id ?? x.notif_id ?? 0) || 0,
      text:      x.text || x.title || x.body || x.message || 'Notification',
      link:      (x.link || x.url || '').toString().trim(),
      is_read:   Number(x.is_read ?? x.read ?? x.seen ?? 0) ? 1 : 0,
      renter_id: Number(x.renter_id ?? 0) || 0,
      lender_id: Number(x.lender_id ?? 0) || 0,
      item_id:   Number(x.item_id ?? 0) || 0,
      rental_id: Number(x.rental_id ?? 0) || 0,
      sender_id: Number(x.sender_id ?? 0) || 0,
      receiver_id: Number(x.receiver_id ?? 0) || 0
    }));
  }

  function toChatURL(n){
    let other = 0;
    if (n.renter_id && n.lender_id) {
      other = (CURRENT_UID === n.renter_id) ? n.lender_id : n.renter_id;
    } else if (n.sender_id && n.receiver_id) {
      other = (CURRENT_UID === n.sender_id) ? n.receiver_id : n.sender_id;
    } else if (n.link && n.link.includes('user_id=')) {
      return n.link;
    }
    if (other > 0) {
      let u = 'chat.php?user_id=' + other;
      if (n.item_id) u += '&item_id=' + n.item_id;
      if (n.rental_id) u += '&rental_id=' + n.rental_id;
      return u;
    }
    return n.link || 'messaging.php';
  }

  async function markAllRead(){
    try{
      await fetch(API_BASE+'notifications_mark_read.php', { 
        method:'POST', 
        headers:{'Cache-Control':'no-store'} 
      });
    }catch(_){}
    updateBadge(0);
    listEl?.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));
  }

  async function loadNotifList(){
    try{
      listEl.innerHTML = '<div class="notif-empty">Loading…</div>';
      const data = await fetchJSON(API_BASE+'notifications_list.php?t=' + Date.now());
      const items = normalizeItems(data);

      if (!items.length){
        listEl.innerHTML = '<div class="notif-empty">No notifications</div>';
        return;
      }

      listEl.innerHTML = '';
      for (const n of items){
        const url = toChatURL(n);
        const row = document.createElement('a');
        row.href = url;
        row.className = 'notif-item' + (n.is_read ? '' : ' unread');

        // map type => emoji + color
        const t = (n.type || '').toString().toLowerCase();
        const typeMap = {
          'payment': {emoji:'💰', color:'#10B981'},
          'message': {emoji:'💬', color:'#7c3aed'},
          'rental_request': {emoji:'📅', color:'#3b82f6'},
          'reminder': {emoji:'📦', color:'#f97316'}
        };
        const mm = typeMap[t] || {emoji:'🔔', color:'#6b7280'};

        // build markup
        row.innerHTML = ''
          + '<div class="notif-icon" style="background:'+mm.color+'">'+escapeHtml(mm.emoji)+'</div>'
          + '<div class="notif-content">'
            + '<div class="notif-title"><h4>' + escapeHtml(n.text || n.title || 'Notification') + '</h4>' 
            + (n.is_read ? '' : '<div style="width:8px;height:8px;background:#3b82f6;border-radius:8px"></div>') + '</div>'
            + '<div class="notif-desc">' + (escapeHtml(n.body || n.message || n.text || '')) + '</div>'
            + '<div class="notif-meta"><span class="notif-time">' + escapeHtml(n.time || '') + '</span><span class="notif-open">Open →</span></div>'
          + '</div>';

        row.addEventListener('click', async (e)=>{
          e.preventDefault();
          let target = row.href;
          try{
            if (n.id){
              const fd = new FormData();
              fd.append('id', String(n.id));
              await fetch(API_BASE+'notifications_mark_read.php', { method:'POST', body: fd, headers:{'Cache-Control':'no-store'}});
            } else {
              await markAllRead();
            }
          }catch(_){}
          updateBadge(0);
          row.classList.remove('unread');
          location.href = target;
        });

        listEl.appendChild(row);
      }
      // no footer to reveal
     }catch(_){
       listEl.innerHTML = '<div class="notif-empty">Failed to load notifications.</div>';
     }
   }

  async function openDD(){
    if (!dd) return;
    dd.removeAttribute('hidden');
    await markAllRead();
    await loadNotifList();
  }
  
  function closeDD(){ 
    dd?.setAttribute('hidden',''); 
  }
  
  function toggleDD(){ 
    dd?.hasAttribute('hidden') ? openDD() : closeDD(); 
  }

  bell?.addEventListener('click', (e)=>{ 
    e.stopPropagation(); 
    toggleDD(); 
  });
  
  document.addEventListener('click', (e)=>{ 
    if (!e.target.closest('.notif-container')) closeDD(); 
  });
  
  markAllBtn?.addEventListener('click', async ()=>{ 
    await markAllRead(); 
    await loadNotifList(); 
  });

  refreshBellCount();
  if (!window.__RENTAYO_NOTIF_TIMER__) {
    window.__RENTAYO_NOTIF_TIMER__ = setInterval(refreshBellCount, 15000);
  }
})();

// ═══════════════════════════════════════════════════════════════
// ITEM EXPIRY CHECK (once per 12h per user)
// ═══════════════════════════════════════════════════════════════

(function(){
  const UID = <?php echo isset($_SESSION['ID']) ? (int)$_SESSION['ID'] : 0; ?>;
  if (!UID) return;

  const key = 'rentayo_expire_ping_' + UID;
  const last = Number(localStorage.getItem(key) || 0);
  const now = Date.now();
  
  if (now - last > 12*60*60*1000) {
    fetch('<?php echo (stripos($_SERVER['REQUEST_URI'],'/rentayo-main/')!==false) ? '/rentayo-main' : '/RENTayo-main'; ?>/api/expire_items.php', { method:'POST' })
      .finally(()=> localStorage.setItem(key, String(now)));
  }
})();
</script>
