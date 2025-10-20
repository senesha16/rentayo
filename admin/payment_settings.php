<?php

include("../connections.php");
session_start();

// Admin gate
if (empty($_SESSION['ID'])) { header("Location: ../login.php"); exit; }
$uid = (int)$_SESSION['ID'];
$chk = mysqli_query($connections, "SELECT is_admin FROM users WHERE ID = $uid LIMIT 1");
$row = $chk ? mysqli_fetch_assoc($chk) : null;
if (!$row || (int)$row['is_admin'] !== 1) { header("Location: ../login.php"); exit; }

// Ensure table/row exist (same helper as before)
function getPaymentSettings(mysqli $connections): array {
    $create = "CREATE TABLE IF NOT EXISTS `payment_settings` (
        `id` TINYINT(1) NOT NULL PRIMARY KEY,
        `gcash_number` VARCHAR(30) NOT NULL DEFAULT '',
        `gcash_qr_url` VARCHAR(255) NOT NULL DEFAULT '',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($connections, $create);

    $check = mysqli_query($connections, "SELECT * FROM payment_settings WHERE id = 1");
    if (!$check || mysqli_num_rows($check) === 0) {
        mysqli_query($connections, "INSERT INTO payment_settings (id, gcash_number, gcash_qr_url) VALUES (1, '09123456789', '')");
        return ['gcash_number' => '09123456789', 'gcash_qr_url' => ''];
    }
    return mysqli_fetch_assoc($check) ?: ['gcash_number' => '09123456789', 'gcash_qr_url' => ''];
}

$msg = $err = "";
$settings = getPaymentSettings($connections);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gcash_number = trim($_POST['gcash_number'] ?? '');
    if ($gcash_number === '' || !preg_match('/^[0-9]{11}$/', $gcash_number)) {
        $err = "GCash number must be 11 digits.";
    }

    // Handle QR upload (optional)
    $qr_web = $settings['gcash_qr_url'] ?? '';
    if (!$err && isset($_FILES['gcash_qr']) && $_FILES['gcash_qr']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['gcash_qr']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['gcash_qr']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png'])) {
                $err = "QR must be JPG/PNG.";
            } elseif (($_FILES['gcash_qr']['size'] ?? 0) > 4 * 1024 * 1024) {
                $err = "QR image max 4MB.";
            } else {
                $dir_abs = dirname(__DIR__) . '/uploads/payments/';
                $dir_web = 'uploads/payments/';
                if (!is_dir($dir_abs)) { @mkdir($dir_abs, 0777, true); }
                if (!is_dir($dir_abs) || !is_writable($dir_abs)) {
                    $err = "Upload dir not writable: " . $dir_abs;
                } else {
                    $fname = 'gcash_qr_' . time() . '.' . $ext;
                    $dest_abs = $dir_abs . $fname;
                    $dest_web = $dir_web . $fname;
                    if (!move_uploaded_file($_FILES['gcash_qr']['tmp_name'], $dest_abs)) {
                        $err = "Failed to save QR image.";
                    } else {
                        $qr_web = $dest_web;
                    }
                }
            }
        } else {
            $err = "QR upload error: " . (int)$_FILES['gcash_qr']['error'];
        }
    }

    if (!$err) {
        $num = mysqli_real_escape_string($connections, $gcash_number);
        $qr  = mysqli_real_escape_string($connections, $qr_web);
        $ok = mysqli_query($connections, "UPDATE payment_settings SET gcash_number='$num', gcash_qr_url='$qr' WHERE id=1");
        if ($ok) {
            $msg = "Settings updated.";
            $settings = getPaymentSettings($connections);
        } else {
            $err = "DB error: " . mysqli_error($connections);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Payment Settings</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        /* small local styles for preview */
        .preview img { max-width:220px; border:1px solid #eee; border-radius:8px; }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
<?php include __DIR__ . '/admin_navbar.php'; ?>

<div class="min-h-screen flex">
  <!-- Left Panel -->
  <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-cyan-400 via-blue-500 to-blue-600 p-12 flex-col justify-between text-white">
    <div>
      <div class="flex items-center gap-3 mb-12">
        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center">
          <i data-lucide="credit-card" class="w-5 h-5 text-white"></i>
        </div>
        <div>
          <div class="font-medium">RENTayo</div>
          <div class="text-sm text-white/80">Admin Panel</div>
        </div>
      </div>

      <h1 class="text-4xl font-bold mb-4">Payment Settings</h1>
      <p class="text-white/90 text-sm max-w-md">Configure your GCash payment details to accept payments from students.</p>
    </div>

    <div class="text-white/60 text-xs">© <?php echo date('Y'); ?> RENTayo. All rights reserved.</div>
  </div>

  <!-- Right Panel -->
  <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
    <div class="w-full max-w-md">
      <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
        <div class="mb-6">
          <h2 class="text-2xl font-semibold text-gray-800 mb-1">GCash Settings</h2>
          <p class="text-sm text-gray-500">Update your payment information</p>
        </div>

        <!-- server-side messages -->
        <?php if ($msg): ?>
          <div id="serverMsg" class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-800 flex items-start gap-2">
            <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
            <div><?php echo htmlspecialchars($msg); ?></div>
          </div>
        <?php endif; ?>
        <?php if ($err): ?>
          <div id="serverErr" class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 flex items-start gap-2">
            <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
            <div><?php echo htmlspecialchars($err); ?></div>
          </div>
        <?php endif; ?>

        <form id="settingsForm" method="post" enctype="multipart/form-data" class="space-y-6" novalidate>
          <div>
            <label for="gcash_number" class="block text-sm font-medium text-gray-700 mb-2">GCash Number (11 digits)</label>
            <input id="gcash_number" name="gcash_number" type="text" inputmode="numeric" maxlength="11" pattern="[0-9]{11}"
                   class="w-full h-12 px-4 border rounded-xl border-gray-200 focus:border-cyan-400 focus:ring-cyan-200"
                   value="<?php echo htmlspecialchars($settings['gcash_number'] ?? ''); ?>" required>
            <p id="gcashNumberErr" class="mt-2 text-sm text-red-600 hidden">GCash number must be 11 digits.</p>
          </div>

          <div>
            <label for="gcash_qr" class="block text-sm font-medium text-gray-700 mb-2">GCash QR Code (JPG/PNG, max 4MB)</label>
            <input id="gcash_qr" name="gcash_qr" type="file" accept="image/jpeg,image/png"
                   class="block w-full text-sm text-gray-700 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-cyan-50 file:text-cyan-700" />
            <div class="preview mt-3">
              <?php if (!empty($settings['gcash_qr_url'])): ?>
                <div class="text-sm text-gray-600 mb-2">Current QR:</div>
                <img id="currentQr" src="<?php echo '../' . htmlspecialchars($settings['gcash_qr_url']); ?>" alt="Current GCash QR">
              <?php else: ?>
                <div class="text-sm text-gray-500"><em>No QR uploaded</em></div>
              <?php endif; ?>
            </div>
          </div>

          <div>
            <button id="saveBtn" type="submit" class="w-full h-12 rounded-xl bg-gradient-to-r from-cyan-400 to-blue-500 text-white font-medium shadow">Save Settings</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- client-side JS: lucide icons + validation + optional preview -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.lucide && typeof lucide.createIcons === 'function') lucide.createIcons();

        const form = document.getElementById('settingsForm');
        const gcashInput = document.getElementById('gcash_number');
        const gcashErr = document.getElementById('gcashNumberErr');

        form.addEventListener('submit', (e) => {
            gcashErr.classList.add('hidden');
            const val = (gcashInput.value || '').trim();
            if (!/^[0-9]{11}$/.test(val)) {
                e.preventDefault();
                gcashErr.classList.remove('hidden');
                gcashInput.focus();
                return false;
            }
            // allow submit to continue to server
        });

        // file preview update
        const fileInput = document.getElementById('gcash_qr');
        const currentQr = document.getElementById('currentQr');
        if (fileInput) {
            fileInput.addEventListener('change', (ev) => {
                const f = ev.target.files && ev.target.files[0];
                if (!f) return;
                const reader = new FileReader();
                reader.onload = (e) => {
                    if (currentQr) {
                        currentQr.src = e.target.result;
                    } else {
                        const img = document.createElement('img');
                        img.id = 'currentQr';
                        img.src = e.target.result;
                        img.alt = 'GCash QR';
                        img.className = '';
                        const preview = document.querySelector('.preview');
                        if (preview) preview.innerHTML = ''; preview.appendChild(img);
                    }
                };
                reader.readAsDataURL(f);
            });
        }
    });
 </script>
</body>
</html>