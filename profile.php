<?php
// filepath: c:\xampp\htdocs\RENTayo-main\profile.php
session_start();
include 'connections.php';

if (!isset($_SESSION["ID"]) || !is_numeric($_SESSION["ID"])) { header("Location: login.php"); exit; }

$logged_in_id    = (int)$_SESSION["ID"];
$profile_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $logged_in_id;
if ($profile_user_id <= 0) { header('Location: login.php'); exit; }
$isOwner = ($logged_in_id === $profile_user_id);

/* Helpers */
function normalizeProfilePath($raw) {
  $raw = trim((string)$raw);
  if ($raw === '' || strtoupper($raw) === 'NULL') return '';
  if (preg_match('#^https?://#i', $raw)) return $raw;
  $norm = str_replace('\\', '/', $raw);
  if (preg_match('#^uploads/#i', $norm)) return $norm;
  $filename = basename($norm);
  return $filename ? 'uploads/' . $filename : '';
}
function toWebUrl($relPath) {
  if ($relPath === '' || preg_match('#^https?://#i', $relPath)) return $relPath;
  $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
  $rel  = ltrim(str_replace('\\', '/', $relPath), '/');
  return ($base ? $base : '') . '/' . $rel;
}
function uploadErrorMessage($code) {
  switch ($code) {
    case UPLOAD_ERR_INI_SIZE: return 'The uploaded file exceeds upload_max_filesize in php.ini';
    case UPLOAD_ERR_FORM_SIZE: return 'The uploaded file exceeds MAX_FILE_SIZE directive in the HTML form';
    case UPLOAD_ERR_PARTIAL: return 'The uploaded file was only partially uploaded';
    case UPLOAD_ERR_NO_FILE: return 'No file was uploaded';
    case UPLOAD_ERR_NO_TMP_DIR: return 'Missing a temporary folder (upload_tmp_dir)';
    case UPLOAD_ERR_CANT_WRITE: return 'Failed to write file to disk';
    case UPLOAD_ERR_EXTENSION: return 'A PHP extension stopped the file upload';
    default: return 'Unknown upload error';
  }
}
function ensureUploadsDir($sub='') {
  $dir = __DIR__ . '/uploads' . ($sub?'/'.$sub:'');
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  return $dir;
}
function handleImageUpload($inputName, $destPrefix, $ownerId, $oldWebPath = '') {
  if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) return $oldWebPath;
  $err = $_FILES[$inputName]['error'];
  if ($err !== UPLOAD_ERR_OK) throw new RuntimeException(uploadErrorMessage($err));
  $info = pathinfo($_FILES[$inputName]['name'] ?? '');
  $ext  = strtolower($info['extension'] ?? '');
  $allowed = ['jpg','jpeg','png','gif','webp'];
  if (!in_array($ext, $allowed)) throw new RuntimeException('Unsupported file type.');
  if (($_FILES[$inputName]['size'] ?? 0) > 6*1024*1024) throw new RuntimeException('File must be smaller than 6MB.');
  $uploadDirAbs = ensureUploadsDir('payment');
  if (!is_writable($uploadDirAbs)) throw new RuntimeException('Uploads folder not writable.');
  $newName = $destPrefix . '_' . $ownerId . '_' . time() . '.' . $ext;
  $destAbs = $uploadDirAbs . '/' . $newName;
  $destWeb = 'uploads/payment/' . $newName;
  if (!is_uploaded_file($_FILES[$inputName]['tmp_name'])) throw new RuntimeException('Temporary upload missing.');
  if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $destAbs)) throw new RuntimeException('Failed to save uploaded file.');
  if ($oldWebPath && stripos($oldWebPath, 'uploads/payment/') === 0) {
    $oldAbs = __DIR__ . '/' . str_replace(['\\','/'], DIRECTORY_SEPARATOR, $oldWebPath);
    if (is_file($oldAbs) && basename($oldAbs) !== basename($destAbs)) @unlink($oldAbs);
  }
  return $destWeb;
}
function handleReportUpload($inputName, $reporterId) { // evidence upload
  if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) return '';
  $err = $_FILES[$inputName]['error'];
  if ($err !== UPLOAD_ERR_OK) throw new RuntimeException(uploadErrorMessage($err));
  $info = pathinfo($_FILES[$inputName]['name'] ?? '');
  $ext  = strtolower($info['extension'] ?? '');
  $allowed = ['jpg','jpeg','png','gif','webp','pdf'];
  if (!in_array($ext, $allowed)) throw new RuntimeException('Unsupported file type.');
  if (($_FILES[$inputName]['size'] ?? 0) > 8*1024*1024) throw new RuntimeException('File must be smaller than 8MB.');
  $dir = ensureUploadsDir('reports');
  if (!is_writable($dir)) throw new RuntimeException('Uploads folder not writable.');
  $newName = 'report_' . $reporterId . '_' . time() . '.' . $ext;
  $destAbs = $dir . '/' . $newName;
  $destWeb = 'uploads/reports/' . $newName;
  if (!is_uploaded_file($_FILES[$inputName]['tmp_name'])) throw new RuntimeException('Temporary upload missing.');
  if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $destAbs)) throw new RuntimeException('Failed to save uploaded file.');
  return $destWeb;
}
function onlyDigits($s){ return preg_replace('/\D+/', '', (string)$s); }
function clean($s){ return trim((string)$s); }
function columnExists(mysqli $c, string $table, string $col): bool {
  $r = mysqli_query($c, "SHOW COLUMNS FROM `$table` LIKE '".mysqli_real_escape_string($c, $col)."'");
  return $r && mysqli_num_rows($r) > 0;
}
function ensurePaymentTable(mysqli $c) {
  @mysqli_query($c, "CREATE TABLE IF NOT EXISTS `user_payment_settings` (
    `user_id` INT NOT NULL,
    `gcash_name` VARCHAR(100) DEFAULT NULL,
    `gcash_number` VARCHAR(50) DEFAULT NULL,
    `gcash_qr` VARCHAR(255) DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  foreach (['gcash_name'=>"VARCHAR(100) DEFAULT NULL",'gcash_number'=>"VARCHAR(50) DEFAULT NULL",'gcash_qr'=>"VARCHAR(255) DEFAULT NULL"] as $col=>$ddl) {
    if (!columnExists($c, 'user_payment_settings', $col)) { @mysqli_query($c, "ALTER TABLE `user_payment_settings` ADD COLUMN `$col` $ddl"); }
  }
}
function ensureReportsTable(mysqli $c) {
  @mysqli_query($c, "CREATE TABLE IF NOT EXISTS `user_reports` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reporter_id` INT NOT NULL,
    `reported_user_id` INT NOT NULL,
    `reason` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `evidence_url` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending','reviewed','rejected') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_reporter` (`reporter_id`),
    KEY `idx_reported` (`reported_user_id`),
    KEY `idx_status` (`status`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* Load viewed user */
$user_stmt = mysqli_prepare($connections, "SELECT ID, username, email, phone_number, profile_picture_url, created_at FROM users WHERE ID = ? LIMIT 1");
mysqli_stmt_bind_param($user_stmt, "i", $profile_user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
if (!$user_result || mysqli_num_rows($user_result) === 0) { header('Location: index.php'); exit; }
$user_data = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_stmt);

$user_data['username']            = $user_data['username'] ?? '';
$user_data['email']               = $user_data['email'] ?? '';
$user_data['phone_number']        = $user_data['phone_number'] ?? '';
$user_data['profile_picture_url'] = normalizeProfilePath($user_data['profile_picture_url'] ?? '');
$display_profile_url              = $user_data['profile_picture_url'] ? toWebUrl($user_data['profile_picture_url']) : '';

/* Stats */
$stats = ['total_items' => 0, 'available_items' => 0, 'member_since' => 'Unknown'];
if ($st = mysqli_prepare($connections, "SELECT COUNT(*) FROM Items WHERE lender_id = ?")) {
  mysqli_stmt_bind_param($st, "i", $profile_user_id);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  $stats['total_items'] = (int)($rs ? mysqli_fetch_row($rs)[0] : 0);
  $stats['available_items'] = $stats['total_items'];
  mysqli_stmt_close($st);
}
if ($st = mysqli_prepare($connections, "SELECT DATE_FORMAT(created_at, '%B %Y') FROM users WHERE ID = ? LIMIT 1")) {
  mysqli_stmt_bind_param($st, "i", $profile_user_id);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  $row = $rs ? mysqli_fetch_row($rs) : null;
  $stats['member_since'] = $row && $row[0] ? $row[0] : 'Unknown';
  mysqli_stmt_close($st);
}

/* Tables needed */
ensurePaymentTable($connections);
ensureReportsTable($connections);

/* Payment (GCash only, owner) */
$payment = ['gcash_name'=>'','gcash_number'=>'','gcash_qr'=>''];
if ($isOwner) {
  if ($rs = mysqli_prepare($connections, "SELECT gcash_name, gcash_number, gcash_qr FROM user_payment_settings WHERE user_id = ? LIMIT 1")) {
    mysqli_stmt_bind_param($rs, "i", $logged_in_id);
    mysqli_stmt_execute($rs);
    $res = mysqli_stmt_get_result($rs);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
      foreach ($payment as $k => $_) { if (isset($row[$k])) $payment[$k] = (string)$row[$k]; }
    }
    mysqli_stmt_close($rs);
  }
}

/* Handle POST: owner edits OR non-owner report */
$message = ""; $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['report_user'])) {
    // Non-owner can report
    if ($isOwner) { $error = "You cannot report yourself."; }
    else {
      $reason  = clean($_POST['reason'] ?? '');
      $details = clean($_POST['details'] ?? '');
      if ($reason === '') { $error = "Please select a reason."; }
      else {
        try {
          $evidence = '';
          if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] !== UPLOAD_ERR_NO_FILE) {
            $evidence = handleReportUpload('evidence', $logged_in_id);
          }
          // optional: prevent duplicate pending report by same reporter on same user
          $chk = mysqli_prepare($connections, "SELECT id FROM user_reports WHERE reporter_id=? AND reported_user_id=? AND status='pending' LIMIT 1");
          mysqli_stmt_bind_param($chk, "ii", $logged_in_id, $profile_user_id);
          mysqli_stmt_execute($chk);
          $dupRes = mysqli_stmt_get_result($chk);
          $hasDup = $dupRes && mysqli_num_rows($dupRes) > 0;
          mysqli_stmt_close($chk);

          if ($hasDup) {
            $message = "You already have a pending report for this user.";
          } else {
            $st = mysqli_prepare($connections, "INSERT INTO user_reports (reporter_id, reported_user_id, reason, details, evidence_url) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($st, "iisss", $logged_in_id, $profile_user_id, $reason, $details, $evidence);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
            $message = "Report submitted to admin.";
          }
        } catch (Throwable $e) {
          $error = "Failed to submit report: " . htmlspecialchars($e->getMessage());
        }
      }
    }
  } else {
    // Owner profile edit + GCash
    if (!$isOwner) { http_response_code(403); exit('Not allowed.'); }

    $errors = [];
    $username = clean($_POST['username'] ?? $user_data['username']);
    $email    = clean($_POST['email'] ?? $user_data['email']);
    $phone    = clean($_POST['phone_number'] ?? $user_data['phone_number']);

    if ($username === '' || strlen($username) < 3) $errors[] = "Username must be at least 3 characters.";
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Enter a valid email address.";

    if (empty($errors)) {
      $chk = mysqli_prepare($connections, "SELECT ID FROM users WHERE (username = ? OR email = ?) AND ID != ?");
      mysqli_stmt_bind_param($chk, "ssi", $username, $email, $logged_in_id);
      mysqli_stmt_execute($chk);
      $res = mysqli_stmt_get_result($chk);
      if ($res && mysqli_num_rows($res) > 0) $errors[] = "Username or email already exists.";
      mysqli_stmt_close($chk);
    }

    $profile_picture_path = $user_data['profile_picture_url'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
      try { $profile_picture_path = handleImageUpload('profile_picture', 'profile', $logged_in_id, $profile_picture_path); }
      catch (Throwable $e) { $errors[] = "Profile picture: " . $e->getMessage(); }
    }

    $enable_gcash = isset($_POST['enable_gcash']);
    $gcash_name   = $enable_gcash ? clean($_POST['gcash_name'] ?? '') : '';
    $gcash_number = $enable_gcash ? onlyDigits($_POST['gcash_number'] ?? '') : '';
    if ($enable_gcash) {
      if ($gcash_name === '') $errors[] = "GCash name is required.";
      if ($gcash_number === '' || strlen($gcash_number) < 9) $errors[] = "GCash number must be at least 9 digits.";
    }
    $gcash_qr = $payment['gcash_qr'];
    if ($enable_gcash && isset($_FILES['gcash_qr']) && $_FILES['gcash_qr']['error'] !== UPLOAD_ERR_NO_FILE) {
      try { $gcash_qr = handleImageUpload('gcash_qr', 'qr_gcash', $logged_in_id, $gcash_qr); }
      catch (Throwable $e) { $errors[] = "GCash QR: " . $e->getMessage(); }
    } elseif (!$enable_gcash) { $gcash_qr = ''; }

    if (empty($errors)) {
      $upd = mysqli_prepare($connections, "UPDATE users SET username=?, email=?, phone_number=?, profile_picture_url=? WHERE ID=?");
      mysqli_stmt_bind_param($upd, "ssssi", $username, $email, $phone, $profile_picture_path, $logged_in_id);
      mysqli_stmt_execute($upd);
      mysqli_stmt_close($upd);

      $sql = "INSERT INTO user_payment_settings (user_id, gcash_name, gcash_number, gcash_qr)
              VALUES (?,?,?,?)
              ON DUPLICATE KEY UPDATE
                gcash_name=VALUES(gcash_name),
                gcash_number=VALUES(gcash_number),
                gcash_qr=VALUES(gcash_qr)";
      $st = mysqli_prepare($connections, $sql);
      mysqli_stmt_bind_param($st, "isss", $logged_in_id, $gcash_name, $gcash_number, $gcash_qr);
      mysqli_stmt_execute($st);
      mysqli_stmt_close($st);

      $message = "Profile updated successfully.";
      $user_data['username'] = $username;
      $user_data['email'] = $email;
      $user_data['phone_number'] = $phone;
      $user_data['profile_picture_url'] = $profile_picture_path;
      $display_profile_url = $profile_picture_path ? toWebUrl($profile_picture_path) : '';
      $payment = ['gcash_name'=>$gcash_name,'gcash_number'=>$gcash_number,'gcash_qr'=>$gcash_qr];
    } else {
      $error = implode("<br>", $errors);
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($user_data['username'] ?: 'Profile'); ?> - RENTayo</title>
  <link href="styles/style.css" rel="stylesheet">
<!-- 
═══════════════════════════════════════════════════════════════════
COPY-PASTE READY STYLES FOR PROFILE.PHP
═══════════════════════════════════════════════════════════════════

INSTRUCTIONS:
1. Find the <style> section in your profile.php (around line 190)
2. REPLACE the entire <style>...</style> block with the one below
3. Save and refresh - that's it!

Note: This uses the same HTML structure as your existing profile.php,
so you don't need to change any PHP code.
═══════════════════════════════════════════════════════════════════
-->

<style>
    /* ═══════════════════════════════════════════════════════════════
       MODERN PROFILE STYLES - Blue Gradient Theme
       ═══════════════════════════════════════════════════════════ */
    
    /* Base Reset & Layout */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        margin-top: 80px;
        background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 50%, #eef2ff 100%);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
        color: #1e293b;
        min-height: 100vh;
    }
    
    .profile-container {
        max-width: 1100px;
        margin: 40px auto;
        padding: 0 20px;
    }
    
    /* ═══════════════════════════════════════════════════════════════
       PROFILE HEADER CARD - Blue Gradient with Avatar & Stats
       ═══════════════════════════════════════════════════════════ */
    
    .profile-header {
        position: relative;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #6366f1 100%);
        color: #fff;
        padding: 48px 40px;
        border-radius: 24px;
        text-align: center;
        margin-bottom: 30px;
        box-shadow: 
            0 20px 50px -12px rgba(59, 130, 246, 0.35),
            0 10px 25px -8px rgba(99, 102, 241, 0.25);
        overflow: hidden;
    }
    
    /* Decorative background elements */
    .profile-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .profile-header::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    
    /* Avatar */
    .profile-avatar-large {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        margin: 0 auto 24px;
        border: 5px solid rgba(255, 255, 255, 0.3);
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        position: relative;
        z-index: 1;
    }
    
    .profile-avatar-large img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    /* Name & Email */
    .profile-name {
        font-size: 34px;
        font-weight: 700;
        margin-bottom: 8px;
        letter-spacing: -0.5px;
        position: relative;
        z-index: 1;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .profile-email {
        font-size: 17px;
        opacity: 0.95;
        margin-bottom: 28px;
        color: rgba(255, 255, 255, 0.9);
        position: relative;
        z-index: 1;
    }
    
    /* Stats */
    .profile-stats {
        display: flex;
        justify-content: center;
        gap: 60px;
        margin-bottom: 28px;
        position: relative;
        z-index: 1;
    }
    
    .stat-item {
        text-align: center;
    }
    
    .stat-number {
        font-size: 36px;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 8px;
        text-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }
    
    .stat-label {
        font-size: 14px;
        opacity: 0.9;
        color: rgba(255, 255, 255, 0.85);
        letter-spacing: 0.3px;
    }
    
    /* Action Buttons in Header */
    .profile-actions {
        display: flex;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    
    .btn-edit {
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
        padding: 12px 24px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        font-size: 15px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }
    
    .btn-edit:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }
    
    .btn-danger {
        background: #ef4444;
        color: #fff;
        border: 2px solid #dc2626;
        padding: 12px 24px;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        font-size: 15px;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
    }
    
    /* ═══════════════════════════════════════════════════════════════
       CONTENT CARDS - Details & Form
       ═══════════════════════════════════════════════════════════ */
    
    .profile-details-container,
    .profile-form-container {
        background: #fff;
        padding: 36px;
        border-radius: 20px;
        box-shadow: 
            0 4px 6px -1px rgba(0, 0, 0, 0.08),
            0 2px 4px -1px rgba(0, 0, 0, 0.04);
        margin-bottom: 24px;
        border: 1px solid rgba(226, 232, 240, 0.8);
    }
    
    .profile-form-container {
        display: none;
    }
    
    .profile-form-container.active {
        display: block;
    }
    
    /* Section Headers */
    .details-section {
        margin-bottom: 32px;
    }
    
    .details-section:last-child {
        margin-bottom: 0;
    }
    
    .details-section h3 {
        margin-bottom: 20px;
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
        padding-bottom: 12px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    /* Detail Items (View Mode) */
    .detail-item {
        display: grid;
        grid-template-columns: 180px 1fr;
        gap: 20px;
        padding: 16px 0;
        border-bottom: 1px solid #f1f5f9;
        align-items: start;
    }
    
    .detail-item:last-child {
        border-bottom: 0;
    }
    
    .detail-label {
        font-weight: 600;
        color: #64748b;
        font-size: 15px;
    }
    
    .detail-value {
        color: #1e293b;
        font-size: 15px;
        line-height: 1.6;
    }
    
    .empty-value {
        color: #94a3b8;
        font-style: italic;
    }
    
    .detail-value img {
        max-width: 200px;
        max-height: 200px;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    
    /* ═══════════════════════════════════════════════════════════════
       FORM STYLES - Edit Mode
       ═══════════════════════════════════════════════════════════ */
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #334155;
        font-size: 15px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 15px;
        transition: all 0.2s ease;
        background: #f8fafc;
        color: #1e293b;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #3b82f6;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    
    .form-group textarea {
        min-height: 120px;
        resize: vertical;
        font-family: inherit;
    }
    
    .form-group input[type="file"] {
        padding: 10px 12px;
        cursor: pointer;
        background: #fff;
    }
    
    /* Payment Method Toggle */
    .method {
        border: 2px solid #e2e8f0;
        border-radius: 14px;
        margin: 20px 0;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .method:hover {
        border-color: #cbd5e1;
    }
    
    .method-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 600;
        color: #1e293b;
    }
    
    .method.active .method-header {
        background: #eff6ff;
        border-bottom-color: #3b82f6;
    }
    
    .method-body {
        padding: 24px;
        display: none;
        background: #fff;
    }
    
    .method.active .method-body {
        display: block;
    }
    
    /* Switch Toggle */
    .switch {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
    }
    
    .switch input[type="checkbox"] {
        width: 48px;
        height: 26px;
        cursor: pointer;
        appearance: none;
        background: #cbd5e1;
        border-radius: 13px;
        position: relative;
        transition: all 0.3s ease;
    }
    
    .switch input[type="checkbox"]:checked {
        background: #3b82f6;
    }
    
    .switch input[type="checkbox"]::before {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #fff;
        top: 3px;
        left: 3px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    
    .switch input[type="checkbox"]:checked::before {
        left: 25px;
    }
    
    .switch span {
        font-size: 15px;
        font-weight: 500;
        color: #64748b;
    }
    
    /* ═══════════════════════════════════════════════════════════════
       ALERTS & MESSAGES
       ═══════════════════════════════════════════════════════════ */
    
    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 15px;
        font-weight: 500;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    
    .alert::before {
        content: '';
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 2px solid #a7f3d0;
    }
    
    .alert-success::before {
        content: '✓';
        background: #10b981;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 2px solid #fecaca;
    }
    
    .alert-error::before {
        content: '⚠';
        background: #ef4444;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    /* ═══════════════════════════════════════════════════════════════
       BUTTONS
       ═══════════════════════════════════════════════════════════ */
    
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
        color: #fff;
        border: none;
        padding: 14px 28px;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        font-size: 15px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
    }
    
    .btn-cancel {
        background: #f1f5f9;
        border: 2px solid #e2e8f0;
        color: #475569;
        padding: 14px 28px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-cancel:hover {
        background: #e2e8f0;
        border-color: #cbd5e1;
    }
    
    .form-actions {
        margin-top: 28px;
        display: flex;
        gap: 12px;
        padding-top: 24px;
        border-top: 2px solid #f1f5f9;
    }
    
    /* ═══════════════════════════════════════════════════════════════
       MODAL STYLES - Report User
       ═══════════════════════════════════════════════════════════ */
    
    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 20px;
        animation: fadeIn 0.2s ease;
    }
    
    .modal-backdrop.show {
        display: flex !important;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .modal {
        background: #fff;
        width: 95%;
        max-width: 540px;
        border-radius: 20px;
        box-shadow: 
            0 25px 50px -12px rgba(0, 0, 0, 0.25),
            0 0 0 1px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        animation: slideUp 0.3s ease;
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .modal-header {
        padding: 20px 24px;
        border-bottom: 2px solid #f1f5f9;
        font-weight: 700;
        font-size: 20px;
        color: #1e293b;
        background: linear-gradient(to bottom, #fff, #f8fafc);
    }
    
    .modal-body {
        padding: 24px;
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 20px 24px;
        border-top: 2px solid #f1f5f9;
        background: #f8fafc;
    }
    
    /* ═══════════════════════════════════════════════════════════════
       RESPONSIVE DESIGN
       ═══════════════════════════════════════════════════════════ */
    
    @media (max-width: 768px) {
        .profile-header {
            padding: 32px 24px;
        }
        
        .profile-name {
            font-size: 28px;
        }
        
        .profile-stats {
            gap: 40px;
        }
        
        .stat-number {
            font-size: 28px;
        }
        
        .profile-actions {
            flex-direction: column;
            width: 100%;
        }
        
        .btn-edit,
        .btn-danger {
            width: 100%;
            justify-content: center;
        }
        
        .profile-details-container,
        .profile-form-container {
            padding: 24px;
        }
        
        .detail-item {
            grid-template-columns: 1fr;
            gap: 8px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn-primary,
        .btn-cancel {
            width: 100%;
        }
    }
    
    @media (max-width: 480px) {
        .profile-container {
            padding: 0 16px;
        }
        
        .profile-stats {
            gap: 30px;
        }
        
        .stat-number {
            font-size: 24px;
        }
        
        .stat-label {
            font-size: 12px;
        }
    }
    
    /* ═══════════════════════════════════════════════════════════════
       ADDITIONAL POLISH
       ═══════════════════════════════════════════════════════════ */
    
    /* Smooth scrolling */
    html {
        scroll-behavior: smooth;
    }
    
    /* Selection colors */
    ::selection {
        background: #3b82f6;
        color: #fff;
    }
    
    /* Focus styles for accessibility */
    button:focus-visible,
    a:focus-visible {
        outline: 3px solid #3b82f6;
        outline-offset: 2px;
    }
    
    /* Disabled state */
    button:disabled,
    input:disabled,
    select:disabled,
    textarea:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
</style>

</head>
<body>
<?php include("navbar.php"); ?>

<div class="profile-container">
  <div class="profile-header">
    <div class="profile-avatar-large">
      <?php if (!empty($display_profile_url)): ?>
        <img src="<?php echo htmlspecialchars($display_profile_url); ?>" alt="Profile Picture">
      <?php else: ?>
        <div style="font-size:48px;font-weight:600;color:#fff"><?php echo strtoupper(substr($user_data['username'] ?: 'U', 0, 1)); ?></div>
      <?php endif; ?>
    </div>
    <div class="profile-name"><?php echo htmlspecialchars($user_data['username'] ?: 'User'); ?></div>
    <div class="profile-email"><?php echo $isOwner ? htmlspecialchars($user_data['email'] ?: '') : ''; ?></div>
    <div class="profile-stats">
      <div class="stat-item"><div class="stat-number"><?php echo $stats['total_items']; ?></div><div class="stat-label">Total Items</div></div>
      <div class="stat-item"><div class="stat-number"><?php echo $stats['available_items']; ?></div><div class="stat-label">Available</div></div>
    </div>
    <div class="profile-actions">
      <?php if ($isOwner): ?>
        <button onclick="toggleEditMode()" class="btn-edit" id="editBtn">✏️ Edit Profile</button>
        <a href="my_items.php" class="btn-edit">📦 My Items</a>
      <?php else: ?>
        <a href="chat.php?user_id=<?php echo (int)$profile_user_id; ?>" class="btn-edit">💬 Message</a>
        <button class="btn-danger" onclick="openReport()">Report</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

  <?php if ($isOwner): ?>
  <!-- Owner-only details -->
  <div class="profile-details-container" id="detailsView">
    <div class="details-section">
      <h3>Basic Information</h3>
      <div class="detail-item"><div class="detail-label">Username</div><div class="detail-value"><?php echo $user_data['username'] ? htmlspecialchars($user_data['username']) : '<span class="empty-value">Not provided</span>'; ?></div></div>
      <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value"><?php echo $user_data['email'] ? htmlspecialchars($user_data['email']) : '<span class="empty-value">Not provided</span>'; ?></div></div>
      <div class="detail-item"><div class="detail-label">Phone</div><div class="detail-value"><?php echo $user_data['phone_number'] ? htmlspecialchars($user_data['phone_number']) : '<span class="empty-value">Not provided</span>'; ?></div></div>
    </div>

    <div class="details-section">
      <h3>Account Information</h3>
      <div class="detail-item"><div class="detail-label">Member Since</div><div class="detail-value"><?php echo htmlspecialchars($stats['member_since']); ?></div></div>
      <div class="detail-item"><div class="detail-label">Total Items</div><div class="detail-value"><?php echo $stats['total_items']; ?> items listed</div></div>
      <div class="detail-item"><div class="detail-label">Available Items</div><div class="detail-value"><?php echo $stats['available_items']; ?> currently available</div></div>
    </div>

    <div class="details-section">
      <h3>Profile Picture</h3>
      <div class="detail-item">
        <div class="detail-label">Current Picture</div>
        <div class="detail-value">
          <?php if (!empty($display_profile_url)): ?>
            <img src="<?php echo htmlspecialchars($display_profile_url); ?>" alt="Profile Picture" style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #e5e7eb;object-fit:cover">
          <?php else: ?>
            <span class="empty-value">No profile picture uploaded</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="details-section">
      <h3>Payment Details</h3>
      <div class="detail-item">
        <div class="detail-label">GCash Name</div>
        <div class="detail-value"><?php echo $payment['gcash_name'] ? htmlspecialchars($payment['gcash_name']) : '<span class="empty-value">Not set</span>'; ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">GCash Number</div>
        <div class="detail-value"><?php echo $payment['gcash_number'] ? htmlspecialchars($payment['gcash_number']) : '<span class="empty-value">Not set</span>'; ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">GCash QR</div>
        <div class="detail-value">
          <?php if (!empty($payment['gcash_qr'])): ?>
            <img src="<?php echo htmlspecialchars(toWebUrl($payment['gcash_qr'])); ?>" alt="GCash QR" style="max-width:160px;border:1px solid #e5e7eb;border-radius:8px">
          <?php else: ?>
            <span class="empty-value">No QR uploaded</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <!-- Public view for non-owners (no private details) -->
  <div class="profile-details-container" id="detailsView">
    <div class="details-section">
      <h3>Basic Information</h3>
      <div class="detail-item">
        <div class="detail-label">Username</div>
        <div class="detail-value"><?php echo $user_data['username'] ? htmlspecialchars($user_data['username']) : '<span class="empty-value">Not provided</span>'; ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Email</div>
        <div class="detail-value"><span class="empty-value">Hidden for privacy</span></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Phone</div>
        <div class="detail-value"><span class="empty-value">Hidden for privacy</span></div>
      </div>
    </div>

    <div class="details-section">
      <h3>Account Information</h3>
      <div class="detail-item"><div class="detail-label">Member Since</div><div class="detail-value"><?php echo htmlspecialchars($stats['member_since']); ?></div></div>
      <div class="detail-item"><div class="detail-label">Total Items</div><div class="detail-value"><?php echo $stats['total_items']; ?> items listed</div></div>
      <div class="detail-item"><div class="detail-label">Available Items</div><div class="detail-value"><?php echo $stats['available_items']; ?> currently available</div></div>
    </div>

    <div class="details-section">
      <h3>Profile Picture</h3>
      <div class="detail-item">
        <div class="detail-label">Current Picture</div>
        <div class="detail-value">
          <?php if (!empty($display_profile_url)): ?>
            <img src="<?php echo htmlspecialchars($display_profile_url); ?>" alt="Profile Picture" style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #e5e7eb;object-fit:cover">
          <?php else: ?>
            <span class="empty-value">No profile picture uploaded</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($isOwner): ?>
  <!-- Edit form (owner only) -->
  <div class="profile-form-container" id="editView">
    <form method="POST" enctype="multipart/form-data" autocomplete="off">
      <div class="details-section">
        <h3>Edit Basic Information</h3>
        <div class="form-row">
          <div class="form-group">
            <label for="username">Username *</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
          </div>
          <div class="form-group">
            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label for="phone_number">Phone Number</label>
          <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number']); ?>">
        </div>
        <div class="form-group">
          <label for="profile_picture">Profile Picture</label>
          <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
        </div>
      </div>

      <div class="details-section">
        <h3>Payment Methods</h3>
        <div class="method" id="m-gcash">
          <div class="method-header">
            <strong>GCash</strong>
            <label class="switch">
              <input type="checkbox" name="enable_gcash" id="enable_gcash" <?php echo ($payment['gcash_name'] || $payment['gcash_number'] || $payment['gcash_qr'])?'checked':''; ?>>
              <span>Enable</span>
            </label>
          </div>
          <div class="method-body">
            <div class="form-row">
              <div class="form-group">
                <label for="gcash_name">GCash Name</label>
                <input type="text" id="gcash_name" name="gcash_name" value="<?php echo htmlspecialchars($payment['gcash_name']); ?>">
              </div>
              <div class="form-group">
                <label for="gcash_number">GCash Number</label>
                <input type="tel" id="gcash_number" name="gcash_number" value="<?php echo htmlspecialchars($payment['gcash_number']); ?>" inputmode="numeric" pattern="[0-9]*">
              </div>
            </div>
            <div class="form-group">
              <label for="gcash_qr">GCash QR (image)</label>
              <input type="file" id="gcash_qr" name="gcash_qr" accept="image/*">
              <?php if (!empty($payment['gcash_qr'])): ?>
                <div style="margin-top:8px"><img src="<?php echo htmlspecialchars(toWebUrl($payment['gcash_qr'])); ?>" alt="GCash QR" style="max-width:160px;border:1px solid #e5e7eb;border-radius:8px"></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="toggleEditMode()">Cancel</button>
        <button type="submit" class="btn-primary">Save changes</button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>

<!-- Report Modal (non-owner) -->
<?php if (!$isOwner): ?>
<div class="modal-backdrop" id="reportModal">
  <div class="modal">
    <div class="modal-header">Report <?php echo htmlspecialchars($user_data['username']); ?></div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="form-group">
          <label for="reason">Reason</label>
          <select id="reason" name="reason" required>
            <option value="">Select a reason...</option>
            <option>Scam / Fraud</option>
            <option>Impersonation</option>
            <option>Harassment</option>
            <option>Spam</option>
            <option>Other</option>
          </select>
        </div>
        <div class="form-group">
          <label for="details">Details (optional)</label>
          <textarea id="details" name="details" placeholder="Describe what happened..."></textarea>
        </div>
        <div class="form-group">
          <label for="evidence">Evidence (image/pdf, optional)</label>
          <input type="file" id="evidence" name="evidence" accept="image/*,.pdf">
        </div>
        <input type="hidden" name="report_user" value="1">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeReport()">Cancel</button>
        <button type="submit" class="btn-danger">Submit report</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function toggleEditMode() {
  const detailsView = document.getElementById('detailsView');
  const editView    = document.getElementById('editView');
  const btn         = document.getElementById('editBtn');
  if (!editView) return;
  if (editView.classList.contains('active')) {
    editView.classList.remove('active');
    detailsView.style.display = 'block';
    if (btn) btn.innerHTML = '✏️ Edit Profile';
  } else {
    editView.classList.add('active');
    detailsView.style.display = 'none';
    if (btn) btn.innerHTML = '👁️ View Profile';
  }
}
function bindToggle(id, boxId){
  const wrap = document.getElementById(id);
  const chk  = document.getElementById(boxId);
  if (!wrap || !chk) return;
  function sync(){ wrap.classList.toggle('active', chk.checked); }
  chk.addEventListener('change', sync); sync();
}
bindToggle('m-gcash','enable_gcash');

function openReport(){ document.getElementById('reportModal')?.classList.add('show'); }
function closeReport(){ document.getElementById('reportModal')?.classList.remove('show'); }
</script>
</body>
</html>