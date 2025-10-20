<?php
include("connections.php");
session_start();
header('Content-Type: application/json');

// Better error reporting to catch SQL issues
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (empty($_SESSION['ID'])) {
        echo json_encode(['success' => false, 'message' => 'Please log in first.']);
        exit;
    }

   
   
    $renter_id = (int)$_SESSION['ID'];
    $item_id   = (int)($_POST['item_id'] ?? 0);
    $lender_id = (int)($_POST['lender_id'] ?? 0);
    $days      = (int)($_POST['days'] ?? 1);
    $payment_method  = ($_POST['payment_method'] ?? '') === 'gcash' ? 'gcash' : 'cash';
    $gcash_ref = trim($_POST['gcash_ref'] ?? '');
    $delivery_method = ($_POST['delivery_method'] ?? '') === 'delivery' ? 'delivery' : 'pickup';
    $delivery_address= trim($_POST['delivery_address'] ?? '');

    if ($item_id <= 0 || $lender_id <= 0 || $days < 1 || $days > 60) {
        echo json_encode(['success' => false, 'message' => 'Invalid rental details.']);
        exit;
    }
    if ($renter_id === $lender_id) {
        echo json_encode(['success' => false, 'message' => 'You cannot rent your own item.']);
        exit;
    }
    if ($payment_method === 'gcash' && strlen($gcash_ref) < 6) {
        echo json_encode(['success' => false, 'message' => 'Invalid GCash reference number.']);
        exit;
    }
    if ($delivery_method === 'delivery' && strlen($delivery_address) < 8) {
        echo json_encode(['success' => false, 'message' => 'Please provide a delivery address.']);
        exit;
    }

    // Load item price and verify lender + approved status
    // Also ensure Items.status exists (fallback approved)
  $col = mysqli_query($connections, "SHOW COLUMNS FROM items LIKE 'status'");
    if (!$col || mysqli_num_rows($col) === 0) {
    mysqli_query($connections, "ALTER TABLE items ADD COLUMN `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
    }

  $itRes = mysqli_query($connections, "SELECT price_per_day, lender_id, status FROM items WHERE item_id = $item_id LIMIT 1");
    $it    = $itRes ? mysqli_fetch_assoc($itRes) : null;
    if (!$it) {
        echo json_encode(['success' => false, 'message' => 'Item not found.']);
        exit;
    }
    if ((int)$it['lender_id'] !== $lender_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid item owner.']);
        exit;
    }
    if ($it['status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'This item is not available for renting yet.']);
        exit;
    }

    $amount_total = (float)$it['price_per_day'] * $days;

    // Use NULLIF to store NULL for optional fields instead of empty strings
    $sql = "INSERT INTO rent_orders
            (item_id, lender_id, renter_id, `days`, amount_total, payment_method, gcash_ref, delivery_method, delivery_address)
            VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''))";
    $stmt = mysqli_prepare($connections, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "iiiidssss",
        $item_id,
        $lender_id,
        $renter_id,
        $days,
        $amount_total,
        $payment_method,
        $gcash_ref,       // pass '' when not gcash
        $delivery_method,
        $delivery_address // pass '' when pickup
    );
    mysqli_stmt_execute($stmt);
    $order_id = mysqli_insert_id($connections);
    mysqli_stmt_close($stmt);

    // Notify the lender
    $renterName = '';
    if ($u = mysqli_query($connections, "SELECT username FROM users WHERE ID = $renter_id LIMIT 1")) {
        if ($ur = mysqli_fetch_assoc($u)) $renterName = $ur['username'];
    }
    $title = "New rental request";
    $body  = $renterName ? ($renterName . " wants to rent your item (ID #$item_id) for $days day(s). Total ₱" . number_format($amount_total, 2))
                         : ("Someone wants to rent your item (ID #$item_id) for $days day(s). Total ₱" . number_format($amount_total, 2));
    $link  = "item_details.php?item_id=".$item_id."#orders"; // adjust when you add a manage page
    $nstmt = mysqli_prepare($connections, "INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($nstmt, "isss", $lender_id, $title, $body, $link);
    mysqli_stmt_execute($nstmt);
    mysqli_stmt_close($nstmt);

    echo json_encode(['success' => true, 'message' => 'Rental request submitted.', 'order_id' => $order_id]);
} catch (Throwable $e) {
    // Return the actual DB error to help debugging
    echo json_encode(['success' => false, 'message' => 'Submit failed: ' . $e->getMessage()]);
}
?><?php
// filepath: c:\xampp\htdocs\RENTayo-main\profile_payment.php
session_start();
include 'connections.php';

if (!isset($_SESSION['ID']) || !is_numeric($_SESSION['ID'])) {
  header('Location: login.php'); exit;
}
$user_id = (int)$_SESSION['ID'];

// Create settings table if missing (safe no-op if it exists)
@mysqli_query($connections, "CREATE TABLE IF NOT EXISTS `user_payment_settings` (
  `user_id` INT NOT NULL,
  `gcash_name` VARCHAR(100) DEFAULT NULL,
  `gcash_number` VARCHAR(50) DEFAULT NULL,
  `paymaya_name` VARCHAR(100) DEFAULT NULL,
  `paymaya_number` VARCHAR(50) DEFAULT NULL,
  `bank_name` VARCHAR(100) DEFAULT NULL,
  `bank_account_name` VARCHAR(100) DEFAULT NULL,
  `bank_account_number` VARCHAR(64) DEFAULT NULL,
  `paypal_email` VARCHAR(190) DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Load current settings
$settings = [
  'gcash_name' => '', 'gcash_number' => '',
  'paymaya_name' => '', 'paymaya_number' => '',
  'bank_name' => '', 'bank_account_name' => '', 'bank_account_number' => '',
  'paypal_email' => ''
];
if ($rs = mysqli_prepare($connections, "SELECT gcash_name, gcash_number, paymaya_name, paymaya_number, bank_name, bank_account_name, bank_account_number, paypal_email FROM user_payment_settings WHERE user_id = ? LIMIT 1")) {
  mysqli_stmt_bind_param($rs, "i", $user_id);
  mysqli_stmt_execute($rs);
  $res = mysqli_stmt_get_result($rs);
  if ($res && ($row = mysqli_fetch_assoc($res))) {
    foreach ($settings as $k => $_) { if (isset($row[$k])) $settings[$k] = (string)$row[$k]; }
  }
  mysqli_stmt_close($rs);
}

$msg = ""; $err = "";

function onlyDigits($s){ return preg_replace('/\D+/', '', $s ?? ''); }
function clean($s){ return trim((string)($s ?? '')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Checkboxes control enable/disable of blocks
  $enable_gcash  = isset($_POST['enable_gcash']);
  $enable_maya   = isset($_POST['enable_maya']);
  $enable_bank   = isset($_POST['enable_bank']);
  $enable_paypal = isset($_POST['enable_paypal']);

  $gcash_name   = $enable_gcash  ? clean($_POST['gcash_name'] ?? '') : '';
  $gcash_number = $enable_gcash  ? onlyDigits($_POST['gcash_number'] ?? '') : '';

  $paymaya_name   = $enable_maya ? clean($_POST['paymaya_name'] ?? '') : '';
  $paymaya_number = $enable_maya ? onlyDigits($_POST['paymaya_number'] ?? '') : '';

  $bank_name           = $enable_bank ? clean($_POST['bank_name'] ?? '') : '';
  $bank_account_name   = $enable_bank ? clean($_POST['bank_account_name'] ?? '') : '';
  $bank_account_number = $enable_bank ? preg_replace('/[^0-9\- ]+/', '', $_POST['bank_account_number'] ?? '') : '';

  $paypal_email = $enable_paypal ? clean($_POST['paypal_email'] ?? '') : '';

  $errors = [];

  // Basic validations (only when enabled)
  if ($enable_gcash) {
    if ($gcash_name === '')   $errors[] = "GCash name is required.";
    if ($gcash_number === '' || strlen($gcash_number) < 9) $errors[] = "GCash number must be at least 9 digits.";
  }
  if ($enable_maya) {
    if ($paymaya_name === '')   $errors[] = "Maya name is required.";
    if ($paymaya_number === '' || strlen($paymaya_number) < 9) $errors[] = "Maya number must be at least 9 digits.";
  }
  if ($enable_bank) {
    if ($bank_name === '') $errors[] = "Bank name is required.";
    if ($bank_account_name === '') $errors[] = "Account name is required.";
    if ($bank_account_number === '' || strlen(preg_replace('/\D+/', '', $bank_account_number)) < 8) $errors[] = "Bank account number looks invalid.";
  }
  if ($enable_paypal) {
    if ($paypal_email === '' || !filter_var($paypal_email, FILTER_VALIDATE_EMAIL)) $errors[] = "Enter a valid PayPal email.";
  }

  if (empty($errors)) {
    // Upsert
    $sql = "INSERT INTO user_payment_settings 
      (user_id, gcash_name, gcash_number, paymaya_name, paymaya_number, bank_name, bank_account_name, bank_account_number, paypal_email)
      VALUES (?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE
        gcash_name = VALUES(gcash_name),
        gcash_number = VALUES(gcash_number),
        paymaya_name = VALUES(paymaya_name),
        paymaya_number = VALUES(paymaya_number),
        bank_name = VALUES(bank_name),
        bank_account_name = VALUES(bank_account_name),
        bank_account_number = VALUES(bank_account_number),
        paypal_email = VALUES(paypal_email)";

    if ($st = mysqli_prepare($connections, $sql)) {
      mysqli_stmt_bind_param(
        $st, "issssssss",
        $user_id,
        $gcash_name, $gcash_number,
        $paymaya_name, $paymaya_number,
        $bank_name, $bank_account_name, $bank_account_number,
        $paypal_email
      );
      if (mysqli_stmt_execute($st)) {
        $msg = "Payment settings updated.";
        // refresh in-memory
        $settings = [
          'gcash_name' => $gcash_name, 'gcash_number' => $gcash_number,
          'paymaya_name' => $paymaya_name, 'paymaya_number' => $paymaya_number,
          'bank_name' => $bank_name, 'bank_account_name' => $bank_account_name, 'bank_account_number' => $bank_account_number,
          'paypal_email' => $paypal_email
        ];
      } else {
        $err = "Failed to save settings.";
      }
      mysqli_stmt_close($st);
    } else {
      $err = "Failed to prepare save statement.";
    }
  } else {
    $err = implode("<br>", $errors);
    // keep posted values in the form
    $settings = [
      'gcash_name' => $gcash_name, 'gcash_number' => $gcash_number,
      'paymaya_name' => $paymaya_name, 'paymaya_number' => $paymaya_number,
      'bank_name' => $bank_name, 'bank_account_name' => $bank_account_name, 'bank_account_number' => $bank_account_number,
      'paypal_email' => $paypal_email
    ];
  }
}

// Determine which blocks are currently enabled (based on saved values)
$enabled = [
  'gcash'  => ($settings['gcash_name'] !== '' || $settings['gcash_number'] !== ''),
  'maya'   => ($settings['paymaya_name'] !== '' || $settings['paymaya_number'] !== ''),
  'bank'   => ($settings['bank_name'] !== '' || $settings['bank_account_name'] !== '' || $settings['bank_account_number'] !== ''),
  'paypal' => ($settings['paypal_email'] !== '')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Settings - RENTayo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="styles/style.css" rel="stylesheet">
  <style>
    body { margin-top: 80px; background:#f8fafc; font-family: 'Poppins',sans-serif; }
    .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
    .card { background:#fff; border-radius:16px; box-shadow: 0 10px 24px rgba(0,0,0,.06); padding:28px; margin-bottom:18px; }
    .title { font-weight:700; font-size:22px; margin-bottom:4px; }
    .subtitle { color:#64748b; margin-bottom:18px; }
    .method { border:1px solid #e5e7eb; border-radius:12px; margin:16px 0; overflow:hidden; }
    .method-header { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:#f8fafc; }
    .method-body { padding:14px; display:none; }
    .method.active .method-body { display:block; }
    .switch { display:flex; align-items:center; gap:10px; }
    .switch input { transform: scale(1.1); }
    .row { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    .row-1 { display:grid; grid-template-columns: 1fr; gap:16px; }
    label { display:block; margin-bottom:6px; font-weight:600; color:#374151; }
    input[type="text"], input[type="tel"], input[type="email"] {
      width:100%; padding:12px 14px; border-radius:10px; border:2px solid #e5e7eb; outline: none;
    }
    input:focus { border-color:#6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
    .actions { display:flex; gap:12px; justify-content:flex-end; margin-top:16px; }
    .btn { padding:12px 18px; border-radius:10px; border:2px solid #e5e7eb; background:#f3f4f6; cursor:pointer; font-weight:600; }
    .btn-primary { background: linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; border:none; }
    .alert { padding:14px 16px; border-radius:10px; margin: 12px 0; }
    .alert-success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
    .alert-error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
    @media (max-width:800px){ .row{ grid-template-columns:1fr; } }
  </style>
</head>
<body>
  <?php include 'navbar.php'; ?>

  <div class="container">
    <div class="card">
      <div class="title">Payment Settings</div>
      <div class="subtitle">Choose which payment methods you accept and fill in the details. You can enable any combination.</div>

      <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-error"><?php echo $err; ?></div><?php endif; ?>

      <form method="POST" action="profile_payment.php" autocomplete="off" novalidate>
        <!-- GCash -->
        <div class="method <?php echo $enabled['gcash']?'active':''; ?>" id="m-gcash">
          <div class="method-header">
            <strong>GCash</strong>
            <label class="switch">
              <input type="checkbox" name="enable_gcash" id="enable_gcash" <?php echo $enabled['gcash']?'checked':''; ?>>
              <span>Enable</span>
            </label>
          </div>
          <div class="method-body">
            <div class="row">
              <div>
                <label for="gcash_name">GCash Name</label>
                <input type="text" id="gcash_name" name="gcash_name" value="<?php echo htmlspecialchars($settings['gcash_name']); ?>">
              </div>
              <div>
                <label for="gcash_number">GCash Number</label>
                <input type="tel" id="gcash_number" name="gcash_number" value="<?php echo htmlspecialchars($settings['gcash_number']); ?>" inputmode="numeric" pattern="[0-9]*">
              </div>
            </div>
          </div>
        </div>

        <!-- Maya -->
        <div class="method <?php echo $enabled['maya']?'active':''; ?>" id="m-maya">
          <div class="method-header">
            <strong>Maya</strong>
            <label class="switch">
              <input type="checkbox" name="enable_maya" id="enable_maya" <?php echo $enabled['maya']?'checked':''; ?>>
              <span>Enable</span>
            </label>
          </div>
          <div class="method-body">
            <div class="row">
              <div>
                <label for="paymaya_name">Maya Name</label>
                <input type="text" id="paymaya_name" name="paymaya_name" value="<?php echo htmlspecialchars($settings['paymaya_name']); ?>">
              </div>
              <div>
                <label for="paymaya_number">Maya Number</label>
                <input type="tel" id="paymaya_number" name="paymaya_number" value="<?php echo htmlspecialchars($settings['paymaya_number']); ?>" inputmode="numeric" pattern="[0-9]*">
              </div>
            </div>
          </div>
        </div>

        <!-- Bank Transfer -->
        <div class="method <?php echo $enabled['bank']?'active':''; ?>" id="m-bank">
          <div class="method-header">
            <strong>Bank Transfer</strong>
            <label class="switch">
              <input type="checkbox" name="enable_bank" id="enable_bank" <?php echo $enabled['bank']?'checked':''; ?>>
              <span>Enable</span>
            </label>
          </div>
          <div class="method-body">
            <div class="row">
              <div>
                <label for="bank_name">Bank Name</label>
                <input type="text" id="bank_name" name="bank_name" value="<?php echo htmlspecialchars($settings['bank_name']); ?>">
              </div>
              <div>
                <label for="bank_account_name">Account Name</label>
                <input type="text" id="bank_account_name" name="bank_account_name" value="<?php echo htmlspecialchars($settings['bank_account_name']); ?>">
              </div>
            </div>
            <div class="row-1" style="margin-top:10px;">
              <div>
                <label for="bank_account_number">Account Number</label>
                <input type="text" id="bank_account_number" name="bank_account_number" value="<?php echo htmlspecialchars($settings['bank_account_number']); ?>" placeholder="e.g. 1234-5678-9012">
              </div>
            </div>
          </div>
        </div>

        <!-- PayPal -->
        <div class="method <?php echo $enabled['paypal']?'active':''; ?>" id="m-paypal">
          <div class="method-header">
            <strong>PayPal</strong>
            <label class="switch">
              <input type="checkbox" name="enable_paypal" id="enable_paypal" <?php echo $enabled['paypal']?'checked':''; ?>>
              <span>Enable</span>
            </label>
          </div>
          <div class="method-body">
            <div class="row-1">
              <div>
                <label for="paypal_email">PayPal Email</label>
                <input type="email" id="paypal_email" name="paypal_email" value="<?php echo htmlspecialchars($settings['paypal_email']); ?>" placeholder="name@example.com">
              </div>
            </div>
          </div>
        </div>

        <div class="actions">
          <a href="profile.php" class="btn">Cancel</a>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Expand/collapse method bodies when toggled
    function bindToggle(id, boxId){
      const wrap = document.getElementById(id);
      const chk  = document.getElementById(boxId);
      if (!wrap || !chk) return;
      function sync(){ wrap.classList.toggle('active', chk.checked); }
      chk.addEventListener('change', sync); sync();
    }
    bindToggle('m-gcash','enable_gcash');
    bindToggle('m-maya','enable_maya');
    bindToggle('m-bank','enable_bank');
    bindToggle('m-paypal','enable_paypal');

    // Keep numbers numeric in UI (non-blocking)
    const numOnly = (e)=>{ e.target.value = e.target.value.replace(/\D+/g,''); };
    document.getElementById('gcash_number')?.addEventListener('input', numOnly);
    document.getElementById('paymaya_number')?.addEventListener('input', numOnly);
  </script>
</body>
</html>