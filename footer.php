<!-- Copy everything below this line into your footer.php -->

<style>
.site-footer {
  position: relative;
  background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 50%, #06b6d4 100%);
  color: white;
  overflow: hidden;
}

/* Decorative background elements */
.site-footer::before {
  content: '';
  position: absolute;
  bottom: -5rem;
  left: -5rem;
  width: 16rem;
  height: 16rem;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 50%;
  filter: blur(60px);
}

.site-footer::after {
  content: '';
  position: absolute;
  top: 5rem;
  right: 5rem;
  width: 24rem;
  height: 24rem;
  background: rgba(37, 99, 235, 0.2);
  border-radius: 50%;
  filter: blur(60px);
}

.site-footer-inner {
  position: relative;
  z-index: 10;
  max-width: 1280px;
  margin: 0 auto;
  padding: 4rem 1.5rem;
}

.footer-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 3rem;
  margin-bottom: 3rem;
}

@media (min-width: 768px) {
  .footer-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.footer-col h3,
.footer-col h4 {
  margin-bottom: 1rem;
  font-weight: 600;
}

.footer-col h3 {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.5rem;
}

.footer-logo-icon {
  background: rgba(255, 255, 255, 0.2);
  padding: 0.5rem;
  border-radius: 0.5rem;
  display: inline-flex;
}

.footer-col h4 {
  font-size: 1.125rem;
}

.footer-about p {
  color: rgba(255, 255, 255, 0.9);
  line-height: 1.75;
}

.footer-links ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-links li {
  margin-bottom: 0.5rem;
}

.footer-links a {
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  transition: all 0.2s;
  display: inline-block;
}

.footer-links a:hover {
  color: white;
  transform: translateX(0.25rem);
}

.footer-contact p {
  color: rgba(255, 255, 255, 0.9);
  margin-bottom: 0.75rem;
}

.footer-contact a {
  color: white;
  text-decoration: none;
  transition: all 0.2s;
}

.footer-contact a:hover {
  text-decoration: underline;
}

.site-footer-bottom {
  position: relative;
  z-index: 10;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
  background: rgba(0, 0, 0, 0.1);
}

.site-footer-bottom .site-footer-inner {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  align-items: center;
  justify-content: space-between;
}

@media (min-width: 768px) {
  .site-footer-bottom .site-footer-inner {
    flex-direction: row;
  }
}

.site-footer-bottom div {
  color: rgba(255, 255, 255, 0.9);
}

.back-to-top {
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  transition: all 0.2s;
  display: inline-block;
}

.back-to-top:hover {
  color: white;
  transform: translateY(-0.25rem);
}

/* Decorative shapes */
.footer-shape-1 {
  position: absolute;
  top: 10rem;
  left: 25%;
  width: 8rem;
  height: 8rem;
  border: 4px solid rgba(255, 255, 255, 0.1);
  border-radius: 1.5rem;
  transform: rotate(12deg);
  pointer-events: none;
}

.footer-shape-2 {
  position: absolute;
  bottom: 8rem;
  right: 33%;
  width: 5rem;
  height: 5rem;
  background: rgba(34, 211, 238, 0.2);
  border-radius: 1rem;
  transform: rotate(45deg);
  pointer-events: none;
}

.footer-shape-3 {
  position: absolute;
  top: 50%;
  right: 25%;
  width: 4rem;
  height: 4rem;
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  pointer-events: none;
}
</style>

<footer class="site-footer">
  <div class="footer-shape-1"></div>
  <div class="footer-shape-2"></div>
  <div class="footer-shape-3"></div>

  <div class="site-footer-inner">
    <div class="footer-grid">
      <div class="footer-col footer-about">
        <h3>
          <span class="footer-logo-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span>RenTayo</span>
        </h3>
        <p>Rent smart. Live easy. Find trusted rentals nearby quickly and securely.</p>
      </div>

      <div class="footer-col footer-links">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="index.php">Home</a></li>
          <li><a href="add_item.php">Add Item</a></li>
          <li><a href="register.php">Register</a></li>
          <li><a href="login.php">Login</a></li>
        </ul>
      </div>

      <div class="footer-col footer-contact">
        <h4>Contact</h4>
        <p>Email: <a href="mailto:contact.rentayo@gmail.com">contact.rentayo@gmail.com</a></p>
        <p>Phone: <a href="tel:+1234567890">+1 234 567 890</a></p>
      </div>
    </div>
  </div>

  <div class="site-footer-bottom">
    <div class="site-footer-inner">
      <div>© <?php echo date('Y'); ?> RenTayo. All rights reserved.</div>
      <div><a href="#top" class="back-to-top">Back to top ↑</a></div>
    </div>
  </div>
</footer>

<script>
// Safe fallback: if a page doesn't include the navbar or its hamburger,
// make toggleSidebar a no-op to avoid console errors from other scripts.
if (typeof toggleSidebar === 'undefined') {
  window.toggleSidebar = function() {
    // no-op when navbar/sidebar not present
    var sidebar = document.getElementById('sidebar');
    if (sidebar) {
      sidebar.classList.toggle('open');
    }
    var main = document.getElementById('mainContent');
    if (main) main.classList.toggle('shifted');
  };
}
</script>
