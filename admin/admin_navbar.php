<!-- Copy everything below this line into your navbar.php or header file -->

<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$on_dashboard = (basename($_SERVER['PHP_SELF']) === 'index.php');
?>

<style>
.navbar {
  position: sticky;
  top: 0;
  z-index: 1000;
  background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 50%, #0ea5e9 100%);
  color: #FAF7F3;
  padding: 0;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.navbar::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 300px;
  height: 300px;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 50%;
  filter: blur(40px);
  pointer-events: none;
}

.navbar::after {
  content: '';
  position: absolute;
  bottom: -30%;
  left: 10%;
  width: 200px;
  height: 200px;
  background: rgba(37, 99, 235, 0.15);
  border-radius: 50%;
  filter: blur(30px);
  pointer-events: none;
}

.navbar-inner {
  position: relative;
  z-index: 10;
  max-width: 1400px;
  margin: 0 auto;
  padding: 16px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.navbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo-link {
  color: #FAF7F3;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: all 0.3s ease;
}

.logo-link:hover {
  transform: translateY(-1px);
}

.logo-icon {
  width: 36px;
  height: 36px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
}

.logo-img { height: 48px; width: auto; display:block; }

.navbar-right {
  display: flex;
  gap: 8px;
  align-items: center;
}

.nav-link {
  color: #FAF7F3;
  text-decoration: none;
  padding: 10px 18px;
  border-radius: 10px;
  font-weight: 500;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.nav-link::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: left 0.5s ease;
}

.nav-link:hover::before {
  left: 100%;
}

.nav-link:hover {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.3);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.nav-link.logout {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
  border-color: rgba(248, 113, 113, 0.3);
}

.nav-link.logout:hover {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.25), rgba(220, 38, 38, 0.25));
  border-color: rgba(248, 113, 113, 0.5);
}

/* Decorative shapes */
.navbar-shape-1 {
  position: absolute;
  top: -20px;
  left: 15%;
  width: 60px;
  height: 60px;
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  transform: rotate(15deg);
  pointer-events: none;
}

.navbar-shape-2 {
  position: absolute;
  bottom: -15px;
  right: 30%;
  width: 40px;
  height: 40px;
  background: rgba(34, 211, 238, 0.15);
  border-radius: 8px;
  transform: rotate(45deg);
  pointer-events: none;
}

/* Responsive Design */
@media (max-width: 640px) {
  .navbar-inner {
    padding: 12px 16px;
  }

  .logo-img { height: 40px; }

  .logo-icon {
    width: 32px;
    height: 32px;
  }

  .nav-link {
    padding: 8px 14px;
    font-size: 14px;
  }

  .navbar-right {
    gap: 6px;
  }
}
</style>

<nav class="navbar">
  <div class="navbar-shape-1"></div>
  <div class="navbar-shape-2"></div>
  
  <div class="navbar-inner">
    <div class="navbar-left">
      <a href="index.php" class="logo-link">
        <div class="logo-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
  <img src="../rentayo_logo.png" alt="RENTayo Admin" class="logo-img" />
      </a>
    </div>
    
    <div class="navbar-right">
      <?php if (!$on_dashboard): ?>
        <a href="index.php" class="nav-link">
          <span>← Back to Dashboard</span>
        </a>
      <?php endif; ?>
      <a href="../logout.php" class="nav-link logout">
        <span>Logout →</span>
      </a>
    </div>
  </div>
</nav>
